<?php

declare(strict_types=1);

namespace App\Domain\Rate;

use App\Domain\Currency\Currency;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Rate Entity
 * 
 * Represents an exchange rate for a specific currency on a specific date.
 * Contains mid rate from NBP and calculated buy/sell rates.
 * 
 * Entity Pattern:
 * - Has identity (currency + date combination)
 * - Mutable (rates can be updated)
 * - Rich domain model with business logic
 */
final class Rate
{
    private Currency $currency;
    private DateTimeImmutable $date;
    private float $midRate;
    private ?float $buyRate;
    private float $sellRate;

    public function __construct(
        Currency $currency,
        DateTimeImmutable $date,
        float $midRate,
        ?float $buyRate = null,
        ?float $sellRate = null
    ) {
        $this->validateMidRate($midRate);
        
        $this->currency = $currency;
        $this->date = $date;
        $this->midRate = $midRate;
        
        // Auto-calculate buy/sell rates if not provided
        $this->buyRate = $buyRate ?? $this->calculateBuyRate();
        $this->sellRate = $sellRate ?? $this->calculateSellRate();
    }

    /**
     * Create Rate with auto-calculated buy/sell rates based on business rules
     */
    public static function fromMidRate(
        Currency $currency,
        DateTimeImmutable $date,
        float $midRate
    ): self {
        return new self($currency, $date, $midRate);
    }

    /**
     * Get currency
     */
    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    /**
     * Get date
     */
    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    /**
     * Get mid rate (NBP average rate)
     */
    public function getMidRate(): float
    {
        return $this->midRate;
    }

    /**
     * Get buy rate (null if currency doesn't support buying)
     */
    public function getBuyRate(): ?float
    {
        return $this->buyRate;
    }

    /**
     * Get sell rate
     */
    public function getSellRate(): float
    {
        return $this->sellRate;
    }

    /**
     * Update mid rate and recalculate buy/sell rates
     */
    public function updateMidRate(float $newMidRate): void
    {
        $this->validateMidRate($newMidRate);
        
        $this->midRate = $newMidRate;
        $this->buyRate = $this->calculateBuyRate();
        $this->sellRate = $this->calculateSellRate();
    }

    /**
     * Get entity identity (currency code + date)
     */
    public function getId(): string
    {
        return sprintf('%s_%s', 
            $this->currency->getCode(), 
            $this->date->format('Y-m-d')
        );
    }

    /**
     * Entity equality - two rates are equal if they have the same identity
     */
    public function equals(Rate $other): bool
    {
        return $this->getId() === $other->getId();
    }

    /**
     * Convert to array for API responses
     * 
     * @return array{code: string, date: string, mid: float, buy: float|null, sell: float}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->currency->getCode(),
            'date' => $this->date->format('Y-m-d'),
            'mid' => round($this->midRate, 4),
            'buy' => $this->buyRate !== null ? round($this->buyRate, 4) : null,
            'sell' => round($this->sellRate, 4),
        ];
    }

    /**
     * Calculate buy rate based on currency business rules
     */
    private function calculateBuyRate(): ?float
    {
        $buyMargin = $this->currency->getBuyMargin();
        
        if ($buyMargin === null) {
            return null; // Currency doesn't support buying
        }
        
        return $this->midRate + $buyMargin;
    }

    /**
     * Calculate sell rate based on currency business rules
     */
    private function calculateSellRate(): float
    {
        $sellMargin = $this->currency->getSellMargin();
        return $this->midRate + $sellMargin;
    }

    /**
     * Validate mid rate value
     */
    private function validateMidRate(float $midRate): void
    {
        if ($midRate <= 0) {
            throw new InvalidArgumentException(
                sprintf('Mid rate must be positive, got: %f', $midRate)
            );
        }
    }
}
