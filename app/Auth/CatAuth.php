<?php

declare(strict_types=1);

namespace App\Auth;

use App\Core\Database;
use PDO;

final class CatAuth
{
    private const SESSION_KEY = 'cat_user_id';

    public static function user(): ?array
    {
        $id = (int)($_SESSION[self::SESSION_KEY] ?? 0);
        if ($id < 1) return null;
        $stmt = Database::connection()->prepare('SELECT id,company_name,contact_first_name,contact_last_name,email,phone,username,active,verified_at,last_login_at FROM cat_accounts WHERE id=? AND active=1 LIMIT 1');
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    public static function attempt(string $login, string $password): bool
    {
        $stmt = Database::connection()->prepare('SELECT * FROM cat_accounts WHERE active=1 AND (username=? OR email=?) LIMIT 1');
        $stmt->execute([$login,$login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !password_verify($password, (string)$user['password_hash'])) return false;
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = (int)$user['id'];
        Database::connection()->prepare('UPDATE cat_accounts SET last_login_at=NOW() WHERE id=?')->execute([(int)$user['id']]);
        return true;
    }

    public static function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
        session_regenerate_id(true);
    }

    public static function requireLogin(): array
    {
        $user = self::user();
        if ($user) return $user;
        $_SESSION['cat_return_to'] = $_SERVER['REQUEST_URI'] ?? '/campus/cat';
        header('Location: /campus/cat/login');
        exit;
    }
}
