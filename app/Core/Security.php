<?php

declare(strict_types=1);

namespace App\Core;

final class Security
{
    private static ?string $cspNonce = null;

    public static function nonce(): string
    {
        if (self::$cspNonce === null) self::$cspNonce = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
        return self::$cspNonce;
    }

    public static function sendHeaders(): void
    {
        $nonce = self::nonce();
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Frame-Options: SAMEORIGIN');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        header("Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline' https://cdn.iubenda.com; script-src 'self' 'unsafe-inline' 'nonce-{$nonce}' https://www.googletagmanager.com https://cdn.iubenda.com https://cs.iubenda.com; connect-src 'self' https://www.google-analytics.com https://region1.google-analytics.com https://*.google-analytics.com https://*.iubenda.com; font-src 'self' data:; frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com https://*.iubenda.com");

        $env = strtolower((string)($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production'));
        if ($env === 'staging') {
            header('X-Robots-Tag: noindex, nofollow, noarchive', true);
        }

        if (self::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    public static function startSession(): void
    {
        $cfg = require dirname(__DIR__, 2) . '/config/app.php';
        session_name($cfg['session_cookie']);
        $cookiePath = Url::basePath();
        session_set_cookie_params([
            'httponly' => true,
            'secure' => self::isHttps(),
            'samesite' => 'Lax',
            'path' => $cookiePath === '' ? '/' : $cookiePath . '/',
        ]);
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        return is_string($token)
            && isset($_SESSION['_csrf'])
            && hash_equals($_SESSION['_csrf'], $token);
    }

    public static function adminNoStore(): void
    {
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Robots-Tag: noindex, nofollow, noarchive', true);
    }

    public static function requireAppKey(): void
    {
        $env = strtolower((string)($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production'));
        $key = trim((string)($_ENV['APP_KEY'] ?? getenv('APP_KEY') ?: ''));
        if ($env === 'production' && strlen($key) < 32) {
            throw new \RuntimeException('APP_KEY obbligatoria in produzione e lunga almeno 32 caratteri.');
        }
    }

    public static function clientIp(): string
    {
        $remote = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($remote === '' || !filter_var($remote, FILTER_VALIDATE_IP)) return '';
        if (!self::isTrustedProxy($remote)) return $remote;

        $cf = trim((string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
        if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP)) return $cf;

        $xff = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($xff !== '') {
            foreach (array_map('trim', explode(',', $xff)) as $candidate) {
                if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)) return $candidate;
            }
        }
        return $remote;
    }

    private static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') return true;
        $remote = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($remote === '' || !self::isTrustedProxy($remote)) return false;
        return strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))) === 'https';
    }

    private static function isTrustedProxy(string $ip): bool
    {
        $raw = trim((string)($_ENV['TRUSTED_PROXIES'] ?? getenv('TRUSTED_PROXIES') ?: ''));
        if ($raw === '') return false;
        foreach (array_filter(array_map('trim', explode(',', $raw))) as $rule) {
            if ($rule === '*' || $rule === $ip) return true;
            if (str_contains($rule, '/') && self::ipInCidr($ip, $rule)) return true;
        }
        return false;
    }

    private static function ipInCidr(string $ip, string $cidr): bool
    {
        [$network, $prefix] = array_pad(explode('/', $cidr, 2), 2, null);
        if ($network === null || $prefix === null) return false;
        $ipBin = @inet_pton($ip);
        $netBin = @inet_pton($network);
        if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin)) return false;
        $bits = (int)$prefix;
        $maxBits = strlen($ipBin) * 8;
        if ($bits < 0 || $bits > $maxBits) return false;
        $bytes = intdiv($bits, 8);
        $remainder = $bits % 8;
        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($netBin, 0, $bytes)) return false;
        if ($remainder === 0) return true;
        $mask = (0xFF << (8 - $remainder)) & 0xFF;
        return (ord($ipBin[$bytes]) & $mask) === (ord($netBin[$bytes]) & $mask);
    }
}
