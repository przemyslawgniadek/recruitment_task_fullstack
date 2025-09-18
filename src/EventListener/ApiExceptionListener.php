<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Dto\Response\ErrorResponse;
use App\Exception\ApiException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * API Exception Listener
 * 
 * Handles all exceptions in API endpoints and converts them to
 * standardized JSON error responses following RFC 7807.
 */
final class ApiExceptionListener
{
    public function __construct(
        private LoggerInterface $logger,
        private string $environment = 'prod'
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Only handle API routes
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $this->logger->error('API Exception occurred', [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'path' => $request->getPathInfo(),
            'method' => $request->getMethod(),
            'trace' => $this->environment === 'dev' ? $exception->getTraceAsString() : null
        ]);

        $response = $this->createErrorResponse($exception);
        $event->setResponse($response);
    }

    private function createErrorResponse(\Throwable $exception): JsonResponse
    {
        // Handle custom API exceptions
        if ($exception instanceof ApiException) {
            $errorResponse = new ErrorResponse(
                error: $exception->getErrorType(),
                message: $exception->getMessage(),
                details: !empty($exception->getDetails()) ? $exception->getDetails() : null,
                timestamp: (new \DateTimeImmutable())->format('c')
            );

            return new JsonResponse(
                $errorResponse->toArray(),
                $exception->getStatusCode()
            );
        }

        // Handle Symfony HTTP exceptions
        if ($exception instanceof HttpExceptionInterface) {
            $errorResponse = new ErrorResponse(
                error: 'http_error',
                message: $exception->getMessage() ?: Response::$statusTexts[$exception->getStatusCode()] ?? 'Unknown error',
                timestamp: (new \DateTimeImmutable())->format('c')
            );

            return new JsonResponse(
                $errorResponse->toArray(),
                $exception->getStatusCode()
            );
        }

        // Handle validation errors from Symfony
        if ($exception instanceof \InvalidArgumentException) {
            $errorResponse = ErrorResponse::badRequest($exception->getMessage());
            return new JsonResponse($errorResponse->toArray(), Response::HTTP_BAD_REQUEST);
        }

        // Handle generic exceptions
        $message = $this->environment === 'dev' 
            ? $exception->getMessage() 
            : 'An internal server error occurred';

        $details = $this->environment === 'dev' ? [
            'exception' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine()
        ] : null;

        $errorResponse = new ErrorResponse(
            error: 'internal_server_error',
            message: $message,
            details: $details,
            timestamp: (new \DateTimeImmutable())->format('c')
        );

        return new JsonResponse(
            $errorResponse->toArray(),
            Response::HTTP_INTERNAL_SERVER_ERROR
        );
    }
}
