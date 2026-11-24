<?php

namespace App\Core\Exceptions;

use Exception;

class ApiException extends Exception
{
    protected int $statusCode;
    protected string $errorCode;
    protected mixed $details;

    public function __construct(string $message, int $statusCode = 400, string $errorCode = 'BAD_REQUEST', mixed $details = null)
    {
        parent::__construct($message, $statusCode);
        $this->statusCode = $statusCode;
        $this->errorCode = $errorCode;
        $this->details = $details;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getDetails(): mixed
    {
        return $this->details;
    }
}
