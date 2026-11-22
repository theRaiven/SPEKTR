<?php

return [
    'name' => 'SPEKTR Service Center API',
    'version' => '1.0.0',
    'env' => getenv('APP_ENV') ?: 'development',
    'debug' => (bool) (getenv('APP_DEBUG') ?: true),
    'timezone' => 'Asia/Novosibirsk',
    'token_ttl_hours' => (int) (getenv('TOKEN_TTL_HOURS') ?: 72),
];
