<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/Router.php';

use App\Core\Router;

$router = new Router();
$captured = null;
$router->get('/schede-tecniche/prodotto/{slug}', static function (string $slug) use (&$captured): void {
    $captured = $slug;
});

ob_start();
$router->dispatch('GET', '/schede-tecniche/prodotto/linea-residenziale-r32--ispt-r32?x=1');
ob_end_clean();

if ($captured !== 'linea-residenziale-r32--ispt-r32') {
    fwrite(STDERR, "Router dinamico: FAIL\n");
    exit(1);
}

fwrite(STDOUT, "Router dinamico: OK\n");
