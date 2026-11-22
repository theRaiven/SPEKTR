<?php

namespace App\Core\Exceptions;

class BadRequestException extends ApiException
{
    public function __construct(string $message = 'Некорректный запрос', mixed $details = null)
    {
        parent::__construct($message, 400, 'BAD_REQUEST', $details);
    }
}
