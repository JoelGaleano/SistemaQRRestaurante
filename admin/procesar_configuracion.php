<?php
require 'conexion.php';
require 'seguridad.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErrorJSON("Método no permitido", 405);
}

$id_restaurante = autenticarRestaurante($pdo);

$rawInput = file_get_contents('php://input');
$jsonInput = !empty($rawInput) ? json_decode($rawInput, true) : null;

$nombre               = trim($jsonInput['nombre'] ?? $_POST['nombre'] ?? '');
$telefono             = trim($jsonInput['telefono'] ?? $_POST['telefono'] ?? '');
$direccion            = trim($jsonInput['direccion'] ?? $_POST['direccion'] ?? '');
$cobra_servicio_mesa  = !empty($jsonInput['cobra_servicio_mesa'] ?? $_POST['cobra_servicio_mesa'] ?? 0) ? 1 : 0;
$precio_servicio_mesa = max(0, (float) ($jsonInput['precio_servicio_mesa'] ?? $_POST['precio_servicio_mesa'] ?? 0));

if ($cobra_servicio_mesa === 0) {
    $precio_servicio_mesa = 0.00;
}

if ($nombre === '') {
    if (esPeticionAPI()) {
        responderErrorJSON("El nombre del restaurante es obligatorio.", 400);
    }
    die("El nombre del restaurante es obligatorio.");
}

$logo_query = "";
$params = [$nombre, $telefono, $direccion, $cobra_servicio_mesa, $precio_servicio_mesa, $id_restaurante];
$ruta_relativa = null;

if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
    try {
        $ruta_relativa = subirImagenSegura($_FILES['logo'], 'logos', 'logo_' . $id_restaurante);

        $stmtOld = $pdo->prepare("SELECT logo FROM restaurantes WHERE id = ?");
        $stmtOld->execute([$id_restaurante]);
        $oldLogo = $stmtOld->fetchColumn();
        if ($oldLogo && file_exists(dirname(__DIR__) . '/' . $oldLogo)) {
            @unlink(dirname(__DIR__) . '/' . $oldLogo);
        }

        $logo_query = ", logo = ?";
        $params = [$nombre, $telefono, $direccion, $cobra_servicio_mesa, $precio_servicio_mesa, $ruta_relativa, $id_restaurante];
    } catch (Exception $e) {
        if (esPeticionAPI()) {
            responderErrorJSON($e->getMessage(), 400);
        }
        die("Error en el logo: " . htmlspecialchars($e->getMessage()));
    }
}

try {
    $stmt = $pdo->prepare("UPDATE restaurantes SET nombre = ?, telefono = ?, direccion = ?, cobra_servicio_mesa = ?, precio_servicio_mesa = ? $logo_query WHERE id = ?");
    $stmt->execute($params);

    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['restaurante_nombre'] = $nombre;
    }

    if (esPeticionAPI()) {
        responderJSON([
            'status'               => 'success',
            'code'                 => 200,
            'message'              => 'Configuración actualizada correctamente',
            'cobra_servicio_mesa'  => (bool) $cobra_servicio_mesa,
            'precio_servicio_mesa' => $precio_servicio_mesa,
            'logo'                 => $ruta_relativa
        ], 200);
    }
} catch (PDOException $e) {
    if (esPeticionAPI()) {
        responderErrorJSON("Error al actualizar la configuración.", 500);
    }
}

header("Location: configuracion.php");
exit;
?>
