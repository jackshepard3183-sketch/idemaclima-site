<?php

declare(strict_types=1);

return [
    'env' => getenv('APP_ENV') ?: 'production',
    'debug' => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOL),
    'url' => rtrim((string) (getenv('APP_URL') ?: ''), '/'),
    'key' => (string) (getenv('APP_KEY') ?: ''),
    'session_cookie' => (string) (getenv('SESSION_COOKIE') ?: 'idema_session'),
];
