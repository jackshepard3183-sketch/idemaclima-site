<?php

declare(strict_types=1);

function extractDatasheetsObject(string $source): array
{
    $markerPos = strpos($source, 'datasheetsByCategory');
    if ($markerPos === false) {
        throw new RuntimeException('Variabile datasheetsByCategory non trovata.');
    }

    $equalsPos = strpos($source, '=', $markerPos);
    if ($equalsPos === false) {
        throw new RuntimeException('Assegnazione datasheetsByCategory non trovata.');
    }

    $start = strpos($source, '{', $equalsPos);
    if ($start === false) {
        throw new RuntimeException('Oggetto JSON iniziale non trovato.');
    }

    $depth = 0;
    $inString = false;
    $escaped = false;
    $end = null;
    $length = strlen($source);

    for ($i = $start; $i < $length; $i++) {
        $char = $source[$i];
        if ($inString) {
            if ($escaped) { $escaped = false; continue; }
            if ($char === '\\') { $escaped = true; continue; }
            if ($char === '"') { $inString = false; }
            continue;
        }
        if ($char === '"') { $inString = true; continue; }
        if ($char === '{') {
            $depth++;
        } elseif ($char === '}') {
            $depth--;
            if ($depth === 0) { $end = $i; break; }
        }
    }

    if ($end === null) {
        throw new RuntimeException('Fine oggetto datasheetsByCategory non trovata.');
    }

    $json = substr($source, $start, $end - $start + 1);
    $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException('Il payload decodificato non è un oggetto valido.');
    }
    return $decoded;
}

function ensureCategory(PDO $pdo, ?int $parentId, string $name, string $slug): int
{
    $stmt = $pdo->prepare('SELECT id FROM product_categories WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $id = $stmt->fetchColumn();
    if ($id !== false) return (int) $id;
    $stmt = $pdo->prepare('INSERT INTO product_categories (parent_id, name, slug) VALUES (?, ?, ?)');
    $stmt->execute([$parentId, $name, $slug]);
    return (int) $pdo->lastInsertId();
}

function ensureProduct(PDO $pdo, int $categoryId, string $name, string $slug, string $description, string $role, ?string $refrigerant, string $status, ?string $image, int $sortOrder): int
{
    $stmt = $pdo->prepare('SELECT id FROM products WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $id = $stmt->fetchColumn();
    if ($id !== false) return (int) $id;
    $stmt = $pdo->prepare('INSERT INTO products (category_id, name, slug, description, product_role, refrigerant, status, image_path, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$categoryId, $name, $slug, $description ?: null, $role, $refrigerant, $status, $image, $sortOrder]);
    return (int) $pdo->lastInsertId();
}

function ensureModel(PDO $pdo, int $productId, string $code, int $sortOrder): int
{
    $stmt = $pdo->prepare('SELECT id FROM product_models WHERE product_id = ? AND code = ? LIMIT 1');
    $stmt->execute([$productId, $code]);
    $id = $stmt->fetchColumn();
    if ($id !== false) return (int) $id;
    $stmt = $pdo->prepare('INSERT INTO product_models (product_id, code, name, sort_order) VALUES (?, ?, ?, ?)');
    $stmt->execute([$productId, $code, $code, $sortOrder]);
    return (int) $pdo->lastInsertId();
}

function ensureDocumentType(PDO $pdo, string $name): int
{
    $slug = slugify($name);
    $stmt = $pdo->prepare('SELECT id FROM document_types WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $id = $stmt->fetchColumn();
    if ($id !== false) return (int) $id;
    $stmt = $pdo->prepare('INSERT INTO document_types (name, slug) VALUES (?, ?)');
    $stmt->execute([$name, $slug]);
    return (int) $pdo->lastInsertId();
}

function ensureDocument(PDO $pdo, int $typeId, string $title, string $filename, string $filePath, int $sortOrder): int
{
    $stmt = $pdo->prepare('SELECT id FROM documents WHERE file_path = ? LIMIT 1');
    $stmt->execute([$filePath]);
    $id = $stmt->fetchColumn();
    if ($id !== false) return (int) $id;
    $stmt = $pdo->prepare('INSERT INTO documents (document_type_id, title, filename, file_path, sort_order) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$typeId, $title, $filename, $filePath, $sortOrder]);
    return (int) $pdo->lastInsertId();
}

function ensureDocumentLink(PDO $pdo, int $documentId, ?int $categoryId, ?int $productId, ?int $modelId): void
{
    $stmt = $pdo->prepare('SELECT id FROM document_links WHERE document_id = ? AND category_id <=> ? AND product_id <=> ? AND model_id <=> ? LIMIT 1');
    $stmt->execute([$documentId, $categoryId, $productId, $modelId]);
    if ($stmt->fetchColumn() !== false) return;
    $stmt = $pdo->prepare('INSERT INTO document_links (document_id, category_id, product_id, model_id) VALUES (?, ?, ?, ?)');
    $stmt->execute([$documentId, $categoryId, $productId, $modelId]);
}

function classifyDocumentType(string $group, string $label): string
{
    $g = strtoupper(normalizeWhitespace($group));
    $l = strtoupper(normalizeWhitespace($label));
    if (str_contains($g, 'MANUAL')) {
        if (str_contains($l, 'INSTALL')) return 'Manuale installazione';
        if (preg_match('/\b(USO|USER)\b/u', $l)) return 'Manuale uso';
        if (str_contains($l, 'TELECOMANDO') || str_contains($l, 'COMANDO REMOTO')) return 'Manuale telecomando';
        if (str_contains($l, 'WI-FI') || str_contains($l, 'WIFI')) return 'Manuale Wi-Fi';
        return 'Manuale';
    }
    if (str_contains($g, 'SCHED')) return 'Scheda tecnica';
    if (str_contains($g, 'RESE')) return 'Tabella rese';
    if (str_contains($g, 'DETRAZ')) return 'Detrazioni fiscali';
    if (str_contains($g, 'CONTO TERMICO')) return 'Conto termico';
    if (str_contains($g, 'CONTRIBUTO GSE') || str_contains($g, 'GSE')) return 'Contributo GSE';
    if (str_contains($g, 'CE') || str_contains($g, 'DICHIARAZ')) return 'Dichiarazione CE';
    if (str_contains($g, 'CERTIFIC')) return 'Certificazione';
    return ucwords(lowerText($group));
}

function shouldCreateModel(string $label, string $productName, string $group): bool
{
    $label = trim($label);
    $groupUpper = strtoupper(normalizeWhitespace($group));

    // I modelli vengono derivati solo dai gruppi tecnici, mai da manuali/documenti generici.
    if (!str_contains($groupUpper, 'SCHED') && !str_contains($groupUpper, 'RESE')) return false;
    if ($label === '' || strcasecmp($label, $productName) === 0 || isGenericDocumentLabel($label)) return false;
    if (str_contains($label, '+')) return false;
    if (!preg_match('/\d/u', $label)) return false;
    if (preg_match('/\s{2,}/u', $label) || textLength($label) > 80) return false;

    return (bool) preg_match('/^[A-Z0-9][A-Z0-9()._\-\/ ]*$/iu', $label);
}

function isGenericDocumentLabel(string $label): bool
{
    $u = strtoupper(normalizeWhitespace($label));
    foreach ([
        'INSTALLAZIONE', 'INSTALLATION', 'USO', 'USER', 'TELECOMANDO', 'COMANDO REMOTO',
        'MODULO WI-FI', 'MODULO WIFI', 'WI-FI', 'WIFI', 'MANUALE', 'CONFIG'
    ] as $generic) {
        if ($u === $generic || str_starts_with($u, $generic . ' (')) return true;
    }
    return false;
}

function inferProductRole(string $subcategoryName, string $productName): string
{
    $text = strtoupper(normalizeWhitespace($subcategoryName . ' ' . $productName));
    if (str_contains($text, 'ACCESSOR')) return 'accessory';
    if (str_contains($text, 'SERBATOIO') || str_contains($text, 'TANK')) return 'tank';
    if (str_contains($text, 'UNITÀ INTERNA') || str_contains($text, 'UNITA INTERNA') || str_contains($text, 'UNITÀ INTERNE') || str_contains($text, 'UNITA INTERNE')) return 'indoor_unit';
    if (str_contains($text, 'UNITÀ ESTERNA') || str_contains($text, 'UNITA ESTERNA') || str_contains($text, 'UNITÀ ESTERNE') || str_contains($text, 'UNITA ESTERNE')) return 'outdoor_unit';
    if (str_contains($text, 'CONTROLLER') || str_contains($text, 'COMANDO')) return 'controller';
    return 'complete_system';
}

function inferRefrigerant(string $text): ?string
{
    if (preg_match('/\bR(290|32|410A|134A|407C)\b/i', $text, $m)) return 'R' . strtoupper($m[1]);
    return null;
}

function buildDocumentTitle(string $documentType, string $productName, string $label): string
{
    if ($label === '' || strcasecmp($label, $productName) === 0) return $documentType . ' - ' . $productName;
    return $documentType . ' - ' . $label;
}

function filenameFromUrl(string $url): string
{
    $path = parse_url($url, PHP_URL_PATH);
    $filename = basename(is_string($path) ? $path : $url);
    return rawurldecode($filename !== '' ? $filename : 'documento.pdf');
}

function slugify(string $value): string
{
    $value = trim(lowerText($value));
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($ascii !== false) $value = $ascii;
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;
    return trim($value, '-') ?: 'item';
}

function uniqueScopedSlug(string $scope, string $slug): string { return slugify($scope) . '--' . slugify($slug); }
function humanizeSlug(string $slug): string { return ucwords(str_replace('-', ' ', $slug)); }
function lowerText(string $value): string { return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value); }
function textLength(string $value): int { return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value); }
function normalizeWhitespace(string $value): string { return trim(preg_replace('/\s+/u', ' ', $value) ?? $value); }
function fail(string $message): never { fwrite(STDERR, "ERRORE: {$message}\n"); exit(1); }
