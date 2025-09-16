<?php

declare(strict_types=1);

namespace App\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Current Rate Request DTO
 * 
 * Validates query parameters for current rate endpoints.
 * Handles optional date parameter with proper validation.
 */
final class CurrentRateRequest
{
    public function __construct(
        #[Assert\Date(message: 'Date must be in valid format (YYYY-MM-DD)')]
        public readonly ?string $date = null,

        public readonly ?string $currency = null
    ) {
    }

    /**
     * Create from query parameters
     * 
     * @param array<string, mixed> $queryParams
     */
    public static function fromQueryParams(array $queryParams, ?string $currency = null): self
    {
        return new self(
            date: $queryParams['date'] ?? null,
            currency: $currency ? strtoupper($currency) : null
        );
    }

    /**
     * Get parsed date or default to today
     */
    public function getDate(): \DateTimeImmutable
    {
        if ($this->date === null) {
            return new \DateTimeImmutable();
        }

        try {
            return new \DateTimeImmutable($this->date);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(
                sprintf('Invalid date format: %s. Expected format: YYYY-MM-DD', $this->date)
            );
        }
    }

    /**
     * Check if currency is provided and valid
     */
    public function hasCurrency(): bool
    {
        return $this->currency !== null;
    }

    /**
     * Get currency code (throws if not provided)
     */
    public function getCurrency(): string
    {
        if ($this->currency === null) {
            throw new \LogicException('Currency not provided in request');
        }

        return $this->currency;
    }
}
