<?php

declare(strict_types=1);

namespace App\Auth;

use App\Core\Database;
use PDO;

final class AdminAuth
{
    private const SESSION_KEY = 'admin_user_id';

    public static function attempt(string $login, string $password): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT id, password_hash, active FROM admin_users WHERE email = :login OR username = :login LIMIT 1'
        );
        $stmt->execute(['login' => trim($login)]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !(bool) $user['active'] || !password_verify($password, (string) $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = (int) $user['id'];
        $pdo->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?')->execute([(int) $user['id']]);
        return true;
    }

    public static function check(): bool
    {
        return isset($_SESSION[self::SESSION_KEY]) && is_int($_SESSION[self::SESSION_KEY]);
    }

    public static function id(): ?int
    {
        return self::check() ? $_SESSION[self::SESSION_KEY] : null;
    }

    public static function user(): ?array
    {
        $id = self::id();
        if ($id === null) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT id, first_name, last_name, email, username, role, active, last_login_at FROM admin_users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !(bool) $user['active']) {
            self::logout();
            return null;
        }
        return $user;
    }

    public static function requireLogin(): void
    {
        if (self::user() === null) {
            header('Location: /admin/login', true, 302);
            exit;
        }
    }

    public static function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
        session_regenerate_id(true);
    }
}
