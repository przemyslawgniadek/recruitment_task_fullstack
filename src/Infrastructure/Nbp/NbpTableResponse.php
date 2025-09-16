<?php

declare(strict_types=1);

namespace App\Infrastructure\Nbp;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * NBP Table Response
 * 
 * Represents response from: /api/exchangerates/tables/A/
 */
final class NbpTableResponse
{
    /**
     * @param NbpTableEntry[] $rates
     */
    public function __construct(
        private string $table,
        private string $no,
        private DateTimeImmutable $effectiveDate,
        private array $rates
    ) {
    }

    /**
     * Create from NBP API JSON response
     * 
     * @param array<mixed> $data Raw JSON decoded array
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data[0])) {
            throw new InvalidArgumentException('Invalid NBP table response: missing table data');
        }

        $tableData = $data[0];
        
        if (!isset($tableData['table'], $tableData['no'], $tableData['effectiveDate'], $tableData['rates'])) {
            throw new InvalidArgumentException('Invalid NBP table response: missing required fields');
        }

        $rates = array_map(
            fn(array $rate) => NbpTableEntry::fromArray($rate),
            $tableData['rates']
        );

        return new self(
            $tableData['table'],
            $tableData['no'],
            new DateTimeImmutable($tableData['effectiveDate']),
            $rates
        );
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getNo(): string
    {
        return $this->no;
    }

    public function getEffectiveDate(): DateTimeImmutable
    {
        return $this->effectiveDate;
    }

    /**
     * @return NbpTableEntry[]
     */
    public function getRates(): array
    {
        return $this->rates;
    }

    /**
     * Find rate by currency code
     */
    public function findRateByCurrency(string $currencyCode): ?NbpTableEntry
    {
        foreach ($this->rates as $rate) {
            if ($rate->getCode() === strtoupper($currencyCode)) {
                return $rate;
            }
        }

        return null;
    }
}
