<?php
require 'conexion.php';
require 'seguridad.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErrorJSON("Método no permitido", 405);
}

$id_restaurante = autenticarRestaurante($pdo);

$rawInput = file_get_contents('php://input');
$jsonInput = !empty($rawInput) ? json_decode($rawInput, true) : null;

$nombre = trim($jsonInput['nombre_categoria'] ?? $_POST['nombre_categoria'] ?? '');

if ($nombre === '') {
    if (esPeticionAPI()) {
        responderErrorJSON("El nombre de la categoría es obligatorio", 400);
    }
    die("El nombre de la categoría es obligatorio");
}

try {
    $stmt = $pdo->prepare("INSERT INTO categorias (id_restaurante, nombre) VALUES (?, ?)");
    $stmt->execute([$id_restaurante, $nombre]);
    $id_categoria = (int) $pdo->lastInsertId();

    if (esPeticionAPI()) {
        responderJSON([
            'status'       => 'success',
            'code'         => 201,
            'message'      => 'Categoría creada correctamente',
            'id_categoria' => $id_categoria,
            'nombre'       => $nombre
        ], 201);
    }
} catch (Exception $e) {
    if (esPeticionAPI()) {
        responderErrorJSON("Error al crear la categoría", 500);
    }
    die("Error al crear la categoría");
}

header("Location: menu.php");
exit;
?>
