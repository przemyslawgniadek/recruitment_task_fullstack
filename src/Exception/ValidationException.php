<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Validation Exception
 * 
 * Thrown when request validation fails.
 * Contains detailed validation error information.
 */
final class ValidationException extends ApiException
{
    /**
     * @param array<string, string[]> $validationErrors
     */
    public function __construct(
        array $validationErrors,
        string $message = 'Validation failed'
    ) {
        parent::__construct(
            message: $message,
            statusCode: Response::HTTP_BAD_REQUEST,
            details: ['validation_errors' => $validationErrors]
        );
    }

    /**
     * Create from Symfony ConstraintViolationList
     */
    public static function fromConstraintViolationList(
        ConstraintViolationListInterface $violations
    ): self {
        $errors = [];
        
        foreach ($violations as $violation) {
            $propertyPath = $violation->getPropertyPath();
            $errors[$propertyPath][] = $violation->getMessage();
        }

        return new self($errors);
    }

    public function getErrorType(): string
    {
        return 'validation_error';
    }
}
