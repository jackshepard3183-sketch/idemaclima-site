<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/scripts/bootstrap.php';

use App\Core\Database;
use PDO;

$download = in_array('--download', $argv, true) || in_array('--execute', $argv, true);
$execute = in_array('--execute', $argv, true);
$manifest = __DIR__ . '/document_migration_manifest.csv';
$targetRoot = dirname(__DIR__, 2) . '/public/uploads/documents/migrated';

$fh = fopen($manifest, 'rb');
if (!$fh) {
    fwrite(STDERR, "Manifest non leggibile.\n");
    exit(1);
}
$header = fgetcsv($fh);
if (!$header) {
    fwrite(STDERR, "Manifest vuoto.\n");
    exit(1);
}

$rows = [];
while (($row = fgetcsv($fh)) !== false) {
    if (count($row) !== count($header)) continue;
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

$pdo = $execute ? Database::connection() : null;
$stats = ['manifest'=>count($rows),'downloaded'=>0,'verified'=>0,'db_inserted'=>0,'skipped'=>0,'errors'=>0];
$report = [];

foreach ($rows as $row) {
    $item = ['source_url'=>$row['source_url'],'title'=>$row['title'],'status'=>'dry-run'];
    try {
        $filename = basename((string)$row['target_filename']);
        if ($filename === '' || !preg_match('/\.pdf$/i', $filename)) throw new RuntimeException('Nome file target non valido.');
        $relativePath = '/uploads/documents/migrated/' . $filename;
        $absolutePath = $targetRoot . '/' . $filename;

        if ($download) {
            if (is_file($absolutePath)) {
                $bytes = file_get_contents($absolutePath);
                if (!is_string($bytes)) throw new RuntimeException('Impossibile leggere il PDF locale esistente.');
                validatePdf($bytes);
                $stats['skipped']++;
                $item['status'] = 'existing-local';
            } else {
                $bytes = fetchRemotePdf((string)$row['source_url']);
                validatePdf($bytes);
                if (!is_dir($targetRoot) && !mkdir($targetRoot, 0755, true) && !is_dir($targetRoot)) {
                    throw new RuntimeException('Impossibile creare la cartella documenti migrati.');
                }
                if (file_put_contents($absolutePath, $bytes, LOCK_EX) === false) throw new RuntimeException('Scrittura PDF fallita.');
                @chmod($absolutePath, 0644);
                $stats['downloaded']++;
                $item['status'] = 'downloaded';
            }
            $item['sha256'] = hash_file('sha256', $absolutePath);
            $item['bytes'] = filesize($absolutePath);
            $stats['verified']++;
        }

        if ($execute && $pdo instanceof PDO) {
            $type = $pdo->prepare('SELECT id FROM document_types WHERE slug=? LIMIT 1');
            $type->execute([$row['type_slug']]);
            $typeId = $type->fetchColumn();
            if ($typeId === false) throw new RuntimeException('Tipo documento non trovato: ' . $row['type_slug']);

            $existing = $pdo->prepare('SELECT id FROM documents WHERE file_path=? LIMIT 1');
            $existing->execute([$relativePath]);
            $documentId = $existing->fetchColumn();
            if ($documentId === false) {
                $ins = $pdo->prepare('INSERT INTO documents(document_type_id,title,filename,file_path,published,sort_order) VALUES(?,?,?,?,1,0)');
                $ins->execute([(int)$typeId,$row['title'],$filename,$relativePath]);
                $documentId = (int)$pdo->lastInsertId();
                $stats['db_inserted']++;
            } else {
                $documentId = (int)$documentId;
                $stats['skipped']++;
            }

            if (!empty($row['category_slug'])) {
                $cat = $pdo->prepare('SELECT id FROM product_categories WHERE slug=? LIMIT 1');
                $cat->execute([$row['category_slug']]);
                $categoryId = $cat->fetchColumn();
                if ($categoryId === false) throw new RuntimeException('Categoria non trovata: ' . $row['category_slug']);
                $checkLink = $pdo->prepare('SELECT id FROM document_links WHERE document_id=? AND category_id=? LIMIT 1');
                $checkLink->execute([$documentId,(int)$categoryId]);
                if ($checkLink->fetchColumn() === false) {
                    $pdo->prepare('INSERT INTO document_links(document_id,category_id) VALUES(?,?)')->execute([$documentId,(int)$categoryId]);
                }
            }

            $page = $pdo->prepare('SELECT id FROM editorial_pages WHERE page_key="ce" LIMIT 1');
            $page->execute();
            $pageId = $page->fetchColumn();
            if ($pageId !== false) {
                $check = $pdo->prepare('SELECT id FROM editorial_page_documents WHERE page_id=? AND document_id=? LIMIT 1');
                $check->execute([(int)$pageId,$documentId]);
                if ($check->fetchColumn() === false) {
                    $pdo->prepare('INSERT INTO editorial_page_documents(page_id,document_id,group_label,label,sort_order) VALUES(?,?,?,?,0)')
                        ->execute([(int)$pageId,$documentId,$row['group_label'] ?: null,$row['title']]);
                }
            }
            $item['status'] .= '+db';
        }
    } catch (Throwable $e) {
        $stats['errors']++;
        $item['status'] = 'error';
        $item['error'] = $e->getMessage();
    }
    $report[] = $item;
}

$reportPath = __DIR__ . '/reports/document-migration-latest.json';
file_put_contents($reportPath, json_encode(['mode'=>$execute?'execute':($download?'download':'dry-run'),'stats'=>$stats,'items'=>$report], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));

echo strtoupper($execute ? 'EXECUTE' : ($download ? 'DOWNLOAD' : 'DRY-RUN')) . " document migration\n";
foreach ($stats as $k=>$v) echo str_pad($k, 16) . ': ' . $v . "\n";
echo 'Report: ' . $reportPath . "\n";
if (!$download) echo "Nessun file scaricato. Usa --download per copiare e verificare i PDF, --execute solo per inserire anche nel DB.\n";
