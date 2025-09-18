<?php

declare(strict_types=1);

namespace App\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Historical Rates Request DTO
 * 
 * Validates query parameters for historical rates endpoint.
 * Ensures proper date format and days range validation.
 */
final class HistoricalRatesRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Currency code is required')]
        #[Assert\Length(exactly: 3, exactMessage: 'Currency code must be exactly 3 characters')]
        #[Assert\Regex(pattern: '/^[A-Z]{3}$/', message: 'Currency code must contain only uppercase letters')]
        public readonly string $currency,

        #[Assert\Date(message: 'Date must be in valid format (YYYY-MM-DD)')]
        public readonly ?string $date = null,

        #[Assert\Type(type: 'integer', message: 'Days must be an integer')]
        #[Assert\Range(min: 1, max: 365, notInRangeMessage: 'Days must be between 1 and 365')]
        public readonly int $days = 14
    ) {
    }

    /**
     * Create from query parameters
     * 
     * @param array<string, mixed> $queryParams
     */
    public static function fromQueryParams(string $currency, array $queryParams): self
    {
        return new self(
            currency: strtoupper($currency),
            date: $queryParams['date'] ?? null,
            days: (int) ($queryParams['days'] ?? 14)
        );
    }

    /**
     * Get parsed end date
     */
    public function getEndDate(): \DateTimeImmutable
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
     * Get calculated start date
     */
    public function getStartDate(): \DateTimeImmutable
    {
        return $this->getEndDate()->modify("-{$this->days} days");
    }
}
