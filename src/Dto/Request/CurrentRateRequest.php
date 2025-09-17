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
            $requestedDate = new \DateTimeImmutable($this->date);
            
            // Validate date range: max 14 days back, not future
            $today = new \DateTimeImmutable();
            $minDate = $today->modify('-14 days');
            
            if ($requestedDate > $today) {
                throw new \InvalidArgumentException(
                    sprintf('Date cannot be in the future. Requested: %s, Today: %s', 
                        $requestedDate->format('Y-m-d'), 
                        $today->format('Y-m-d')
                    )
                );
            }
            
            if ($requestedDate < $minDate) {
                throw new \InvalidArgumentException(
                    sprintf('Date cannot be more than 14 days in the past. Requested: %s, Minimum: %s', 
                        $requestedDate->format('Y-m-d'), 
                        $minDate->format('Y-m-d')
                    )
                );
            }
            
            return $requestedDate;
        } catch (\Exception $e) {
            if ($e instanceof \InvalidArgumentException) {
                throw $e; // Re-throw our validation errors
            }
            
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
