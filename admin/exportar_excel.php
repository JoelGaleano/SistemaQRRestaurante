<?php
session_start();
require 'conexion.php';

if (!isset($_SESSION['restaurante_id'])) {
    die("Acceso denegado");
}

$id_restaurante = (int) $_SESSION['restaurante_id'];
$nombre_restaurante = $_SESSION['restaurante_nombre'];

$nombre_archivo = "Ventas_" . preg_replace('/[^a-zA-Z0-9]/', '_', $nombre_restaurante) . "_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $nombre_archivo);

$salida = fopen('php://output', 'w');

// Firma BOM UTF-8 para compatibilidad con Microsoft Excel
fputs($salida, (chr(0xEF) . chr(0xBB) . chr(0xBF)));

// 1. Encabezados incluyendo el Mozo y el Detalle de Productos
fputcsv($salida, [
    'ID Pedido',
    'N° Mesa',
    'Mozo que Tomó el Pedido',
    'Detalle del Pedido',
    'Fecha y Hora',
    'Estado',
    'Total Facturado ($)',
    'Turno (ID Cierre)'
], ';');

try {
    $stmt = $pdo->prepare("
        SELECT 
            p.id, 
            m.numero_mesa, 
            p.nombre_mozo, 
            p.fecha_creacion, 
            p.estado, 
            p.total, 
            p.cargo_servicio_mesa,
            p.id_cierre,
            GROUP_CONCAT(CONCAT(i.cantidad, 'x ', pr.nombre) SEPARATOR ' | ') AS detalle_items
        FROM pedidos p
        JOIN mesas m ON p.id_mesa = m.id
        LEFT JOIN pedidos_items i ON i.id_pedido = p.id
        LEFT JOIN productos pr ON i.id_producto = pr.id
        WHERE p.id_restaurante = ? AND p.estado IN ('servido', 'cancelado')
        GROUP BY p.id, m.numero_mesa, p.nombre_mozo, p.fecha_creacion, p.estado, p.total, p.cargo_servicio_mesa, p.id_cierre
        ORDER BY p.fecha_creacion DESC
    ");
    $stmt->execute([$id_restaurante]);
    $pedidos = $stmt->fetchAll();

    foreach ($pedidos as $pedido) {
        $id_cierre = $pedido['id_cierre'] ? "Turno #" . $pedido['id_cierre'] : "Caja Abierta";
        $mozo      = !empty($pedido['nombre_mozo']) ? $pedido['nombre_mozo'] : 'Sin asignar';
        $detalle   = $pedido['detalle_items'] ?: 'Sin productos';
        if ((float) $pedido['cargo_servicio_mesa'] > 0) {
            $detalle .= ' | + Servicio de Mesa';
        }
        $total = number_format((float)$pedido['total'], 2, ',', '');

        fputcsv($salida, [
            $pedido['id'],
            "Mesa " . $pedido['numero_mesa'],
            $mozo,
            $detalle,
            $pedido['fecha_creacion'],
            ucfirst($pedido['estado']),
            $total,
            $id_cierre
        ], ';');
    }

} catch (Exception $e) {
    fputcsv($salida, ['Error al exportar los datos']);
}

fclose($salida);
exit;
?>
