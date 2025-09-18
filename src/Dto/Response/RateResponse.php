<?php

declare(strict_types=1);

namespace App\Dto\Response;

use App\Domain\Rate\Rate;

/**
 * Single Exchange Rate Response DTO
 * 
 * Represents a single currency exchange rate in API responses.
 * Provides consistent JSON format for frontend consumption.
 */
final class RateResponse
{
    public function __construct(
        public readonly string $currency,
        public readonly string $date,
        public readonly float $mid,
        public readonly ?float $buy,
        public readonly float $sell,
        public readonly bool $supportsBuying,
        public readonly array $margins
    ) {
    }

    /**
     * Create from Domain Rate entity
     */
    public static function fromRate(Rate $rate): self
    {
        return new self(
            currency: $rate->getCurrency()->getCode(),
            date: $rate->getDate()->format('Y-m-d'),
            mid: $rate->getMidRate(),
            buy: $rate->getBuyRate(),
            sell: $rate->getSellRate(),
            supportsBuying: $rate->getCurrency()->supportsBuying(),
            margins: [
                'buy' => $rate->getCurrency()->getBuyMargin(),
                'sell' => $rate->getCurrency()->getSellMargin()
            ]
        );
    }

    /**
     * Convert to array for JSON serialization
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'date' => $this->date,
            'mid' => $this->mid,
            'buy' => $this->buy,
            'sell' => $this->sell,
            'supports_buying' => $this->supportsBuying,
            'margins' => $this->margins
        ];
    }
}
