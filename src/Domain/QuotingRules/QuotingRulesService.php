<?php

declare(strict_types=1);

namespace App\Domain\QuotingRules;

use App\Domain\Currency\Currency;
use App\Domain\Rate\Rate;
use DateTimeImmutable;

/**
 * Quoting Rules Service
 * 
 * Domain Service that encapsulates business rules for currency rate calculations.
 * Centralizes all quoting logic in one place for consistency and maintainability.
 * 
 * Business Rules:
 * - EUR, USD: buy = mid - 0.15 PLN, sell = mid + 0.11 PLN
 * - CZK, IDR, BRL: no buy operations, sell = mid + 0.20 PLN
 * 
 * Domain Service Pattern:
 * - Stateless operations
 * - Pure business logic
 * - No infrastructure dependencies
 */
final class QuotingRulesService
{
    /**
     * Calculate buy rate for currency
     * 
     * @return float|null Returns null if currency doesn't support buying
     */
    public function calculateBuyRate(Currency $currency, float $midRate): ?float
    {
        $buyMargin = $currency->getBuyMargin();
        
        if ($buyMargin === null) {
            return null; // Currency doesn't support buying
        }
        
        return $midRate + $buyMargin;
    }

    /**
     * Calculate sell rate for currency
     */
    public function calculateSellRate(Currency $currency, float $midRate): float
    {
        $sellMargin = $currency->getSellMargin();
        return $midRate + $sellMargin;
    }

    /**
     * Create Rate with calculated buy/sell rates
     */
    public function createRateFromMid(
        Currency $currency,
        DateTimeImmutable $date,
        float $midRate
    ): Rate {
        $buyRate = $this->calculateBuyRate($currency, $midRate);
        $sellRate = $this->calculateSellRate($currency, $midRate);
        
        return new Rate($currency, $date, $midRate, $buyRate, $sellRate);
    }

    /**
     * Calculate profit margin for buy operation
     * 
     * @return float|null Returns null if currency doesn't support buying
     */
    public function getBuyProfitMargin(Currency $currency): ?float
    {
        return $currency->getBuyMargin();
    }

    /**
     * Calculate profit margin for sell operation
     */
    public function getSellProfitMargin(Currency $currency): float
    {
        return $currency->getSellMargin();
    }

    /**
     * Check if currency supports buy operations
     */
    public function supportsBuying(Currency $currency): bool
    {
        return $currency->supportsBuying();
    }

    /**
     * Get all business rules for currency as array
     * 
     * @return array{
     *     code: string,
     *     supportsBuying: bool,
     *     buyMargin: float|null,
     *     sellMargin: float
     * }
     */
    public function getCurrencyRules(Currency $currency): array
    {
        return [
            'code' => $currency->getCode(),
            'supportsBuying' => $currency->supportsBuying(),
            'buyMargin' => $currency->getBuyMargin(),
            'sellMargin' => $currency->getSellMargin(),
        ];
    }

    /**
     * Get business rules for all supported currencies
     * 
     * @return array<string, array{
     *     code: string,
     *     supportsBuying: bool,
     *     buyMargin: float|null,
     *     sellMargin: float
     * }>
     */
    public function getAllCurrencyRules(): array
    {
        $rules = [];
        
        foreach (['EUR', 'USD', 'CZK', 'IDR', 'BRL'] as $code) {
            $currency = Currency::fromCode($code);
            $rules[$code] = $this->getCurrencyRules($currency);
        }
        
        return $rules;
    }

    /**
     * Validate if mid rate is reasonable for currency
     * 
     * Basic sanity checks to prevent obviously wrong rates
     */
    public function validateMidRate(Currency $currency, float $midRate): bool
    {
        // Basic validation - rate must be positive
        if ($midRate <= 0) {
            return false;
        }
        
        // Currency-specific reasonable ranges (very loose validation)
        return match ($currency->getCode()) {
            'EUR' => $midRate >= 3.0 && $midRate <= 6.0,     // EUR typically 4.0-5.0 PLN
            'USD' => $midRate >= 3.0 && $midRate <= 6.0,     // USD typically 3.5-4.5 PLN
            'CZK' => $midRate >= 0.10 && $midRate <= 0.30,   // CZK typically 0.15-0.20 PLN
            'IDR' => $midRate >= 0.0001 && $midRate <= 0.001, // IDR very small values
            'BRL' => $midRate >= 0.50 && $midRate <= 1.50,   // BRL typically 0.7-1.0 PLN
            default => true, // Unknown currency, skip validation
        };
    }
}
