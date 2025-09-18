<?php

declare(strict_types=1);

namespace App\Domain\Rate;

use App\Domain\Currency\Currency;
use DateTimeImmutable;

/**
 * Rate Repository Interface
 * 
 * Defines contract for rate data access.
 * Follows Repository Pattern for clean architecture.
 * 
 * Benefits:
 * - Testability (easy mocking)
 * - Flexibility (multiple implementations: NBP, cache, database)
 * - Separation of concerns (domain doesn't know about infrastructure)
 */
interface RateRepository
{
    /**
     * Find rate for specific currency and date
     * 
     * @return Rate|null Returns null if rate not found
     */
    public function findByCurrencyAndDate(Currency $currency, DateTimeImmutable $date): ?Rate;

    /**
     * Find rates for all supported currencies on specific date
     * 
     * @return Rate[] Array of rates indexed by currency code
     */
    public function findAllByDate(DateTimeImmutable $date): array;

    /**
     * Find historical rates for currency within date range
     * 
     * @param Currency $currency
     * @param DateTimeImmutable $startDate
     * @param DateTimeImmutable $endDate
     * @return Rate[] Array of rates ordered by date (newest first)
     */
    public function findHistoricalRates(
        Currency $currency, 
        DateTimeImmutable $startDate, 
        DateTimeImmutable $endDate
    ): array;

    /**
     * Save rate (create or update)
     */
    public function save(Rate $rate): void;

    /**
     * Save multiple rates in batch
     * 
     * @param Rate[] $rates
     */
    public function saveAll(array $rates): void;

    /**
     * Check if rate exists for currency and date
     */
    public function exists(Currency $currency, DateTimeImmutable $date): bool;
}
