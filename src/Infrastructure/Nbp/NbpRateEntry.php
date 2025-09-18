<?php

declare(strict_types=1);

namespace App\Infrastructure\Nbp;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * NBP Rate Entry
 * 
 * Single rate entry with date from single rate response
 */
final class NbpRateEntry
{
    public function __construct(
        private string $no,
        private DateTimeImmutable $effectiveDate,
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
        if (!isset($data['no'], $data['effectiveDate'], $data['mid'])) {
            throw new InvalidArgumentException('Invalid NBP rate entry: missing required fields');
        }

        return new self(
            $data['no'],
            new DateTimeImmutable($data['effectiveDate']),
            (float) $data['mid']
        );
    }

    public function getNo(): string
    {
        return $this->no;
    }

    public function getEffectiveDate(): DateTimeImmutable
    {
        return $this->effectiveDate;
    }

    public function getMid(): float
    {
        return $this->mid;
    }
}
