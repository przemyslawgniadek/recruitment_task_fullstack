<?php

declare(strict_types=1);

namespace App\Dto\Response;

/**
 * Error Response DTO
 * 
 * Standardized error response format for API endpoints.
 * Follows Problem Details for HTTP APIs (RFC 7807) principles.
 */
final class ErrorResponse
{
    public function __construct(
        public readonly string $error,
        public readonly string $message,
        public readonly ?array $details = null,
        public readonly ?string $timestamp = null
    ) {
    }

    /**
     * Create validation error response
     * 
     * @param array<string, string[]> $validationErrors
     */
    public static function validationError(array $validationErrors): self
    {
        return new self(
            error: 'Validation failed',
            message: 'The request contains invalid data',
            details: [
                'validation_errors' => $validationErrors
            ],
            timestamp: (new \DateTimeImmutable())->format('c')
        );
    }

    /**
     * Create not found error response
     */
    public static function notFound(string $resource, array $criteria = []): self
    {
        $message = "Resource '{$resource}' not found";
        $details = null;
        
        if (!empty($criteria)) {
            $details = ['criteria' => $criteria];
        }

        return new self(
            error: 'Resource not found',
            message: $message,
            details: $details,
            timestamp: (new \DateTimeImmutable())->format('c')
        );
    }

    /**
     * Create bad request error response
     */
    public static function badRequest(string $message, array $details = []): self
    {
        return new self(
            error: 'Bad request',
            message: $message,
            details: !empty($details) ? $details : null,
            timestamp: (new \DateTimeImmutable())->format('c')
        );
    }

    /**
     * Create internal server error response
     */
    public static function internalServerError(string $message = 'An internal server error occurred'): self
    {
        return new self(
            error: 'Internal server error',
            message: $message,
            timestamp: (new \DateTimeImmutable())->format('c')
        );
    }

    /**
     * Create service unavailable error response
     */
    public static function serviceUnavailable(string $message = 'Service temporarily unavailable'): self
    {
        return new self(
            error: 'Service unavailable',
            message: $message,
            timestamp: (new \DateTimeImmutable())->format('c')
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
            'error' => $this->error,
            'message' => $this->message
        ];

        if ($this->details !== null) {
            $data['details'] = $this->details;
        }

        if ($this->timestamp !== null) {
            $data['timestamp'] = $this->timestamp;
        }

        return $data;
    }
}
