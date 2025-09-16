<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;

/**
 * Invalid Currency Exception
 * 
 * Thrown when unsupported currency code is provided.
 * Includes list of supported currencies for better UX.
 */
final class InvalidCurrencyException extends ApiException
{
    /**
     * @param string[] $supportedCurrencies
     */
    public function __construct(
        string $providedCurrency,
        array $supportedCurrencies
    ) {
        $message = sprintf(
            'Currency code "%s" is not supported',
            $providedCurrency
        );

        parent::__construct(
            message: $message,
            statusCode: Response::HTTP_BAD_REQUEST,
            details: [
                'provided_currency' => $providedCurrency,
                'supported_currencies' => $supportedCurrencies
            ]
        );
    }

    public function getErrorType(): string
    {
        return 'invalid_currency';
    }
}
