<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/scripts/bootstrap.php';

use App\Core\Database;
use PDO;

$execute = in_array('--execute', $argv, true);
$registryPath = __DIR__ . '/historical_products_registry.json';

foreach ($argv as $arg) {
    if (!str_starts_with($arg, '--registry=')) continue;

    $value = trim(substr($arg, strlen('--registry=')));
    if ($value === '' || str_contains($value, "\0")) {
        fwrite(STDERR, "Valore --registry non valido.\n");
        exit(1);
    }

    $candidate = str_starts_with($value, DIRECTORY_SEPARATOR)
        ? $value
        : __DIR__ . DIRECTORY_SEPARATOR . ltrim($value, '/\\');

    $real = realpath($candidate);
    if ($real === false || !is_file($real)) {
        fwrite(STDERR, "Registry non trovato: {$value}\n");
        exit(1);
    }

    if (!str_starts_with($value, DIRECTORY_SEPARATOR)) {
        $importRoot = realpath(__DIR__);
        if ($importRoot === false || !str_starts_with($real, $importRoot . DIRECTORY_SEPARATOR)) {
            fwrite(STDERR, "Registry relativo fuori dalla cartella import non consentito.\n");
            exit(1);
        }
    }
    $registryPath = $real;
}

$raw = file_get_contents($registryPath);
if ($raw === false) {
    fwrite(STDERR, 'Impossibile leggere ' . basename($registryPath) . "\n");
    exit(1);
}
$data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

$stats = [
    'categories'=>0,
    'products'=>0,
    'models'=>0,
    'secondary_links'=>0,
    'skipped_categories'=>0,
    'skipped_products'=>0,
    'skipped_models'=>0,
    'skipped_secondary_links'=>0,
];
$secondaryRequests = [];

$pdo = null;
if ($execute) {
    $pdo = Database::connection();
    $pdo->beginTransaction();
}

$findCategory = static function(PDO $pdo, string $slug): ?int {
    $s = $pdo->prepare('SELECT id FROM product_categories WHERE slug=? LIMIT 1');
    $s->execute([$slug]);
    $id = $s->fetchColumn();
    return $id === false ? null : (int)$id;
};

$upsertCategory = static function(PDO $pdo, ?int $parentId, array $category) use (&$stats, $findCategory): int {
    $existing = $findCategory($pdo, (string)$category['slug']);
    if ($existing !== null) {
        $stats['skipped_categories']++;
        return $existing;
    }
    $s = $pdo->prepare('INSERT INTO product_categories(parent_id,name,slug,sort_order,published) VALUES(?,?,?,?,1)');
    $s->execute([$parentId,$category['name'],$category['slug'],(int)($category['sort_order']??0)]);
    $stats['categories']++;
    return (int)$pdo->lastInsertId();
};

$upsertProduct = static function(PDO $pdo, int $categoryId, array $product) use (&$stats): int {
    $s = $pdo->prepare('SELECT id FROM products WHERE slug=? LIMIT 1');
    $s->execute([$product['slug']]);
    $existing = $s->fetchColumn();
    if ($existing !== false) {
        $stats['skipped_products']++;
        return (int)$existing;
    }
    $s = $pdo->prepare('INSERT INTO products(category_id,name,slug,description,product_role,refrigerant,status,sort_order,published) VALUES(?,?,?,?,?,?,?,?,1)');
    $s->execute([
        $categoryId,
        $product['name'],
        $product['slug'],
        $product['description'] ?? null,
        $product['role']??'other',
        $product['refrigerant']??null,
        $product['status']??'discontinued',
        (int)($product['sort_order']??0),
    ]);
    $stats['products']++;
    return (int)$pdo->lastInsertId();
};

$upsertModels = static function(PDO $pdo, int $productId, array $models) use (&$stats): void {
    $checkScoped = $pdo->prepare('SELECT id FROM product_models WHERE product_id=? AND LOWER(code)=LOWER(?) LIMIT 1');
    $checkGlobal = $pdo->prepare('SELECT pm.id,pm.product_id,p.name product_name FROM product_models pm JOIN products p ON p.id=pm.product_id WHERE LOWER(pm.code)=LOWER(?) LIMIT 1');
    $insert = $pdo->prepare('INSERT INTO product_models(product_id,code,name,sort_order,published) VALUES(?,?,NULL,?,1)');
    foreach (array_values($models) as $i => $code) {
        $code = trim((string)$code);
        if ($code === '') throw new RuntimeException('Codice modello vuoto.');
        $checkScoped->execute([$productId,$code]);
        if ($checkScoped->fetchColumn() !== false) {
            $stats['skipped_models']++;
            continue;
        }
        $checkGlobal->execute([$code]);
        $global = $checkGlobal->fetch(PDO::FETCH_ASSOC);
        if ($global && (int)$global['product_id'] !== $productId) {
            throw new RuntimeException('Conflitto modello: ' . $code . ' esiste già nel prodotto ' . $global['product_name'] . '. Verifica prima di importare lo storico.');
        }
        $insert->execute([$productId,$code,$i]);
        $stats['models']++;
    }
};

$walkCategory = function(array $category, ?int $forcedParentId = null) use (&$walkCategory,$execute,$pdo,&$stats,&$secondaryRequests,$findCategory,$upsertCategory,$upsertProduct,$upsertModels): void {
    if (!$execute) {
        $stats['categories']++;
        foreach ($category['products'] ?? [] as $p) {
            $stats['products']++;
            $stats['models'] += count($p['models'] ?? []);
            $secondary = array_values(array_unique(array_filter(array_map('trim', $p['also_category_slugs'] ?? []))));
            $stats['secondary_links'] += count($secondary);
        }
        foreach ($category['children'] ?? [] as $child) {
            $walkCategory($child, -1);
        }
        return;
    }

    $parentId = $forcedParentId;
    if ($parentId === null && !empty($category['parent_slug'])) {
        $parentId = $findCategory($pdo, (string)$category['parent_slug']);
        if ($parentId === null) {
            throw new RuntimeException('Categoria padre non trovata: ' . $category['parent_slug']);
        }
    }

    $categoryId = $upsertCategory($pdo, $parentId, $category);
    foreach ($category['products'] ?? [] as $product) {
        $productId = $upsertProduct($pdo, $categoryId, $product);
        $upsertModels($pdo, $productId, $product['models'] ?? []);
        foreach (array_values(array_unique(array_filter(array_map('trim', $product['also_category_slugs'] ?? [])))) as $secondarySlug) {
            $secondaryRequests[] = [
                'product_id' => $productId,
                'product_slug' => (string)$product['slug'],
                'primary_category_id' => $categoryId,
                'category_slug' => $secondarySlug,
            ];
        }
    }
    foreach ($category['children'] ?? [] as $child) {
        $walkCategory($child, $categoryId);
    }
};

try {
    foreach ($data['categories'] ?? [] as $category) {
        $walkCategory($category);
    }

    if ($execute && $pdo instanceof PDO) {
        $checkLink = $pdo->prepare('SELECT id FROM product_category_links WHERE product_id=? AND category_id=? LIMIT 1');
        $insertLink = $pdo->prepare('INSERT INTO product_category_links(product_id,category_id) VALUES(?,?)');
        foreach ($secondaryRequests as $request) {
            $categoryId = $findCategory($pdo, (string)$request['category_slug']);
            if ($categoryId === null) {
                throw new RuntimeException('Categoria secondaria non trovata per ' . $request['product_slug'] . ': ' . $request['category_slug']);
            }
            if ($categoryId === (int)$request['primary_category_id']) {
                throw new RuntimeException('Categoria secondaria uguale alla primaria per ' . $request['product_slug'] . '.');
            }
            $checkLink->execute([(int)$request['product_id'],$categoryId]);
            if ($checkLink->fetchColumn() !== false) {
                $stats['skipped_secondary_links']++;
                continue;
            }
            $insertLink->execute([(int)$request['product_id'],$categoryId]);
            $stats['secondary_links']++;
        }
    }

    if ($execute && $pdo?->inTransaction()) {
        $pdo->commit();
    }
} catch (Throwable $e) {
    if ($execute && $pdo?->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Errore: ' . $e->getMessage() . "\n");
    exit(1);
}

echo ($execute ? 'EXECUTE' : 'DRY-RUN') . " historical products\n";
echo 'registry             : ' . basename($registryPath) . "\n";
foreach ($stats as $key => $value) {
    echo str_pad($key, 24) . ': ' . $value . "\n";
}
if (!$execute) {
    echo "Nessuna scrittura eseguita. Usa --execute solo dopo aver verificato il report.\n";
}
