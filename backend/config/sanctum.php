<?php

return [
    'guard' => 'sanctum',
    'expiration' => null,
    'token_prefix' => 'booking_',
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,localhost:8000,127.0.0.1,127.0.0.1:8000,127.0.0.1:3000',
        env('APP_URL') ? ','.parse_url(env('APP_URL'), PHP_URL_HOST) : ''
    ))),
    'permissions' => [
        'create',
        'read',
        'update',
        'delete',
    ],
    'abilities' => [
        'create',
        'read',
        'update',
        'delete',
    ],
];
