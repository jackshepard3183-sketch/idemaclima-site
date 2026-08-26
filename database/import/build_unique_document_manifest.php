<?php

declare(strict_types=1);

/**
 * Build a candidate physical-copy manifest from the curated migration CSV.
 * This step is read-only with respect to DB/storage: it only writes a CSV plan.
 * Definitive deduplication still happens by SHA-256 in migrate_documents.php.
 *
 * Usage:
 * php database/import/build_unique_document_manifest.php \
 *   --manifest=database/import/document_migration_manifest.csv \
 *   --output=/tmp/unique-document-plan.csv
 */
$options = getopt('', ['manifest:', 'output:']);
$manifest = (string)($options['manifest'] ?? __DIR__ . '/document_migration_manifest.csv');
$output = (string)($options['output'] ?? __DIR__ . '/reports/unique-document-plan-latest.csv');

$fh = fopen($manifest, 'rb');
if (!$fh) { fwrite(STDERR, "Manifest non leggibile.\n"); exit(1); }
$header = fgetcsv($fh);
if (!$header) { fwrite(STDERR, "Manifest vuoto.\n"); exit(1); }
$rows = [];
while (($row = fgetcsv($fh)) !== false) {
    if (count($row) !== count($header)) continue;
    $rows[] = array_combine($header, $row);
}
fclose($fh);

$groups = [];
foreach ($rows as $row) {
    $url = trim((string)$row['source_url']);
    $filename = strtolower(basename((string)(parse_url($url, PHP_URL_PATH) ?: $row['target_filename'])));
    if ($filename === '') continue;
    $groups[$filename][] = $row;
}

$dir = dirname($output);
if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
    fwrite(STDERR, "Impossibile creare cartella report.\n"); exit(1);
}
$out = fopen($output, 'wb');
if (!$out) { fwrite(STDERR, "Output non scrivibile.\n"); exit(1); }
fputcsv($out, ['filename_key','candidate_source_url','alias_count','titles','type_slugs','groups','category_slugs','physical_action','final_dedupe']);

$stats = ['manifest_rows'=>count($rows),'filename_groups'=>count($groups),'candidate_copies'=>0,'filename_aliases'=>0];
ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);
foreach ($groups as $filename => $items) {
    $urls = array_values(array_unique(array_map(static fn($r)=>(string)$r['source_url'], $items)));
    $titles = array_values(array_unique(array_filter(array_map(static fn($r)=>(string)$r['title'], $items))));
    $types = array_values(array_unique(array_filter(array_map(static fn($r)=>(string)$r['type_slug'], $items))));
    $labels = array_values(array_unique(array_filter(array_map(static fn($r)=>(string)$r['group_label'], $items))));
    $cats = array_values(array_unique(array_filter(array_map(static fn($r)=>(string)$r['category_slug'], $items))));
    $stats['candidate_copies']++;
    $stats['filename_aliases'] += max(0, count($urls)-1);
    fputcsv($out, [
        $filename,
        $urls[0] ?? '',
        count($urls),
        implode(' | ', $titles),
        implode(' | ', $types),
        implode(' | ', $labels),
        implode(' | ', $cats),
        'copy_once',
        'sha256_required',
    ]);
}
fclose($out);

echo "Unique document copy plan\n";
foreach ($stats as $k=>$v) echo str_pad($k, 20) . ': ' . $v . "\n";
echo "Output: {$output}\n";
echo "Nota: il raggruppamento per filename è solo un piano candidato; SHA-256 resta l'autorità finale per la deduplica fisica.\n";
