<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$options = getopt('', ['first-name:', 'last-name:', 'email:', 'username:', 'password::', 'role::']);
$required = ['first-name', 'last-name', 'email', 'username'];
foreach ($required as $key) {
    if (!isset($options[$key]) || trim((string) $options[$key]) === '') {
        fwrite(STDERR, "Uso: php scripts/create_admin.php --first-name=Nome --last-name=Cognome --email=mail@example.it --username=admin [--password=...] [--role=administrator|editor]\n");
        exit(2);
    }
}

$email = strtolower(trim((string) $options['email']));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Email non valida.\n");
    exit(2);
}

$role = (string) ($options['role'] ?? 'administrator');
if (!in_array($role, ['administrator', 'editor'], true)) {
    fwrite(STDERR, "Ruolo non valido.\n");
    exit(2);
}

$password = isset($options['password']) && is_string($options['password']) && $options['password'] !== ''
    ? $options['password']
    : bin2hex(random_bytes(12));

if (strlen($password) < 12) {
    fwrite(STDERR, "La password deve contenere almeno 12 caratteri.\n");
    exit(2);
}

$pdo = Database::connection();
$stmt = $pdo->prepare('INSERT INTO admin_users (first_name, last_name, email, username, password_hash, role, active) VALUES (?, ?, ?, ?, ?, ?, 1)');
try {
    $stmt->execute([
        trim((string) $options['first-name']),
        trim((string) $options['last-name']),
        $email,
        trim((string) $options['username']),
        password_hash($password, PASSWORD_DEFAULT),
        $role,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Impossibile creare l'utente: email o username potrebbero essere già presenti.\n");
    exit(1);
}

echo "Utente amministrativo creato.\nUsername: " . trim((string) $options['username']) . "\n";
if (!isset($options['password']) || $options['password'] === false || $options['password'] === '') {
    echo "Password temporanea: {$password}\nCambiala al primo utilizzo quando sarà disponibile la gestione profilo.\n";
}
