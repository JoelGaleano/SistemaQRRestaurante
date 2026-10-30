<?php
header('Content-Type: application/json; charset=utf-8');
require '../admin/conexion.php';
require '../admin/seguridad.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErrorJSON('Método no permitido', 405);
}

$input = json_decode(file_get_contents('php://input'), true);

$id_restaurante = (int) ($input['id_restaurante'] ?? 0);
$id_mesa        = (int) ($input['id_mesa'] ?? 0);

if ($id_restaurante <= 0 || $id_mesa <= 0) {
    responderErrorJSON('Datos de mesa inválidos', 400);
}

try {
    // 1. Verificar que la mesa tenga pedidos abiertos (cuenta_cerrada = 0)
    $stmtPedidos = $pdo->prepare("
        SELECT id, total, cargo_servicio_mesa
        FROM pedidos
        WHERE id_restaurante = ? AND id_mesa = ? AND cuenta_cerrada = 0 AND estado != 'cancelado'
        ORDER BY id ASC
    ");
    $stmtPedidos->execute([$id_restaurante, $id_mesa]);
    $pedidosAbiertos = $stmtPedidos->fetchAll();

    if (empty($pedidosAbiertos)) {
        responderErrorJSON('No hay consumos activos en esta mesa para pedir la cuenta.', 400);
    }

    // 2. Marcar todos los pedidos abiertos de esta mesa con pidio_cuenta = 1
    $stmtUpdate = $pdo->prepare("
        UPDATE pedidos 
        SET pidio_cuenta = 1 
        WHERE id_restaurante = ? AND id_mesa = ? AND cuenta_cerrada = 0 AND estado != 'cancelado'
    ");
    $stmtUpdate->execute([$id_restaurante, $id_mesa]);

    $total_final = 0.00;
    $servicio_mesa = 0.00;
    foreach ($pedidosAbiertos as $p) {
        $total_final += (float) $p['total'];
        $servicio_mesa += (float) $p['cargo_servicio_mesa'];
    }

    responderJSON([
        'status'        => 'success',
        'code'          => 200,
        'message'       => '¡Solicitaste la cuenta! Enseguida se acercan a tu mesa.',
        'mensaje'       => '¡Solicitaste la cuenta! Enseguida se acercan a tu mesa.',
        'servicio_mesa' => $servicio_mesa,
        'total_final'   => $total_final
    ], 200);

} catch (Exception $e) {
    responderErrorJSON('Error al solicitar la cuenta de la mesa.', 500);
}
?>
