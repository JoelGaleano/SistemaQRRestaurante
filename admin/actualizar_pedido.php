<?php
require 'conexion.php';
require 'seguridad.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErrorJSON("Método no permitido", 405);
}

$id_restaurante = autenticarRestaurante($pdo);
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    responderErrorJSON("Datos JSON inválidos", 400);
}

$accion = $input['accion'] ?? 'cambiar_estado_pedido';

try {
    // ACCIÓN A: Cobrar y cerrar la cuenta acumulada de toda una mesa (la libera a $0 para el siguiente cliente)
    if ($accion === 'cerrar_cuenta_mesa') {
        $id_mesa = (int) ($input['id_mesa'] ?? 0);
        if ($id_mesa <= 0) {
            responderErrorJSON("ID de mesa inválido", 400);
        }

        $stmt = $pdo->prepare("
            UPDATE pedidos 
            SET estado = 'servido', cuenta_cerrada = 1, pidio_cuenta = 0 
            WHERE id_restaurante = ? AND id_mesa = ? AND cuenta_cerrada = 0 AND estado != 'cancelado'
        ");
        $stmt->execute([$id_restaurante, $id_mesa]);

        responderJSON([
            "status"  => "success",
            "code"    => 200,
            "message" => "Cuenta de la mesa cobrada y cerrada correctamente",
            "id_mesa" => $id_mesa
        ], 200);
    }

    // ACCIÓN B: Cambiar estado de una comanda individual de cocina ('preparando', 'servido', 'cancelado')
    if (empty($input['id_pedido']) || empty($input['estado'])) {
        responderErrorJSON("Datos inválidos: se requiere id_pedido y estado", 400);
    }

    $id_pedido    = (int) $input['id_pedido'];
    $nuevo_estado = $input['estado'];
    $nombre_mozo  = trim($input['nombre_mozo'] ?? '');

    $estadosPermitidos = ['pendiente', 'preparando', 'servido', 'cancelado'];
    if (!in_array($nuevo_estado, $estadosPermitidos, true)) {
        responderErrorJSON("Estado de pedido no válido", 400);
    }

    if ($nuevo_estado === 'cancelado') {
        $pdo->beginTransaction();

        // Obtener datos del pedido antes de cancelar (por si tenía el cargo de servicio de mesa)
        $stmtInfo = $pdo->prepare("
            SELECT id_mesa, cargo_servicio_mesa 
            FROM pedidos 
            WHERE id = ? AND id_restaurante = ? 
            FOR UPDATE
        ");
        $stmtInfo->execute([$id_pedido, $id_restaurante]);
        $pedidoActual = $stmtInfo->fetch();

        if (!$pedidoActual) {
            $pdo->rollBack();
            responderErrorJSON("Pedido no encontrado", 404);
        }

        $idMesaCancelar = (int) $pedidoActual['id_mesa'];
        $servicioCancelar = (float) $pedidoActual['cargo_servicio_mesa'];

        // Marcar el pedido como cancelado y cerrado para que no sume en la cuenta de la mesa ni quede en cocina
        $stmtCancel = $pdo->prepare("
            UPDATE pedidos 
            SET estado = 'cancelado', cuenta_cerrada = 1, pidio_cuenta = 0, cargo_servicio_mesa = 0, total = total - ?
            WHERE id = ? AND id_restaurante = ?
        ");
        $stmtCancel->execute([$servicioCancelar, $id_pedido, $id_restaurante]);

        // Si este pedido tenía el cargo de servicio de mesa y quedan otros pedidos abiertos en la misma mesa, transferir el cargo
        if ($servicioCancelar > 0 && $idMesaCancelar > 0) {
            $stmtOtro = $pdo->prepare("
                SELECT id FROM pedidos
                WHERE id_restaurante = ? AND id_mesa = ? AND cuenta_cerrada = 0 AND estado != 'cancelado' AND id != ?
                ORDER BY id ASC
                LIMIT 1
                FOR UPDATE
            ");
            $stmtOtro->execute([$id_restaurante, $idMesaCancelar, $id_pedido]);
            $otroPedidoId = (int) $stmtOtro->fetchColumn();

            if ($otroPedidoId > 0) {
                $stmtTransf = $pdo->prepare("
                    UPDATE pedidos
                    SET cargo_servicio_mesa = cargo_servicio_mesa + ?, total = total + ?
                    WHERE id = ? AND id_restaurante = ?
                ");
                $stmtTransf->execute([$servicioCancelar, $servicioCancelar, $otroPedidoId, $id_restaurante]);
            }
        }

        $pdo->commit();
    } elseif ($nombre_mozo !== '') {
        // Actualizar estado y asignar el nombre del mozo que aceptó el pedido
        $stmt = $pdo->prepare("UPDATE pedidos SET estado = ?, nombre_mozo = ? WHERE id = ? AND id_restaurante = ?");
        $stmt->execute([$nuevo_estado, $nombre_mozo, $id_pedido, $id_restaurante]);

        // Si en la misma cuenta abierta de esa mesa había otra tanda sin mozo asignado, completarlo también
        $stmtMesa = $pdo->prepare("SELECT id_mesa FROM pedidos WHERE id = ? AND id_restaurante = ?");
        $stmtMesa->execute([$id_pedido, $id_restaurante]);
        $idMesaPedido = (int) $stmtMesa->fetchColumn();
        if ($idMesaPedido > 0) {
            $stmtProp = $pdo->prepare("
                UPDATE pedidos 
                SET nombre_mozo = ? 
                WHERE id_restaurante = ? AND id_mesa = ? AND cuenta_cerrada = 0 AND (nombre_mozo IS NULL OR nombre_mozo = '')
            ");
            $stmtProp->execute([$nombre_mozo, $id_restaurante, $idMesaPedido]);
        }
    } else {
        $stmt = $pdo->prepare("UPDATE pedidos SET estado = ? WHERE id = ? AND id_restaurante = ?");
        $stmt->execute([$nuevo_estado, $id_pedido, $id_restaurante]);
    }

    responderJSON([
        "status"      => "success",
        "code"        => 200,
        "message"     => "Estado actualizado correctamente",
        "id_pedido"   => $id_pedido,
        "estado"      => $nuevo_estado,
        "nombre_mozo" => $nombre_mozo
    ], 200);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    responderErrorJSON("Error al actualizar el pedido", 500);
}
?>
