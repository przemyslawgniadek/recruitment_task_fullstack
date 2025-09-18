<?php

declare(strict_types=1);

namespace App\Infrastructure\Nbp;

use InvalidArgumentException;

/**
 * NBP Single Rate Response
 * 
 * Represents response from: /api/exchangerates/rates/A/{code}/
 */
final class NbpSingleRateResponse
{
    /**
     * @param NbpRateEntry[] $rates
     */
    public function __construct(
        private string $table,
        private string $currency,
        private string $code,
        private array $rates
    ) {
    }

    /**
     * Create from NBP API JSON response
     * 
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['table'], $data['currency'], $data['code'], $data['rates'])) {
            throw new InvalidArgumentException('Invalid NBP single rate response: missing required fields');
        }

        $rates = array_map(
            fn(array $rate) => NbpRateEntry::fromArray($rate),
            $data['rates']
        );

        return new self(
            $data['table'],
            $data['currency'],
            $data['code'],
            $rates
        );
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @return NbpRateEntry[]
     */
    public function getRates(): array
    {
        return $this->rates;
    }

    /**
     * Get latest rate entry
     */
    public function getLatestRate(): ?NbpRateEntry
    {
        if (empty($this->rates)) {
            return null;
        }

        // Rates are usually ordered by date, get the last one
        return end($this->rates);
    }
}
