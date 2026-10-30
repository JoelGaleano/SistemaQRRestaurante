<?php
require '../vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

$hash = $_GET['hash'] ?? '';

if ($hash === '' || !preg_match('/^[a-zA-Z0-9_-]{3,80}$/', $hash)) {
    http_response_code(400);
    die("Hash inválido o no proporcionado");
}

// Construir dinámicamente la URL amigable del cliente (ej: http://localhost/resto/m/<hash>)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');

$url_cliente = "{$scheme}://{$host}{$basePath}/m/" . rawurlencode($hash);

// Crear el QR con la URL amigable del cliente
$qrCode = QrCode::create($url_cliente)
    ->setSize(300)
    ->setMargin(10);

$writer = new PngWriter();
$resultado = $writer->write($qrCode);

// Si se pidió descargar, forzamos la descarga
if (isset($_GET['descargar']) && $_GET['descargar'] == '1') {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="qr_mesa.png"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
} else {
    header('Content-Type: ' . $resultado->getMimeType());
}

echo $resultado->getString();
?>
