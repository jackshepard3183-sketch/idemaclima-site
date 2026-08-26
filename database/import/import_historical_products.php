<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/scripts/bootstrap.php';

use App\Core\Database;
use PDO;

$execute = in_array('--execute', $argv, true);
$registryPath = __DIR__ . '/historical_products_registry.json';
$raw = file_get_contents($registryPath);
if ($raw === false) {
    fwrite(STDERR, "Impossibile leggere historical_products_registry.json\n");
    exit(1);
}
$data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

$stats = ['categories'=>0,'products'=>0,'models'=>0,'skipped_categories'=>0,'skipped_products'=>0,'skipped_models'=>0];

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
    $existing = $findCategory($pdo, $category['slug']);
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
    $s->execute([$categoryId,$product['name'],$product['slug'],null,$product['role']??'other',$product['refrigerant']??null,$product['status']??'discontinued',0]);
    $stats['products']++;
    return (int)$pdo->lastInsertId();
};

$upsertModels = static function(PDO $pdo, int $productId, array $models) use (&$stats): void {
    $check = $pdo->prepare('SELECT id FROM product_models WHERE product_id=? AND code=? LIMIT 1');
    $insert = $pdo->prepare('INSERT INTO product_models(product_id,code,name,sort_order,published) VALUES(?,?,NULL,?,1)');
    foreach (array_values($models) as $i => $code) {
        $check->execute([$productId,$code]);
        if ($check->fetchColumn() !== false) {
            $stats['skipped_models']++;
            continue;
        }
        $insert->execute([$productId,$code,$i]);
        $stats['models']++;
    }
};

$walkCategory = function(array $category, ?int $forcedParentId = null) use (&$walkCategory,$execute,$pdo,&$stats,$findCategory,$upsertCategory,$upsertProduct,$upsertModels): void {
    if (!$execute) {
        $stats['categories']++;
        foreach ($category['products'] ?? [] as $p) {
            $stats['products']++;
            $stats['models'] += count($p['models'] ?? []);
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
    }
    foreach ($category['children'] ?? [] as $child) {
        $walkCategory($child, $categoryId);
    }
};

try {
    foreach ($data['categories'] ?? [] as $category) {
        $walkCategory($category);
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
foreach ($stats as $key => $value) {
    echo str_pad($key, 20) . ': ' . $value . "\n";
}
if (!$execute) {
    echo "Nessuna scrittura eseguita. Usa --execute solo dopo aver verificato il report.\n";
}
