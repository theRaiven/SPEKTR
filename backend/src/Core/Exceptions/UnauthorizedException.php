<?php

namespace App\Core\Exceptions;

class UnauthorizedException extends ApiException
{
    public function __construct(string $message = 'Требуется авторизация')
    {
        parent::__construct($message, 401, 'UNAUTHORIZED');
    }
}
