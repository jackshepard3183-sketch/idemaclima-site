<?php

declare(strict_types=1);

return [
    'env' => getenv('APP_ENV') ?: 'production',
    'debug' => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOL),
    'url' => rtrim((string) (getenv('APP_URL') ?: ''), '/'),
    'base_path' => (string) (getenv('APP_BASE_PATH') ?: ''),
    'key' => (string) (getenv('APP_KEY') ?: ''),
    'timezone' => (string) (getenv('APP_TIMEZONE') ?: 'Europe/Rome'),
    'session_cookie' => (string) (getenv('SESSION_COOKIE') ?: 'idema_session'),
];
