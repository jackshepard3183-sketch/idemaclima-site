<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/scripts/bootstrap.php';

use App\Core\Database;
use PDO;

$download = in_array('--download', $argv, true) || in_array('--execute', $argv, true);
$execute = in_array('--execute', $argv, true);
$manifest = __DIR__ . '/document_migration_manifest.csv';

foreach ($argv as $arg) {
    if (!str_starts_with($arg, '--manifest=')) continue;
    $value = trim(substr($arg, strlen('--manifest=')));
    if ($value === '' || str_contains($value, "\0")) {
        fwrite(STDERR, "Valore --manifest non valido.\n");
        exit(1);
    }

    $candidate = str_starts_with($value, DIRECTORY_SEPARATOR)
        ? $value
        : __DIR__ . DIRECTORY_SEPARATOR . ltrim($value, '/\\');
    $real = realpath($candidate);
    if ($real === false || !is_file($real)) {
        fwrite(STDERR, "Manifest non trovato: {$value}\n");
        exit(1);
    }

    if (!str_starts_with($value, DIRECTORY_SEPARATOR)) {
        $importRoot = realpath(__DIR__);
        if ($importRoot === false || !str_starts_with($real, $importRoot . DIRECTORY_SEPARATOR)) {
            fwrite(STDERR, "Manifest relativo fuori dalla cartella import non consentito.\n");
            exit(1);
        }
    }
    $manifest = $real;
}

$targetRoot = dirname(__DIR__, 2) . '/public/uploads/documents/migrated';

$fh = fopen($manifest, 'rb');
if (!$fh) { fwrite(STDERR, "Manifest non leggibile.\n"); exit(1); }
$header = fgetcsv($fh);
if (!$header) { fwrite(STDERR, "Manifest vuoto.\n"); exit(1); }

$required = ['source_url','title','type_slug','group_label','target_filename','category_slug'];
$missingColumns = array_values(array_diff($required, $header));
if ($missingColumns !== []) {
    fwrite(STDERR, 'Colonne obbligatorie mancanti: ' . implode(', ', $missingColumns) . "\n");
    fclose($fh);
    exit(1);
}

$rows = [];
$line = 1;
while (($row = fgetcsv($fh)) !== false) {
    $line++;
    if (count($row) !== count($header)) {
        fwrite(STDERR, "Riga {$line} ignorata: numero colonne non valido.\n");
        continue;
    }
    $rows[] = array_combine($header, $row);
}
fclose($fh);

function fetchRemotePdf(string $url): string
{
    if (!preg_match('#^https://www\.idemaclima\.it/wp-content/uploads/#i', $url)) {
        throw new RuntimeException('URL sorgente non consentito: ' . $url);
    }
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 4,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_USERAGENT => 'IDEMA-Migration/1.0',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if (!is_string($body) || $status < 200 || $status >= 300) {
            throw new RuntimeException('Download fallito HTTP ' . $status . ($err ? ': ' . $err : ''));
        }
        return $body;
    }
    $context = stream_context_create(['http'=>['timeout'=>60,'user_agent'=>'IDEMA-Migration/1.0','follow_location'=>1,'max_redirects'=>4]]);
    $body = @file_get_contents($url, false, $context);
    if (!is_string($body)) throw new RuntimeException('Download fallito');
    return $body;
}

function validatePdf(string $bytes): void
{
    if (strlen($bytes) < 5 || substr($bytes, 0, 5) !== '%PDF-') {
        throw new RuntimeException('Il contenuto ricevuto non è un PDF valido.');
    }
    if (strlen($bytes) > 50 * 1024 * 1024) {
        throw new RuntimeException('PDF oltre il limite di 50 MB.');
    }
}

function localPdfByHash(string $root, string $sha256): ?string
{
    if (!is_dir($root)) return null;
    foreach (glob($root . '/*.pdf') ?: [] as $path) {
        if (is_file($path) && hash_file('sha256', $path) === $sha256) return $path;
    }
    return null;
}

function ensureDocumentLink(PDO $pdo, int $documentId, ?int $categoryId, ?int $productId, ?int $modelId): bool
{
    $check = $pdo->prepare(
        'SELECT id FROM document_links
         WHERE document_id=? AND category_id <=> ? AND product_id <=> ? AND model_id <=> ?
         LIMIT 1'
    );
    $check->execute([$documentId,$categoryId,$productId,$modelId]);
    if ($check->fetchColumn() !== false) return false;

    $insert = $pdo->prepare(
        'INSERT INTO document_links(document_id,category_id,product_id,model_id) VALUES(?,?,?,?)'
    );
    $insert->execute([$documentId,$categoryId,$productId,$modelId]);
    return true;
}

$pdo = $execute ? Database::connection() : null;
$stats = [
    'manifest'=>count($rows),
    'downloaded'=>0,
    'verified'=>0,
    'physical_deduped'=>0,
    'db_inserted'=>0,
    'db_deduped'=>0,
    'aliases'=>0,
    'category_links'=>0,
    'product_links'=>0,
    'model_links'=>0,
    'editorial_links'=>0,
    'errors'=>0,
];
$report = [];

foreach ($rows as $row) {
    $item = ['source_url'=>$row['source_url'],'title'=>$row['title'],'status'=>'dry-run'];
    try {
        $filename = basename((string)$row['target_filename']);
        if ($filename === '' || !preg_match('/\.pdf$/i', $filename)) {
            throw new RuntimeException('Nome file target non valido.');
        }

        $bytes = null;
        $sha256 = null;
        $size = null;
        $relativePath = '/uploads/documents/migrated/' . $filename;
        $absolutePath = $targetRoot . '/' . $filename;

        if ($download) {
            if (is_file($absolutePath)) {
                $bytes = file_get_contents($absolutePath);
                if (!is_string($bytes)) throw new RuntimeException('Impossibile leggere il PDF locale esistente.');
                validatePdf($bytes);
                $item['status'] = 'existing-local';
            } else {
                $bytes = fetchRemotePdf((string)$row['source_url']);
                validatePdf($bytes);
            }

            $sha256 = hash('sha256', $bytes);
            $size = strlen($bytes);
            $stats['verified']++;

            if (!is_file($absolutePath)) {
                $samePhysical = localPdfByHash($targetRoot, $sha256);
                if ($samePhysical !== null) {
                    $absolutePath = $samePhysical;
                    $filename = basename($samePhysical);
                    $relativePath = '/uploads/documents/migrated/' . $filename;
                    $stats['physical_deduped']++;
                    $item['status'] = 'deduped-local';
                } else {
                    if (!is_dir($targetRoot) && !mkdir($targetRoot, 0755, true) && !is_dir($targetRoot)) {
                        throw new RuntimeException('Impossibile creare la cartella documenti migrati.');
                    }
                    if (file_put_contents($absolutePath, $bytes, LOCK_EX) === false) {
                        throw new RuntimeException('Scrittura PDF fallita.');
                    }
                    @chmod($absolutePath, 0644);
                    $stats['downloaded']++;
                    $item['status'] = 'downloaded';
                }
            }
            $item['sha256'] = $sha256;
            $item['bytes'] = $size;
            $item['file_path'] = $relativePath;
        }

        if ($execute && $pdo instanceof PDO) {
            if ($sha256 === null || $size === null) throw new RuntimeException('Hash del PDF non disponibile.');

            $type = $pdo->prepare('SELECT id FROM document_types WHERE slug=? LIMIT 1');
            $type->execute([$row['type_slug']]);
            $typeId = $type->fetchColumn();
            if ($typeId === false) throw new RuntimeException('Tipo documento non trovato: ' . $row['type_slug']);

            $existing = $pdo->prepare('SELECT id,file_path,filename FROM documents WHERE sha256=? LIMIT 1');
            $existing->execute([$sha256]);
            $existingRow = $existing->fetch(PDO::FETCH_ASSOC);
            if ($existingRow) {
                $documentId = (int)$existingRow['id'];
                $relativePath = (string)$existingRow['file_path'];
                $filename = (string)$existingRow['filename'];
                $stats['db_deduped']++;
            } else {
                $ins = $pdo->prepare(
                    'INSERT INTO documents(document_type_id,title,filename,file_path,sha256,file_size,mime_type,published,sort_order)
                     VALUES(?,?,?,?,?,?,\'application/pdf\',1,0)'
                );
                $ins->execute([(int)$typeId,$row['title'],$filename,$relativePath,$sha256,$size]);
                $documentId = (int)$pdo->lastInsertId();
                $stats['db_inserted']++;
            }

            $alias = $pdo->prepare(
                'INSERT IGNORE INTO document_source_aliases(document_id,source_url,source_kind,source_page)
                 VALUES(?,?,\'wordpress\',NULL)'
            );
            $alias->execute([$documentId,$row['source_url']]);
            $stats['aliases'] += $alias->rowCount();

            $categorySlug = trim((string)($row['category_slug'] ?? ''));
            if ($categorySlug !== '') {
                $cat = $pdo->prepare('SELECT id FROM product_categories WHERE slug=? LIMIT 1');
                $cat->execute([$categorySlug]);
                $categoryId = $cat->fetchColumn();
                if ($categoryId === false) throw new RuntimeException('Categoria non trovata: ' . $categorySlug);
                if (ensureDocumentLink($pdo,$documentId,(int)$categoryId,null,null)) $stats['category_links']++;
            }

            $productSlugs = array_values(array_filter(array_map(
                'trim',
                explode(';', (string)($row['product_slug'] ?? ''))
            )));
            $modelCode = trim((string)($row['model_code'] ?? ''));

            if ($modelCode !== '' && count($productSlugs) !== 1) {
                throw new RuntimeException('model_code richiede esattamente un product_slug.');
            }

            foreach ($productSlugs as $productSlug) {
                $productStmt = $pdo->prepare('SELECT id FROM products WHERE slug=? LIMIT 1');
                $productStmt->execute([$productSlug]);
                $productId = $productStmt->fetchColumn();
                if ($productId === false) throw new RuntimeException('Prodotto non trovato: ' . $productSlug);

                $modelId = null;
                if ($modelCode !== '') {
                    $modelStmt = $pdo->prepare(
                        'SELECT id FROM product_models WHERE product_id=? AND LOWER(code)=LOWER(?) LIMIT 1'
                    );
                    $modelStmt->execute([(int)$productId,$modelCode]);
                    $modelId = $modelStmt->fetchColumn();
                    if ($modelId === false) {
                        throw new RuntimeException('Modello non trovato nel prodotto ' . $productSlug . ': ' . $modelCode);
                    }
                    $modelId = (int)$modelId;
                }

                if (ensureDocumentLink($pdo,$documentId,null,(int)$productId,$modelId)) {
                    if ($modelId !== null) $stats['model_links']++;
                    else $stats['product_links']++;
                }
            }

            if ($row['type_slug'] === 'dichiarazione-ce') {
                $page = $pdo->prepare('SELECT id FROM editorial_pages WHERE slug=? LIMIT 1');
                $page->execute(['schede-tecniche/dichiarazioni-conformita-ce']);
                $pageId = $page->fetchColumn();
                if ($pageId === false) {
                    throw new RuntimeException('Pagina editoriale Dichiarazioni CE non trovata.');
                }

                $check = $pdo->prepare(
                    'SELECT id FROM editorial_page_documents
                     WHERE page_id=? AND document_id=? AND group_label <=> ?
                     LIMIT 1'
                );
                $groupLabel = trim((string)($row['group_label'] ?? '')) ?: null;
                $check->execute([(int)$pageId,$documentId,$groupLabel]);
                if ($check->fetchColumn() === false) {
                    $pdo->prepare(
                        'INSERT INTO editorial_page_documents(page_id,document_id,group_label,label,sort_order)
                         VALUES(?,?,?,?,0)'
                    )->execute([(int)$pageId,$documentId,$groupLabel,$row['title']]);
                    $stats['editorial_links']++;
                }
            }

            $item['status'] .= '+db';
            $item['document_id'] = $documentId;
        }
    } catch (Throwable $e) {
        $stats['errors']++;
        $item['status'] = 'error';
        $item['error'] = $e->getMessage();
    }
    $report[] = $item;
}

$reportStem = preg_replace('/[^a-z0-9._-]+/i', '-', pathinfo($manifest, PATHINFO_FILENAME)) ?: 'manifest';
$reportPath = __DIR__ . '/reports/document-migration-' . $reportStem . '-latest.json';
file_put_contents(
    $reportPath,
    json_encode(
        [
            'mode'=>$execute?'execute':($download?'download':'dry-run'),
            'manifest'=>basename($manifest),
            'stats'=>$stats,
            'items'=>$report,
        ],
        JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE
    )
);

echo strtoupper($execute ? 'EXECUTE' : ($download ? 'DOWNLOAD' : 'DRY-RUN')) . " document migration\n";
echo 'manifest            : ' . basename($manifest) . "\n";
foreach ($stats as $k=>$v) echo str_pad($k, 20) . ': ' . $v . "\n";
echo 'Report: ' . $reportPath . "\n";
if (!$download) {
    echo "Nessun file scaricato. Usa --download per copiare/verificare/deduplicare i PDF, --execute solo per inserire anche nel DB.\n";
}
