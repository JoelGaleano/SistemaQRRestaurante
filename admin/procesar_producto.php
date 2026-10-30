<?php
require 'conexion.php';
require 'seguridad.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderErrorJSON("Método no permitido", 405);
}

$id_restaurante = autenticarRestaurante($pdo);

$rawInput = file_get_contents('php://input');
$jsonInput = !empty($rawInput) ? json_decode($rawInput, true) : null;

$id_categoria = (int) ($jsonInput['id_categoria'] ?? $_POST['id_categoria'] ?? 0);
$nombre       = trim($jsonInput['nombre'] ?? $_POST['nombre'] ?? '');
$descripcion  = trim($jsonInput['descripcion'] ?? $_POST['descripcion'] ?? '');
$precio       = (float) ($jsonInput['precio'] ?? $_POST['precio'] ?? 0);

if ($nombre === '' || $precio <= 0) {
    if (esPeticionAPI()) {
        responderErrorJSON("Datos de producto inválidos (nombre y precio requeridos).", 400);
    }
    die("Datos de producto inválidos.");
}

// 1. Validar que la categoría seleccionada realmente pertenezca a este restaurante
$stmtCheck = $pdo->prepare("SELECT id FROM categorias WHERE id = ? AND id_restaurante = ?");
$stmtCheck->execute([$id_categoria, $id_restaurante]);
if ($stmtCheck->rowCount() === 0) {
    if (esPeticionAPI()) {
        responderErrorJSON("Categoría inválida o no pertenece al restaurante.", 403);
    }
    die("Categoría inválida.");
}

// 2. Procesar la Imagen de forma segura (validación MIME + dimensiones + tamaño)
$url_imagen = null;

if (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
    try {
        $url_imagen = subirImagenSegura($_FILES['foto'], 'productos', 'prod_' . $id_restaurante);
    } catch (Exception $e) {
        if (esPeticionAPI()) {
            responderErrorJSON($e->getMessage(), 400);
        }
        die("Error en la imagen: " . htmlspecialchars($e->getMessage()));
    }
}

// 3. Insertar el producto en la Base de Datos
$stmt = $pdo->prepare("INSERT INTO productos (id_categoria, nombre, descripcion, precio, url_imagen) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([$id_categoria, $nombre, $descripcion, $precio, $url_imagen]);
$id_producto = (int) $pdo->lastInsertId();

if (esPeticionAPI()) {
    responderJSON([
        'status'      => 'success',
        'code'        => 201,
        'message'     => 'Producto creado correctamente',
        'id_producto' => $id_producto,
        'url_imagen'  => $url_imagen
    ], 201);
}

header("Location: menu.php");
exit;
?>
