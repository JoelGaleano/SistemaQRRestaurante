<?php
require 'conexion.php';
require 'seguridad.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErrorJSON("Método no permitido", 405);
}

$id_restaurante = autenticarRestaurante($pdo);

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['id_producto']) || !isset($input['disponible'])) {
    responderErrorJSON("Parámetros incompletos (id_producto, disponible)", 400);
}

$id_producto  = (int) $input['id_producto'];
$nuevo_estado = ((int) $input['disponible'] === 1) ? 1 : 0;

try {
    // Actualizamos el producto asegurándonos de que pertenece a una categoría de este restaurante
    $stmt = $pdo->prepare("
        UPDATE productos p
        JOIN categorias c ON p.id_categoria = c.id
        SET p.disponible = ?
        WHERE p.id = ? AND c.id_restaurante = ?
    ");
    $stmt->execute([$nuevo_estado, $id_producto, $id_restaurante]);

    responderJSON([
        "status"      => "success",
        "code"        => 200,
        "id_producto" => $id_producto,
        "disponible"  => (bool) $nuevo_estado
    ], 200);
} catch (Exception $e) {
    responderErrorJSON("Error en la base de datos al actualizar disponibilidad", 500);
}
?>
