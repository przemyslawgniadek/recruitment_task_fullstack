<?php

declare(strict_types=1);

namespace App\Infrastructure\Exception;

use Exception;

/**
 * NBP API Exception
 * 
 * Custom exception for NBP API related errors.
 * Helps distinguish NBP API failures from other application errors.
 */
class NbpApiException extends Exception
{
    public static function requestFailed(string $url, string $reason): self
    {
        return new self(sprintf('NBP API request failed for URL: %s. Reason: %s', $url, $reason));
    }

    public static function invalidResponse(string $url, string $reason): self
    {
        return new self(sprintf('NBP API returned invalid response for URL: %s. Reason: %s', $url, $reason));
    }

    public static function currencyNotFound(string $currencyCode, string $date): self
    {
        return new self(sprintf('Currency %s not found in NBP data for date %s', $currencyCode, $date));
    }

    public static function noDataAvailable(string $date): self
    {
        return new self(sprintf('No NBP data available for date %s (weekend, holiday, or future date)', $date));
    }
}
