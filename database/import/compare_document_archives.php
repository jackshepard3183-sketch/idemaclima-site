<?php

declare(strict_types=1);

/**
 * Compare Lovable datasheets.ts document URLs with a historical crawl JSON report.
 * Read-only: writes only the requested CSV report.
 *
 * Usage:
 * php database/import/compare_document_archives.php \
 *   --lovable=/path/to/datasheets.ts \
 *   --historical=/path/to/historical-documents.json \
 *   --output=/tmp/document-comparison.csv
 */

$options = getopt('', ['lovable:', 'historical:', 'output:']);
foreach (['lovable','historical','output'] as $required) {
    if (empty($options[$required])) {
        fwrite(STDERR, "Parametro mancante --{$required}\n");
        exit(1);
    }
}

$lovableRaw = file_get_contents((string)$options['lovable']);
$historicalRaw = file_get_contents((string)$options['historical']);
if ($lovableRaw === false || $historicalRaw === false) {
    fwrite(STDERR, "Impossibile leggere uno dei file di input.\n");
    exit(1);
}

// Extract every PDF-like URL/path in datasheets.ts without depending on TS execution.
preg_match_all('~(?:https?://[^\s\"\'`]+|/[^\s\"\'`]+\.pdf)~i', $lovableRaw, $matches);
$lovableUrls = [];
foreach ($matches[0] ?? [] as $raw) {
    $url = html_entity_decode(trim($raw, " \t\n\r\0\x0B,;)]}"), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (stripos($url, '.pdf') === false) continue;
    $key = strtolower(basename((string)(parse_url($url, PHP_URL_PATH) ?: $url)));
    if ($key !== '') $lovableUrls[$key][] = $url;
}

$historical = json_decode($historicalRaw, true, flags: JSON_THROW_ON_ERROR);
$rows = $historical['documents'] ?? $historical;
if (!is_array($rows)) {
    fwrite(STDERR, "Formato historical JSON non valido.\n");
    exit(1);
}

$out = fopen((string)$options['output'], 'wb');
if ($out === false) {
    fwrite(STDERR, "Impossibile creare il report di output.\n");
    exit(1);
}
fputcsv($out, ['filename_key','historical_url','source_page','anchor_text','lovable_status','lovable_matches','migration_action']);

$stats = ['historical'=>0,'also_in_lovable'=>0,'historical_only'=>0,'ambiguous_filename'=>0];
$seenHistorical = [];
foreach ($rows as $row) {
    if (!is_array($row)) continue;
    $url = (string)($row['url'] ?? $row['source_url'] ?? '');
    if ($url === '' || stripos($url, '.pdf') === false) continue;
    $filename = strtolower(basename((string)(parse_url($url, PHP_URL_PATH) ?: $url)));
    if ($filename === '' || isset($seenHistorical[strtolower($url)])) continue;
    $seenHistorical[strtolower($url)] = true;
    $stats['historical']++;

    $matchesForFile = array_values(array_unique($lovableUrls[$filename] ?? []));
    if (count($matchesForFile) === 0) {
        $status = 'historical_only';
        $action = 'migrate';
        $stats['historical_only']++;
    } elseif (count($matchesForFile) === 1) {
        $status = 'also_in_lovable';
        $action = 'dedupe_after_hash';
        $stats['also_in_lovable']++;
    } else {
        $status = 'ambiguous_filename';
        $action = 'review_hash';
        $stats['ambiguous_filename']++;
    }

    fputcsv($out, [
        $filename,
        $url,
        (string)($row['source_page'] ?? ''),
        (string)($row['text'] ?? $row['anchor_text'] ?? ''),
        $status,
        implode(' | ', $matchesForFile),
        $action,
    ]);
}
fclose($out);

echo "Document archive comparison\n";
foreach ($stats as $k=>$v) echo str_pad($k, 22) . ': ' . $v . "\n";
echo "Nota: il match per filename è solo preliminare. La deduplica definitiva deve avvenire su SHA-256 dopo il download.\n";
