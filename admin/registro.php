<?php
session_start();
require 'conexion.php';
require 'seguridad.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responderErrorJSON("Método no permitido", 405);
}

// Rate limit: máx 5 registros por minuto por IP
verificarRateLimit($pdo, 'registro_restaurante', 5, 60);

$rawInput = file_get_contents('php://input');
$jsonInput = !empty($rawInput) ? json_decode($rawInput, true) : null;

$nombre       = trim($jsonInput['nombre'] ?? $_POST['nombre'] ?? '');
$email        = trim($jsonInput['email'] ?? $_POST['email'] ?? '');
$telefono     = trim($jsonInput['telefono'] ?? $_POST['telefono'] ?? '');
$direccion    = trim($jsonInput['direccion'] ?? $_POST['direccion'] ?? '');
$passwordRaw  = $jsonInput['password'] ?? $_POST['password'] ?? '';

if ($nombre === '' || $email === '' || $passwordRaw === '') {
    responderErrorJSON("Nombre, email y contraseña son obligatorios.", 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    responderErrorJSON("El formato del email no es válido.", 400);
}

$password = password_hash($passwordRaw, PASSWORD_DEFAULT);

// Crear un alias limpio para la URL (Ej: "Pizzería Joe" -> "pizzeria-joe")
$alias = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $nombre));
$alias = trim($alias, '-');
$apiToken = bin2hex(random_bytes(32));

try {
    $stmt = $pdo->prepare("INSERT INTO restaurantes (nombre, alias, email, telefono, direccion, password, api_token) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$nombre, $alias, $email, $telefono, $direccion, $password, $apiToken]);
    $nuevoId = (int) $pdo->lastInsertId();

    responderJSON([
        'status'         => 'success',
        'code'           => 201,
        'message'        => 'Cuenta creada exitosamente. Ya puedes iniciar sesión.',
        'mensaje'        => 'Cuenta creada exitosamente. Ya puedes iniciar sesión.',
        'restaurante_id' => $nuevoId,
        'api_token'      => $apiToken
    ], 201);
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        responderErrorJSON('El email o el nombre del restaurante ya están registrados.', 409);
    } else {
        responderErrorJSON('Error interno del servidor al registrar el restaurante.', 500);
    }
}
?>
