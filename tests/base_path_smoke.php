<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/Core/Url.php';

use App\Core\Url;

putenv('APP_BASE_PATH=/idemaclima');
$_ENV['APP_BASE_PATH'] = '/idemaclima';

if (Url::basePath() !== '/idemaclima') throw new RuntimeException('APP_BASE_PATH non normalizzato.');
if (Url::to('/schede-tecniche') !== '/idemaclima/schede-tecniche') throw new RuntimeException('Prefisso URL non applicato.');
if (Url::to('/idemaclima/admin') !== '/idemaclima/admin') throw new RuntimeException('Doppio prefisso URL.');
if (Url::stripBasePath('/idemaclima/campus/evento') !== '/campus/evento') throw new RuntimeException('Base path non rimosso dal router.');
if (Url::stripBasePath('/idemaclima') !== '/') throw new RuntimeException('Root base path non normalizzata.');

$html = '<a href="/schede-tecniche">Schede</a><form action="/contatti"><img src="/uploads/x.png"><a href="/idemaclima/admin">Admin</a></form>';
$rewritten = Url::rewriteHtml($html);
foreach (['href="/idemaclima/schede-tecniche"','action="/idemaclima/contatti"','src="/idemaclima/uploads/x.png"','href="/idemaclima/admin"'] as $needle) {
    if (!str_contains($rewritten, $needle)) throw new RuntimeException('Riscrittura HTML base path fallita: ' . $needle);
}
if (str_contains($rewritten, '/idemaclima/idemaclima/')) throw new RuntimeException('Doppio prefisso HTML rilevato.');

$router = file_get_contents($root . '/app/Core/Router.php');
$security = file_get_contents($root . '/app/Core/Security.php');
$seo = file_get_contents($root . '/app/Core/Seo.php');
$seoController = file_get_contents($root . '/app/Controllers/Public/SeoController.php');
$bootstrap = file_get_contents($root . '/scripts/bootstrap.php');
$htaccess = file_get_contents($root . '/.htaccess');
$env = file_get_contents($root . '/.env.example');
$readiness = file_get_contents($root . '/scripts/staging_readiness.php');
foreach ([$router,$security,$seo,$seoController,$bootstrap,$htaccess,$env,$readiness] as $content) {
    if (!is_string($content) || $content === '') throw new RuntimeException('File base-path non leggibile.');
}

$checks = [
    [$router, 'Url::stripBasePath', 'router strip base path'],
    [$security, "Url::basePath()", 'cookie base path'],
    [$security, "\$env === 'staging'", 'staging noindex'],
    [$seo, 'Url::stripBasePath', 'SEO request path'],
    [$seoController, "Disallow: /\\n", 'robots staging disallow'],
    [$bootstrap, 'Url::installBasePathSupport()', 'bootstrap base path'],
    [$htaccess, '^(uploads|assets)/(.*)$', 'public uploads rewrite'],
    [$env, 'APP_BASE_PATH=', 'env base path'],
    [$env, 'rappresentanzeguanzirolisas.it/idemaclima', 'staging example'],
    [$readiness, 'APP_URL coerente con APP_BASE_PATH', 'readiness consistency'],
];
foreach ($checks as [$haystack,$needle,$label]) {
    if (!str_contains($haystack, $needle)) throw new RuntimeException('Check base-path fallito: ' . $label);
}

fwrite(STDOUT, "Base path smoke OK\n");
