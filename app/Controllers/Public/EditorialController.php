<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Database;
use App\Core\Security;
use PDO;

final class EditorialController
{
    public static function page(string $slug, array $extra=[]): void
    {
        $pdo = Database::connection();
        $s = $pdo->prepare('SELECT * FROM editorial_pages WHERE slug=? AND published=1 LIMIT 1');
        $s->execute([$slug]);
        $page = $s->fetch(PDO::FETCH_ASSOC);
        if (!$page) {
            http_response_code(404);
            self::render('404', ['title'=>'Pagina non trovata']);
            return;
        }

        $sections = $pdo->prepare('SELECT * FROM editorial_sections WHERE page_id=? AND published=1 ORDER BY sort_order,id');
        $sections->execute([(int)$page['id']]);

        $faqs = $pdo->prepare('SELECT * FROM faq_items WHERE page_id=? AND published=1 ORDER BY sort_order,id');
        $faqs->execute([(int)$page['id']]);

        $docs = $pdo->prepare('SELECT epd.*,d.title,d.file_path,d.id document_id,t.name document_type FROM editorial_page_documents epd JOIN documents d ON d.id=epd.document_id JOIN document_types t ON t.id=d.document_type_id WHERE epd.page_id=? AND epd.published=1 AND d.published=1 ORDER BY epd.group_label,epd.sort_order,epd.id');
        $docs->execute([(int)$page['id']]);
        $documentGroups = [];
        foreach ($docs->fetchAll(PDO::FETCH_ASSOC) as $doc) {
            $group = trim((string)($doc['group_label'] ?? '')) ?: 'Documenti';
            $documentGroups[$group][] = $doc;
        }

        self::render('editorial/page', array_merge([
            'title'=>$page['meta_title'] ?: $page['title'],
            'page'=>$page,
            'sections'=>$sections->fetchAll(PDO::FETCH_ASSOC),
            'faqs'=>$faqs->fetchAll(PDO::FETCH_ASSOC),
            'documentGroups'=>$documentGroups,
            'csrf'=>Security::csrfToken(),'errors'=>[],'old'=>[],
        ],$extra));
    }

    private static function render(string $view, array $data): void
    {
        extract($data, EXTR_SKIP);
        header('Content-Type: text/html; charset=UTF-8');
        require dirname(__DIR__,2).'/Views/public/_layout_start.php';
        require dirname(__DIR__,2).'/Views/public/'.$view.'.php';
        require dirname(__DIR__,2).'/Views/public/_layout_end.php';
    }
}
