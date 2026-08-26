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
        $stmt = Database::connection()->prepare('SELECT id,company_name,contact_first_name,contact_last_name,email,phone,username,active,verified_at,last_login_at FROM cat_accounts WHERE id=? AND active=1 AND verified_at IS NOT NULL LIMIT 1');
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if(!$user){unset($_SESSION[self::SESSION_KEY]);return null;}
        return $user;
    }

    public static function attempt(string $login, string $password): bool
    {
        $login=trim($login);
        if($login==='' || $password==='')return false;
        $stmt = Database::connection()->prepare('SELECT * FROM cat_accounts WHERE active=1 AND verified_at IS NOT NULL AND (username=? OR email=?) LIMIT 1');
        $stmt->execute([$login,$login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !password_verify($password, (string)$user['password_hash'])) return false;
        $pdo=Database::connection();
        if(password_needs_rehash((string)$user['password_hash'],PASSWORD_DEFAULT)){
            $pdo->prepare('UPDATE cat_accounts SET password_hash=?,password_changed_at=COALESCE(password_changed_at,NOW()) WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),(int)$user['id']]);
        }
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = (int)$user['id'];
        $pdo->prepare('UPDATE cat_accounts SET last_login_at=NOW() WHERE id=?')->execute([(int)$user['id']]);
        return true;
    }

    public static function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
        unset($_SESSION['cat_return_to']);
        session_regenerate_id(true);
    }

    public static function requireLogin(): array
    {
        $user = self::user();
        if ($user) return $user;
        $returnTo=(string)($_SERVER['REQUEST_URI'] ?? '/campus/cat');
        if(!str_starts_with($returnTo,'/campus/cat'))$returnTo='/campus/cat';
        $_SESSION['cat_return_to'] = $returnTo;
        header('Location: /campus/cat/login');
        exit;
    }
}
