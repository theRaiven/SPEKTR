<?php

namespace App\Core\Exceptions;

class ForbiddenException extends ApiException
{
    public function __construct(string $message = 'Доступ запрещён для вашей роли')
    {
        parent::__construct($message, 403, 'FORBIDDEN');
    }
}
