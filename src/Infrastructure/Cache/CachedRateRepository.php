<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Domain\Currency\Currency;
use App\Domain\Rate\Rate;
use App\Domain\Rate\RateRepository;
use DateTimeImmutable;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Cached Rate Repository Decorator
 * 
 * Implements Decorator Pattern to add caching functionality to any RateRepository.
 * Wraps original repository with cache layer for performance and reliability.
 * 
 * Decorator Pattern Benefits:
 * - Adds caching without modifying original repository
 * - Can wrap any RateRepository implementation
 * - Transparent to Domain Layer (same interface)
 * - Easy to enable/disable caching
 */
class CachedRateRepository implements RateRepository
{
    private const CACHE_TTL = 86400; // 24 hours in seconds
    
    public function __construct(
        private RateRepository $decoratedRepository,
        private CacheItemPoolInterface $cache,
        private CacheKeyGenerator $keyGenerator,
        private LoggerInterface $logger = new NullLogger()
    ) {
    }

    /**
     * Find rate for specific currency and date with caching
     */
    public function findByCurrencyAndDate(Currency $currency, DateTimeImmutable $date): ?Rate
    {
        $cacheKey = $this->keyGenerator->forSingleRate($currency, $date);
        $cacheItem = $this->cache->getItem($cacheKey);
        
        if ($cacheItem->isHit()) {
            $this->logger->debug('Cache HIT for rate', [
                'currency' => $currency->getCode(),
                'date' => $date->format('Y-m-d'),
                'key' => $cacheKey
            ]);
            
            $cachedData = $cacheItem->get();
            return $this->deserializeRate($cachedData);
        }
        
        $this->logger->debug('Cache MISS for rate', [
            'currency' => $currency->getCode(),
            'date' => $date->format('Y-m-d'),
            'key' => $cacheKey
        ]);
        
        // Fetch from decorated repository
        try {
            $rate = $this->decoratedRepository->findByCurrencyAndDate($currency, $date);
            
            if ($rate !== null) {
                // Cache the result
                $cacheItem->set($this->serializeRate($rate));
                $cacheItem->expiresAfter(self::CACHE_TTL);
                $this->cache->save($cacheItem);
                
                $this->logger->info('Rate cached successfully', [
                    'currency' => $currency->getCode(),
                    'date' => $date->format('Y-m-d'),
                    'ttl' => self::CACHE_TTL
                ]);
            }
            
            return $rate;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch rate from decorated repository', [
                'currency' => $currency->getCode(),
                'date' => $date->format('Y-m-d'),
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Find rates for all supported currencies on specific date with caching
     * 
     * @return Rate[] Array of rates indexed by currency code
     */
    public function findAllByDate(DateTimeImmutable $date): array
    {
        $cacheKey = $this->keyGenerator->forAllRates($date);
        $cacheItem = $this->cache->getItem($cacheKey);
        
        if ($cacheItem->isHit()) {
            $this->logger->debug('Cache HIT for all rates', [
                'date' => $date->format('Y-m-d'),
                'key' => $cacheKey
            ]);
            
            $cachedData = $cacheItem->get();
            return $this->deserializeRatesArray($cachedData);
        }
        
        $this->logger->debug('Cache MISS for all rates', [
            'date' => $date->format('Y-m-d'),
            'key' => $cacheKey
        ]);
        
        // Fetch from decorated repository
        try {
            $rates = $this->decoratedRepository->findAllByDate($date);
            
            if (!empty($rates)) {
                // Cache the result
                $cacheItem->set($this->serializeRatesArray($rates));
                $cacheItem->expiresAfter(self::CACHE_TTL);
                $this->cache->save($cacheItem);
                
                $this->logger->info('All rates cached successfully', [
                    'date' => $date->format('Y-m-d'),
                    'count' => count($rates),
                    'ttl' => self::CACHE_TTL
                ]);
            }
            
            return $rates;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch all rates from decorated repository', [
                'date' => $date->format('Y-m-d'),
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Find historical rates for currency within date range with caching
     * 
     * @return Rate[] Array of rates ordered by date (newest first)
     */
    public function findHistoricalRates(
        Currency $currency,
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate
    ): array {
        $cacheKey = $this->keyGenerator->forHistoricalRates($currency, $startDate, $endDate);
        $cacheItem = $this->cache->getItem($cacheKey);
        
        if ($cacheItem->isHit()) {
            $this->logger->debug('Cache HIT for historical rates', [
                'currency' => $currency->getCode(),
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
                'key' => $cacheKey
            ]);
            
            $cachedData = $cacheItem->get();
            return $this->deserializeRatesArray($cachedData);
        }
        
        $this->logger->debug('Cache MISS for historical rates', [
            'currency' => $currency->getCode(),
            'start' => $startDate->format('Y-m-d'),
            'end' => $endDate->format('Y-m-d'),
            'key' => $cacheKey
        ]);
        
        // Fetch from decorated repository
        try {
            $rates = $this->decoratedRepository->findHistoricalRates($currency, $startDate, $endDate);
            
            if (!empty($rates)) {
                // Cache the result
                $cacheItem->set($this->serializeRatesArray($rates));
                $cacheItem->expiresAfter(self::CACHE_TTL);
                $this->cache->save($cacheItem);
                
                $this->logger->info('Historical rates cached successfully', [
                    'currency' => $currency->getCode(),
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                    'count' => count($rates),
                    'ttl' => self::CACHE_TTL
                ]);
            }
            
            return $rates;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch historical rates from decorated repository', [
                'currency' => $currency->getCode(),
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Save rate (delegates to decorated repository)
     */
    public function save(Rate $rate): void
    {
        $this->decoratedRepository->save($rate);
        
        // Invalidate related cache entries
        $this->invalidateCacheForRate($rate);
    }

    /**
     * Save multiple rates (delegates to decorated repository)
     */
    public function saveAll(array $rates): void
    {
        $this->decoratedRepository->saveAll($rates);
        
        // Invalidate related cache entries
        foreach ($rates as $rate) {
            $this->invalidateCacheForRate($rate);
        }
    }

    /**
     * Check if rate exists (with caching)
     */
    public function exists(Currency $currency, DateTimeImmutable $date): bool
    {
        // Try to find the rate (which uses cache)
        $rate = $this->findByCurrencyAndDate($currency, $date);
        return $rate !== null;
    }

    /**
     * Clear all cache entries
     */
    public function clearCache(): bool
    {
        $this->logger->info('Clearing all cache entries');
        return $this->cache->clear();
    }

    /**
     * Warm cache for specific date (preload all currencies)
     */
    public function warmCacheForDate(DateTimeImmutable $date): void
    {
        $this->logger->info('Warming cache for date', ['date' => $date->format('Y-m-d')]);
        
        try {
            // This will fetch and cache all rates for the date
            $this->findAllByDate($date);
        } catch (\Exception $e) {
            $this->logger->warning('Failed to warm cache for date', [
                'date' => $date->format('Y-m-d'),
                'error' => $e->getMessage()
            ]);
        }
    }


    /**
     * Serialize rate for caching
     * 
     * @return array<string, mixed>
     */
    private function serializeRate(Rate $rate): array
    {
        return $rate->toArray();
    }

    /**
     * Deserialize rate from cache
     */
    private function deserializeRate(array $data): ?Rate
    {
        if (!isset($data['code'], $data['date'], $data['mid'], $data['sell'])) {
            return null;
        }
        
        try {
            $currency = Currency::fromCode($data['code']);
            $date = new DateTimeImmutable($data['date']);
            
            return new Rate(
                $currency,
                $date,
                $data['mid'],
                $data['buy'] ?? null,
                $data['sell']
            );
        } catch (\Exception $e) {
            $this->logger->warning('Failed to deserialize rate from cache', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Serialize rates array for caching
     * 
     * @param Rate[] $rates
     * @return array<string, array<string, mixed>>
     */
    private function serializeRatesArray(array $rates): array
    {
        $serialized = [];
        foreach ($rates as $key => $rate) {
            $serialized[$key] = $this->serializeRate($rate);
        }
        return $serialized;
    }

    /**
     * Deserialize rates array from cache
     * 
     * @param array<string, array<string, mixed>> $data
     * @return Rate[]
     */
    private function deserializeRatesArray(array $data): array
    {
        $rates = [];
        foreach ($data as $key => $rateData) {
            $rate = $this->deserializeRate($rateData);
            if ($rate !== null) {
                $rates[$key] = $rate;
            }
        }
        return $rates;
    }

    /**
     * Invalidate cache entries related to a rate
     */
    private function invalidateCacheForRate(Rate $rate): void
    {
        $currency = $rate->getCurrency();
        $date = $rate->getDate();
        
        // Invalidate single rate cache
        $singleKey = $this->keyGenerator->forSingleRate($currency, $date);
        $this->cache->deleteItem($singleKey);
        
        // Invalidate all rates cache for the date
        $allKey = $this->keyGenerator->forAllRates($date);
        $this->cache->deleteItem($allKey);
        
        $this->logger->debug('Cache invalidated for rate', [
            'currency' => $currency->getCode(),
            'date' => $date->format('Y-m-d'),
            'keys' => [$singleKey, $allKey]
        ]);
    }
}
