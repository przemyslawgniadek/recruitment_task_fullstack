<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;

/**
 * Rate Not Found Exception
 * 
 * Thrown when requested exchange rate is not available.
 * Provides specific information about what was not found.
 */
final class RateNotFoundException extends ApiException
{
    public function __construct(
        string $currency,
        string $date,
        ?string $customMessage = null
    ) {
        $message = $customMessage ?? sprintf(
            'Exchange rate not found for currency %s on date %s',
            $currency,
            $date
        );

        parent::__construct(
            message: $message,
            statusCode: Response::HTTP_NOT_FOUND,
            details: [
                'currency' => $currency,
                'date' => $date,
                'resource' => 'exchange_rate'
            ]
        );
    }

    /**
     * Create for historical rates not found
     */
    public static function forHistoricalRates(
        string $currency,
        string $startDate,
        string $endDate,
        int $daysRequested
    ): self {
        $message = sprintf(
            'No historical exchange rates found for currency %s between %s and %s (%d days)',
            $currency,
            $startDate,
            $endDate,
            $daysRequested
        );

        $exception = new self($currency, $startDate, $message);
        $exception->details['end_date'] = $endDate;
        $exception->details['days_requested'] = $daysRequested;
        $exception->details['resource'] = 'historical_exchange_rates';

        return $exception;
    }

    public function getErrorType(): string
    {
        return 'rate_not_found';
    }
}
