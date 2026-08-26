<?php

declare(strict_types=1);

require dirname(__DIR__) . '/scripts/bootstrap.php';

use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\CategoriesController;
use App\Controllers\Admin\DocumentsController;
use App\Controllers\Admin\ProductsController;
use App\Controllers\Admin\WarrantyController as AdminWarrantyController;
use App\Controllers\Public\TechnicalSheetsController;
use App\Controllers\Public\WarrantyController;
use App\Core\Router;

$router = new Router();
$router->get('/', static function (): void {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>IDEMA Clima</title></head><body><main><h1>IDEMA Clima</h1><p>Nuovo progetto autonomo - ambiente di sviluppo.</p><p><a href="/schede-tecniche">Schede tecniche</a> · <a href="/garanzia">Garanzia</a></p></main></body></html>';
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

// Area pubblica garanzia
$router->get('/garanzia', [WarrantyController::class, 'form']);
$router->post('/garanzia', [WarrantyController::class, 'register']);

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
$router->get('/admin/warranties', [AdminWarrantyController::class, 'registrations']);
$router->get('/admin/warranties/registration', [AdminWarrantyController::class, 'registration']);
$router->post('/admin/warranties/status', [AdminWarrantyController::class, 'updateStatus']);
$router->get('/admin/warranties/rules', [AdminWarrantyController::class, 'rules']);
$router->get('/admin/warranties/rules/form', [AdminWarrantyController::class, 'ruleForm']);
$router->post('/admin/warranties/rules/save', [AdminWarrantyController::class, 'saveRule']);
$router->get('/admin/warranties/file/{kind}/{id}', [AdminWarrantyController::class, 'privateFile']);

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
