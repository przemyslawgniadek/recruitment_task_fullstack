<?php

declare(strict_types=1);

namespace App\Infrastructure\Nbp;

use InvalidArgumentException;

/**
 * NBP Table Entry
 * 
 * Single currency rate entry from table response
 */
final class NbpTableEntry
{
    public function __construct(
        private string $currency,
        private string $code,
        private float $mid
    ) {
    }

    /**
     * Create from NBP API rate entry
     * 
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['currency'], $data['code'], $data['mid'])) {
            throw new InvalidArgumentException('Invalid NBP rate entry: missing required fields');
        }

        return new self(
            $data['currency'],
            $data['code'],
            (float) $data['mid']
        );
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getMid(): float
    {
        return $this->mid;
    }
}
