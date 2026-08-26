<?php

declare(strict_types=1);

$sourcesPath = __DIR__ . '/historical_document_sources.json';
$outPath = __DIR__ . '/reports/historical-document-discovery-latest.json';

$raw = file_get_contents($sourcesPath);
if ($raw === false) throw new RuntimeException('File sorgenti non leggibile.');
$config = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

function fetchHtml(string $url): string
{
    if (!preg_match('#^https://www\.idemaclima\.it/#i', $url)) throw new RuntimeException('Host non consentito.');
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 4,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_USERAGENT => 'IDEMA-Inventory/1.1',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $html = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if (!is_string($html) || $status < 200 || $status >= 300) throw new RuntimeException('HTTP ' . $status . ($err ? ': ' . $err : ''));
        return $html;
    }
    $ctx = stream_context_create(['http'=>['timeout'=>45,'user_agent'=>'IDEMA-Inventory/1.1','follow_location'=>1,'max_redirects'=>4]]);
    $html = @file_get_contents($url, false, $ctx);
    if (!is_string($html)) throw new RuntimeException('Download HTML fallito.');
    return $html;
}

function absoluteUrl(string $href, string $base): ?string
{
    $href = html_entity_decode(trim($href), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if ($href === '' || str_starts_with($href, '#') || str_starts_with(strtolower($href), 'javascript:')) return null;
    if (preg_match('#^https://#i', $href)) return $href;
    if (str_starts_with($href, '//')) return 'https:' . $href;
    $parts = parse_url($base);
    if (!$parts || empty($parts['host'])) return null;
    $origin = 'https://' . $parts['host'];
    if (str_starts_with($href, '/')) return $origin . $href;
    $basePath = $parts['path'] ?? '/';
    $dir = rtrim(str_replace('\\','/',dirname($basePath)), '/');
    return $origin . ($dir ? $dir : '') . '/' . $href;
}

function normalizeIdemaUrl(string $url): string
{
    $url = preg_replace('#^https://idemaclima\.it/#i','https://www.idemaclima.it/',$url) ?? $url;
    $parts = parse_url($url);
    if (!$parts) return $url;
    $path = $parts['path'] ?? '/';
    $query = isset($parts['query']) ? '?' . $parts['query'] : '';
    return 'https://www.idemaclima.it' . $path . $query;
}

function isAllowedPage(string $url): bool
{
    $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?? ''));
    if ($host !== 'www.idemaclima.it' && $host !== 'idemaclima.it') return false;
    $path = (string)(parse_url($url, PHP_URL_PATH) ?? '/');
    return str_starts_with($path, '/schede-tecniche/') || $path === '/schede-tecniche/' || $path === '/cataloghi/' || $path === '/cataloghi';
}

$all = [];
$pageReports = [];
$queue = [];
$queued = [];
$maxDepth = 5;
$maxPages = 500;

foreach ($config['sources'] ?? [] as $source) {
    $url = normalizeIdemaUrl((string)$source['url']);
    if (!isset($queued[$url])) {
        $queue[] = ['url'=>$url,'label'=>(string)$source['label'],'depth'=>0];
        $queued[$url] = true;
    }
}

while ($queue !== [] && count($pageReports) < $maxPages) {
    $current = array_shift($queue);
    $pageUrl = $current['url'];
    $report = ['label'=>$current['label'],'url'=>$pageUrl,'depth'=>$current['depth'],'status'=>'ok','pdf_count'=>0,'child_pages'=>0];
    try {
        $html = fetchHtml($pageUrl);
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);
        foreach ($xpath->query('//a[@href]') ?: [] as $node) {
            $href = absoluteUrl((string)$node->getAttribute('href'), $pageUrl);
            if (!$href) continue;
            $href = normalizeIdemaUrl($href);
            $path = (string)(parse_url($href, PHP_URL_PATH) ?? '');
            $host = strtolower((string)(parse_url($href, PHP_URL_HOST) ?? ''));
            if ($host !== 'www.idemaclima.it' && $host !== 'idemaclima.it') continue;

            if (preg_match('/\.pdf$/i', $path)) {
                if (!isset($all[$href])) {
                    $all[$href] = [
                        'url'=>$href,
                        'filename'=>basename($path),
                        'found_on'=>[],
                        'anchor_text'=>[],
                    ];
                }
                $all[$href]['found_on'][] = $pageUrl;
                $text = trim(preg_replace('/\s+/u',' ',(string)$node->textContent) ?? '');
                if ($text !== '') $all[$href]['anchor_text'][] = $text;
                $report['pdf_count']++;
                continue;
            }

            if ($current['depth'] >= $maxDepth || !isAllowedPage($href)) continue;
            $childPath = (string)(parse_url($href, PHP_URL_PATH) ?? '');
            if (preg_match('#/(?:wp-admin|wp-login|feed|tag|author|page)/#i', $childPath)) continue;
            if (!str_ends_with($childPath, '/')) continue;
            if (!isset($queued[$href])) {
                $queue[] = ['url'=>$href,'label'=>'discovered','depth'=>$current['depth']+1];
                $queued[$href] = true;
                $report['child_pages']++;
            }
        }
    } catch (Throwable $e) {
        $report['status'] = 'error';
        $report['error'] = $e->getMessage();
    }
    $pageReports[] = $report;
}

ksort($all, SORT_NATURAL | SORT_FLAG_CASE);
foreach ($all as &$item) {
    $item['found_on'] = array_values(array_unique($item['found_on']));
    $item['anchor_text'] = array_values(array_unique($item['anchor_text']));
}
unset($item);

$result = [
    'generated_at'=>date(DATE_ATOM),
    'mode'=>'read-only-recursive-discovery',
    'max_depth'=>$maxDepth,
    'max_pages'=>$maxPages,
    'pages_crawled'=>count($pageReports),
    'unique_pdf_count'=>count($all),
    'pages'=>$pageReports,
    'documents'=>array_values($all),
];
file_put_contents($outPath, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

echo "Historical document discovery\n";
echo 'Pagine attraversate: ' . count($pageReports) . "\n";
echo 'PDF unici: ' . count($all) . "\n";
echo 'Report: ' . $outPath . "\n";
echo "Crawler confinato a /schede-tecniche/ e /cataloghi/, profondità massima {$maxDepth}, massimo {$maxPages} pagine. Nessun file remoto è stato modificato o copiato nello storage pubblico.\n";
