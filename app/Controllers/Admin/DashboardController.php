<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\AdminAuth;
use App\Core\Database;
use App\Core\Security;

final class DashboardController
{
    public static function index(): void
    {
        AdminAuth::requireLogin();
        $pdo = Database::connection();
        $counts = [];
        foreach (['products', 'product_models', 'documents', 'warranty_registrations', 'events', 'contact_submissions'] as $table) {
            $counts[$table] = (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        }
        $user = AdminAuth::user();
        $csrf = Security::csrfToken();
        require dirname(__DIR__, 2) . '/Views/admin/dashboard.php';
    }
}
