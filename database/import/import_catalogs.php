<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/scripts/bootstrap.php';

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

$execute = in_array('--execute', $argv, true);
$preflight = $execute || in_array('--preflight', $argv, true);
$registryPath = __DIR__ . '/catalogs_registry.csv';

$fh = fopen($registryPath, 'rb');
if (!$fh) {
    fwrite(STDERR, "Registry cataloghi non leggibile.\n");
    exit(1);
}
$header = fgetcsv($fh);
$required = ['slug','title','description','document_year','source_filename','sort_order','published'];
if (!$header || array_diff($required, $header)) {
    fwrite(STDERR, "Registry cataloghi: intestazione non valida.\n");
    exit(1);
}

$rows = [];
$line = 1;
while (($csv = fgetcsv($fh)) !== false) {
    $line++;
    if (count($csv) !== count($header)) {
        throw new RuntimeException("Riga {$line}: numero colonne non valido.");
    }
    $row = array_combine($header, $csv);
    if (!is_array($row)) throw new RuntimeException("Riga {$line}: impossibile leggere i dati.");
    $row['_line'] = $line;
    $rows[] = $row;
}
fclose($fh);

$seenSlugs = [];
$seenFiles = [];
$errors = [];
foreach ($rows as $row) {
    $line = (int)$row['_line'];
    $slug = trim((string)$row['slug']);
    $title = trim((string)$row['title']);
    $filename = trim((string)$row['source_filename']);
    $year = (int)$row['document_year'];
    $published = (int)$row['published'];
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) $errors[] = "Riga {$line}: slug non valido.";
    if ($title === '' || mb_strlen($title) > 220) $errors[] = "Riga {$line}: titolo non valido.";
    if (!preg_match('/\.pdf$/i', $filename) || basename($filename) !== $filename) $errors[] = "Riga {$line}: source_filename non valido.";
    if ($year < 1990 || $year > ((int)date('Y') + 1)) $errors[] = "Riga {$line}: anno non valido.";
    if (!in_array($published, [0,1], true)) $errors[] = "Riga {$line}: published deve essere 0 o 1.";
    if (isset($seenSlugs[strtolower($slug)])) $errors[] = "Riga {$line}: slug duplicato {$slug}.";
    if (isset($seenFiles[strtolower($filename)])) $errors[] = "Riga {$line}: PDF duplicato {$filename}.";
    $seenSlugs[strtolower($slug)] = true;
    $seenFiles[strtolower($filename)] = true;
}
if ($errors) {
    foreach ($errors as $error) fwrite(STDERR, $error . "\n");
    exit(1);
}

$stats = ['registry'=>count($rows),'resolved_documents'=>0,'unresolved_documents'=>0,'inserted'=>0,'updated'=>0,'unchanged'=>0];
$resolved = [];
$pdo = null;

$resolveDocument = static function(PDO $pdo, string $filename): ?array {
    $candidates = [];
    $stmt = $pdo->prepare(
        "SELECT id,filename,file_path FROM documents
         WHERE LOWER(filename)=LOWER(?) OR LOWER(SUBSTRING_INDEX(file_path,'/',-1))=LOWER(?)"
    );
    $stmt->execute([$filename,$filename]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $candidates[(int)$row['id']] = $row;

    $alias = $pdo->prepare(
        "SELECT d.id,d.filename,d.file_path
         FROM document_source_aliases a
         JOIN documents d ON d.id=a.document_id
         WHERE LOWER(SUBSTRING_INDEX(a.source_url,'/',-1))=LOWER(?)"
    );
    $alias->execute([$filename]);
    foreach ($alias->fetchAll(PDO::FETCH_ASSOC) as $row) $candidates[(int)$row['id']] = $row;

    if (count($candidates) > 1) {
        throw new RuntimeException('Più documenti canonici corrispondono a ' . $filename . '.');
    }
    return $candidates ? array_values($candidates)[0] : null;
};

try {
    if ($preflight) {
        $pdo = Database::connection();
        foreach ($rows as $i => $row) {
            $doc = $resolveDocument($pdo, trim((string)$row['source_filename']));
            $resolved[$i] = $doc;
            if ($doc) $stats['resolved_documents']++;
            else $stats['unresolved_documents']++;
        }
        if ($stats['unresolved_documents'] > 0) {
            foreach ($rows as $i => $row) {
                if (!isset($resolved[$i]) || $resolved[$i] === null) {
                    fwrite(STDERR, 'Documento non ancora migrato: ' . $row['source_filename'] . "\n");
                }
            }
            if ($execute) throw new RuntimeException('Import cataloghi bloccato: prima migra tutti i documenti del registry.');
        }
    }

    if ($execute && $pdo instanceof PDO) {
        $pdo->beginTransaction();
        $find = $pdo->prepare('SELECT id,document_id,title,description,document_year,published,sort_order FROM catalogs WHERE slug=? LIMIT 1');
        $insert = $pdo->prepare(
            'INSERT INTO catalogs(title,slug,description,cover_image,document_id,pdf_path,document_year,published,sort_order)
             VALUES(?,?,?,NULL,?,NULL,?,?,?)'
        );
        $update = $pdo->prepare(
            'UPDATE catalogs SET title=?,description=?,document_id=?,document_year=?,published=?,sort_order=? WHERE id=?'
        );

        foreach ($rows as $i => $row) {
            $doc = $resolved[$i] ?? null;
            if (!$doc) throw new RuntimeException('Documento non risolto: ' . $row['source_filename']);
            $slug = trim((string)$row['slug']);
            $title = trim((string)$row['title']);
            $description = trim((string)$row['description']) ?: null;
            $year = (int)$row['document_year'];
            $published = (int)$row['published'];
            $sort = (int)$row['sort_order'];
            $documentId = (int)$doc['id'];

            $find->execute([$slug]);
            $existing = $find->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                $insert->execute([$title,$slug,$description,$documentId,$year,$published,$sort]);
                $stats['inserted']++;
                continue;
            }
            if (!empty($existing['document_id']) && (int)$existing['document_id'] !== $documentId) {
                throw new RuntimeException('Conflitto catalogo ' . $slug . ': già collegato a un altro documento.');
            }
            $same = (string)$existing['title'] === $title
                && (string)($existing['description'] ?? '') === (string)($description ?? '')
                && (int)($existing['document_id'] ?? 0) === $documentId
                && (int)($existing['document_year'] ?? 0) === $year
                && (int)$existing['published'] === $published
                && (int)$existing['sort_order'] === $sort;
            if ($same) {
                $stats['unchanged']++;
                continue;
            }
            $update->execute([$title,$description,$documentId,$year,$published,$sort,(int)$existing['id']]);
            $stats['updated']++;
        }
        $pdo->commit();
    }
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, 'Errore: ' . $e->getMessage() . "\n");
    exit(1);
}

$mode = $execute ? 'EXECUTE' : ($preflight ? 'PREFLIGHT' : 'DRY-RUN');
echo $mode . " catalog import\n";
foreach ($stats as $key => $value) echo str_pad($key, 24) . ': ' . $value . "\n";
if (!$preflight) echo "Controllo strutturale soltanto. Usa --preflight per verificare i documenti nel DB.\n";
if ($preflight && !$execute) echo "Nessuna scrittura eseguita. Usa --execute solo quando unresolved_documents = 0.\n";
