<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Currency\Currency;
use App\Domain\Currency\CurrencyCode;
use App\Domain\Rate\RateRepository;
use App\Dto\Request\CurrentRateRequest;
use App\Dto\Request\HistoricalRatesRequest;
use App\Dto\Response\CurrenciesCollectionResponse;
use App\Dto\Response\RatesCollectionResponse;
use App\Dto\Response\RateResponse;
use App\Exception\InvalidCurrencyException;
use App\Exception\RateNotFoundException;
use App\Exception\ServiceUnavailableException;
use App\Service\TimezoneService;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

/**
 * Exchange Rate API Controller
 * 
 * Provides REST API endpoints for currency exchange rates.
 * Serves as the Application Layer between frontend and Domain Layer.
 * 
 * Endpoints:
 * - GET /api/rates/current - Current rates for all supported currencies
 * - GET /api/rates/current/{currency} - Current rate for specific currency
 * - GET /api/rates/historical/{currency} - Historical rates for currency
 * - GET /api/currencies - List of supported currencies
 * - GET /api/health - Health check endpoint
 */
#[Route('/api', name: 'api_')]
class ExchangeRateController extends AbstractController
{
    public function __construct(
        private RateRepository $rateRepository,
        private TimezoneService $timezoneService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Get current exchange rates for all supported currencies
     * 
     * @Route("/rates/current", name="rates_current", methods={"GET"})
     */
    public function getCurrentRates(Request $request): JsonResponse
    {
        $requestDto = CurrentRateRequest::fromQueryParams($request->query->all());
        $date = $requestDto->getDate();
        
        $this->logger->info('API: Getting current rates', [
            'date' => $date->format('Y-m-d'),
            'endpoint' => '/api/rates/current'
        ]);
        
        $rates = $this->rateRepository->findAllByDate($date);
        
        if (empty($rates)) {
            throw new RateNotFoundException('ALL', $date->format('Y-m-d'), 
                'No exchange rates found for the specified date');
        }
        
        $response = RatesCollectionResponse::fromCurrentRates($rates, $date->format('Y-m-d'));
        
        $this->logger->info('API: Current rates retrieved successfully', [
            'date' => $date->format('Y-m-d'),
            'count' => $response->count
        ]);
        
        return $this->json($response->toArray());
    }

    /**
     * Get current exchange rate for specific currency
     * 
     * @Route("/rates/current/{currency}", name="rates_current_currency", methods={"GET"})
     */
    public function getCurrentRateForCurrency(string $currency, Request $request): JsonResponse
    {
        // Validate currency code
        if (!$this->isValidCurrencyCode($currency)) {
            throw new InvalidCurrencyException(strtoupper($currency), $this->getSupportedCurrencyCodes());
        }
        
        $requestDto = CurrentRateRequest::fromQueryParams($request->query->all(), $currency);
        $currencyObj = Currency::fromCode(strtoupper($currency));
        $date = $requestDto->getDate();
        
        $this->logger->info('API: Getting current rate for currency', [
            'currency' => $currency,
            'date' => $date->format('Y-m-d'),
            'endpoint' => '/api/rates/current/' . $currency
        ]);
        
        $rate = $this->rateRepository->findByCurrencyAndDate($currencyObj, $date);
        
        if ($rate === null) {
            throw new RateNotFoundException(strtoupper($currency), $date->format('Y-m-d'));
        }
        
        $response = RateResponse::fromRate($rate);
        
        $this->logger->info('API: Current rate retrieved successfully', [
            'currency' => $currency,
            'date' => $date->format('Y-m-d')
        ]);
        
        return $this->json($response->toArray());
    }

    /**
     * Get historical exchange rates for specific currency
     * 
     * @Route("/rates/historical/{currency}", name="rates_historical", methods={"GET"})
     */
    public function getHistoricalRates(string $currency, Request $request): JsonResponse
    {
        // Validate currency code
        if (!$this->isValidCurrencyCode($currency)) {
            throw new InvalidCurrencyException(strtoupper($currency), $this->getSupportedCurrencyCodes());
        }
        
        $requestDto = HistoricalRatesRequest::fromQueryParams($currency, $request->query->all());
        $currencyObj = Currency::fromCode(strtoupper($currency));
        $startDate = $requestDto->getStartDate();
        $endDate = $requestDto->getEndDate();
        
        $this->logger->info('API: Getting historical rates', [
            'currency' => $currency,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'days' => $requestDto->days,
            'endpoint' => '/api/rates/historical/' . $currency
        ]);
        
        $rates = $this->rateRepository->findHistoricalRates($currencyObj, $startDate, $endDate);
        
        if (empty($rates)) {
            throw RateNotFoundException::forHistoricalRates(
                strtoupper($currency),
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d'),
                $requestDto->days
            );
        }
        
        $response = RatesCollectionResponse::fromHistoricalRates(
            $rates,
            strtoupper($currency),
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d'),
            $requestDto->days
        );
        
        $this->logger->info('API: Historical rates retrieved successfully', [
            'currency' => $currency,
            'count' => $response->count,
            'date_range' => $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d')
        ]);
        
        return $this->json($response->toArray());
    }

    /**
     * Get list of supported currencies
     * 
     * @Route("/currencies", name="currencies", methods={"GET"})
     */
    public function getSupportedCurrencies(): JsonResponse
    {
        $this->logger->info('API: Getting supported currencies', [
            'endpoint' => '/api/currencies'
        ]);
        
        $response = CurrenciesCollectionResponse::fromSupportedCurrencies();
        
        $this->logger->info('API: Supported currencies retrieved successfully', [
            'count' => $response->count
        ]);
        
        return $this->json($response->toArray());
    }

    /**
     * Health check endpoint
     * 
     * @Route("/health", name="health", methods={"GET"})
     */
    public function healthCheck(): JsonResponse
    {
        try {
            // Test repository connection by checking if we can get today's rates
            $today = $this->timezoneService->today();
            $testCurrency = Currency::fromCode('EUR');
            
            // This will test the entire chain: Controller -> Repository -> Cache -> NBP API
            $exists = $this->rateRepository->exists($testCurrency, $today);
            
            $responseData = [
                'status' => 'healthy',
                'timestamp' => $today->format('c'),
                'services' => [
                    'api' => 'operational',
                    'repository' => 'operational',
                    'cache' => 'operational'
                ],
                'test_results' => [
                    'eur_rate_available' => $exists
                ]
            ];
            
            return $this->json($responseData);
            
        } catch (\Exception $e) {
            throw new ServiceUnavailableException('exchange_rate_api', 
                'Health check failed: ' . $e->getMessage(), $e);
        }
    }


    /**
     * Check if currency code is supported
     */
    private function isValidCurrencyCode(string $currency): bool
    {
        $supportedCodes = $this->getSupportedCurrencyCodes();
        return in_array(strtoupper($currency), $supportedCodes, true);
    }

    /**
     * Get array of supported currency codes
     * 
     * @return string[]
     */
    private function getSupportedCurrencyCodes(): array
    {
        return array_map(
            fn(CurrencyCode $code) => $code->value,
            CurrencyCode::cases()
        );
    }
}
