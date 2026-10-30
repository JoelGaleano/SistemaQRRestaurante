<?php
// admin/seguridad.php

/**
 * Envía una respuesta JSON estandarizada (compatible con Retrofit y Frontend Web) y termina la ejecución.
 */
function responderJSON(array $payload, int $httpCode = 200): void {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Envía un error JSON estandarizado: {"status": "error", "code": 401, "message": "...", "mensaje": "..."}
 */
function responderErrorJSON(string $message, int $httpCode = 400): void {
    responderJSON([
        'status'  => 'error',
        'code'    => $httpCode,
        'message' => $message,
        'mensaje' => $message
    ], $httpCode);
}

/**
 * Detecta si la petición proviene de una API / App Móvil (Retrofit) o Fetch JSON.
 */
function esPeticionAPI(): bool {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';

    return stripos($accept, 'application/json') !== false
        || stripos($contentType, 'application/json') !== false
        || !empty($authHeader)
        || !empty($apiKey);
}

/**
 * Extrae el Bearer Token o X-API-Key de la petición actual.
 */
function obtenerTokenRequest(): ?string {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization']
        ?? $headers['authorization']
        ?? $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';

    if (preg_match('/Bearer\s+([a-fA-F0-9]{32,128})/i', $authHeader, $matches)) {
        return $matches[1];
    }

    $apiKey = $headers['X-API-Key']
        ?? $headers['x-api-key']
        ?? $_SERVER['HTTP_X_API_KEY']
        ?? ($_GET['token'] ?? '');

    if (!empty($apiKey) && preg_match('/^[a-fA-F0-9]{32,128}$/', $apiKey)) {
        return $apiKey;
    }

    return null;
}

/**
 * Autentica al restaurante ya sea por Sesión PHP (Web) o por Token Bearer / API Key (Android Retrofit).
 * Devuelve el id_restaurante autenticado o responde con 401 JSON estandarizado.
 */
function autenticarRestaurante(PDO $pdo): int {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!empty($_SESSION['restaurante_id'])) {
        return (int) $_SESSION['restaurante_id'];
    }

    $token = obtenerTokenRequest();
    if ($token) {
        $stmt = $pdo->prepare("SELECT id, nombre FROM restaurantes WHERE api_token = ? LIMIT 1");
        $stmt->execute([$token]);
        $rest = $stmt->fetch();
        if ($rest) {
            return (int) $rest['id'];
        }
    }

    responderErrorJSON('No autorizado. Inicia sesión o proporciona un Bearer Token válido.', 401);
    return 0;
}

/**
 * Consulta optimizada de pedidos activos para un restaurante (sin problema N+1).
 * Compartida entre api_pedidos.php (REST) y sse_pedidos.php (Tiempo Real).
 */
function obtenerPedidosActivos(PDO $pdo, int $id_restaurante): array {
    $stmtPedidos = $pdo->prepare("
        SELECT p.id, p.estado, p.nombre_mozo, p.total, DATE_FORMAT(p.fecha_creacion, '%H:%i') AS hora, m.numero_mesa 
        FROM pedidos p
        JOIN mesas m ON p.id_mesa = m.id
        WHERE p.id_restaurante = ? AND p.estado IN ('pendiente', 'preparando')
        ORDER BY p.fecha_creacion ASC
    ");
    $stmtPedidos->execute([$id_restaurante]);
    $pedidos = $stmtPedidos->fetchAll();

    if (empty($pedidos)) {
        return [];
    }

    // Obtener todos los items de los pedidos activos en una sola consulta usando IN (...)
    $idsPedidos = array_column($pedidos, 'id');
    $placeholders = implode(',', array_fill(0, count($idsPedidos), '?'));

    $stmtItems = $pdo->prepare("
        SELECT i.id_pedido, i.cantidad, pr.nombre 
        FROM pedidos_items i
        JOIN productos pr ON i.id_producto = pr.id
        WHERE i.id_pedido IN ($placeholders)
        ORDER BY i.id ASC
    ");
    $stmtItems->execute($idsPedidos);
    $todosItems = $stmtItems->fetchAll();

    $itemsPorPedido = [];
    foreach ($todosItems as $item) {
        $itemsPorPedido[$item['id_pedido']][] = [
            'cantidad' => (int) $item['cantidad'],
            'nombre'   => $item['nombre']
        ];
    }

    foreach ($pedidos as &$pedido) {
        $pedido['id']          = (int) $pedido['id'];
        $pedido['numero_mesa'] = (int) $pedido['numero_mesa'];
        $pedido['nombre_mozo'] = $pedido['nombre_mozo'] ?? '';
        $pedido['total']       = (float) $pedido['total'];
        $pedido['items']       = $itemsPorPedido[$pedido['id']] ?? [];
    }
    unset($pedido);

    return $pedidos;
}

/**
 * Obtiene las cuentas abiertas agrupadas por mesa (cuenta_cerrada = 0)
 * incluyendo el servicio de mesa y si el cliente tocó "Pedir la Cuenta" (pidio_cuenta = 1).
 */
function obtenerCuentasMesasAbiertas(PDO $pdo, int $id_restaurante): array {
    $stmt = $pdo->prepare("
        SELECT 
            m.id AS id_mesa,
            m.numero_mesa,
            COUNT(p.id) AS cantidad_rondas,
            COALESCE(SUM(p.total), 0) AS total_acumulado,
            COALESCE(SUM(p.cargo_servicio_mesa), 0) AS servicio_mesa,
            MAX(p.pidio_cuenta) AS pidio_cuenta,
            GROUP_CONCAT(DISTINCT NULLIF(p.nombre_mozo, '') SEPARATOR ', ') AS nombre_mozo,
            DATE_FORMAT(MIN(p.fecha_creacion), '%H:%i') AS hora_apertura
        FROM pedidos p
        JOIN mesas m ON p.id_mesa = m.id
        WHERE p.id_restaurante = ? AND p.cuenta_cerrada = 0 AND p.estado != 'cancelado'
        GROUP BY m.id, m.numero_mesa
        ORDER BY MAX(p.pidio_cuenta) DESC, m.numero_mesa ASC
    ");
    $stmt->execute([$id_restaurante]);
    $cuentas = $stmt->fetchAll();

    if (empty($cuentas)) {
        return [];
    }

    // Obtener detalle de ítems agrupados por mesa para las cuentas abiertas
    $stmtItems = $pdo->prepare("
        SELECT p.id_mesa, pr.nombre, SUM(i.cantidad) AS cantidad, i.precio_unitario
        FROM pedidos_items i
        JOIN pedidos p ON i.id_pedido = p.id
        JOIN productos pr ON i.id_producto = pr.id
        WHERE p.id_restaurante = ? AND p.cuenta_cerrada = 0 AND p.estado != 'cancelado'
        GROUP BY p.id_mesa, pr.id, pr.nombre, i.precio_unitario
        ORDER BY pr.nombre ASC
    ");
    $stmtItems->execute([$id_restaurante]);
    $filas = $stmtItems->fetchAll();

    $itemsPorMesa = [];
    foreach ($filas as $f) {
        $itemsPorMesa[$f['id_mesa']][] = [
            'nombre'          => $f['nombre'],
            'cantidad'        => (int) $f['cantidad'],
            'precio_unitario' => (float) $f['precio_unitario'],
            'subtotal'        => (float) $f['precio_unitario'] * (int) $f['cantidad']
        ];
    }

    foreach ($cuentas as &$c) {
        $c['id_mesa']         = (int) $c['id_mesa'];
        $c['numero_mesa']     = (int) $c['numero_mesa'];
        $c['cantidad_rondas'] = (int) $c['cantidad_rondas'];
        $c['total_acumulado'] = (float) $c['total_acumulado'];
        $c['servicio_mesa']   = (float) $c['servicio_mesa'];
        $c['pidio_cuenta']    = (int) $c['pidio_cuenta'] === 1;
        $c['items']           = $itemsPorMesa[$c['id_mesa']] ?? [];
    }
    unset($c);

    return $cuentas;
}

/**
 * Limita la cantidad de peticiones por IP en una ventana de tiempo (segundos).
 */
function verificarRateLimit(PDO $pdo, string $endpoint, int $maxIntentos = 20, int $ventanaSegundos = 60): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Limpieza probabilística (5% de las peticiones) de registros expirados
    if (random_int(1, 20) === 1) {
        $stmtClean = $pdo->prepare("DELETE FROM api_rate_limits WHERE ventana_inicio < (NOW() - INTERVAL ? SECOND)");
        $stmtClean->execute([$ventanaSegundos * 2]);
    }

    $stmt = $pdo->prepare("SELECT intentos, TIMESTAMPDIFF(SECOND, ventana_inicio, NOW()) AS transcurrido FROM api_rate_limits WHERE ip_address = ? AND endpoint = ?");
    $stmt->execute([$ip, $endpoint]);
    $registro = $stmt->fetch();

    if (!$registro) {
        $stmtInsert = $pdo->prepare("INSERT INTO api_rate_limits (ip_address, endpoint, intentos, ventana_inicio) VALUES (?, ?, 1, NOW())");
        $stmtInsert->execute([$ip, $endpoint]);
        return;
    }

    if ($registro['transcurrido'] > $ventanaSegundos) {
        $stmtReset = $pdo->prepare("UPDATE api_rate_limits SET intentos = 1, ventana_inicio = NOW() WHERE ip_address = ? AND endpoint = ?");
        $stmtReset->execute([$ip, $endpoint]);
        return;
    }

    if ($registro['intentos'] >= $maxIntentos) {
        responderErrorJSON('Demasiadas peticiones. Por favor, espera unos segundos e intenta de nuevo.', 429);
    }

    $stmtInc = $pdo->prepare("UPDATE api_rate_limits SET intentos = intentos + 1 WHERE ip_address = ? AND endpoint = ?");
    $stmtInc->execute([$ip, $endpoint]);
}

/**
 * Valida y guarda una imagen subida de forma segura.
 * Retorna la ruta relativa para guardar en BD (ej. 'uploads/productos/prod_1_...jpg') o lanza Exception.
 */
function subirImagenSegura(array $file, string $subcarpeta, string $prefijo): string {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Error al subir el archivo (Código: {$file['error']}).");
    }

    // 1. Validar tamaño máximo (2 MB)
    $maxBytes = 2 * 1024 * 1024;
    if ($file['size'] > $maxBytes || $file['size'] <= 0) {
        throw new Exception("La imagen no debe superar los 2MB.");
    }

    // 2. Validar MIME Type real inspeccionando los bytes del archivo (no la cabecera HTTP)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeReal = $finfo->file($file['tmp_name']);

    $mimesPermitidos = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    if (!array_key_exists($mimeReal, $mimesPermitidos)) {
        throw new Exception("Formato no permitido ($mimeReal). Solo se aceptan JPG, PNG o WEBP.");
    }

    // 3. Verificar que realmente sea una imagen válida con dimensiones reales
    $imgInfo = @getimagesize($file['tmp_name']);
    if ($imgInfo === false || $imgInfo[0] < 10 || $imgInfo[1] < 10 || $imgInfo[0] > 5000 || $imgInfo[1] > 5000) {
        throw new Exception("El archivo no es una imagen válida o sus dimensiones son incorrectas.");
    }

    // 4. Generar nombre seguro forzando la extensión según el MIME detectado
    $extensionSegura = $mimesPermitidos[$mimeReal];
    $nombreArchivo = $prefijo . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $extensionSegura;

    $directorioBase = dirname(__DIR__) . '/uploads/' . trim($subcarpeta, '/') . '/';
    if (!is_dir($directorioBase)) {
        mkdir($directorioBase, 0755, true);
    }

    $rutaDestinoAbsoluta = $directorioBase . $nombreArchivo;
    if (!move_uploaded_file($file['tmp_name'], $rutaDestinoAbsoluta)) {
        throw new Exception("No se pudo guardar la imagen en el servidor.");
    }

    return 'uploads/' . trim($subcarpeta, '/') . '/' . $nombreArchivo;
}
?>
