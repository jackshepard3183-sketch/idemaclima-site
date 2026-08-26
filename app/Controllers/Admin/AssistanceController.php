<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\AdminAuth;
use App\Core\Audit;
use App\Core\Database;
use App\Core\Security;
use App\Core\Validator;
use PDO;

final class AssistanceController
{
    public static function index(): void
    {
        AdminAuth::requireLogin();
        $rows = Database::connection()->query(
            'SELECT ar.*, d.title document_title
             FROM assistance_resources ar
             LEFT JOIN documents d ON d.id = ar.document_id
             WHERE ar.archived_at IS NULL
             ORDER BY ar.section, ar.sort_order, ar.id'
        )->fetchAll(PDO::FETCH_ASSOC);
        self::view('assistance_resources', ['title'=>'Assistenza - Risorse','rows'=>$rows]);
    }

    public static function form(): void
    {
        AdminAuth::requireLogin();
        $pdo = Database::connection();
        $id = Validator::int($_GET['id'] ?? 0);
        $resource = ['id'=>0,'section'=>'warranty','label'=>'','document_id'=>'','external_url'=>'','sort_order'=>0,'published'=>1];

        if ($id) {
            $s = $pdo->prepare('SELECT * FROM assistance_resources WHERE id=? AND archived_at IS NULL LIMIT 1');
            $s->execute([$id]);
            $found = $s->fetch(PDO::FETCH_ASSOC);
            if (!$found) {
                http_response_code(404);
                exit('Risorsa Assistenza non trovata.');
            }
            $resource = $found;
        }

        $documents = $pdo->query('SELECT d.id,d.title FROM documents d WHERE d.published=1 ORDER BY d.title')->fetchAll(PDO::FETCH_ASSOC);
        self::view('assistance_resource_form', ['title'=>'Risorsa Assistenza','resource'=>$resource,'documents'=>$documents,'errors'=>[]]);
    }

    public static function save(): void
    {
        AdminAuth::requireLogin();
        self::csrf();
        $errors = [];
        $pdo = Database::connection();
        $id = Validator::int($_POST['id'] ?? 0);

        if ($id) {
            $exists = $pdo->prepare('SELECT id FROM assistance_resources WHERE id=? AND archived_at IS NULL LIMIT 1');
            $exists->execute([$id]);
            if (!$exists->fetchColumn()) {
                http_response_code(404);
                exit('Risorsa Assistenza non trovata.');
            }
        }

        $section = in_array($_POST['section'] ?? '', ['warranty','error_code'], true) ? (string)$_POST['section'] : 'warranty';
        $label = Validator::requiredString($_POST['label'] ?? '', 'Etichetta', 220, $errors);
        $documentId = Validator::int($_POST['document_id'] ?? 0) ?: null;
        $external = trim((string)($_POST['external_url'] ?? '')) ?: null;

        if ($documentId !== null) {
            $s = $pdo->prepare('SELECT COUNT(*) FROM documents WHERE id=? AND published=1');
            $s->execute([$documentId]);
            if (!(int)$s->fetchColumn()) $errors[] = 'Documento non valido o non pubblicato.';
        }

        if ($external !== null) {
            $parts = parse_url($external);
            $scheme = strtolower((string)($parts['scheme'] ?? ''));
            if (!filter_var($external, FILTER_VALIDATE_URL) || !in_array($scheme, ['http','https'], true)) {
                $errors[] = 'L’URL esterno deve essere un indirizzo HTTP o HTTPS valido.';
            }
        }

        if (($documentId === null) === ($external === null)) {
            $errors[] = 'Seleziona esattamente una destinazione: un documento oppure un URL esterno.';
        }

        $sort = Validator::int($_POST['sort_order'] ?? 0);
        $published = Validator::bool($_POST['published'] ?? 0);
        $resource = ['id'=>$id,'section'=>$section,'label'=>$label,'document_id'=>$documentId,'external_url'=>$external,'sort_order'=>$sort,'published'=>$published];

        if ($errors) {
            $documents = $pdo->query('SELECT d.id,d.title FROM documents d WHERE d.published=1 ORDER BY d.title')->fetchAll(PDO::FETCH_ASSOC);
            self::view('assistance_resource_form', ['title'=>'Risorsa Assistenza','resource'=>$resource,'documents'=>$documents,'errors'=>$errors]);
            return;
        }

        if ($id) {
            $s = $pdo->prepare('UPDATE assistance_resources SET section=?,label=?,document_id=?,external_url=?,sort_order=?,published=? WHERE id=? AND archived_at IS NULL');
            $s->execute([$section,$label,$documentId,$external,$sort,$published,$id]);
            $entityId = $id;
            $action = 'assistance_resource.update';
        } else {
            $s = $pdo->prepare('INSERT INTO assistance_resources(section,label,document_id,external_url,sort_order,published) VALUES(?,?,?,?,?,?)');
            $s->execute([$section,$label,$documentId,$external,$sort,$published]);
            $entityId = (int)$pdo->lastInsertId();
            $action = 'assistance_resource.create';
        }

        Audit::log($action, 'assistance_resource', $entityId, [
            'section'=>$section,
            'label'=>$label,
            'document_id'=>$documentId,
            'external_url'=>$external,
            'published'=>$published,
        ]);

        header('Location:/admin/assistance');
        exit;
    }

    public static function archive(): void
    {
        AdminAuth::requireLogin();
        self::csrf();
        $id = Validator::int($_POST['id'] ?? 0);
        if ($id < 1) {
            http_response_code(422);
            exit('ID risorsa non valido.');
        }

        $pdo = Database::connection();
        $s = $pdo->prepare('UPDATE assistance_resources SET archived_at=CURRENT_TIMESTAMP,published=0 WHERE id=? AND archived_at IS NULL');
        $s->execute([$id]);
        if ($s->rowCount() === 0) {
            http_response_code(404);
            exit('Risorsa Assistenza non trovata.');
        }

        Audit::log('assistance_resource.archive', 'assistance_resource', $id, []);
        header('Location:/admin/assistance');
        exit;
    }

    private static function view(string $file, array $data): void
    {
        extract($data, EXTR_SKIP);
        $user = AdminAuth::user();
        $csrf = Security::csrfToken();
        require dirname(__DIR__, 2) . '/Views/admin/' . $file . '.php';
    }

    private static function csrf(): void
    {
        if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            exit('Sessione non valida');
        }
    }
}
