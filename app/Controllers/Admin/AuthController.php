<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\AdminAuth;
use App\Core\Security;

final class AuthController
{
    public static function loginForm(?string $error = null): void
    {
        if (AdminAuth::check()) {
            header('Location: /admin', true, 302);
            exit;
        }
        $csrf = Security::csrfToken();
        require dirname(__DIR__, 2) . '/Views/admin/login.php';
    }

    public static function login(): void
    {
        if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            self::loginForm('Sessione scaduta. Ricarica la pagina e riprova.');
            return;
        }

        $login = trim((string) ($_POST['login'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if ($login === '' || $password === '' || !AdminAuth::attempt($login, $password)) {
            self::loginForm('Credenziali non valide.');
            return;
        }

        header('Location: /admin', true, 302);
        exit;
    }

    public static function logout(): void
    {
        if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'Sessione non valida';
            return;
        }
        AdminAuth::logout();
        header('Location: /admin/login', true, 302);
        exit;
    }
}
