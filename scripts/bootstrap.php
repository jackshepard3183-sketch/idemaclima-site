<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = dirname(__DIR__) . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$envFile = dirname(__DIR__) . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (getenv($name) === false) {
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
        }
    }
}

$timezone = trim((string)($_ENV['APP_TIMEZONE'] ?? getenv('APP_TIMEZONE') ?: 'Europe/Rome'));
if (!in_array($timezone, timezone_identifiers_list(), true)) {
    throw new RuntimeException('APP_TIMEZONE non valida.');
}
date_default_timezone_set($timezone);

\App\Core\Security::requireAppKey();
\App\Core\Url::installBasePathSupport();
\App\Core\Security::sendHeaders();
\App\Core\Security::startSession();
