<?php
require 'conexion.php';
require 'seguridad.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErrorJSON("Método no permitido", 405);
}

$id_restaurante = autenticarRestaurante($pdo);

try {
    $pdo->beginTransaction();

    // 1. Obtener los totales exactos ANTES de cerrar (usa idx_pedidos_rest_cierre_estado)
    $stmtTotales = $pdo->prepare("
        SELECT COUNT(id) as cantidad_pedidos, COALESCE(SUM(total), 0) as total_caja 
        FROM pedidos 
        WHERE id_restaurante = ? AND estado = 'servido' AND cuenta_cerrada = 1 AND id_cierre IS NULL
        FOR UPDATE
    ");
    $stmtTotales->execute([$id_restaurante]);
    $totales = $stmtTotales->fetch();

    $cantidad_pedidos = (int) ($totales['cantidad_pedidos'] ?? 0);
    $total_recaudado  = (float) ($totales['total_caja'] ?? 0);

    if ($cantidad_pedidos === 0) {
        $pdo->rollBack();
        if (esPeticionAPI()) {
            responderErrorJSON("No hay pedidos servidos pendientes de cierre", 400);
        }
        header("Location: caja.php");
        exit;
    }

    // 2. Insertar el registro del nuevo cierre en la tabla 'cierres_caja'
    $stmtInsertCierre = $pdo->prepare("
        INSERT INTO cierres_caja (id_restaurante, total_recaudado, cantidad_pedidos) 
        VALUES (?, ?, ?)
    ");
    $stmtInsertCierre->execute([$id_restaurante, $total_recaudado, $cantidad_pedidos]);
    $id_nuevo_cierre = (int) $pdo->lastInsertId();

    // 3. Vincular los pedidos sueltos a este nuevo cierre
    $stmtUpdatePedidos = $pdo->prepare("
        UPDATE pedidos 
        SET id_cierre = ? 
        WHERE id_restaurante = ? AND estado = 'servido' AND cuenta_cerrada = 1 AND id_cierre IS NULL
    ");
    $stmtUpdatePedidos->execute([$id_nuevo_cierre, $id_restaurante]);

    $pdo->commit();

    if (esPeticionAPI()) {
        responderJSON([
            'status'           => 'success',
            'code'             => 200,
            'message'          => 'Caja cerrada correctamente',
            'id_cierre'        => $id_nuevo_cierre,
            'cantidad_pedidos' => $cantidad_pedidos,
            'total_recaudado'  => $total_recaudado
        ], 200);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (esPeticionAPI()) {
        responderErrorJSON("Error al procesar el cierre de caja", 500);
    }
}

header("Location: caja.php");
exit;
?>
