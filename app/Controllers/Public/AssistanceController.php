<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Database;
use PDO;

final class AssistanceController
{
    public static function index(): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->query(
            'SELECT ar.id,ar.section,ar.label,ar.document_id,ar.external_url,d.published document_published
             FROM assistance_resources ar
             LEFT JOIN documents d ON d.id=ar.document_id
             WHERE ar.published=1 AND (ar.document_id IS NULL OR d.published=1)
             ORDER BY ar.section,ar.sort_order,ar.id'
        );
        $groups=['warranty'=>[],'error_code'=>[]];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){$groups[$row['section']][]=$row;}
        $title='Assistenza';
        header('Content-Type:text/html; charset=UTF-8');
        require dirname(__DIR__,2).'/Views/public/_layout_start.php';
        require dirname(__DIR__,2).'/Views/public/assistance/index.php';
        require dirname(__DIR__,2).'/Views/public/_layout_end.php';
    }
}
