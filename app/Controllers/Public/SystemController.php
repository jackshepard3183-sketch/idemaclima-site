<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Database;
use PDO;

final class SystemController
{
    public static function notFound(string $path, string $method = 'GET'): void
    {
        if ($method === 'GET') {
            try {
                $pdo = Database::connection();
                $s = $pdo->prepare('SELECT id,target_path,status_code FROM seo_redirects WHERE source_path=? AND enabled=1 LIMIT 1');
                $s->execute([$path]);
                $redirect = $s->fetch(PDO::FETCH_ASSOC);
                if ($redirect) {
                    $pdo->prepare('UPDATE seo_redirects SET hit_count=hit_count+1,last_hit_at=NOW() WHERE id=?')->execute([(int)$redirect['id']]);
                    header('Location: ' . $redirect['target_path'], true, (int)$redirect['status_code']);
                    return;
                }
            } catch (\Throwable) {
                // In bootstrap/migration environments the redirects table may not exist yet.
            }
        }

        http_response_code(404);
        header('Content-Type: text/html; charset=UTF-8');
        $title = 'Pagina non trovata';
        require dirname(__DIR__, 2) . '/Views/public/_layout_start.php';
        require dirname(__DIR__, 2) . '/Views/public/404.php';
        require dirname(__DIR__, 2) . '/Views/public/_layout_end.php';
    }
}
