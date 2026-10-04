<?php
declare(strict_types=1);

namespace Marketplace\Shared\Support;

final class ValidationException extends \InvalidArgumentException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 422,
        public readonly string $errorCode = 'VALIDATION_ERROR',
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }
}

