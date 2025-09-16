<?php

declare(strict_types=1);

namespace App\Infrastructure\Nbp;

use App\Domain\Currency\Currency;
use App\Domain\QuotingRules\QuotingRulesService;
use App\Domain\Rate\Rate;
use App\Domain\Rate\RateRepository;
use App\Infrastructure\Exception\NbpApiException;
use DateTimeImmutable;
use DateInterval;

/**
 * NBP Rate Repository Implementation
 * 
 * Implements RateRepository interface using NBP API as data source.
 * Bridges Domain Layer (business logic) with Infrastructure Layer (NBP API).
 * 
 * Repository Pattern Benefits:
 * - Domain doesn't know about NBP API details
 * - Easy to test with mocked NBP client
 * - Can be replaced with different data source (DB, cache, etc.)
 */
final class NbpRateRepository implements RateRepository
{
    public function __construct(
        private NbpHttpClient $nbpClient,
        private QuotingRulesService $quotingRules
    ) {
    }

    /**
     * Find rate for specific currency and date
     * 
     * @throws NbpApiException
     */
    public function findByCurrencyAndDate(Currency $currency, DateTimeImmutable $date): ?Rate
    {
        try {
            // Try to get rate from NBP table (all currencies for date)
            $tableResponse = $this->nbpClient->getExchangeRatesTable($date);
            $nbpEntry = $tableResponse->findRateByCurrency($currency->getCode());
            
            if ($nbpEntry === null) {
                return null; // Currency not found in NBP data
            }
            
            // Create Rate using QuotingRules for buy/sell calculation
            return $this->quotingRules->createRateFromMid(
                $currency,
                $tableResponse->getEffectiveDate(),
                $nbpEntry->getMid()
            );
            
        } catch (NbpApiException $e) {
            // If no data for specific date, try to find last available business day
            if ($this->isNoDataException($e)) {
                return $this->findLastAvailableRate($currency, $date);
            }
            
            throw $e; // Re-throw other NBP API errors
        }
    }

    /**
     * Find rates for all supported currencies on specific date
     * 
     * @return Rate[] Array of rates indexed by currency code
     */
    public function findAllByDate(DateTimeImmutable $date): array
    {
        try {
            $tableResponse = $this->nbpClient->getExchangeRatesTable($date);
            $rates = [];
            
            // Get all supported currencies from our Domain
            $supportedCurrencies = ['EUR', 'USD', 'CZK', 'IDR', 'BRL'];
            
            foreach ($supportedCurrencies as $currencyCode) {
                $currency = Currency::fromCode($currencyCode);
                $nbpEntry = $tableResponse->findRateByCurrency($currencyCode);
                
                if ($nbpEntry !== null) {
                    $rate = $this->quotingRules->createRateFromMid(
                        $currency,
                        $tableResponse->getEffectiveDate(),
                        $nbpEntry->getMid()
                    );
                    
                    $rates[$currencyCode] = $rate;
                }
            }
            
            return $rates;
            
        } catch (NbpApiException $e) {
            // If no data for specific date, try last available business day
            if ($this->isNoDataException($e)) {
                return $this->findAllRatesForLastAvailableDay($date);
            }
            
            throw $e;
        }
    }

    /**
     * Find historical rates for currency within date range
     * 
     * @return Rate[] Array of rates ordered by date (newest first)
     */
    public function findHistoricalRates(
        Currency $currency,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate
    ): array {
        try {
            $response = $this->nbpClient->getHistoricalRates(
                $currency->getCode(),
                $startDate,
                $endDate
            );
            
            $rates = [];
            
            foreach ($response->getRates() as $nbpRateEntry) {
                $rate = $this->quotingRules->createRateFromMid(
                    $currency,
                    $nbpRateEntry->getEffectiveDate(),
                    $nbpRateEntry->getMid()
                );
                
                $rates[] = $rate;
            }
            
            // Sort by date (newest first)
            usort($rates, fn(Rate $a, Rate $b) => $b->getDate() <=> $a->getDate());
            
            return $rates;
            
        } catch (NbpApiException $e) {
            // For historical data, if no data found, return empty array
            if ($this->isNoDataException($e)) {
                return [];
            }
            
            throw $e;
        }
    }

    /**
     * Save rate (not implemented for NBP - read-only data source)
     */
    public function save(Rate $rate): void
    {
        throw new \BadMethodCallException(
            'Cannot save rates to NBP API - it is a read-only data source'
        );
    }

    /**
     * Save multiple rates (not implemented for NBP - read-only data source)
     */
    public function saveAll(array $rates): void
    {
        throw new \BadMethodCallException(
            'Cannot save rates to NBP API - it is a read-only data source'
        );
    }

    /**
     * Check if rate exists for currency and date
     */
    public function exists(Currency $currency, DateTimeImmutable $date): bool
    {
        try {
            $rate = $this->findByCurrencyAndDate($currency, $date);
            return $rate !== null;
        } catch (NbpApiException $e) {
            return false;
        }
    }

    /**
     * Find last available rate before given date (business day handling)
     */
    private function findLastAvailableRate(Currency $currency, DateTimeImmutable $date): ?Rate
    {
        // Try up to 7 days back to find last business day
        for ($i = 1; $i <= 7; $i++) {
            $previousDate = $date->sub(new DateInterval("P{$i}D"));
            
            try {
                $rate = $this->findByCurrencyAndDate($currency, $previousDate);
                if ($rate !== null) {
                    return $rate;
                }
            } catch (NbpApiException $e) {
                // Continue searching backwards
                continue;
            }
        }
        
        return null; // No data found in last 7 days
    }

    /**
     * Find all rates for last available business day
     * 
     * @return Rate[]
     */
    private function findAllRatesForLastAvailableDay(DateTimeImmutable $date): array
    {
        // Try up to 7 days back to find last business day
        for ($i = 1; $i <= 7; $i++) {
            $previousDate = $date->sub(new DateInterval("P{$i}D"));
            
            try {
                $rates = $this->findAllByDate($previousDate);
                if (!empty($rates)) {
                    return $rates;
                }
            } catch (NbpApiException $e) {
                // Continue searching backwards
                continue;
            }
        }
        
        return []; // No data found in last 7 days
    }

    /**
     * Check if exception indicates "no data available"
     */
    private function isNoDataException(NbpApiException $e): bool
    {
        $message = $e->getMessage();
        return str_contains($message, '404') || 
               str_contains($message, 'No data') || 
               str_contains($message, 'not found');
    }
}
