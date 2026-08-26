<?php

declare(strict_types=1);

require dirname(__DIR__) . '/scripts/bootstrap.php';

use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\CategoriesController;
use App\Controllers\Admin\DocumentsController;
use App\Controllers\Admin\ProductsController;
use App\Controllers\Public\TechnicalSheetsController;
use App\Core\Router;

$router = new Router();
$router->get('/', static function (): void {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>IDEMA Clima</title></head><body><main><h1>IDEMA Clima</h1><p>Nuovo progetto autonomo - ambiente di sviluppo.</p><p><a href="/schede-tecniche">Schede tecniche</a></p></main></body></html>';
});
$router->get('/health', static function (): void {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => true], JSON_THROW_ON_ERROR);
});

// Area pubblica schede tecniche
$router->get('/schede-tecniche', [TechnicalSheetsController::class, 'index']);
$router->get('/schede-tecniche/ricerca', [TechnicalSheetsController::class, 'search']);
$router->get('/schede-tecniche/famiglia/{slug}', [TechnicalSheetsController::class, 'family']);
$router->get('/schede-tecniche/prodotto/{slug}', [TechnicalSheetsController::class, 'product']);
$router->get('/schede-tecniche/{slug}', [TechnicalSheetsController::class, 'category']);

// Area amministrativa
$router->get('/admin/login', [AuthController::class, 'loginForm']);
$router->post('/admin/login', [AuthController::class, 'login']);
$router->post('/admin/logout', [AuthController::class, 'logout']);
$router->get('/admin', [DashboardController::class, 'index']);
$router->get('/admin/categories', [CategoriesController::class, 'index']);
$router->get('/admin/categories/form', [CategoriesController::class, 'form']);
$router->post('/admin/categories/save', [CategoriesController::class, 'save']);
$router->get('/admin/products', [ProductsController::class, 'index']);
$router->get('/admin/products/form', [ProductsController::class, 'form']);
$router->post('/admin/products/save', [ProductsController::class, 'save']);
$router->post('/admin/models/save', [ProductsController::class, 'saveModel']);
$router->get('/admin/documents', [DocumentsController::class, 'index']);
$router->get('/admin/documents/form', [DocumentsController::class, 'form']);
$router->post('/admin/documents/save', [DocumentsController::class, 'save']);

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
