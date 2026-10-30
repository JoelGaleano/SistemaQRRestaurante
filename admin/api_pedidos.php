<?php
require 'conexion.php';
require 'seguridad.php';

$id_restaurante = autenticarRestaurante($pdo);

try {
    $pedidosCompletos = obtenerPedidosActivos($pdo, $id_restaurante);
    $cuentasMesas     = obtenerCuentasMesasAbiertas($pdo, $id_restaurante);

    responderJSON([
        "status"        => "success",
        "code"          => 200,
        "data"          => $pedidosCompletos,
        "cuentas_mesas" => $cuentasMesas
    ], 200);

} catch (Exception $e) {
    responderErrorJSON("Error al obtener pedidos activos", 500);
}
?>
