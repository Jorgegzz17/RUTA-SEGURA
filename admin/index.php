<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/admin_auth.php';

startAdminSession();
if (!isAdminAuthenticated()) {
    header('Location: login.php');
    exit;
}

$csrf = createAdminCsrfToken();
$stmt = getDB()->query(
    'SELECT id, type, location, description, severity, status, likes, created_at
     FROM reports ORDER BY created_at DESC LIMIT 100'
);
$reports = $stmt->fetchAll();
$stats = getDB()->query(
    "SELECT COUNT(*) AS total,
        SUM(status = 'activo') AS activos,
        SUM(status = 'resuelto') AS resueltos
     FROM reports"
)->fetch();

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administración | Rutas Seguras</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body{font-family:Inter,sans-serif}</style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-800">
    <header class="bg-slate-900 text-white">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-cbtis-wine bg-rose-900 flex items-center justify-center font-extrabold border border-amber-400">258</div>
                <div>
                    <h1 class="font-bold">Moderación de reportes</h1>
                    <p class="text-xs text-slate-400">Sesión: <?= h((string) ($_SESSION['admin_username'] ?? 'Administrador')) ?></p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="locations.php" class="rounded-lg px-3 py-2 text-xs font-semibold bg-slate-800 hover:bg-slate-700">
                    <i class="fa-solid fa-location-dot mr-1"></i><span class="hidden sm:inline">Ubicaciones</span>
                </a>
                <a href="../index.php" class="rounded-lg px-3 py-2 text-xs font-semibold bg-slate-800 hover:bg-slate-700">
                    <i class="fa-solid fa-map mr-1"></i><span class="hidden sm:inline">Ver mapa</span>
                </a>
                <form method="post" action="logout.php">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <button class="rounded-lg px-3 py-2 text-xs font-semibold bg-slate-800 hover:bg-red-700" type="submit">
                        <i class="fa-solid fa-right-from-bracket mr-1"></i><span class="hidden sm:inline">Salir</span>
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 py-6">
        <section class="grid grid-cols-3 gap-3 mb-6">
            <div class="rounded-2xl bg-white border border-slate-200 p-4">
                <p class="text-xs uppercase font-bold text-slate-500">Reportes</p>
                <p class="text-2xl font-extrabold mt-1"><?= (int) $stats['total'] ?></p>
            </div>
            <div class="rounded-2xl bg-amber-50 border border-amber-200 p-4">
                <p class="text-xs uppercase font-bold text-amber-700">Activos</p>
                <p class="text-2xl font-extrabold text-amber-700 mt-1"><?= (int) $stats['activos'] ?></p>
            </div>
            <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-4">
                <p class="text-xs uppercase font-bold text-emerald-700">Resueltos</p>
                <p class="text-2xl font-extrabold text-emerald-700 mt-1"><?= (int) $stats['resueltos'] ?></p>
            </div>
        </section>

        <div class="flex items-center justify-between mb-4 gap-3">
            <div>
                <h2 class="text-lg font-extrabold">Últimos reportes</h2>
                <p class="text-xs text-slate-500">Revisa la información y cambia su estado cuando corresponda.</p>
            </div>
            <button onclick="location.reload()" class="shrink-0 rounded-xl bg-white border border-slate-200 px-3 py-2 text-xs font-bold hover:bg-slate-50">
                <i class="fa-solid fa-rotate mr-1"></i>Actualizar
            </button>
        </div>

        <section class="space-y-3">
            <?php if (!$reports): ?>
                <div class="bg-white rounded-2xl border border-slate-200 p-8 text-center text-sm text-slate-500">Todavía no hay reportes.</div>
            <?php endif; ?>

            <?php foreach ($reports as $report): ?>
                <?php
                    $resolved = $report['status'] === 'resuelto';
                    $severityColor = ['baja' => 'emerald', 'media' => 'amber', 'alta' => 'red'][$report['severity']] ?? 'slate';
                ?>
                <article class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5">
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-bold bg-<?= $severityColor ?>-50 text-<?= $severityColor ?>-700 border border-<?= $severityColor ?>-200">
                                    Riesgo <?= h(ucfirst($report['severity'])) ?>
                                </span>
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-bold <?= $resolved ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' ?>">
                                    <?= $resolved ? 'Resuelto' : 'Activo' ?>
                                </span>
                                <span class="text-[10px] text-slate-400"><?= h($report['created_at']) ?></span>
                            </div>
                            <h3 class="font-bold text-sm"><?= h($report['type']) ?></h3>
                            <p class="text-xs font-semibold text-slate-600 mt-1"><?= h($report['location']) ?></p>
                            <p class="text-sm text-slate-500 mt-2 whitespace-pre-line"><?= h($report['description']) ?></p>
                            <p class="text-[11px] text-slate-400 mt-2"><i class="fa-regular fa-thumbs-up mr-1"></i><?= (int) $report['likes'] ?> apoyos</p>
                        </div>
                        <button type="button" data-report-id="<?= (int) $report['id'] ?>" data-next-status="<?= $resolved ? 'activo' : 'resuelto' ?>"
                            class="resolve-btn shrink-0 rounded-xl px-3 py-2 text-xs font-bold transition <?= $resolved ? 'bg-slate-100 text-slate-700 hover:bg-slate-200' : 'bg-emerald-600 text-white hover:bg-emerald-700' ?>">
                            <i class="fa-solid <?= $resolved ? 'fa-rotate-left' : 'fa-check' ?> mr-1"></i>
                            <?= $resolved ? 'Reactivar' : 'Marcar resuelto' ?>
                        </button>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    </main>

    <div id="toast" role="status" class="fixed bottom-4 right-4 hidden max-w-sm rounded-xl px-4 py-3 text-sm font-semibold text-white shadow-lg"></div>
    <script>
        const csrfToken = <?= json_encode($csrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        document.querySelectorAll('.resolve-btn').forEach((button) => {
            button.addEventListener('click', async () => {
                button.disabled = true;
                try {
                    const response = await fetch('../api/reports.php?action=resolve', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify({ id: Number(button.dataset.reportId) })
                    });
                    const result = await response.json();
                    if (!response.ok || !result.ok) throw new Error(result.error || 'No se pudo actualizar');
                    location.reload();
                } catch (error) {
                    const toast = document.getElementById('toast');
                    toast.textContent = error.message;
                    toast.className = 'fixed bottom-4 right-4 max-w-sm rounded-xl px-4 py-3 text-sm font-semibold text-white shadow-lg bg-red-600';
                } finally {
                    button.disabled = false;
                }
            });
        });
    </script>
</body>
</html>
