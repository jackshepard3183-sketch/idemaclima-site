<?php

declare(strict_types=1);

namespace App\Core;

use App\Auth\AdminAuth;

final class Audit
{
    public static function log(string $action, ?string $entityType = null, ?int $entityId = null, array $metadata = []): void
    {
        try {
            $stmt = Database::connection()->prepare(
                'INSERT INTO audit_log (admin_user_id, action, entity_type, entity_id, ip_address, metadata) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                AdminAuth::id(),
                $action,
                $entityType,
                $entityId,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ]);
        } catch (\Throwable) {
            // Audit failures must never break an admin operation.
        }
    }
}
