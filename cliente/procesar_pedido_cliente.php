<?php
header('Content-Type: application/json; charset=utf-8');
require '../admin/conexion.php';
require '../admin/seguridad.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErrorJSON('Método no permitido', 405);
}

// Rate limit: máx 10 envíos de pedido por minuto por IP
verificarRateLimit($pdo, 'crear_pedido_cliente', 10, 60);

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['items']) || !is_array($input['items'])) {
    responderErrorJSON('Datos de pedido incompletos', 400);
}

$id_restaurante = (int) ($input['id_restaurante'] ?? 0);
$id_mesa        = (int) ($input['id_mesa'] ?? 0);
$items          = $input['items'];

try {
    // 1. Verificar que la mesa exista y obtener configuración de servicio de mesa del restaurante
    $stmtMesa = $pdo->prepare("
        SELECT m.id, r.cobra_servicio_mesa, r.precio_servicio_mesa
        FROM mesas m
        JOIN restaurantes r ON m.id_restaurante = r.id
        WHERE m.id = ? AND m.id_restaurante = ?
        LIMIT 1
    ");
    $stmtMesa->execute([$id_mesa, $id_restaurante]);
    $datosMesaRest = $stmtMesa->fetch();

    if (!$datosMesaRest) {
        responderErrorJSON('Mesa o restaurante inválido', 403);
    }

    $pdo->beginTransaction();

    // 2. Verificar si la mesa ya tiene una cuenta abierta (pedidos activos con cuenta_cerrada = 0)
    $stmtAbiertos = $pdo->prepare("
        SELECT COUNT(id) AS cant_pedidos, COALESCE(SUM(cargo_servicio_mesa), 0) AS servicio_ya_cobrado
        FROM pedidos
        WHERE id_restaurante = ? AND id_mesa = ? AND cuenta_cerrada = 0 AND estado != 'cancelado'
        FOR UPDATE
    ");
    $stmtAbiertos->execute([$id_restaurante, $id_mesa]);
    $estadoCuenta = $stmtAbiertos->fetch();

    $cargo_servicio_mesa = 0.00;
    if (
        (int) $datosMesaRest['cobra_servicio_mesa'] === 1 &&
        (float) $datosMesaRest['precio_servicio_mesa'] > 0 &&
        (float) ($estadoCuenta['servicio_ya_cobrado'] ?? 0) == 0.00
    ) {
        // Se cobra el servicio de mesa una única vez al abrir la cuenta de la mesa
        $cargo_servicio_mesa = (float) $datosMesaRest['precio_servicio_mesa'];
    }

    // 3. Validar cada producto en BD (que pertenezca al restaurante, esté disponible y tomar su precio real)
    $stmtProd = $pdo->prepare("
        SELECT p.id, p.nombre, p.precio, p.disponible
        FROM productos p
        JOIN categorias c ON p.id_categoria = c.id
        WHERE p.id = ? AND c.id_restaurante = ?
        FOR UPDATE
    ");

    $subtotal_items = 0.00;
    $items_validados = [];

    foreach ($items as $item) {
        $id_producto = (int) ($item['id'] ?? 0);
        $cantidad    = (int) ($item['cantidad'] ?? 0);

        if ($id_producto <= 0 || $cantidad <= 0 || $cantidad > 50) {
            throw new Exception("Cantidad inválida en uno de los productos.");
        }

        $stmtProd->execute([$id_producto, $id_restaurante]);
        $prodDB = $stmtProd->fetch();

        if (!$prodDB) {
            throw new Exception("Uno de los productos seleccionados no existe.");
        }
        if ((int) $prodDB['disponible'] !== 1) {
            throw new Exception("El producto '{$prodDB['nombre']}' se acaba de agotar.");
        }

        $precio_real = (float) $prodDB['precio'];
        $subtotal_items += $precio_real * $cantidad;

        $items_validados[] = [
            'id_producto'     => (int) $prodDB['id'],
            'nombre'          => $prodDB['nombre'],
            'cantidad'        => $cantidad,
            'precio_unitario' => $precio_real
        ];
    }

    $total_pedido = $subtotal_items + $cargo_servicio_mesa;

    // 4. Insertar cabecera de esta tanda/pedido dentro de la cuenta abierta de la mesa (cuenta_cerrada = 0)
    $stmtPedido = $pdo->prepare("
        INSERT INTO pedidos (id_restaurante, id_mesa, estado, total, cargo_servicio_mesa, pidio_cuenta, cuenta_cerrada) 
        VALUES (?, ?, 'pendiente', ?, ?, 0, 0)
    ");
    $stmtPedido->execute([$id_restaurante, $id_mesa, $total_pedido, $cargo_servicio_mesa]);
    $id_pedido = (int) $pdo->lastInsertId();

    // Si la mesa había pedido la cuenta antes y decidió agregar algo más, reactivamos la cuenta (pidio_cuenta = 0)
    $stmtReactivar = $pdo->prepare("
        UPDATE pedidos SET pidio_cuenta = 0 
        WHERE id_restaurante = ? AND id_mesa = ? AND cuenta_cerrada = 0
    ");
    $stmtReactivar->execute([$id_restaurante, $id_mesa]);

    // 5. Insertar los ítems del pedido
    $stmtItem = $pdo->prepare("
        INSERT INTO pedidos_items (id_pedido, id_producto, cantidad, precio_unitario) 
        VALUES (?, ?, ?, ?)
    ");
    foreach ($items_validados as $iv) {
        $stmtItem->execute([
            $id_pedido,
            $iv['id_producto'],
            $iv['cantidad'],
            $iv['precio_unitario']
        ]);
    }

    $pdo->commit();

    // 6. Obtener el total acumulado actualizado de toda la cuenta abierta de la mesa
    $stmtTotalAcumulado = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) AS total_acumulado
        FROM pedidos
        WHERE id_restaurante = ? AND id_mesa = ? AND cuenta_cerrada = 0 AND estado != 'cancelado'
    ");
    $stmtTotalAcumulado->execute([$id_restaurante, $id_mesa]);
    $total_acumulado_mesa = (float) $stmtTotalAcumulado->fetchColumn();

    responderJSON([
        'status'               => 'success',
        'code'                 => 200,
        'id_pedido'            => $id_pedido,
        'total'                => $total_pedido,
        'cargo_servicio_mesa'  => $cargo_servicio_mesa,
        'total_acumulado_mesa' => $total_acumulado_mesa
    ], 200);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    responderErrorJSON($e->getMessage(), 400);
}
?>
