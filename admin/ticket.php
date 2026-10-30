<?php
session_start();
require 'conexion.php';

if (!isset($_SESSION['restaurante_id'])) {
    die("Acceso denegado");
}

$id_restaurante = (int) $_SESSION['restaurante_id'];
$modo           = $_GET['modo'] ?? 'comanda';
$id_mesa        = (int) ($_GET['id_mesa'] ?? 0);
$id_pedido      = (int) ($_GET['id'] ?? 0);

try {
    if ($modo === 'cuenta' && $id_mesa > 0) {
        // MODO A: Ticket Final de Cuenta Acumulada de la Mesa (suma todas las rondas abiertas + Servicio de Mesa)
        $stmtInfo = $pdo->prepare("
            SELECT m.numero_mesa, r.nombre AS restaurante, r.direccion
            FROM mesas m
            JOIN restaurantes r ON m.id_restaurante = r.id
            WHERE m.id = ? AND m.id_restaurante = ?
        ");
        $stmtInfo->execute([$id_mesa, $id_restaurante]);
        $cabecera = $stmtInfo->fetch();
        if (!$cabecera) {
            die("Mesa no encontrada.");
        }

        $stmtPedidos = $pdo->prepare("
            SELECT id, total, cargo_servicio_mesa, nombre_mozo, fecha_creacion
            FROM pedidos
            WHERE id_restaurante = ? AND id_mesa = ? AND cuenta_cerrada = 0 AND estado != 'cancelado'
            ORDER BY id ASC
        ");
        $stmtPedidos->execute([$id_restaurante, $id_mesa]);
        $pedidosMesa = $stmtPedidos->fetchAll();

        if (empty($pedidosMesa)) {
            die("No hay consumos abiertos en esta mesa.");
        }

        $totalGeneral = 0.00;
        $servicioMesa = 0.00;
        $mozoTicket   = '';
        foreach ($pedidosMesa as $pm) {
            $totalGeneral += (float) $pm['total'];
            $servicioMesa += (float) $pm['cargo_servicio_mesa'];
            if (!empty($pm['nombre_mozo'])) {
                $mozoTicket = $pm['nombre_mozo'];
            }
        }

        $stmtItems = $pdo->prepare("
            SELECT pr.nombre, SUM(i.cantidad) AS cantidad, i.precio_unitario
            FROM pedidos_items i
            JOIN pedidos p ON i.id_pedido = p.id
            JOIN productos pr ON i.id_producto = pr.id
            WHERE p.id_restaurante = ? AND p.id_mesa = ? AND p.cuenta_cerrada = 0 AND p.estado != 'cancelado'
            GROUP BY pr.id, pr.nombre, i.precio_unitario
            ORDER BY pr.nombre ASC
        ");
        $stmtItems->execute([$id_restaurante, $id_mesa]);
        $items = $stmtItems->fetchAll();

        $tituloTicket   = "CUENTA MESA " . $cabecera['numero_mesa'];
        $subtituloOrden = count($pedidosMesa) . " comanda(s) acumulada(s)";
        $fechaTicket    = date('d/m/Y H:i');
    } else {
        // MODO B: Ticket de Comanda Individual de Cocina
        $stmtPedido = $pdo->prepare("
            SELECT p.id, p.fecha_creacion, p.total, p.cargo_servicio_mesa, p.nombre_mozo, m.numero_mesa, r.nombre AS restaurante, r.direccion 
            FROM pedidos p
            JOIN mesas m ON p.id_mesa = m.id
            JOIN restaurantes r ON p.id_restaurante = r.id
            WHERE p.id = ? AND p.id_restaurante = ?
        ");
        $stmtPedido->execute([$id_pedido, $id_restaurante]);
        $cabecera = $stmtPedido->fetch();

        if (!$cabecera) {
            die("Pedido no encontrado.");
        }

        $stmtItems = $pdo->prepare("
            SELECT i.cantidad, i.precio_unitario, pr.nombre 
            FROM pedidos_items i
            JOIN productos pr ON i.id_producto = pr.id
            WHERE i.id_pedido = ?
        ");
        $stmtItems->execute([$id_pedido]);
        $items = $stmtItems->fetchAll();

        $totalGeneral   = (float) $cabecera['total'];
        $servicioMesa   = (float) $cabecera['cargo_servicio_mesa'];
        $mozoTicket     = $cabecera['nombre_mozo'] ?? '';
        $tituloTicket   = "MESA " . $cabecera['numero_mesa'];
        $subtituloOrden = "Comanda #" . $cabecera['id'];
        $fechaTicket    = date('d/m/Y H:i', strtotime($cabecera['fecha_creacion']));
    }
} catch (Exception $e) {
    die("Error al generar el ticket.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($tituloTicket) ?></title>
    <style>
        @page { margin: 0; }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 13px;
            color: #000;
            background: #fff;
            width: 58mm;
            margin: 0 auto;
            padding: 10px;
        }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .divider { border-top: 1px dashed #000; margin: 9px 0; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        td { padding: 3px 0; vertical-align: top; }
        .col-cant { width: 15%; text-align: left; font-weight: bold; }
        .col-desc { width: 55%; text-align: left; }
        .col-precio { width: 30%; text-align: right; }
    </style>
</head>
<body>

    <div class="center">
        <h2 style="margin: 0; font-size: 17px;"><?= htmlspecialchars($cabecera['restaurante']) ?></h2>
        <p style="margin: 4px 0 0 0; font-size: 11px;"><?= htmlspecialchars($cabecera['direccion']) ?></p>
    </div>

    <div class="divider"></div>

    <div class="center bold" style="font-size: 15px;">
        <?= htmlspecialchars($tituloTicket) ?>
    </div>

    <div style="font-size: 11px; margin-top: 5px;">
        <div>Detalle: <?= htmlspecialchars($subtituloOrden) ?></div>
        <?php if (!empty($mozoTicket)): ?>
            <div>Mozo: <?= htmlspecialchars($mozoTicket) ?></div>
        <?php endif; ?>
        <div>Fecha: <?= htmlspecialchars($fechaTicket) ?></div>
    </div>

    <div class="divider"></div>

    <table>
        <?php foreach ($items as $item): ?>
            <tr>
                <td class="col-cant"><?= (int)$item['cantidad'] ?>x</td>
                <td class="col-desc"><?= htmlspecialchars($item['nombre']) ?></td>
                <td class="col-precio">$<?= number_format((float)$item['precio_unitario'] * (int)$item['cantidad'], 2, ',', '.') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($servicioMesa > 0): ?>
            <tr>
                <td class="col-cant">1x</td>
                <td class="col-desc">Servicio de Mesa</td>
                <td class="col-precio">$<?= number_format($servicioMesa, 2, ',', '.') ?></td>
            </tr>
        <?php endif; ?>
    </table>

    <div class="divider"></div>

    <div style="text-align: right; font-size: 15px; font-weight: bold;">
        TOTAL: $<?= number_format($totalGeneral, 2, ',', '.') ?>
    </div>

    <div class="divider"></div>

    <div class="center" style="font-size: 11px;">
        ¡Gracias por su visita!
    </div>

    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
