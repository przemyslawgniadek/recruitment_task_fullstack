<?php

declare(strict_types=1);

namespace App\Dto\Response;

use App\Domain\Rate\Rate;

/**
 * Exchange Rates Collection Response DTO
 * 
 * Represents multiple currency exchange rates in API responses.
 * Used for current rates and historical rates endpoints.
 */
final class RatesCollectionResponse
{
    /**
     * @param RateResponse[] $rates
     */
    public function __construct(
        public readonly string $date,
        public readonly array $rates,
        public readonly int $count,
        public readonly ?string $startDate = null,
        public readonly ?string $endDate = null,
        public readonly ?int $daysRequested = null
    ) {
    }

    /**
     * Create from Domain Rate entities for current rates
     * 
     * @param Rate[] $rates
     */
    public static function fromCurrentRates(array $rates, string $date): self
    {
        $rateResponses = [];
        foreach ($rates as $currencyCode => $rate) {
            $rateResponses[$currencyCode] = RateResponse::fromRate($rate);
        }

        return new self(
            date: $date,
            rates: $rateResponses,
            count: count($rateResponses)
        );
    }

    /**
     * Create from Domain Rate entities for historical rates
     * 
     * @param Rate[] $rates
     */
    public static function fromHistoricalRates(
        array $rates,
        string $currency,
        string $startDate,
        string $endDate,
        int $daysRequested
    ): self {
        $rateResponses = [];
        foreach ($rates as $rate) {
            $rateResponses[] = [
                'date' => $rate->getDate()->format('Y-m-d'),
                'mid' => $rate->getMidRate(),
                'buy' => $rate->getBuyRate(),
                'sell' => $rate->getSellRate()
            ];
        }

        // Sort by date descending (newest first)
        usort($rateResponses, function ($a, $b) {
            return $b['date'] <=> $a['date'];
        });

        return new self(
            date: $currency, // For historical, we use currency instead of single date
            rates: $rateResponses,
            count: count($rateResponses),
            startDate: $startDate,
            endDate: $endDate,
            daysRequested: $daysRequested
        );
    }

    /**
     * Convert to array for JSON serialization
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'count' => $this->count,
            'rates' => []
        ];

        // For current rates
        if ($this->startDate === null && $this->endDate === null) {
            $data['date'] = $this->date;
            foreach ($this->rates as $currencyCode => $rateResponse) {
                if ($rateResponse instanceof RateResponse) {
                    $data['rates'][$currencyCode] = $rateResponse->toArray();
                }
            }
        } else {
            // For historical rates
            $data['currency'] = $this->date; // Currency code stored in date field
            $data['start_date'] = $this->startDate;
            $data['end_date'] = $this->endDate;
            $data['days_requested'] = $this->daysRequested;
            $data['rates'] = $this->rates; // Already in array format
        }

        return $data;
    }
}
