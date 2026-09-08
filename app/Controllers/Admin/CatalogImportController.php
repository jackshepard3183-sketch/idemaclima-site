<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\AdminAuth;
use App\Core\Database;
use App\Core\Security;

final class CatalogImportController
{
    public static function index(): void
    {
        AdminAuth::requireLogin();
        $user = AdminAuth::user();
        $csrf = Security::csrfToken();
        $report = null;
        $urlRepair = null;
        $error = null;
        require dirname(__DIR__, 2) . '/Views/admin/catalog_import.php';
    }

    public static function run(): void
    {
        AdminAuth::requireLogin();
        if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            exit('Sessione non valida');
        }

        $action = (string) ($_POST['action'] ?? '');
        if (!in_array($action, ['dry-run', 'execute', 'repair-check', 'repair-execute'], true)) {
            http_response_code(400);
            exit('Operazione non valida');
        }

        $user = AdminAuth::user();
        $csrf = Security::csrfToken();
        $report = null;
        $urlRepair = null;
        $error = null;

        try {
            if (str_starts_with($action, 'repair-')) {
                $urlRepair = self::repairDocumentUrls($action === 'repair-execute');
            } else {
                $execute = $action === 'execute';
                define('IDEMA_IMPORT_WEB', true);
                define('IDEMA_IMPORT_EXECUTE', $execute);
                $report = require dirname(__DIR__, 3) . '/database/import/import_datasheets.php';
                if (!is_array($report)) {
                    throw new \RuntimeException('Il rapporto di importazione non è disponibile.');
                }
            }
        } catch (\Throwable $exception) {
            $report = null;
            $error = $exception->getMessage();
        }

        require dirname(__DIR__, 2) . '/Views/admin/catalog_import.php';
    }

    /** @return array{mode:string,matched:int,updated:int,columns:array<int,array{column:string,count:int,samples:array<int,string>}>} */
    private static function repairDocumentUrls(bool $execute): array
    {
        $pdo = Database::connection();
        $columns = $pdo->query('SHOW COLUMNS FROM documents')->fetchAll(\PDO::FETCH_ASSOC);
        $result = [
            'mode' => $execute ? 'execute' : 'dry-run',
            'matched' => 0,
            'updated' => 0,
            'columns' => [],
        ];

        if ($execute) {
            $pdo->beginTransaction();
        }

        try {
            foreach ($columns as $column) {
                $name = (string) ($column['Field'] ?? '');
                $type = strtolower((string) ($column['Type'] ?? ''));
                if ($name === '' || !preg_match('/char|text/', $type)) {
                    continue;
                }

                $identifier = '`' . str_replace('`', '``', $name) . '`';
                $pattern = '/__l5e/assets-v1/%';
                $countStatement = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE {$identifier} LIKE :pattern");
                $countStatement->execute(['pattern' => $pattern]);
                $count = (int) $countStatement->fetchColumn();
                if ($count === 0) {
                    continue;
                }

                $sampleStatement = $pdo->prepare("SELECT {$identifier} FROM documents WHERE {$identifier} LIKE :pattern LIMIT 3");
                $sampleStatement->execute(['pattern' => $pattern]);
                $samples = array_values(array_filter(array_map('strval', $sampleStatement->fetchAll(\PDO::FETCH_COLUMN))));

                $result['matched'] += $count;
                $result['columns'][] = ['column' => $name, 'count' => $count, 'samples' => $samples];

                if ($execute) {
                    $update = $pdo->prepare(
                        "UPDATE documents SET {$identifier} = CONCAT(" .
                        "'https://www.idemaclima.it/wp-content/uploads/schede/', " .
                        "SUBSTRING_INDEX({$identifier}, '/', -1)) WHERE {$identifier} LIKE :pattern"
                    );
                    $update->execute(['pattern' => $pattern]);
                    $result['updated'] += $update->rowCount();
                }
            }

            if ($execute) {
                $pdo->commit();
            }
        } catch (\Throwable $exception) {
            if ($execute && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        return $result;
    }
}
