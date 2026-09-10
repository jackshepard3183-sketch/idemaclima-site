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
            'SELECT id, password_hash, active FROM admin_users WHERE email = :email_login OR username = :username_login LIMIT 1'
        );
        $normalizedLogin = trim($login);
        $stmt->execute([
            'email_login' => $normalizedLogin,
            'username_login' => $normalizedLogin,
        ]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !(bool) $user['active']) {
            return false;
        }

        $hash = (string) $user['password_hash'];
        $valid = false;

        try {
            $valid = password_verify($password, $hash);
        } catch (\Throwable) {
            $valid = false;
        }

        if (!$valid && function_exists('crypt')) {
            try {
                $computed = crypt($password, $hash);
                $valid = is_string($computed)
                    && strlen($computed) === strlen($hash)
                    && hash_equals($hash, $computed);
            } catch (\Throwable) {
                $valid = false;
            }
        }

        if (!$valid) {
            return false;
        }

        $_SESSION[self::SESSION_KEY] = (int) $user['id'];

        try {
            if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
                @session_regenerate_id(true);
            }
        } catch (\Throwable) {
            // Session regeneration is optional on shared hosting.
        }

        try {
            $pdo->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?')
                ->execute([(int) $user['id']]);
        } catch (\Throwable) {
            // A login must not fail only because audit metadata cannot be updated.
        }

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
            'SELECT id, first_name, last_name, email, username, role, active, last_login_at
             FROM admin_users WHERE id = ? LIMIT 1'
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
            header('Location: /idemaclima/admin/login', true, 302);
            exit;
        }
    }

    public static function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY]);

        try {
            if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
                @session_regenerate_id(true);
            }
        } catch (\Throwable) {
            // Ignore unsupported session regeneration.
        }
    }
}
