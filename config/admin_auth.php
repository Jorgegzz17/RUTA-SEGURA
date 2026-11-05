<?php
declare(strict_types=1);

function startAdminSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('rutas_seguras_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function createAdminCsrfToken(): string
{
    if (empty($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['admin_csrf'];
}

function isAdminAuthenticated(): bool
{
    return isset($_SESSION['admin_id']) && (int) $_SESSION['admin_id'] > 0;
}

function requireAdminApiAccess(): void
{
    startAdminSession();

    if (!isAdminAuthenticated()) {
        respond(401, ['ok' => false, 'error' => 'Se requiere una cuenta de administrador']);
    }

    $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!isset($_SESSION['admin_csrf']) || !hash_equals($_SESSION['admin_csrf'], $csrf)) {
        respond(403, ['ok' => false, 'error' => 'Token de seguridad no válido']);
    }
}
