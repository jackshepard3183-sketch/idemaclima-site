<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Database;
use App\Core\Seo;
use PDO;

final class SystemController
{
    public static function notFound(string $path, string $method = 'GET'): void
    {
        $path = Seo::normalizePath($path);
        if ($method === 'GET' && $path !== '') {
            try {
                $pdo = Database::connection();
                $s = $pdo->prepare('SELECT id,target_path,status_code FROM seo_redirects WHERE source_path=? AND enabled=1 LIMIT 1');
                $s->execute([$path]);
                $redirect = $s->fetch(PDO::FETCH_ASSOC);
                if ($redirect) {
                    $target = Seo::normalizePath((string)$redirect['target_path']);
                    $code = (int)$redirect['status_code'];
                    if ($target !== '' && $target !== $path && in_array($code, [301,302,307,308], true)) {
                        $pdo->prepare('UPDATE seo_redirects SET hit_count=hit_count+1,last_hit_at=NOW() WHERE id=?')->execute([(int)$redirect['id']]);
                        header('Location: ' . $target, true, $code);
                        return;
                    }
                }
            } catch (\Throwable) {
                // In bootstrap/migration environments the redirects table may not exist yet.
            }
        }

        http_response_code(404);
        header('Content-Type: text/html; charset=UTF-8');
        $title = 'Pagina non trovata';
        $seo = Seo::meta($title, 'La pagina richiesta non è disponibile.', $path ?: '/', 'noindex,follow');
        require dirname(__DIR__, 2) . '/Views/public/_layout_start.php';
        require dirname(__DIR__, 2) . '/Views/public/404.php';
        require dirname(__DIR__, 2) . '/Views/public/_layout_end.php';
    }
}
