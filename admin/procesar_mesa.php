<?php
require 'conexion.php';
require 'seguridad.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErrorJSON("Método no permitido", 405);
}

$id_restaurante = autenticarRestaurante($pdo);

$rawInput = file_get_contents('php://input');
$jsonInput = !empty($rawInput) ? json_decode($rawInput, true) : null;

$numero_mesa = (int) ($jsonInput['numero_mesa'] ?? $_POST['numero_mesa'] ?? 0);

if ($numero_mesa <= 0) {
    if (esPeticionAPI()) {
        responderErrorJSON("Número de mesa inválido", 400);
    }
    die("Número de mesa inválido");
}

$hash_unico = hash('sha256', $id_restaurante . '_' . $numero_mesa . '_' . bin2hex(random_bytes(16)));

try {
    $stmt = $pdo->prepare("INSERT INTO mesas (id_restaurante, numero_mesa, hash_qr) VALUES (?, ?, ?)");
    $stmt->execute([$id_restaurante, $numero_mesa, $hash_unico]);
    $id_mesa = (int) $pdo->lastInsertId();

    if (esPeticionAPI()) {
        responderJSON([
            'status'      => 'success',
            'code'        => 201,
            'message'     => 'Mesa creada correctamente',
            'id_mesa'     => $id_mesa,
            'numero_mesa' => $numero_mesa,
            'hash_qr'     => $hash_unico
        ], 201);
    }
} catch (PDOException $e) {
    if (esPeticionAPI()) {
        responderErrorJSON("Error al registrar la mesa", 500);
    }
}

header("Location: mesas.php");
exit;
?>
