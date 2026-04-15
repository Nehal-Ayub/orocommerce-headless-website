<?php

namespace App\Models;

use Exception;

class ApiException extends Exception
{
    public function __construct(
        string $message,
        private readonly int $statusCode = 500,
        private readonly array $context = []
    ) {
        parent::__construct($message, $statusCode);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
