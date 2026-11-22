<?php

namespace App\Core\Exceptions;

class ConflictException extends ApiException
{
    public function __construct(string $message = 'Конфликт состояния данных')
    {
        parent::__construct($message, 409, 'CONFLICT');
    }
}
