<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;

/**
 * Base API Exception
 * 
 * Base class for all API-related exceptions.
 * Provides consistent error handling with proper HTTP status codes.
 */
abstract class ApiException extends \Exception
{
    public function __construct(
        string $message = '',
        private readonly int $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR,
        private readonly array $details = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Get HTTP status code for this exception
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get additional error details
     * 
     * @return array<string, mixed>
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    /**
     * Get error type for API response
     */
    abstract public function getErrorType(): string;
}
