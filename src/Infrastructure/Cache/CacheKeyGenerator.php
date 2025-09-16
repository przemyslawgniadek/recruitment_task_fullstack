<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Domain\Currency\Currency;
use DateTimeImmutable;

/**
 * Cache Key Generator
 * 
 * Centralized cache key generation for consistent naming and easy maintenance.
 * Provides type-safe methods for generating cache keys for different data types.
 * 
 * Key Format: {prefix}.{type}.{identifier}.{date_or_range}
 * Examples:
 * - fx_rate.single.EUR.2024-01-15
 * - fx_rate.all.ALL.2024-01-15  
 * - fx_rate.history.USD.2024-01-01_2024-01-15
 * - nbp_api.table.A.2024-01-15
 * - nbp_api.rates.EUR.2024-01-01_2024-01-15
 */
class CacheKeyGenerator
{
    // Cache prefixes for different data types
    private const PREFIX_FX_RATES = 'fx_rate';
    private const PREFIX_NBP_API = 'nbp_api';
    private const PREFIX_APP_META = 'app_meta';
    
    // Cache types for exchange rates
    private const TYPE_SINGLE = 'single';
    private const TYPE_ALL = 'all';
    private const TYPE_HISTORY = 'history';
    
    // Cache types for NBP API
    private const TYPE_TABLE = 'table';
    private const TYPE_RATES = 'rates';
    
    // Cache types for app metadata
    private const TYPE_CURRENCIES = 'currencies';
    private const TYPE_CONFIG = 'config';
    
    // Key separator
    private const SEPARATOR = '.';

    /**
     * Generate cache key for single currency rate on specific date
     * 
     * Format: fx_rate.single.{currency}.{date}
     * Example: fx_rate.single.EUR.2024-01-15
     */
    public function forSingleRate(Currency $currency, DateTimeImmutable $date): string
    {
        return $this->buildKey(
            self::PREFIX_FX_RATES,
            self::TYPE_SINGLE,
            $currency->getCode(),
            $date->format('Y-m-d')
        );
    }

    /**
     * Generate cache key for all currency rates on specific date
     * 
     * Format: fx_rate.all.ALL.{date}
     * Example: fx_rate.all.ALL.2024-01-15
     */
    public function forAllRates(DateTimeImmutable $date): string
    {
        return $this->buildKey(
            self::PREFIX_FX_RATES,
            self::TYPE_ALL,
            'ALL',
            $date->format('Y-m-d')
        );
    }

    /**
     * Generate cache key for historical rates of currency within date range
     * 
     * Format: fx_rate.history.{currency}.{start_date}_{end_date}
     * Example: fx_rate.history.USD.2024-01-01_2024-01-15
     */
    public function forHistoricalRates(
        Currency $currency,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate
    ): string {
        $dateRange = sprintf(
            '%s_%s',
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );
        
        return $this->buildKey(
            self::PREFIX_FX_RATES,
            self::TYPE_HISTORY,
            $currency->getCode(),
            $dateRange
        );
    }

    /**
     * Generate cache key for NBP exchange rates table
     * 
     * Format: nbp_api.table.{table}.{date}
     * Example: nbp_api.table.A.2024-01-15
     */
    public function forNbpTable(string $table, DateTimeImmutable $date): string
    {
        return $this->buildKey(
            self::PREFIX_NBP_API,
            self::TYPE_TABLE,
            strtoupper($table),
            $date->format('Y-m-d')
        );
    }

    /**
     * Generate cache key for NBP historical rates
     * 
     * Format: nbp_api.rates.{currency}.{start_date}_{end_date}
     * Example: nbp_api.rates.EUR.2024-01-01_2024-01-15
     */
    public function forNbpHistoricalRates(
        string $currencyCode,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate
    ): string {
        $dateRange = sprintf(
            '%s_%s',
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );
        
        return $this->buildKey(
            self::PREFIX_NBP_API,
            self::TYPE_RATES,
            strtoupper($currencyCode),
            $dateRange
        );
    }

    /**
     * Generate cache key for supported currencies list
     * 
     * Format: app_meta.currencies.list.static
     * Example: app_meta.currencies.list.static
     */
    public function forSupportedCurrencies(): string
    {
        return $this->buildKey(
            self::PREFIX_APP_META,
            self::TYPE_CURRENCIES,
            'list',
            'static'
        );
    }

    /**
     * Generate cache key for application configuration
     * 
     * Format: app_meta.config.{key}.static
     * Example: app_meta.config.quoting_rules.static
     */
    public function forAppConfig(string $configKey): string
    {
        return $this->buildKey(
            self::PREFIX_APP_META,
            self::TYPE_CONFIG,
            $configKey,
            'static'
        );
    }

    /**
     * Generate pattern for cache key matching
     * 
     * Useful for cache invalidation and debugging.
     * Uses wildcards (*) for pattern matching.
     * 
     * Examples:
     * - forPattern('fx_rate', 'single', 'EUR', '*') → 'fx_rate.single.EUR.*'
     * - forPattern('fx_rate', '*', '*', '2024-01-15') → 'fx_rate.*.*.2024-01-15'
     */
    public function forPattern(
        string $prefix,
        string $type = '*',
        string $identifier = '*',
        string $dateOrRange = '*'
    ): string {
        return $this->buildKey($prefix, $type, $identifier, $dateOrRange);
    }

    /**
     * Extract components from cache key
     * 
     * Parses cache key back into its components for debugging and analysis.
     * 
     * @return array{prefix: string, type: string, identifier: string, date_or_range: string}
     */
    public function parseKey(string $cacheKey): array
    {
        $parts = explode(self::SEPARATOR, $cacheKey);
        
        if (count($parts) !== 4) {
            throw new \InvalidArgumentException(
                sprintf('Invalid cache key format: %s. Expected format: prefix.type.identifier.date_or_range', $cacheKey)
            );
        }
        
        return [
            'prefix' => $parts[0],
            'type' => $parts[1],
            'identifier' => $parts[2],
            'date_or_range' => $parts[3]
        ];
    }

    /**
     * Check if cache key belongs to specific prefix
     */
    public function belongsToPrefix(string $cacheKey, string $prefix): bool
    {
        return str_starts_with($cacheKey, $prefix . self::SEPARATOR);
    }

    /**
     * Check if cache key is for exchange rates
     */
    public function isExchangeRateKey(string $cacheKey): bool
    {
        return $this->belongsToPrefix($cacheKey, self::PREFIX_FX_RATES);
    }

    /**
     * Check if cache key is for NBP API data
     */
    public function isNbpApiKey(string $cacheKey): bool
    {
        return $this->belongsToPrefix($cacheKey, self::PREFIX_NBP_API);
    }

    /**
     * Check if cache key is for app metadata
     */
    public function isAppMetadataKey(string $cacheKey): bool
    {
        return $this->belongsToPrefix($cacheKey, self::PREFIX_APP_META);
    }

    /**
     * Get all possible prefixes
     * 
     * @return string[]
     */
    public function getAllPrefixes(): array
    {
        return [
            self::PREFIX_FX_RATES,
            self::PREFIX_NBP_API,
            self::PREFIX_APP_META
        ];
    }

    /**
     * Build cache key from components
     */
    private function buildKey(string $prefix, string $type, string $identifier, string $dateOrRange): string
    {
        // Validate components
        $this->validateKeyComponent($prefix, 'prefix');
        $this->validateKeyComponent($type, 'type');
        $this->validateKeyComponent($identifier, 'identifier');
        $this->validateKeyComponent($dateOrRange, 'date_or_range');
        
        return implode(self::SEPARATOR, [$prefix, $type, $identifier, $dateOrRange]);
    }

    /**
     * Validate cache key component
     */
    private function validateKeyComponent(string $component, string $componentName): void
    {
        if (empty($component)) {
            throw new \InvalidArgumentException(
                sprintf('Cache key component "%s" cannot be empty', $componentName)
            );
        }
        
        // Check for invalid characters (cache keys should be safe for all cache backends)
        if (preg_match('/[^a-zA-Z0-9._\-*]/', $component)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Cache key component "%s" contains invalid characters: %s. Only alphanumeric, dots, underscores, hyphens and asterisks are allowed.',
                    $componentName,
                    $component
                )
            );
        }
    }
}
