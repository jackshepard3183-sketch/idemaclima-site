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
        $count = static function (string $sql) use ($pdo): int {
            try { return (int) $pdo->query($sql)->fetchColumn(); } catch (\Throwable) { return 0; }
        };
        $counts = [
            'contacts_new' => $count("SELECT COUNT(*) FROM contact_submissions WHERE status='new'"),
            'warranties_new' => $count("SELECT COUNT(*) FROM warranty_registrations WHERE status='pending'"),
            'campus_new' => $count("SELECT COUNT(*) FROM event_registrations WHERE status IN ('registered','waitlist')"),
            'incentives_new' => $count("SELECT COUNT(*) FROM incentive_requests WHERE status='new'"),
            'cat_pending' => $count('SELECT COUNT(*) FROM cat_accounts WHERE active=0 AND verified_at IS NULL AND disabled_at IS NULL'),
        ];
        try {
            $recent = $pdo->query("SELECT al.action,al.entity_type,al.entity_id,al.created_at,al.admin_user_id,au.first_name,au.last_name,au.username,COALESCE(ar.name,au.role) AS actor_role FROM audit_log al LEFT JOIN admin_users au ON au.id=al.admin_user_id LEFT JOIN admin_roles ar ON ar.slug=au.role ORDER BY al.created_at DESC LIMIT 8")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            $recent = [];
        }

        $user = AdminAuth::user();
        $csrf = Security::csrfToken();

        require dirname(__DIR__, 2) . '/Views/admin/dashboard.php';
    }
}