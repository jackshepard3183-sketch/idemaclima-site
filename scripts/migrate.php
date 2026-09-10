<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;

if (PHP_SAPI !== 'cli' && (!defined('IDEMA_INTERNAL_MIGRATION_RUN') || IDEMA_INTERNAL_MIGRATION_RUN !== true)) {
    http_response_code(404);
    exit;
}

$writeError = static function (string $message): void {
    if (PHP_SAPI === 'cli' && defined('STDERR')) {
        fwrite(STDERR, $message);
        return;
    }

    echo $message;
};

$execute = in_array('--execute', $argv, true);
$statusOnly = in_array('--status', $argv, true) || !$execute;
$acceptLegacyBaseline = defined('IDEMA_INTERNAL_MIGRATION_RUN')
    && IDEMA_INTERNAL_MIGRATION_RUN === true
    && in_array('--accept-legacy-baseline', $argv, true);
$pdo = Database::connection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$root = dirname(__DIR__);
$dir = $root . '/database/migrations';
$files = glob($dir . '/*.sql') ?: [];
sort($files, SORT_NATURAL);

if ($files === [] || basename($files[0]) !== '000_migrations_table.sql') {
    $writeError("Migration bootstrap 000_migrations_table.sql mancante o fuori ordine.\n");
    exit(1);
}

$bootstrapSql = file_get_contents($files[0]);
if (!is_string($bootstrapSql) || trim($bootstrapSql) === '') {
    $writeError("Migration bootstrap non leggibile.\n");
    exit(1);
}

$tableCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='schema_migrations'");
$tableCheck->execute();
$trackingExists = (int)$tableCheck->fetchColumn() === 1;

if ($execute && !$trackingExists) {
    $pdo->exec($bootstrapSql);
    $trackingExists = true;
}

$existing = [];
if ($trackingExists) {
    $rows = $pdo->query('SELECT migration,checksum,executed_at FROM schema_migrations ORDER BY migration')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) $existing[(string)$row['migration']] = $row;
}

$pending = [];
$checksumErrors = [];
foreach ($files as $file) {
    $name = basename($file);
    if ($name === '000_migrations_table.sql') continue;
    $sql = file_get_contents($file);
    if (!is_string($sql)) throw new RuntimeException("Impossibile leggere {$name}");
    $checksum = hash('sha256', $sql);
    if (isset($existing[$name])) {
        if (!hash_equals((string)$existing[$name]['checksum'], $checksum)) $checksumErrors[] = $name;
        continue;
    }
    $pending[] = ['name'=>$name,'sql'=>$sql,'checksum'=>$checksum];
}

if ($acceptLegacyBaseline && $checksumErrors !== []) {
    $legacyChecksumErrors = array_values(array_filter($checksumErrors, static function (string $name): bool {
        return preg_match('/^(\d{3})_/', $name, $match) === 1 && (int)$match[1] <= 22;
    }));
    $checksumErrors = array_values(array_diff($checksumErrors, $legacyChecksumErrors));
    if ($legacyChecksumErrors !== []) {
        echo "Baseline legacy 001-022 già applicato: checksum storici conservati.\n";
    }
}

if ($checksumErrors !== []) {
    $writeError("ERRORE: migration già applicate sono state modificate:\n - " . implode("\n - ", $checksumErrors) . "\n");
    exit(2);
}

echo "IDEMA migration status\n";
echo 'Tracking : ' . ($trackingExists ? 'presente' : 'non inizializzato') . "\n";
echo 'Applicate: ' . count($existing) . "\n";
echo 'Pendenti : ' . count($pending) . "\n";
foreach ($pending as $item) echo '  - ' . $item['name'] . "\n";

if ($statusOnly) {
    echo "Nessuna scrittura eseguita. Usa --execute per inizializzare/applicare le migration pendenti.\n";
    exit(0);
}

$lockName = 'idemaclima_schema_migrations';
$lock = $pdo->prepare('SELECT GET_LOCK(?,10)');
$lock->execute([$lockName]);
if ((int)$lock->fetchColumn() !== 1) {
    $writeError("Impossibile ottenere il lock esclusivo delle migration.\n");
    exit(3);
}

$applied = 0;
try {
    $insert = $pdo->prepare('INSERT INTO schema_migrations(migration,checksum) VALUES(?,?)');
    foreach ($pending as $item) {
        echo 'RUN   ' . $item['name'] . "\n";
        try {
            $pdo->exec($item['sql']);
            $insert->execute([$item['name'],$item['checksum']]);
            $applied++;
            echo "OK    {$item['name']}\n";
        } catch (Throwable $e) {
            $writeError('FAIL  ' . $item['name'] . ': ' . $e->getMessage() . "\n");
            $writeError("Nota: MySQL/MariaDB esegue implicit commit per molte istruzioni DDL; correggere la migration prima di ripetere.\n");
            exit(4);
        }
    }
} finally {
    try {
        $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    } catch (Throwable) {
    }
}

echo "Completato. Migration applicate: {$applied}\n";
