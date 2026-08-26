<?php

declare(strict_types=1);

require dirname(__DIR__) . '/scripts/bootstrap.php';

use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\CategoriesController;
use App\Controllers\Admin\DocumentsController;
use App\Controllers\Admin\ProductsController;
use App\Controllers\Admin\WarrantyController as AdminWarrantyController;
use App\Controllers\Admin\CampusController as AdminCampusController;
use App\Controllers\Public\TechnicalSheetsController;
use App\Controllers\Public\WarrantyController;
use App\Controllers\Public\CampusController;
use App\Controllers\Public\CatAuthController;
use App\Core\Router;

$router = new Router();
$router->get('/', static function (): void {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>IDEMA Clima</title></head><body><main><h1>IDEMA Clima</h1><p>Nuovo progetto autonomo - ambiente di sviluppo.</p><p><a href="/schede-tecniche">Schede tecniche</a> · <a href="/garanzia">Garanzia</a> · <a href="/campus">Campus</a></p></main></body></html>';
});
$router->get('/health', static function (): void { header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['ok'=>true],JSON_THROW_ON_ERROR); });

$router->get('/schede-tecniche', [TechnicalSheetsController::class, 'index']);
$router->get('/schede-tecniche/ricerca', [TechnicalSheetsController::class, 'search']);
$router->get('/schede-tecniche/famiglia/{slug}', [TechnicalSheetsController::class, 'family']);
$router->get('/schede-tecniche/prodotto/{slug}', [TechnicalSheetsController::class, 'product']);
$router->get('/schede-tecniche/{slug}', [TechnicalSheetsController::class, 'category']);
$router->get('/garanzia', [WarrantyController::class, 'form']);
$router->post('/garanzia', [WarrantyController::class, 'register']);

// Campus pubblico e area CAT
$router->get('/campus', [CampusController::class, 'index']);
$router->get('/campus/cat/login', [CatAuthController::class, 'loginForm']);
$router->post('/campus/cat/login', [CatAuthController::class, 'login']);
$router->post('/campus/cat/logout', [CatAuthController::class, 'logout']);
$router->get('/campus/cat', [CampusController::class, 'catIndex']);
$router->get('/campus/cat/{slug}', [CampusController::class, 'catEvent']);
$router->post('/campus/cat/{slug}/iscrizione', [CampusController::class, 'register']);
$router->get('/campus/{slug}', [CampusController::class, 'event']);
$router->post('/campus/{slug}/iscrizione', [CampusController::class, 'register']);

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
$router->get('/admin/campus/events', [AdminCampusController::class, 'events']);
$router->get('/admin/campus/events/form', [AdminCampusController::class, 'eventForm']);
$router->post('/admin/campus/events/save', [AdminCampusController::class, 'saveEvent']);
$router->get('/admin/campus/registrations', [AdminCampusController::class, 'registrations']);
$router->post('/admin/campus/registrations/update', [AdminCampusController::class, 'updateRegistration']);
$router->get('/admin/cat/users', [AdminCampusController::class, 'catUsers']);
$router->get('/admin/cat/users/form', [AdminCampusController::class, 'catUserForm']);
$router->post('/admin/cat/users/save', [AdminCampusController::class, 'saveCatUser']);

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
