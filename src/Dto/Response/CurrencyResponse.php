<?php

declare(strict_types=1);

namespace App\Dto\Response;

use App\Domain\Currency\Currency;

/**
 * Currency Information Response DTO
 * 
 * Represents currency metadata in API responses.
 * Used for supported currencies endpoint.
 */
final class CurrencyResponse
{
    public function __construct(
        public readonly string $code,
        public readonly bool $supportsBuying,
        public readonly array $margins
    ) {
    }

    /**
     * Create from Domain Currency entity
     */
    public static function fromCurrency(Currency $currency): self
    {
        return new self(
            code: $currency->getCode(),
            supportsBuying: $currency->supportsBuying(),
            margins: [
                'buy' => $currency->getBuyMargin(),
                'sell' => $currency->getSellMargin()
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
            'code' => $this->code,
            'supports_buying' => $this->supportsBuying,
            'margins' => $this->margins
        ];
    }
}
