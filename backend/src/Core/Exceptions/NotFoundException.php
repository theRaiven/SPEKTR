<?php

namespace App\Core\Exceptions;

class NotFoundException extends ApiException
{
    public function __construct(string $message = 'Запрашиваемый ресурс не найден')
    {
        parent::__construct($message, 404, 'NOT_FOUND');
    }
}
