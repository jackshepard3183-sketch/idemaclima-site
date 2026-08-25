<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$pdo = Database::connection();
$root = dirname(__DIR__);
$dir = $root . '/database/migrations';
$files = glob($dir . '/*.sql') ?: [];
sort($files, SORT_NATURAL);

$pdo->exec(file_get_contents($dir . '/000_migrations_table.sql'));
$existing = $pdo->query('SELECT migration, checksum FROM schema_migrations')->fetchAll(PDO::FETCH_KEY_PAIR);

$applied = 0;
foreach ($files as $file) {
    $name = basename($file);
    if ($name === '000_migrations_table.sql') {
        continue;
    }
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException("Impossibile leggere {$name}");
    }
    $checksum = hash('sha256', $sql);
    if (isset($existing[$name])) {
        if (!hash_equals((string) $existing[$name], $checksum)) {
            fwrite(STDERR, "ERRORE: la migration già eseguita {$name} è stata modificata.\n");
            exit(2);
        }
        echo "SKIP  {$name}\n";
        continue;
    }

    echo "RUN   {$name}\n";
    $pdo->beginTransaction();
    try {
        $pdo->exec($sql);
        $stmt = $pdo->prepare('INSERT INTO schema_migrations (migration, checksum) VALUES (?, ?)');
        $stmt->execute([$name, $checksum]);
        $pdo->commit();
        $applied++;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        fwrite(STDERR, "FAIL  {$name}: {$e->getMessage()}\n");
        exit(1);
    }
}

echo "Completato. Migration applicate: {$applied}\n";
