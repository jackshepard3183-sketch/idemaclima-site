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

    public static function verifyPassword(int $userId, string $password): bool
    {
        if ($userId < 1 || $password === '') {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'SELECT password_hash, active FROM admin_users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !(bool) $user['active']) {
            return false;
        }

        $hash = (string) $user['password_hash'];
        try {
            if (password_verify($password, $hash)) {
                return true;
            }
        } catch (\Throwable) {
            return false;
        }

        if (function_exists('crypt')) {
            try {
                $computed = crypt($password, $hash);
                return is_string($computed)
                    && strlen($computed) === strlen($hash)
                    && hash_equals($hash, $computed);
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
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

    public static function requireManager(): array
    {
        $user = self::user();
        if ($user === null) { header('Location: /idemaclima/admin/login', true, 302); exit; }
        if (!in_array(strtolower((string)($user['role'] ?? '')), ['admin','administrator'], true) && !self::hasPermission((string)$user['role'], 'users')) {
            http_response_code(403); exit('Non disponi dei permessi per gestire utenti e accessi.');
        }
        return $user;
    }

    public static function hasPermission(string $role, string $section): bool
    {
        $role=strtolower(trim($role));if(in_array($role,['admin','administrator'],true))return true;
        try{$s=Database::connection()->prepare('SELECT COUNT(*) FROM admin_role_permissions WHERE role_slug=? AND section_key=?');$s->execute([$role,$section]);return (bool)$s->fetchColumn();}
        catch(\Throwable){$legacy=['content'=>['dashboard','media','catalogs','references','technical','assistance','campus'],'requests'=>['dashboard','campus','warranties','incentives','contacts']];return in_array($section,$legacy[$role]??[],true);}
    }

    public static function authorizeRequest(string $path): void
    {
        if (!self::check() || !str_starts_with($path, '/admin')) return;
        $user=self::user();if($user===null)return;$role=strtolower((string)($user['role']??''));if(in_array($role,['admin','administrator'],true))return;
        if($path==='/admin/account'||str_starts_with($path,'/admin/account/'))return;
        $map=[
            'users'=>['/admin/settings/users'],
            'analytics'=>['/admin/analytics'],
            'redirects'=>['/admin/redirects'],
            'settings'=>['/admin/settings'],
            'media'=>['/admin/media'],
            'catalogs'=>['/admin/content/catalogs','/admin/catalog-import'],
            'references'=>['/admin/content/references','/admin/reference-import'],
            'assistance'=>['/admin/assistance'],
            'campus'=>['/admin/campus'],
            'warranties'=>['/admin/warranties'],
            'incentives'=>['/admin/incentives'],
            'contacts'=>['/admin/contacts'],
            'technical'=>['/admin/categories','/admin/products','/admin/product-images','/admin/documents','/admin/mono-split-import','/admin/editorial','/admin/cat/users'],
        ];
        $section='dashboard';foreach($map as $key=>$prefixes)foreach($prefixes as $prefix)if($path===$prefix||str_starts_with($path,$prefix.'/')){$section=$key;break 2;}
        if(!self::hasPermission($role,$section)){http_response_code(403);exit('Non disponi dei permessi necessari per questa sezione.');}
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