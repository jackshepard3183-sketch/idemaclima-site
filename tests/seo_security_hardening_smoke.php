<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$seo = file_get_contents($root . '/app/Core/Seo.php');
$security = file_get_contents($root . '/app/Core/Security.php');
$bootstrap = file_get_contents($root . '/scripts/bootstrap.php');
$layout = file_get_contents($root . '/app/Views/public/_layout_start.php');
$adminLayout = file_get_contents($root . '/app/Views/admin/_layout_start.php');
$seoController = file_get_contents($root . '/app/Controllers/Public/SeoController.php');
$redirects = file_get_contents($root . '/app/Controllers/Admin/RedirectsController.php');
$system = file_get_contents($root . '/app/Controllers/Public/SystemController.php');
$routes = file_get_contents($root . '/public/index.php');
$analytics = file_get_contents($root . '/app/Controllers/Public/AnalyticsController.php');

foreach ([$seo,$security,$bootstrap,$layout,$adminLayout,$seoController,$redirects,$system,$routes,$analytics] as $content) {
    if (!is_string($content) || $content === '') throw new RuntimeException('File SEO/sicurezza non leggibile.');
}

$checks = [
    [$layout, 'rel="canonical"', 'canonical pubblico'],
    [$layout, 'meta name="description"', 'meta description'],
    [$seoController, 'sitemap.xml', 'riferimento sitemap robots'],
    [$seoController, 'archived_at IS NULL', 'sitemap esclude archiviati'],
    [$routes, "'/sitemap.xml'", 'route sitemap'],
    [$routes, "'/robots.txt'", 'route robots'],
    [$redirects, 'resolveFinalTarget', 'flatten redirect chain'],
    [$redirects, 'Il redirect creerebbe un ciclo', 'blocco loop redirect'],
    [$system, "'noindex,follow'", '404 noindex'],
    [$security, 'TRUSTED_PROXIES', 'proxy affidabili'],
    [$security, 'requireAppKey', 'APP_KEY obbligatoria'],
    [$security, 'X-Robots-Tag: noindex, nofollow, noarchive', 'header noindex admin'],
    [$bootstrap, 'Security::requireAppKey()', 'bootstrap APP_KEY'],
    [$adminLayout, 'Security::adminNoStore()', 'layout admin no-store'],
    [$analytics, 'Security::clientIp()', 'IP da proxy affidabile'],
];

foreach ($checks as [$haystack,$needle,$label]) {
    if (!str_contains($haystack, $needle)) throw new RuntimeException('Check SEO/sicurezza fallito: ' . $label);
}

fwrite(STDOUT, "SEO/security hardening smoke OK\n");
