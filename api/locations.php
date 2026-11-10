<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? 'list';
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($action === 'list') {
        if ($method !== 'GET') {
            respond(405, ['ok' => false, 'error' => 'Método no permitido']);
        }
        listLocations();
    }

    if ($method !== 'POST') {
        respond(405, ['ok' => false, 'error' => 'Método no permitido']);
    }

    require_once __DIR__ . '/../config/admin_auth.php';
    requireAdminApiAccess();
    $body = getJsonBody();

    switch ($action) {
        case 'campus-save':
            saveCampusLocation($body);
            break;
        case 'safe-point-create':
            createSafePoint($body);
            break;
        case 'safe-point-delete':
            deleteSafePoint($body);
            break;
        case 'route-create':
            createSafeRoute($body);
            break;
        case 'route-delete':
            deleteSafeRoute($body);
            break;
        default:
            respond(400, ['ok' => false, 'error' => 'Acción no válida']);
    }
} catch (Throwable $e) {
    error_log('Rutas Seguras locations API: ' . $e->getMessage());
    respond(500, ['ok' => false, 'error' => 'Error interno del servidor']);
}

function listLocations(): void
{
    $db = getDB();
    $campus = $db->query(
        'SELECT name, address, description, latitude, longitude, updated_at
         FROM campus_location WHERE id = 1'
    )->fetch();
    $safePoints = $db->query(
        'SELECT id, name, address, description, latitude, longitude, created_at
         FROM safe_points ORDER BY name'
    )->fetchAll();
    $routes = $db->query(
        'SELECT id, name, description, color, created_at FROM safe_routes ORDER BY name'
    )->fetchAll();
    $routePoints = $db->query(
        'SELECT route_id, latitude, longitude FROM safe_route_points ORDER BY route_id, sequence_no'
    )->fetchAll();
    $pointsByRoute = [];
    foreach ($routePoints as $point) {
        $routeId = (int) $point['route_id'];
        $pointsByRoute[$routeId][] = [
            'latitude' => (float) $point['latitude'],
            'longitude' => (float) $point['longitude'],
        ];
    }
    foreach ($routes as &$route) {
        $route['points'] = $pointsByRoute[(int) $route['id']] ?? [];
    }
    unset($route);

    respond(200, [
        'ok' => true,
        'data' => [
            'campus' => $campus ?: null,
            'safePoints' => $safePoints,
            'routes' => $routes,
        ],
    ]);
}

function saveCampusLocation(array $body): void
{
    $location = validateLocation($body);
    $db = getDB();
    $stmt = $db->prepare(
        'INSERT INTO campus_location (id, name, address, description, latitude, longitude)
         VALUES (1, :name, :address, :description, :latitude, :longitude)
         ON DUPLICATE KEY UPDATE name = VALUES(name), address = VALUES(address),
             description = VALUES(description), latitude = VALUES(latitude), longitude = VALUES(longitude)'
    );
    $stmt->execute($location);

    respond(200, ['ok' => true]);
}

function createSafePoint(array $body): void
{
    $location = validateLocation($body);
    $db = getDB();
    $stmt = $db->prepare(
        'INSERT INTO safe_points (name, address, description, latitude, longitude)
         VALUES (:name, :address, :description, :latitude, :longitude)'
    );
    $stmt->execute($location);

    respond(201, ['ok' => true, 'id' => (int) $db->lastInsertId()]);
}

function deleteSafePoint(array $body): void
{
    $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
        respond(422, ['ok' => false, 'error' => 'ID no válido']);
    }

    $stmt = getDB()->prepare('DELETE FROM safe_points WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() === 0) {
        respond(404, ['ok' => false, 'error' => 'Punto seguro no encontrado']);
    }

    respond(200, ['ok' => true]);
}

function createSafeRoute(array $body): void
{
    $name = is_string($body['name'] ?? null) ? trim($body['name']) : '';
    $description = is_string($body['description'] ?? null) ? trim($body['description']) : '';
    $color = is_string($body['color'] ?? null) ? trim($body['color']) : '#059669';
    $points = $body['points'] ?? null;

    $errors = [];
    if (textLength($name) < 2 || textLength($name) > 120) $errors[] = 'El nombre debe tener entre 2 y 120 caracteres';
    if (textLength($description) > 1000) $errors[] = 'La descripción no puede superar 1000 caracteres';
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $errors[] = 'Color no válido';
    if (!is_array($points) || count($points) < 1 || count($points) > 199) $errors[] = 'Traza entre 1 y 199 puntos antes de guardar';

    $validatedPoints = [];
    if (is_array($points)) {
        foreach ($points as $point) {
            if (!is_array($point)) {
                $errors[] = 'La ruta contiene un punto no válido';
                break;
            }
            $latitude = filter_var($point['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
            $longitude = filter_var($point['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
            if ($latitude === false || $latitude < -90 || $latitude > 90 || $longitude === false || $longitude < -180 || $longitude > 180) {
                $errors[] = 'La ruta contiene coordenadas no válidas';
                break;
            }
            $validatedPoints[] = [(float) $latitude, (float) $longitude];
        }
    }

    if ($errors) {
        respond(422, ['ok' => false, 'error' => implode('. ', array_unique($errors))]);
    }

    $db = getDB();
    $campus = $db->query('SELECT latitude, longitude FROM campus_location WHERE id = 1')->fetch();
    if (!$campus) {
        respond(422, ['ok' => false, 'error' => 'Primero guarda la ubicación principal del plantel']);
    }
    $validatedPoints[] = [(float) $campus['latitude'], (float) $campus['longitude']];

    $db->beginTransaction();
    try {
        $stmt = $db->prepare('INSERT INTO safe_routes (name, description, color) VALUES (:name, :description, :color)');
        $stmt->execute([':name' => $name, ':description' => $description, ':color' => $color]);
        $routeId = (int) $db->lastInsertId();
        $pointStmt = $db->prepare(
            'INSERT INTO safe_route_points (route_id, sequence_no, latitude, longitude)
             VALUES (:route_id, :sequence_no, :latitude, :longitude)'
        );
        foreach ($validatedPoints as $index => [$latitude, $longitude]) {
            $pointStmt->execute([
                ':route_id' => $routeId,
                ':sequence_no' => $index,
                ':latitude' => $latitude,
                ':longitude' => $longitude,
            ]);
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }

    respond(201, ['ok' => true, 'id' => $routeId]);
}

function deleteSafeRoute(array $body): void
{
    $id = filter_var($body['id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
        respond(422, ['ok' => false, 'error' => 'ID no válido']);
    }

    $stmt = getDB()->prepare('DELETE FROM safe_routes WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if ($stmt->rowCount() === 0) {
        respond(404, ['ok' => false, 'error' => 'Ruta segura no encontrada']);
    }

    respond(200, ['ok' => true]);
}

function validateLocation(array $body): array
{
    $name = is_string($body['name'] ?? null) ? trim($body['name']) : '';
    $address = is_string($body['address'] ?? null) ? trim($body['address']) : '';
    $description = is_string($body['description'] ?? null) ? trim($body['description']) : '';
    $latitude = filter_var($body['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
    $longitude = filter_var($body['longitude'] ?? null, FILTER_VALIDATE_FLOAT);

    $errors = [];
    if (textLength($name) < 2 || textLength($name) > 120) $errors[] = 'El nombre debe tener entre 2 y 120 caracteres';
    if (textLength($address) < 3 || textLength($address) > 255) $errors[] = 'La dirección debe tener entre 3 y 255 caracteres';
    if (textLength($description) > 1000) $errors[] = 'La descripción no puede superar 1000 caracteres';
    if ($latitude === false || $latitude < -90 || $latitude > 90) $errors[] = 'Latitud no válida';
    if ($longitude === false || $longitude < -180 || $longitude > 180) $errors[] = 'Longitud no válida';

    if ($errors) {
        respond(422, ['ok' => false, 'error' => implode('. ', $errors)]);
    }

    return [
        ':name' => $name,
        ':address' => $address,
        ':description' => $description,
        ':latitude' => $latitude,
        ':longitude' => $longitude,
    ];
}

function getJsonBody(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== 0) {
        respond(415, ['ok' => false, 'error' => 'El contenido debe enviarse como JSON']);
    }

    $data = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($data)) {
        respond(400, ['ok' => false, 'error' => 'JSON no válido']);
    }

    return $data;
}

function textLength(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }

    $count = preg_match_all('/./us', $value);
    return $count === false ? strlen($value) : $count;
}

function respond(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}