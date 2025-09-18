<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Domain\Currency\CurrencyCode;
use App\Domain\Currency\Currency;
use App\Domain\Rate\RateRepository;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Cache Warming Service
 * 
 * Proactively loads critical exchange rate data into cache to improve
 * performance for first requests and ensure data availability.
 * 
 * Warming Strategy:
 * 1. Current rates for all supported currencies
 * 2. Historical rates for last 14 days for each currency
 * 3. Weekend/holiday fallback data
 * 
 * Can be triggered:
 * - Via console command (deployment, cron)
 * - Via HTTP endpoint (manual warming)
 * - Automatically on application startup
 */
final class CacheWarmer
{
    private const DEFAULT_HISTORY_DAYS = 14;
    private const MAX_RETRY_ATTEMPTS = 3;
    private const RETRY_DELAY_SECONDS = 2;

    public function __construct(
        private RateRepository $repository,
        private LoggerInterface $logger = new NullLogger()
    ) {
    }

    /**
     * Warm cache with current rates for all supported currencies
     * 
     * Loads today's exchange rates for all currencies into cache.
     * This is the most critical data for the application.
     */
    public function warmCurrentRates(?DateTimeImmutable $date = null): array
    {
        $date = $date ?? new DateTimeImmutable();
        $results = [];
        
        $this->logger->info('Starting cache warming for current rates', [
            'date' => $date->format('Y-m-d')
        ]);
        
        try {
            // Warm all rates at once (most efficient)
            $rates = $this->repository->findAllByDate($date);
            
            if (!empty($rates)) {
                $results['all_rates'] = [
                    'status' => 'success',
                    'date' => $date->format('Y-m-d'),
                    'count' => count($rates),
                    'currencies' => array_keys($rates)
                ];
                
                $this->logger->info('Successfully warmed all current rates', [
                    'date' => $date->format('Y-m-d'),
                    'count' => count($rates)
                ]);
            } else {
                $results['all_rates'] = [
                    'status' => 'no_data',
                    'date' => $date->format('Y-m-d'),
                    'message' => 'No rates available for date'
                ];
                
                $this->logger->warning('No current rates available for warming', [
                    'date' => $date->format('Y-m-d')
                ]);
            }
            
        } catch (\Exception $e) {
            $results['all_rates'] = [
                'status' => 'error',
                'date' => $date->format('Y-m-d'),
                'error' => $e->getMessage()
            ];
            
            $this->logger->error('Failed to warm current rates', [
                'date' => $date->format('Y-m-d'),
                'error' => $e->getMessage()
            ]);
        }
        
        return $results;
    }

    /**
     * Warm cache with historical rates for specific currency
     * 
     * Loads last N days of historical data for a currency.
     * Useful for charts and trend analysis.
     */
    public function warmHistoricalRates(
        Currency $currency,
        int $days = self::DEFAULT_HISTORY_DAYS,
        ?DateTimeImmutable $endDate = null
    ): array {
        $endDate = $endDate ?? new DateTimeImmutable();
        $startDate = $endDate->modify("-{$days} days");
        
        $this->logger->info('Starting cache warming for historical rates', [
            'currency' => $currency->getCode(),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'days' => $days
        ]);
        
        $attempt = 1;
        while ($attempt <= self::MAX_RETRY_ATTEMPTS) {
            try {
                $rates = $this->repository->findHistoricalRates($currency, $startDate, $endDate);
                
                $result = [
                    'status' => 'success',
                    'currency' => $currency->getCode(),
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'requested_days' => $days,
                    'actual_count' => count($rates),
                    'attempt' => $attempt
                ];
                
                if (!empty($rates)) {
                    $this->logger->info('Successfully warmed historical rates', $result);
                } else {
                    $result['status'] = 'no_data';
                    $result['message'] = 'No historical rates available for period';
                    $this->logger->warning('No historical rates available for warming', $result);
                }
                
                return $result;
                
            } catch (\Exception $e) {
                $this->logger->warning('Failed to warm historical rates', [
                    'currency' => $currency->getCode(),
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'attempt' => $attempt,
                    'error' => $e->getMessage()
                ]);
                
                if ($attempt === self::MAX_RETRY_ATTEMPTS) {
                    return [
                        'status' => 'error',
                        'currency' => $currency->getCode(),
                        'start_date' => $startDate->format('Y-m-d'),
                        'end_date' => $endDate->format('Y-m-d'),
                        'attempts' => $attempt,
                        'error' => $e->getMessage()
                    ];
                }
                
                $attempt++;
                sleep(self::RETRY_DELAY_SECONDS);
            }
        }
        
        // This should never be reached, but just in case
        return [
            'status' => 'error',
            'currency' => $currency->getCode(),
            'error' => 'Unexpected error in retry loop'
        ];
    }

    /**
     * Warm cache for all supported currencies with historical data
     * 
     * Comprehensive warming that loads current and historical rates
     * for all supported currencies. This is the most complete warming operation.
     */
    public function warmAllCurrencies(
        int $historyDays = self::DEFAULT_HISTORY_DAYS,
        ?DateTimeImmutable $date = null
    ): array {
        $date = $date ?? new DateTimeImmutable();
        $results = [];
        
        $this->logger->info('Starting comprehensive cache warming', [
            'date' => $date->format('Y-m-d'),
            'history_days' => $historyDays,
            'currencies' => array_map(fn($c) => $c->value, CurrencyCode::cases())
        ]);
        
        $startTime = microtime(true);
        
        // Step 1: Warm current rates for all currencies
        $results['current'] = $this->warmCurrentRates($date);
        
        // Step 2: Warm historical rates for each currency
        $results['historical'] = [];
        foreach (CurrencyCode::cases() as $currencyCode) {
            $currency = Currency::fromCode($currencyCode->value);
            $results['historical'][$currencyCode->value] = $this->warmHistoricalRates(
                $currency,
                $historyDays,
                $date
            );
        }
        
        // Step 3: Calculate summary statistics
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        
        $successCount = 0;
        $errorCount = 0;
        $totalRates = 0;
        
        // Count current rates
        if ($results['current']['all_rates']['status'] === 'success') {
            $successCount++;
            $totalRates += $results['current']['all_rates']['count'] ?? 0;
        } else {
            $errorCount++;
        }
        
        // Count historical rates
        foreach ($results['historical'] as $currencyResult) {
            if ($currencyResult['status'] === 'success') {
                $successCount++;
                $totalRates += $currencyResult['actual_count'] ?? 0;
            } else {
                $errorCount++;
            }
        }
        
        $results['summary'] = [
            'duration_seconds' => $duration,
            'operations_total' => $successCount + $errorCount,
            'operations_success' => $successCount,
            'operations_error' => $errorCount,
            'success_rate' => $successCount > 0 ? round(($successCount / ($successCount + $errorCount)) * 100, 1) : 0,
            'total_rates_cached' => $totalRates,
            'currencies_processed' => count(CurrencyCode::cases()),
            'history_days' => $historyDays
        ];
        
        $this->logger->info('Completed comprehensive cache warming', $results['summary']);
        
        return $results;
    }

    /**
     * Warm cache for business days fallback
     * 
     * Preloads rates for previous business days to handle weekends
     * and holidays when NBP doesn't publish new rates.
     */
    public function warmBusinessDaysFallback(?DateTimeImmutable $date = null): array
    {
        $date = $date ?? new DateTimeImmutable();
        $results = [];
        
        $this->logger->info('Starting business days fallback warming', [
            'reference_date' => $date->format('Y-m-d')
        ]);
        
        // Warm previous 7 days to cover weekends and potential holidays
        for ($i = 1; $i <= 7; $i++) {
            $fallbackDate = $date->modify("-{$i} days");
            $dayName = $fallbackDate->format('l'); // Monday, Tuesday, etc.
            
            try {
                $rates = $this->repository->findAllByDate($fallbackDate);
                
                $results[$fallbackDate->format('Y-m-d')] = [
                    'status' => !empty($rates) ? 'success' : 'no_data',
                    'date' => $fallbackDate->format('Y-m-d'),
                    'day_name' => $dayName,
                    'count' => count($rates),
                    'days_back' => $i
                ];
                
                if (!empty($rates)) {
                    $this->logger->debug('Warmed fallback date', [
                        'date' => $fallbackDate->format('Y-m-d'),
                        'day' => $dayName,
                        'count' => count($rates)
                    ]);
                }
                
            } catch (\Exception $e) {
                $results[$fallbackDate->format('Y-m-d')] = [
                    'status' => 'error',
                    'date' => $fallbackDate->format('Y-m-d'),
                    'day_name' => $dayName,
                    'days_back' => $i,
                    'error' => $e->getMessage()
                ];
                
                $this->logger->warning('Failed to warm fallback date', [
                    'date' => $fallbackDate->format('Y-m-d'),
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $results;
    }

    /**
     * Get cache warming status and statistics
     * 
     * Provides information about current cache state without warming.
     * Useful for monitoring and debugging.
     */
    public function getWarmingStatus(): array
    {
        $today = new DateTimeImmutable();
        $status = [
            'timestamp' => $today->format('c'),
            'current_rates' => [],
            'supported_currencies' => []
        ];
        
        // Check current rates availability (this will use cache if available)
        try {
            $rates = $this->repository->findAllByDate($today);
            $status['current_rates'] = [
                'date' => $today->format('Y-m-d'),
                'available' => !empty($rates),
                'count' => count($rates),
                'currencies' => array_keys($rates)
            ];
        } catch (\Exception $e) {
            $status['current_rates'] = [
                'date' => $today->format('Y-m-d'),
                'available' => false,
                'error' => $e->getMessage()
            ];
        }
        
        // List supported currencies
        foreach (CurrencyCode::cases() as $currencyCode) {
            $currency = Currency::fromCode($currencyCode->value);
            $status['supported_currencies'][] = [
                'code' => $currencyCode->value,
                'supports_buying' => $currency->supportsBuying(),
                'buy_margin' => $currency->getBuyMargin(),
                'sell_margin' => $currency->getSellMargin()
            ];
        }
        
        return $status;
    }
}
