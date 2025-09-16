<?php

declare(strict_types=1);

namespace App\Domain\Currency;

use InvalidArgumentException;

/**
 * Currency Value Object
 * 
 * Immutable representation of a currency with its code and business rules.
 * Encapsulates currency-specific logic and validation.
 * 
 * Value Object Pattern:
 * - Immutable (no setters)
 * - Equality based on value (code)
 * - Self-validating
 */
final class Currency
{
    public function __construct(
        private CurrencyCode $code
    ) {
    }

    /**
     * Create Currency from string code
     * 
     * @throws InvalidArgumentException if currency code is not supported
     */
    public static function fromCode(string $code): self
    {
        $currencyCode = CurrencyCode::tryFrom(strtoupper($code));
        
        if ($currencyCode === null) {
            throw new InvalidArgumentException(
                sprintf('Unsupported currency code: %s. Supported: %s', 
                    $code, 
                    implode(', ', CurrencyCode::getAllCodes())
                )
            );
        }

        return new self($currencyCode);
    }

    /**
     * Get currency code as string
     */
    public function getCode(): string
    {
        return $this->code->value;
    }

    /**
     * Get currency code enum
     */
    public function getCurrencyCode(): CurrencyCode
    {
        return $this->code;
    }

    /**
     * Check if this currency supports buy operations
     */
    public function supportsBuying(): bool
    {
        return $this->code->supportsBuying();
    }

    /**
     * Get sell margin for this currency
     */
    public function getSellMargin(): float
    {
        return $this->code->getSellMargin();
    }

    /**
     * Get buy margin for this currency (null if not supported)
     */
    public function getBuyMargin(): ?float
    {
        return $this->code->getBuyMargin();
    }

    /**
     * Value object equality - two currencies are equal if they have the same code
     */
    public function equals(Currency $other): bool
    {
        return $this->code === $other->code;
    }

    /**
     * String representation
     */
    public function __toString(): string
    {
        return $this->code->value;
    }
}
