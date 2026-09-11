<?php

declare(strict_types=1);

namespace App\Core;

final class Url
{
    public static function basePath(): string
    {
        $raw = trim((string)($_ENV['APP_BASE_PATH'] ?? getenv('APP_BASE_PATH') ?: ''));
        if ($raw === '' || $raw === '/') return '';
        $path = '/' . trim($raw, '/');
        return preg_replace('#/{2,}#', '/', $path) ?: '';
    }

    public static function to(string $path = '/'): string
    {
        if ($path === '') $path = '/';
        if (preg_match('#^(?:https?:)?//#i', $path)) return $path;
        if ($path[0] !== '/') $path = '/' . $path;
        $base = self::basePath();
        if ($base === '') return $path;
        if ($path === $base || str_starts_with($path, $base . '/')) return $path;
        return $base . ($path === '/' ? '' : $path);
    }

    public static function stripBasePath(string $path): string
    {
        $base = self::basePath();
        if ($base === '') return $path === '' ? '/' : $path;
        if ($path === $base) return '/';
        if (str_starts_with($path, $base . '/')) return substr($path, strlen($base)) ?: '/';
        return $path === '' ? '/' : $path;
    }

    public static function installBasePathSupport(): void
    {
        if (self::basePath() === '') return;
        if (!defined('IDEMA_BASEPATH_BUFFER')) {
            define('IDEMA_BASEPATH_BUFFER', true);
            ob_start([self::class, 'rewriteHtml']);
        }
        header_register_callback(static function (): void {
            foreach (headers_list() as $headerLine) {
                if (stripos($headerLine, 'Location:') !== 0) continue;
                $target = trim(substr($headerLine, strlen('Location:')));
                if ($target === '' || preg_match('#^https?://#i', $target)) continue;
                $status = http_response_code();
                if ($status < 300 || $status > 399) $status = 302;
                header_remove('Location');
                header('Location: ' . self::to($target), true, $status);
                break;
            }
        });
    }

    public static function rewriteHtml(string $html): string
    {
        $base = self::basePath();
        if ($base === '' || $html === '') return $html;

        $html = preg_replace_callback(
            "#\\b(href|src|action|poster)=([\"'])/(?!/)([^\"']*)\\2#i",
            static function (array $m) use ($base): string {
                $value = '/' . ltrim((string)$m[3], '/');
                if ($value === $base || str_starts_with($value, $base . '/')) return $m[0];
                return $m[1] . '=' . $m[2] . $base . ($value === '/' ? '' : $value) . $m[2];
            },
            $html
        ) ?? $html;

        $html = preg_replace_callback(
            "#url\\(([\"']?)/(?!/)([^)\"']*)\\1\\)#i",
            static function (array $m) use ($base): string {
                $value = '/' . ltrim((string)$m[2], '/');
                if ($value === $base || str_starts_with($value, $base . '/')) return $m[0];
                return 'url(' . $m[1] . $base . ($value === '/' ? '' : $value) . $m[1] . ')';
            },
            $html
        ) ?? $html;

        $html = preg_replace_callback(
            "#https://(?:www\\.)?idemaclima\\.it/wp-content/uploads/[^\\s\\\"'<>]+|https://idemaclima\\.lovable\\.app/__l5e/assets-v1/[^\\s\\\"'<>]+#i",
            static function (array $m): string {
                $remote = (string)$m[0];
                $path = (string)parse_url($remote, PHP_URL_PATH);
                $base = rawurldecode(basename($path));
                $base = preg_replace('/[^a-z0-9._-]+/i', '-', $base) ?: 'asset';
                $extension = strtolower(pathinfo($base, PATHINFO_EXTENSION));
                if (!in_array($extension, ['pdf','png','jpg','jpeg','webp','gif','svg'], true)) return $remote;
                $filename = substr(hash('sha256', $remote), 0, 16) . '-' . $base;
                $local = dirname(__DIR__, 2) . '/public/uploads/mirrored/' . $filename;
                return is_file($local) ? self::to('/uploads/mirrored/' . $filename) : $remote;
            },
            $html
        ) ?? $html;

        return $html;
    }
}
