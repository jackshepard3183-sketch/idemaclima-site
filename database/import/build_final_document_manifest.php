<?php

declare(strict_types=1);

/**
 * Build the final candidate migration manifest from:
 *  - historical recursive discovery JSON
 *  - Lovable datasheets.ts
 *
 * Read-only. It does not download PDFs and does not touch MySQL.
 * Final physical deduplication still happens by SHA-256 at download time.
 *
 * Usage:
 * php database/import/build_final_document_manifest.php \
 *   --historical=/path/to/historical-document-discovery-latest.json \
 *   --lovable=/path/to/datasheets.ts \
 *   --output=/tmp/final-document-manifest.csv \
 *   --aliases=/tmp/final-document-aliases.csv
 */

$options = getopt('', ['historical:', 'lovable:', 'output:', 'aliases:']);
foreach (['historical','lovable','output','aliases'] as $required) {
    if (empty($options[$required])) {
        fwrite(STDERR, "Parametro mancante --{$required}\n");
        exit(1);
    }
}

$historicalRaw = file_get_contents((string)$options['historical']);
$lovableRaw = file_get_contents((string)$options['lovable']);
if ($historicalRaw === false || $lovableRaw === false) {
    fwrite(STDERR, "Impossibile leggere gli input.\n");
    exit(1);
}

$historical = json_decode($historicalRaw, true, flags: JSON_THROW_ON_ERROR);
$historicalDocs = $historical['documents'] ?? [];
if (!is_array($historicalDocs)) {
    fwrite(STDERR, "Formato historical discovery non valido.\n");
    exit(1);
}

function normalizedFilename(string $url): string
{
    $path = (string)(parse_url($url, PHP_URL_PATH) ?? '');
    $name = basename(rawurldecode($path));
    return mb_strtolower(trim($name), 'UTF-8');
}

function safeTargetFilename(string $filename): string
{
    $filename = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?? $filename;
    $filename = trim($filename, '-.');
    if ($filename === '') $filename = 'document.pdf';
    if (!preg_match('/\.pdf$/i', $filename)) $filename .= '.pdf';
    return $filename;
}

// Lovable contains both absolute URLs and asset paths. Extract every PDF-like token.
preg_match_all('~(?:https?://[^\s\"\'`<>]+|/[^\s\"\'`<>]+\.pdf)~i', $lovableRaw, $matches);
$lovableByFilename = [];
foreach ($matches[0] ?? [] as $rawUrl) {
    $url = html_entity_decode(trim($rawUrl, " \t\n\r\0\x0B,;)]}"), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (stripos($url, '.pdf') === false) continue;
    $key = normalizedFilename($url);
    if ($key === '') continue;
    $lovableByFilename[$key] ??= [];
    $lovableByFilename[$key][$url] = true;
}

$groups = [];
foreach ($historicalDocs as $doc) {
    if (!is_array($doc)) continue;
    $url = (string)($doc['url'] ?? $doc['source_url'] ?? '');
    if ($url === '' || stripos($url, '.pdf') === false) continue;
    $key = normalizedFilename($url);
    if ($key === '') continue;
    $groups[$key] ??= [
        'filename_key'=>$key,
        'target_filename'=>safeTargetFilename(basename((string)(parse_url($url, PHP_URL_PATH) ?? $key))),
        'historical_urls'=>[],
        'source_pages'=>[],
        'anchor_text'=>[],
    ];
    $groups[$key]['historical_urls'][$url] = true;
    foreach ((array)($doc['found_on'] ?? $doc['source_page'] ?? []) as $page) {
        if ((string)$page !== '') $groups[$key]['source_pages'][(string)$page] = true;
    }
    foreach ((array)($doc['anchor_text'] ?? $doc['text'] ?? []) as $text) {
        if ((string)$text !== '') $groups[$key]['anchor_text'][(string)$text] = true;
    }
}

ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);
$out = fopen((string)$options['output'], 'wb');
$aliases = fopen((string)$options['aliases'], 'wb');
if ($out === false || $aliases === false) {
    fwrite(STDERR, "Impossibile creare i file di output.\n");
    exit(1);
}

fputcsv($out, [
    'filename_key','target_filename','historical_url_count','historical_urls',
    'lovable_url_count','lovable_urls','preliminary_status','migration_action',
    'source_pages','anchor_text','dedupe_rule'
]);
fputcsv($aliases, ['filename_key','source_kind','source_url','target_filename','final_resolution']);

$stats = [
    'historical_unique_filenames'=>0,
    'historical_only'=>0,
    'also_in_lovable'=>0,
    'ambiguous'=>0,
    'alias_rows'=>0,
];

foreach ($groups as $key => $group) {
    $historicalUrls = array_keys($group['historical_urls']);
    $lovableUrls = array_keys($lovableByFilename[$key] ?? []);
    $stats['historical_unique_filenames']++;

    if (!$lovableUrls) {
        $status = 'historical_only';
        $action = 'copy_and_hash';
        $stats['historical_only']++;
    } elseif (count($lovableUrls) === 1 && count($historicalUrls) === 1) {
        $status = 'also_in_lovable';
        $action = 'download_both_if_needed_then_hash';
        $stats['also_in_lovable']++;
    } else {
        $status = 'ambiguous_filename';
        $action = 'hash_all_candidates';
        $stats['ambiguous']++;
    }

    fputcsv($out, [
        $key,
        $group['target_filename'],
        count($historicalUrls),
        implode(' | ', $historicalUrls),
        count($lovableUrls),
        implode(' | ', $lovableUrls),
        $status,
        $action,
        implode(' | ', array_keys($group['source_pages'])),
        implode(' | ', array_keys($group['anchor_text'])),
        'SHA-256 decides physical uniqueness; filename is preliminary only',
    ]);

    foreach ($historicalUrls as $url) {
        fputcsv($aliases, [$key,'wordpress',$url,$group['target_filename'],'pending_sha256']);
        $stats['alias_rows']++;
    }
    foreach ($lovableUrls as $url) {
        fputcsv($aliases, [$key,'lovable',$url,$group['target_filename'],'pending_sha256']);
        $stats['alias_rows']++;
    }
}

fclose($out);
fclose($aliases);

echo "Final document manifest candidate\n";
foreach ($stats as $k=>$v) echo str_pad($k, 30) . ': ' . $v . "\n";
echo "Output: {$options['output']}\n";
echo "Aliases: {$options['aliases']}\n";
echo "Nessun download e nessuna scrittura DB eseguiti. La deduplica fisica finale resta basata su SHA-256.\n";
