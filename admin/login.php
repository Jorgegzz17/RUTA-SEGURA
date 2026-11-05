<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/admin_auth.php';

startAdminSession();

if (isAdminAuthenticated()) {
    header('Location: index.php');
    exit;
}

$error = '';
$csrf = createAdminCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sentToken = $_POST['csrf_token'] ?? '';
    if (!is_string($sentToken) || !hash_equals($csrf, $sentToken)) {
        $error = 'La sesión expiró. Recarga la página e inténtalo de nuevo.';
    } elseif (($_SESSION['admin_login_blocked_until'] ?? 0) > time()) {
        $error = 'Demasiados intentos. Espera unos minutos antes de volver a intentarlo.';
    } else {
        $usernameInput = $_POST['username'] ?? '';
        $passwordInput = $_POST['password'] ?? '';
        $username = is_string($usernameInput) ? trim($usernameInput) : '';
        $password = is_string($passwordInput) ? $passwordInput : '';

        $stmt = getDB()->prepare('SELECT id, username, password_hash FROM admin_users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            unset($_SESSION['admin_csrf']);
            unset($_SESSION['admin_login_attempts'], $_SESSION['admin_login_blocked_until']);
            createAdminCsrfToken();
            header('Location: index.php');
            exit;
        }

        $error = 'Usuario o contraseña incorrectos.';
        $_SESSION['admin_login_attempts'] = ($_SESSION['admin_login_attempts'] ?? 0) + 1;
        if ($_SESSION['admin_login_attempts'] >= 5) {
            $_SESSION['admin_login_blocked_until'] = time() + 300;
            $_SESSION['admin_login_attempts'] = 0;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso administrativo | Rutas Seguras</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body{font-family:Inter,sans-serif}</style>
</head>
<body class="min-h-screen bg-slate-100 flex items-center justify-center p-4">
    <main class="w-full max-w-md bg-white rounded-3xl shadow-xl border border-slate-200 p-7">
        <a href="../index.php" class="inline-flex items-center gap-2 text-sm text-slate-500 hover:text-slate-900 mb-8">
            <i class="fa-solid fa-arrow-left"></i> Volver al mapa
        </a>
        <div class="w-12 h-12 rounded-2xl bg-slate-900 text-white flex items-center justify-center mb-5">
            <i class="fa-solid fa-shield-halved text-xl"></i>
        </div>
        <h1 class="text-2xl font-extrabold text-slate-900">Panel de administración</h1>
        <p class="text-sm text-slate-500 mt-1 mb-6">Inicia sesión para moderar los reportes.</p>

        <?php if ($error !== ''): ?>
            <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="post" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <div>
                <label for="username" class="block text-xs font-bold uppercase text-slate-600 mb-1">Usuario</label>
                <input id="username" name="username" autocomplete="username" required maxlength="80"
                    class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm outline-none focus:ring-2 focus:ring-slate-800">
            </div>
            <div>
                <label for="password" class="block text-xs font-bold uppercase text-slate-600 mb-1">Contraseña</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required
                    class="w-full rounded-xl border border-slate-300 px-3 py-3 text-sm outline-none focus:ring-2 focus:ring-slate-800">
            </div>
            <button type="submit" class="w-full rounded-xl bg-slate-900 hover:bg-slate-800 text-white py-3 font-bold text-sm transition">
                Iniciar sesión
            </button>
        </form>
    </main>
</body>
</html>
