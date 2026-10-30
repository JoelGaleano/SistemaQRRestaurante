<?php
session_start();
require 'conexion.php';
require 'seguridad.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responderErrorJSON("Método no permitido", 405);
}

// Proteger login contra ataques de fuerza bruta (máx 10 intentos por minuto por IP)
verificarRateLimit($pdo, 'login_restaurante', 10, 60);

// Soportar tanto FormData (Web) como JSON Body (Android Retrofit)
$rawInput = file_get_contents('php://input');
$jsonInput = !empty($rawInput) ? json_decode($rawInput, true) : null;

$email    = trim($jsonInput['email'] ?? $_POST['email'] ?? '');
$password = $jsonInput['password'] ?? $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    responderErrorJSON("Por favor ingresa email y contraseña", 400);
}

$stmt = $pdo->prepare("SELECT id, nombre, alias, password, api_token FROM restaurantes WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$restaurante = $stmt->fetch();

if ($restaurante && password_verify($password, $restaurante['password'])) {
    // Generar o recuperar el API Token para consumo desde Android (Retrofit)
    $apiToken = $restaurante['api_token'];
    if (empty($apiToken) || !empty($jsonInput['renovar_token'])) {
        $apiToken = bin2hex(random_bytes(32)); // 64 caracteres hex
        $stmtToken = $pdo->prepare("UPDATE restaurantes SET api_token = ? WHERE id = ?");
        $stmtToken->execute([$apiToken, $restaurante['id']]);
    }

    // Guardar sesión PHP para el panel web
    $_SESSION['restaurante_id'] = (int) $restaurante['id'];
    $_SESSION['restaurante_nombre'] = $restaurante['nombre'];

    responderJSON([
        'status'         => 'success',
        'code'           => 200,
        'redirect'       => 'dashboard.php',
        'api_token'      => $apiToken,
        'restaurante_id' => (int) $restaurante['id'],
        'nombre'         => $restaurante['nombre'],
        'alias'          => $restaurante['alias']
    ], 200);
} else {
    responderErrorJSON("Credenciales incorrectas", 401);
}
?>
