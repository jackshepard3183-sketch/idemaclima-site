<?php

declare(strict_types=1);

require dirname(__DIR__) . '/scripts/bootstrap.php';

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

$execute = in_array('--execute', $argv, true);
if ((string)($_ENV['APP_ENV'] ?? getenv('APP_ENV')) !== 'staging') {
    fwrite(STDERR, "Questa migrazione è consentita solo in staging.\n");
    exit(1);
}

$allowedColumns = [
    'products' => ['description', 'image_path'],
    'documents' => ['file_path'],
    'catalogs' => ['description', 'cover_image', 'pdf_path'],
    'gallery_albums' => ['description', 'cover_image'],
    'gallery_images' => ['image_path'],
    'references_projects' => ['short_description', 'description', 'cover_image'],
    'reference_images' => ['image_path'],
    'assistance_resources' => ['external_url'],
    'editorial_pages' => ['intro', 'body'],
    'editorial_sections' => ['body'],
    'campus_events' => ['description', 'image_path', 'cover_image'],
    'site_settings' => ['value'],
];

$allowedUrlPattern = "~https://(?:www\\.)?idemaclima\\.it/wp-content/uploads/[^\\s\\\"'<>]+|https://idemaclima\\.lovable\\.app/__l5e/assets-v1/[^\\s\\\"'<>]+~i";
$allowedExtensions = ['pdf','png','jpg','jpeg','webp','gif','svg'];
$targetRoot = dirname(__DIR__) . '/public/uploads/mirrored';

function discoverColumns(PDO $pdo, array $allowlist): array
{
    $database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    $stmt = $pdo->prepare(
        "SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=? AND DATA_TYPE IN ('char','varchar','text','mediumtext','longtext')"
    );
    $stmt->execute([$database]);
    $available = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $available[(string)$row['TABLE_NAME']][(string)$row['COLUMN_NAME']] = true;
    }
    $result = [];
    foreach ($allowlist as $table => $columns) {
        foreach ($columns as $column) {
            if (isset($available[$table][$column])) $result[$table][] = $column;
        }
    }
    return $result;
}

function safeFilename(string $url): string
{
    $path = (string)parse_url($url, PHP_URL_PATH);
    $base = rawurldecode(basename($path));
    $base = preg_replace('/[^a-z0-9._-]+/i', '-', $base) ?: 'asset';
    $extension = strtolower(pathinfo($base, PATHINFO_EXTENSION));
    if ($extension === '' || !in_array($extension, $GLOBALS['allowedExtensions'], true)) {
        throw new RuntimeException('Estensione non consentita: ' . $url);
    }
    return substr(hash('sha256', $url), 0, 16) . '-' . $base;
}

function fetchAsset(string $url): string
{
    $ch = curl_init($url);
    if ($ch === false) throw new RuntimeException('Impossibile inizializzare il download.');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 4,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 90,
        CURLOPT_USERAGENT => 'Idema-Clima-Staging-Migration/1.0',
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $type = strtolower((string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
    $error = curl_error($ch);
    curl_close($ch);
    if (!is_string($body) || $status < 200 || $status >= 300) {
        throw new RuntimeException('Download fallito HTTP ' . $status . ($error !== '' ? ': ' . $error : ''));
    }
    if (strlen($body) < 8 || strlen($body) > 50 * 1024 * 1024) {
        throw new RuntimeException('Dimensione del contenuto non valida.');
    }
    if (str_contains($type, 'text/html')) throw new RuntimeException('La sorgente ha restituito HTML.');
    return $body;
}

$pdo = Database::connection();
$columns = discoverColumns($pdo, $allowedColumns);
$references = [];
foreach ($columns as $table => $tableColumns) {
    foreach ($tableColumns as $column) {
        $sql = 'SELECT `' . str_replace('`', '``', $column) . '` AS value FROM `'
            . str_replace('`', '``', $table) . '` WHERE `'
            . str_replace('`', '``', $column) . '` LIKE ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['%idemaclima%']);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $value) {
            if (!is_string($value) || $value === '') continue;
            if (preg_match_all($allowedUrlPattern, html_entity_decode($value, ENT_QUOTES | ENT_HTML5), $matches)) {
                foreach ($matches[0] as $url) $references[$url] = true;
            }
        }
    }
}

$renderedSources = dirname(__DIR__) . '/database/import/rendered_asset_sources.txt';
if (is_file($renderedSources)) {
    foreach (file($renderedSources, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $url) {
        $url = trim($url);
        if (preg_match($allowedUrlPattern, $url, $match) && $match[0] === $url) $references[$url] = true;
    }
}

$stats = ['references' => count($references), 'downloaded' => 0, 'existing' => 0, 'updated_fields' => 0, 'errors' => 0];
$replacements = [];
$errors = [];
foreach (array_keys($references) as $url) {
    try {
        $filename = safeFilename($url);
        $absolute = $targetRoot . '/' . $filename;
        $relative = '/uploads/mirrored/' . $filename;
        if (is_file($absolute) && filesize($absolute) > 8) {
            $stats['existing']++;
        } elseif ($execute) {
            $bytes = fetchAsset($url);
            if (!is_dir($targetRoot) && !mkdir($targetRoot, 0755, true) && !is_dir($targetRoot)) {
                throw new RuntimeException('Impossibile creare la cartella di destinazione.');
            }
            $tmp = $absolute . '.part';
            if (file_put_contents($tmp, $bytes, LOCK_EX) === false || !rename($tmp, $absolute)) {
                @unlink($tmp);
                throw new RuntimeException('Scrittura locale fallita.');
            }
            @chmod($absolute, 0644);
            $stats['downloaded']++;
        }
        if (is_file($absolute)) $replacements[$url] = $relative;
    } catch (Throwable $e) {
        $stats['errors']++;
        $errors[] = ['url' => $url, 'error' => $e->getMessage()];
    }
}

if ($execute && $replacements !== []) {
    $pdo->beginTransaction();
    try {
        foreach ($columns as $table => $tableColumns) {
            foreach ($tableColumns as $column) {
                foreach ($replacements as $remote => $local) {
                    $sql = 'UPDATE `' . str_replace('`', '``', $table) . '` SET `'
                        . str_replace('`', '``', $column) . '`=REPLACE(`'
                        . str_replace('`', '``', $column) . '`,?,?) WHERE `'
                        . str_replace('`', '``', $column) . '` LIKE ?';
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$remote, $local, '%' . $remote . '%']);
                    $stats['updated_fields'] += $stmt->rowCount();
                }
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

$reportDir = dirname(__DIR__) . '/database/import/reports';
if (!is_dir($reportDir)) @mkdir($reportDir, 0755, true);
$report = [
    'mode' => $execute ? 'execute' : 'dry-run',
    'ok' => $errors === [],
    'stats' => $stats,
    'errors' => $errors,
    'replacements' => $replacements,
];
file_put_contents($reportDir . '/remote-assets-migration-latest.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo ($execute ? 'EXECUTE' : 'DRY-RUN') . " remote assets migration\n";
foreach ($stats as $key => $value) echo str_pad($key, 20) . ': ' . $value . "\n";
if (!$execute) echo "Nessuna scrittura eseguita. Usa --execute solo sullo staging.\n";
foreach ($errors as $error) echo 'ERRORE ' . $error['url'] . ': ' . $error['error'] . "\n";
if ($errors !== []) echo "Migrazione parziale: i collegamenti scaricabili sono stati localizzati; i residui restano esterni.\n";
