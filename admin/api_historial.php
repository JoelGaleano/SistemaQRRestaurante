<?php
require 'conexion.php';
require 'seguridad.php';

$id_restaurante = autenticarRestaurante($pdo);
$fecha = $_GET['fecha'] ?? date('Y-m-d'); // Por defecto, la fecha de hoy

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    responderErrorJSON("Formato de fecha inválido. Use YYYY-MM-DD.", 400);
}

try {
    // Buscar pedidos servidos y cancelados en la fecha especificada incluyendo nombre_mozo y cargo_servicio_mesa
    $stmt = $pdo->prepare("
        SELECT 
            p.id, 
            p.estado,
            p.total, 
            p.cargo_servicio_mesa,
            p.nombre_mozo, 
            DATE_FORMAT(p.fecha_creacion, '%H:%i') AS hora, 
            m.numero_mesa 
        FROM pedidos p
        JOIN mesas m ON p.id_mesa = m.id
        WHERE p.id_restaurante = ? 
          AND p.estado IN ('servido', 'cancelado') 
          AND p.fecha_creacion >= ?
          AND p.fecha_creacion < DATE_ADD(?, INTERVAL 1 DAY)
        ORDER BY p.fecha_creacion DESC
    ");
    $stmt->execute([$id_restaurante, $fecha . ' 00:00:00', $fecha . ' 00:00:00']);
    $pedidos = $stmt->fetchAll();

    // Obtener en una sola consulta todos los ítems de los pedidos encontrados
    $itemsPorPedido = [];
    if (!empty($pedidos)) {
        $ids = array_map(static fn($row) => (int) $row['id'], $pedidos);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $stmtItems = $pdo->prepare("
            SELECT 
                i.id_pedido,
                i.cantidad,
                i.precio_unitario,
                pr.nombre
            FROM pedidos_items i
            JOIN productos pr ON i.id_producto = pr.id
            WHERE i.id_pedido IN ($placeholders)
            ORDER BY i.id ASC
        ");
        $stmtItems->execute($ids);

        foreach ($stmtItems->fetchAll() as $item) {
            $idPed    = (int) $item['id_pedido'];
            $cant     = (int) $item['cantidad'];
            $unitario = (float) $item['precio_unitario'];
            $itemsPorPedido[$idPed][] = [
                'nombre'          => $item['nombre'],
                'cantidad'        => $cant,
                'precio_unitario' => $unitario,
                'subtotal'        => $cant * $unitario
            ];
        }
    }

    // Calcular la recaudación total del día consultado (solo pedidos servidos)
    $recaudacion_total = 0.0;
    foreach ($pedidos as &$pedido) {
        $idPed = (int) $pedido['id'];
        $pedido['id']                  = $idPed;
        $pedido['numero_mesa']         = (int) $pedido['numero_mesa'];
        $pedido['nombre_mozo']         = $pedido['nombre_mozo'] ?: 'Sin asignar';
        $pedido['total']               = (float) $pedido['total'];
        $pedido['cargo_servicio_mesa'] = (float) $pedido['cargo_servicio_mesa'];
        $pedido['items']               = $itemsPorPedido[$idPed] ?? [];

        if ($pedido['estado'] === 'servido') {
            $recaudacion_total += $pedido['total'];
        }
    }
    unset($pedido);

    responderJSON([
        "status"      => "success",
        "code"        => 200,
        "data"        => $pedidos,
        "recaudacion" => $recaudacion_total
    ], 200);

} catch (Exception $e) {
    responderErrorJSON("Error al obtener historial", 500);
}
?>
