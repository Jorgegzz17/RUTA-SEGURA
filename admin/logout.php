<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/admin_auth.php';

startAdminSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}

$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || !isset($_SESSION['admin_csrf']) || !hash_equals($_SESSION['admin_csrf'], $token)) {
    http_response_code(403);
    exit('Token de seguridad no válido');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => 'Strict',
    ]);
}
session_destroy();

header('Location: login.php');
exit;
