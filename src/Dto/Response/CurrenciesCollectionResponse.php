<?php

declare(strict_types=1);

namespace App\Dto\Response;

use App\Domain\Currency\Currency;
use App\Domain\Currency\CurrencyCode;

/**
 * Currencies Collection Response DTO
 * 
 * Represents all supported currencies in API responses.
 * Used for /api/currencies endpoint.
 */
final class CurrenciesCollectionResponse
{
    /**
     * @param CurrencyResponse[] $currencies
     */
    public function __construct(
        public readonly array $currencies,
        public readonly int $count
    ) {
    }

    /**
     * Create from all supported currencies
     */
    public static function fromSupportedCurrencies(): self
    {
        $currencyResponses = [];
        
        foreach (CurrencyCode::cases() as $currencyCode) {
            $currency = Currency::fromCode($currencyCode->value);
            $currencyResponses[] = CurrencyResponse::fromCurrency($currency);
        }

        return new self(
            currencies: $currencyResponses,
            count: count($currencyResponses)
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
            'currencies' => array_map(
                fn(CurrencyResponse $currency) => $currency->toArray(),
                $this->currencies
            ),
            'count' => $this->count
        ];
    }
}
