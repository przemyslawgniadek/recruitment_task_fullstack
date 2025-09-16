<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;

/**
 * Service Unavailable Exception
 * 
 * Thrown when external services (like NBP API) are unavailable.
 * Used for temporary service disruptions.
 */
final class ServiceUnavailableException extends ApiException
{
    public function __construct(
        string $service,
        ?string $customMessage = null,
        ?\Throwable $previous = null
    ) {
        $message = $customMessage ?? sprintf(
            'Service "%s" is temporarily unavailable',
            $service
        );

        parent::__construct(
            message: $message,
            statusCode: Response::HTTP_SERVICE_UNAVAILABLE,
            details: [
                'service' => $service,
                'temporary' => true
            ],
            previous: $previous
        );
    }

    public function getErrorType(): string
    {
        return 'service_unavailable';
    }
}
