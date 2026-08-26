<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/Url.php';
require dirname(__DIR__) . '/app/Core/Router.php';

use App\Core\Router;

$fail = static function (string $message): never {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$router = new Router();
$seen = [];
$router->get('/product/{slug}', static function (string $slug) use (&$seen): void { $seen[] = ['product', $slug]; });
$router->get('/file/{kind}/{id}', static function (string $kind, string $id) use (&$seen): void { $seen[] = ['file', $kind, $id]; });
$router->setNotFoundHandler(static function (string $path, string $method) use (&$seen): void { $seen[] = ['404', $path, $method]; });

$router->dispatch('GET', '/product/ICZ-R32?x=1');
$router->dispatch('GET', '/file/invoice/42');
$router->dispatch('GET', '/missing');

if (($seen[0] ?? null) !== ['product','ICZ-R32']) $fail('parametro slug o query-string non gestiti correttamente');
if (($seen[1] ?? null) !== ['file','invoice','42']) $fail('route con due parametri non gestita correttamente');
if (($seen[2] ?? null) !== ['404','/missing','GET']) $fail('fallback 404 non invocato correttamente');

echo "OK router regression\n";
