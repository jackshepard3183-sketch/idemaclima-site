<?php

declare(strict_types=1);

namespace App\Core;

final class Seo
{
    /** @return array{title:string,description:string,canonical:string,robots:string} */
    public static function meta(string $title, ?string $description = null, ?string $canonicalPath = null, string $robots = 'index,follow'): array
    {
        $site = trim((string)($_ENV['SITE_NAME'] ?? getenv('SITE_NAME') ?: 'IDEMA Clima'));
        $siteUrl = rtrim(trim((string)($_ENV['SITE_URL'] ?? getenv('SITE_URL') ?: 'https://www.idemaclima.it')), '/');
        $cleanTitle = trim($title);
        $fullTitle = $cleanTitle === '' ? $site : ($cleanTitle === $site ? $site : $cleanTitle . ' | ' . $site);
        $description = trim((string)$description);
        if ($description === '') $description = 'IDEMA Clima: climatizzazione, pompe di calore, sistemi VRF, documentazione tecnica, assistenza e servizi.';
        $path = $canonicalPath ?? self::requestPath();
        if ($path === '') $path = '/';
        if ($path[0] !== '/') $path = '/' . $path;
        return [
            'title' => mb_substr($fullTitle, 0, 180),
            'description' => mb_substr($description, 0, 320),
            'canonical' => $siteUrl . ($path === '/' ? '/' : rtrim($path, '/')),
            'robots' => $robots,
        ];
    }

    public static function requestPath(): string
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        return is_string($path) && $path !== '' ? $path : '/';
    }

    public static function publicBaseUrl(): string
    {
        return rtrim(trim((string)($_ENV['SITE_URL'] ?? getenv('SITE_URL') ?: 'https://www.idemaclima.it')), '/');
    }

    public static function normalizePath(string $value): string
    {
        $value = trim($value);
        if ($value === '') return '';
        $parts = parse_url($value);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || isset($parts['fragment'])) return '';
        $path = rawurldecode((string)($parts['path'] ?? ''));
        if ($path === '') $path = '/';
        if ($path[0] !== '/') $path = '/' . $path;
        $path = preg_replace('#/{2,}#', '/', $path) ?: '/';
        if ($path !== '/') $path = rtrim($path, '/');
        return $path;
    }
}
