<?php
// admin/sse_pedidos.php - Server-Sent Events (SSE) nativos para el Panel de Cocina y Cuentas de Mesa
require 'conexion.php';
require 'seguridad.php';

$id_restaurante = autenticarRestaurante($pdo);

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);
set_time_limit(0);
ignore_user_abort(false);

echo "retry: 2000\n\n";
flush();

$ultimoHash = '';
$iteraciones = 0;
$maxIteraciones = 25;

while ($iteraciones < $maxIteraciones && !connection_aborted()) {
    try {
        $pedidos      = obtenerPedidosActivos($pdo, $id_restaurante);
        $cuentasMesas = obtenerCuentasMesasAbiertas($pdo, $id_restaurante);

        $payload = json_encode([
            'status'        => 'success',
            'code'          => 200,
            'data'          => $pedidos,
            'cuentas_mesas' => $cuentasMesas
        ], JSON_UNESCAPED_UNICODE);

        $hashActual = md5($payload);

        if ($hashActual !== $ultimoHash) {
            $ultimoHash = $hashActual;
            echo "event: pedidos\n";
            echo "data: {$payload}\n\n";
            flush();
        } else {
            echo ": heartbeat " . time() . "\n\n";
            flush();
        }
    } catch (Exception $e) {
        $errPayload = json_encode([
            'status'  => 'error',
            'code'    => 500,
            'message' => 'Error al consultar pedidos en tiempo real'
        ], JSON_UNESCAPED_UNICODE);
        echo "event: error_servidor\n";
        echo "data: {$errPayload}\n\n";
        flush();
        break;
    }

    $iteraciones++;
    sleep(2);
}
?>
