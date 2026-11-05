<?php
/**
 * API REST sencilla para los reportes de la comunidad.
 *
 *  GET    api/reports.php?action=list[&status=activo][&type=...][&q=texto]
 *  POST   api/reports.php?action=create   { type, location, description, latitude, longitude, severity }
 *  POST   api/reports.php?action=like     { id }
 *  POST   api/reports.php?action=resolve  { id }
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {
        case 'list':
            if ($method !== 'GET') {
                respond(405, ['ok' => false, 'error' => 'Método no permitido']);
            }
            listReports();
            break;

        case 'create':
            if ($method !== 'POST') {
                respond(405, ['ok' => false, 'error' => 'Método no permitido']);
            }
            createReport();
            break;

        case 'like':
            if ($method !== 'POST') {
                respond(405, ['ok' => false, 'error' => 'Método no permitido']);
            }
            likeReport();
            break;

        case 'resolve':
            if ($method !== 'POST') {
                respond(405, ['ok' => false, 'error' => 'Método no permitido']);
            }
            require_once __DIR__ . '/../config/admin_auth.php';
            requireAdminApiAccess();
            resolveReport();
            break;

        case 'stats':
            if ($method !== 'GET') {
                respond(405, ['ok' => false, 'error' => 'Método no permitido']);
            }
            getStats();
            break;

        default:
            respond(400, ['ok' => false, 'error' => 'Acción no válida']);
    }
} catch (Throwable $e) {
    error_log('Rutas Seguras API: ' . $e->getMessage());
    respond(500, ['ok' => false, 'error' => 'Error interno del servidor']);
}

/* ============================================================
 *  Acciones
 * ========================================================== */

function listReports(): void
{
    $db     = getDB();
    $where  = [];
    $params = [];

    $status = $_GET['status'] ?? '';
    if (is_string($status) && in_array($status, ['activo', 'resuelto'], true)) {
        $where[]           = 'status = :status';
        $params[':status'] = $status;
    }

    $type = $_GET['type'] ?? '';
    if (is_string($type) && $type !== '') {
        $where[]          = 'type = :type';
        $params[':type']  = $type;
    }

    $query = $_GET['q'] ?? '';
    if (is_string($query) && trim($query) !== '') {
        $where[]       = '(location LIKE :q OR description LIKE :q)';
        $params[':q']  = '%' . textSlice(trim($query), 100) . '%';
    }

    $sql = 'SELECT id, type, location, description, latitude, longitude, severity, status, likes, created_at
            FROM reports';

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY created_at DESC LIMIT 100';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    respond(200, ['ok' => true, 'data' => $stmt->fetchAll()]);
}

function createReport(): void
{
    $body = getJsonBody();

    $typeValue = $body['type'] ?? null;
    $locationValue = $body['location'] ?? null;
    $descriptionValue = $body['description'] ?? null;
    $type = is_string($typeValue) ? trim($typeValue) : '';
    $location = is_string($locationValue) ? trim($locationValue) : '';
    $description = is_string($descriptionValue) ? trim($descriptionValue) : '';
    $severityValue = $body['severity'] ?? 'media';
    $severity = is_string($severityValue) ? $severityValue : '';
    $lat = filter_var($body['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
    $lng = filter_var($body['longitude'] ?? null, FILTER_VALIDATE_FLOAT);

    $allowedTypes = [
        'Luz Fundida / Zona Oscura',
        'Presencia Escasa de Vía',
        'Zona Solitaria / Sospechosa',
        'Tráfico / Banqueta Obstruida',
        'Acoso / Robo',
        'Otro',
    ];

    $errors = [];
    if (!in_array($type, $allowedTypes, true))            $errors[] = 'Tipo de reporte no válido';
    if (textLength($location) < 3 || textLength($location) > 255) $errors[] = 'La ubicación debe tener entre 3 y 255 caracteres';
    if (textLength($description) < 5 || textLength($description) > 1000) $errors[] = 'La descripción debe tener entre 5 y 1000 caracteres';
    if ($lat === false || $lat < -90  || $lat > 90)       $errors[] = 'Latitud no válida';
    if ($lng === false || $lng < -180 || $lng > 180)      $errors[] = 'Longitud no válida';
    if (!in_array($severity, ['baja', 'media', 'alta'], true)) $errors[] = 'Severidad no válida';

    if ($lat !== false && $lng !== false && distanceInMeters(25.7952, -100.3348, (float) $lat, (float) $lng) > 8000) {
        $errors[] = 'La ubicación debe estar a menos de 8 km del plantel';
    }

    if ($errors) {
        respond(422, ['ok' => false, 'error' => implode('. ', $errors)]);
    }

    $db   = getDB();
    $stmt = $db->prepare(
        'INSERT INTO reports (type, location, description, latitude, longitude, severity)
         VALUES (:type, :location, :description, :lat, :lng, :severity)'
    );
    $stmt->execute([
        ':type'        => $type,
        ':location'    => $location,
        ':description' => $description,
        ':lat'         => $lat,
        ':lng'         => $lng,
        ':severity'    => $severity,
    ]);

    $id = (int) $db->lastInsertId();
    $stmt = $db->prepare('SELECT * FROM reports WHERE id = :id');
    $stmt->execute([':id' => $id]);

    respond(201, ['ok' => true, 'data' => $stmt->fetch()]);
}

function likeReport(): void
{
    $body = getJsonBody();
    $id   = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);

    if (!$id) {
        respond(422, ['ok' => false, 'error' => 'ID no válido']);
    }

    $db = getDB();
    $stmt = $db->prepare('UPDATE reports SET likes = likes + 1 WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) {
        respond(404, ['ok' => false, 'error' => 'Reporte no encontrado']);
    }

    $stmt = $db->prepare('SELECT likes FROM reports WHERE id = :id');
    $stmt->execute([':id' => $id]);

    respond(200, ['ok' => true, 'likes' => (int) $stmt->fetchColumn()]);
}

function resolveReport(): void
{
    $body = getJsonBody();
    $id   = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);

    if (!$id) {
        respond(422, ['ok' => false, 'error' => 'ID no válido']);
    }

    $db = getDB();
    $stmt = $db->prepare(
        "UPDATE reports SET status = IF(status = 'activo', 'resuelto', 'activo') WHERE id = :id"
    );
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) {
        respond(404, ['ok' => false, 'error' => 'Reporte no encontrado']);
    }

    $stmt = $db->prepare('SELECT status FROM reports WHERE id = :id');
    $stmt->execute([':id' => $id]);

    respond(200, ['ok' => true, 'status' => $stmt->fetchColumn()]);
}

function getStats(): void
{
    $db = getDB();

    respond(200, [
        'ok'   => true,
        'data' => [
            'total'     => (int) $db->query('SELECT COUNT(*) FROM reports')->fetchColumn(),
            'activos'   => (int) $db->query("SELECT COUNT(*) FROM reports WHERE status = 'activo'")->fetchColumn(),
            'resueltos' => (int) $db->query("SELECT COUNT(*) FROM reports WHERE status = 'resuelto'")->fetchColumn(),
            'altas'     => (int) $db->query("SELECT COUNT(*) FROM reports WHERE severity = 'alta' AND status = 'activo'")->fetchColumn(),
        ],
    ]);
}

/* ============================================================
 *  Utilidades
 * ========================================================== */

function getJsonBody(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== 0) {
        respond(415, ['ok' => false, 'error' => 'El contenido debe enviarse como JSON']);
    }

    $raw  = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);

    if (!is_array($data)) {
        respond(400, ['ok' => false, 'error' => 'JSON no válido']);
    }

    return $data;
}

function distanceInMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $earthRadius = 6371000;
    $latDelta = deg2rad($lat2 - $lat1);
    $lngDelta = deg2rad($lng2 - $lng1);
    $a = sin($latDelta / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

    return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function textLength(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    $count = preg_match_all('/./us', $value);
    return $count === false ? strlen($value) : $count;
}

function textSlice(string $value, int $length): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $length, 'UTF-8');
    }

    if (preg_match('/^.{0,' . $length . '}/us', $value, $matches) === 1) {
        return $matches[0];
    }

    return substr($value, 0, $length);
}

function respond(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
