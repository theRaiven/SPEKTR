<?php
return [
    'mode' => getenv('APP_ENV') === 'test' ? 'sqlite_test' : 'postgres',
    'debug' => true,
    'db_driver' => getenv('APP_ENV') === 'test' ? 'sqlite' : 'pgsql',
    'db_host' => getenv('DB_HOST') ?: '127.0.0.1',
    'db_port' => getenv('DB_PORT') ?: '5432',
    'db_name' => getenv('DB_NAME') ?: 'spektr',
    'db_user' => getenv('DB_USER') ?: 'postgres',
    'db_password' => getenv('DB_PASSWORD') ?: '',
    'db_sqlite_path' => ':memory:',
];
