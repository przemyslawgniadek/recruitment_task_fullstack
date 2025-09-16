<?php

declare(strict_types=1);

namespace App\Domain\Currency;

/**
 * Currency Code Enum
 * 
 * Represents supported currency codes for the exchange office.
 * Uses PHP 8.1+ enum for type safety and validation.
 * 
 * Business Rules:
 * - EUR, USD: support both buy and sell operations
 * - CZK, IDR, BRL: support only sell operations (no buying)
 */
enum CurrencyCode: string
{
    case EUR = 'EUR';
    case USD = 'USD';
    case CZK = 'CZK';
    case IDR = 'IDR';
    case BRL = 'BRL';

    /**
     * Get all supported currency codes
     * 
     * @return array<string>
     */
    public static function getAllCodes(): array
    {
        return array_map(fn(self $case) => $case->value, self::cases());
    }

    /**
     * Check if currency supports buy operations
     * 
     * Business Rule: Only EUR and USD support buying
     */
    public function supportsBuying(): bool
    {
        return match ($this) {
            self::EUR, self::USD => true,
            self::CZK, self::IDR, self::BRL => false,
        };
    }

    /**
     * Get the sell margin for this currency
     * 
     * Business Rules:
     * - EUR, USD: +0.11 PLN margin
     * - Others: +0.20 PLN margin
     */
    public function getSellMargin(): float
    {
        return match ($this) {
            self::EUR, self::USD => 0.11,
            self::CZK, self::IDR, self::BRL => 0.20,
        };
    }

    /**
     * Get the buy margin for this currency
     * 
     * Business Rules:
     * - EUR, USD: -0.15 PLN margin
     * - Others: null (no buying supported)
     */
    public function getBuyMargin(): ?float
    {
        return match ($this) {
            self::EUR, self::USD => -0.15,
            self::CZK, self::IDR, self::BRL => null,
        };
    }
}
