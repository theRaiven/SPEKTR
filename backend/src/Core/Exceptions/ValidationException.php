<?php

namespace App\Core\Exceptions;

class ValidationException extends ApiException
{
    public function __construct(string $message = 'Ошибка валидации данных', array $errors = [])
    {
        parent::__construct($message, 422, 'VALIDATION_ERROR', $errors);
    }
}
