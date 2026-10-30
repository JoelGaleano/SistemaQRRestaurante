<?php
header('Content-Type: text/html; charset=utf-8');
require '../admin/conexion.php';

// Calcular la ruta base del proyecto (ej: "/resto/") para que las URLs amigables (/m/hash o /carta/alias)
// resuelvan siempre bien las imágenes y el endpoint fetch sin importar la profundidad de la URL.
$scriptDir = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'])));
$basePath = rtrim($scriptDir, '/') . '/';

// Obtener el hash de la mesa o el alias del restaurante desde la URL amigable
$hash  = trim($_GET['m'] ?? '');
$alias = trim($_GET['alias'] ?? '');

if ($hash !== '' && !preg_match('/^[a-zA-Z0-9_-]{3,80}$/', $hash)) {
    http_response_code(400);
    die("<h2 style='color:#ff4d4d; background:#070908; font-family:sans-serif; text-align:center; padding:50px;'>Error: Identificador de mesa inválido.</h2>");
}

if ($alias !== '' && !preg_match('/^[a-zA-Z0-9_-]{2,60}$/', $alias)) {
    http_response_code(400);
    die("<h2 style='color:#ff4d4d; background:#070908; font-family:sans-serif; text-align:center; padding:50px;'>Error: Enlace de restaurante inválido.</h2>");
}

$info = null;

if ($hash !== '') {
    $stmt = $pdo->prepare("
        SELECT m.id AS id_mesa, m.numero_mesa, m.hash_qr, 
               r.id AS id_restaurante, r.nombre AS restaurante_nombre, r.logo, r.direccion, r.telefono,
               r.cobra_servicio_mesa, r.precio_servicio_mesa
        FROM mesas m 
        JOIN restaurantes r ON m.id_restaurante = r.id 
        WHERE m.hash_qr = ?
        LIMIT 1
    ");
    $stmt->execute([$hash]);
    $info = $stmt->fetch();
} elseif ($alias !== '') {
    $stmt = $pdo->prepare("
        SELECT m.id AS id_mesa, m.numero_mesa, m.hash_qr, 
               r.id AS id_restaurante, r.nombre AS restaurante_nombre, r.logo, r.direccion, r.telefono,
               r.cobra_servicio_mesa, r.precio_servicio_mesa
        FROM restaurantes r
        LEFT JOIN mesas m ON m.id_restaurante = r.id
        WHERE r.alias = ?
        ORDER BY m.numero_mesa ASC
        LIMIT 1
    ");
    $stmt->execute([$alias]);
    $info = $stmt->fetch();
    if ($info && !empty($info['hash_qr'])) {
        $hash = $info['hash_qr'];
    }
}

if (!$info || empty($info['id_mesa'])) {
    http_response_code(404);
    die("<h2 style='color:#ff4d4d; background:#070908; font-family:sans-serif; text-align:center; padding:50px;'>Error: La mesa o el restaurante solicitado no existe.</h2>");
}

$id_restaurante       = (int) $info['id_restaurante'];
$id_mesa              = (int) $info['id_mesa'];
$numero_mesa          = (int) $info['numero_mesa'];
$nombre_restaurante   = $info['restaurante_nombre'];
$direccion_rest       = $info['direccion'] ?? '';
$telefono_rest        = $info['telefono'] ?? '';
$cobra_servicio_mesa  = (int) ($info['cobra_servicio_mesa'] ?? 0) === 1;
$precio_servicio_mesa = $cobra_servicio_mesa ? (float) ($info['precio_servicio_mesa'] ?? 0) : 0.00;

// Generar iniciales por si el restaurante no subió logo personalizado
$palabras = preg_split('/\s+/', trim($nombre_restaurante));
$iniciales = strtoupper(mb_substr($palabras[0] ?? 'R', 0, 1) . (isset($palabras[1]) ? mb_substr($palabras[1], 0, 1) : ''));

// =========================================================================
// OBTENER LA CUENTA ABIERTA ACTUAL DE ESTA MESA (cuenta_cerrada = 0)
// =========================================================================
$stmtCuenta = $pdo->prepare("
    SELECT id, estado, total, cargo_servicio_mesa, pidio_cuenta, DATE_FORMAT(fecha_creacion, '%H:%i') AS hora
    FROM pedidos
    WHERE id_restaurante = ? AND id_mesa = ? AND cuenta_cerrada = 0 AND estado != 'cancelado'
    ORDER BY id ASC
");
$stmtCuenta->execute([$id_restaurante, $id_mesa]);
$pedidosAbiertosMesa = $stmtCuenta->fetchAll();

$itemsConsumidosMesa = [];
$subtotalConsumidoMesa = 0.00;
$servicioCobradoMesa = 0.00;
$totalAcumuladoMesa = 0.00;
$yaPidioCuenta = false;

if (!empty($pedidosAbiertosMesa)) {
    $idsAbiertos = array_column($pedidosAbiertosMesa, 'id');
    $estadosPorPedido = [];
    foreach ($pedidosAbiertosMesa as $pa) {
        $estadosPorPedido[$pa['id']] = [
            'estado' => $pa['estado'],
            'hora'   => $pa['hora']
        ];
        $servicioCobradoMesa += (float) $pa['cargo_servicio_mesa'];
        $totalAcumuladoMesa  += (float) $pa['total'];
        if ((int) $pa['pidio_cuenta'] === 1) {
            $yaPidioCuenta = true;
        }
    }

    $placeholders = implode(',', array_fill(0, count($idsAbiertos), '?'));
    $stmtItemsMesa = $pdo->prepare("
        SELECT i.id_pedido, i.cantidad, i.precio_unitario, pr.nombre
        FROM pedidos_items i
        JOIN productos pr ON i.id_producto = pr.id
        WHERE i.id_pedido IN ($placeholders)
        ORDER BY i.id ASC
    ");
    $stmtItemsMesa->execute($idsAbiertos);
    $filasItems = $stmtItemsMesa->fetchAll();

    foreach ($filasItems as $fi) {
        $sub = (float) $fi['precio_unitario'] * (int) $fi['cantidad'];
        $subtotalConsumidoMesa += $sub;
        $itemsConsumidosMesa[] = [
            'id_pedido'       => (int) $fi['id_pedido'],
            'nombre'          => $fi['nombre'],
            'cantidad'        => (int) $fi['cantidad'],
            'precio_unitario' => (float) $fi['precio_unitario'],
            'subtotal'        => $sub,
            'estado'          => $estadosPorPedido[$fi['id_pedido']]['estado'] ?? 'pendiente',
            'hora'            => $estadosPorPedido[$fi['id_pedido']]['hora'] ?? ''
        ];
    }
}

// Obtener categorías y productos disponibles de este restaurante específico
$stmtMenu = $pdo->prepare("
    SELECT c.id AS id_categoria, c.nombre AS categoria, 
           p.id AS id_producto, p.nombre, p.descripcion, p.precio, p.url_imagen 
    FROM categorias c
    LEFT JOIN productos p ON c.id = p.id_categoria AND p.disponible = 1
    WHERE c.id_restaurante = ?
    ORDER BY c.id ASC, p.id ASC
");
$stmtMenu->execute([$id_restaurante]);
$resultados = $stmtMenu->fetchAll();

$menu = [];
$catalogoProductos = [];

foreach ($resultados as $row) {
    $id_cat = $row['id_categoria'];
    if (!isset($menu[$id_cat])) {
        $menu[$id_cat] = [
            'id'        => $id_cat,
            'nombre'    => $row['categoria'],
            'productos' => []
        ];
    }
    if ($row['id_producto']) {
        $prodData = [
            'id'          => (int) $row['id_producto'],
            'nombre'      => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'precio'      => (float) $row['precio'],
            'imagen'      => $row['url_imagen']
        ];
        $menu[$id_cat]['productos'][] = $prodData;
        $catalogoProductos[$prodData['id']] = $prodData;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($nombre_restaurante) ?> | Carta Digital SmartMenu</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-base: #070908;
            --bg-surface: rgba(18, 22, 20, 0.78);
            --bg-card: rgba(24, 29, 26, 0.72);
            --neon: #39ff14;
            --neon-soft: rgba(57, 255, 20, 0.14);
            --neon-border: rgba(57, 255, 20, 0.38);
            --neon-glow: 0 0 24px rgba(57, 255, 20, 0.28);
            --amber: #f59e0b;
            --amber-soft: rgba(245, 158, 11, 0.15);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border-glass: rgba(255, 255, 255, 0.09);
            --danger: #ff4d4f;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            -webkit-tap-highlight-color: transparent;
            scroll-behavior: smooth;
        }

        body {
            background-color: var(--bg-base);
            color: var(--text-main);
            line-height: 1.5;
            padding-bottom: 130px;
            min-height: 100vh;
            background-image:
                radial-gradient(circle at 15% 10%, rgba(57, 255, 20, 0.12), transparent 32%),
                radial-gradient(circle at 85% 25%, rgba(57, 255, 20, 0.07), transparent 30%),
                radial-gradient(circle at 50% 90%, rgba(57, 255, 20, 0.05), transparent 35%);
        }

        /* 1. Barra Superior de la App SaaS (SmartMenu Branding) */
        .saas-topbar {
            background: rgba(7, 9, 8, 0.88);
            border-bottom: 1px solid var(--border-glass);
            padding: 9px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.76rem;
        }
        .saas-brand {
            color: var(--text-main);
            text-decoration: none;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            letter-spacing: -0.3px;
        }
        .saas-brand-badge {
            width: 22px;
            height: 22px;
            border-radius: 6px;
            background: var(--neon-soft);
            border: 1px solid var(--neon);
            color: var(--neon);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.65rem;
            font-weight: 900;
        }
        .saas-brand span { color: var(--neon); }

        .saas-cta-link {
            color: var(--neon);
            text-decoration: none;
            font-weight: 700;
            background: var(--neon-soft);
            border: 1px solid var(--neon-border);
            padding: 4px 11px;
            border-radius: 999px;
            transition: all 0.2s ease;
        }
        .saas-cta-link:hover {
            background: var(--neon);
            color: #050505;
        }

        /* 2. Hero Card del Restaurante */
        .resto-hero {
            max-width: 720px;
            margin: 18px auto 14px;
            padding: 0 16px;
        }
        .resto-hero-card {
            background: linear-gradient(145deg, rgba(57, 255, 20, 0.1), rgba(18, 22, 20, 0.88));
            border: 1px solid var(--border-glass);
            border-radius: 24px;
            padding: 22px 20px;
            display: flex;
            align-items: center;
            gap: 18px;
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.55);
        }

        .resto-logo-ring {
            width: 84px;
            height: 84px;
            border-radius: 22px;
            border: 2px solid var(--neon);
            background: #0d110f;
            box-shadow: var(--neon-glow);
            flex-shrink: 0;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .resto-logo-ring img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .resto-logo-fallback {
            font-size: 1.75rem;
            font-weight: 900;
            color: var(--neon);
            letter-spacing: -1px;
        }

        .resto-hero-info {
            flex: 1;
            min-width: 0;
        }
        .resto-hero-info h1 {
            font-size: 1.4rem;
            font-weight: 900;
            color: #fff;
            letter-spacing: -0.5px;
            line-height: 1.15;
            margin-bottom: 6px;
        }
        .resto-meta {
            color: var(--text-muted);
            font-size: 0.79rem;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 10px;
        }
        .badges-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        .mesa-badge {
            background: var(--neon);
            color: #050505;
            padding: 5px 14px;
            border-radius: 999px;
            font-weight: 900;
            font-size: 0.76rem;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: var(--neon-glow);
        }
        .servicio-pill {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid var(--border-glass);
            color: #e2e8f0;
            padding: 4px 11px;
            border-radius: 999px;
            font-size: 0.74rem;
            font-weight: 600;
        }

        /* 2.5 Banner de Cuenta Abierta Acumulada de la Mesa */
        .cuenta-abierta-wrap {
            max-width: 720px;
            margin: 0 auto 14px;
            padding: 0 16px;
        }
        .cuenta-abierta-card {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.16), rgba(18, 22, 20, 0.94));
            border: 1px solid rgba(245, 158, 11, 0.5);
            border-radius: 20px;
            padding: 16px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.5);
        }
        .cuenta-abierta-info small {
            color: #fcd34d;
            font-size: 0.74rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
        }
        .cuenta-abierta-info strong {
            font-size: 1.35rem;
            font-weight: 900;
            color: #fff;
        }
        .cuenta-abierta-info span {
            color: var(--text-muted);
            font-size: 0.78rem;
            display: block;
        }
        .cuenta-abierta-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .btn-ver-cuenta {
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
            border: 1px solid var(--border-glass);
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 0.82rem;
            font-weight: 700;
            cursor: pointer;
        }
        .btn-pedir-cuenta {
            background: var(--amber);
            color: #050505;
            border: none;
            padding: 10px 16px;
            border-radius: 12px;
            font-size: 0.84rem;
            font-weight: 900;
            cursor: pointer;
            box-shadow: 0 6px 18px rgba(245, 158, 11, 0.35);
        }

        /* 3. Buscador Rápido + Categorías Sticky */
        .sticky-controls {
            position: sticky;
            top: 0;
            z-index: 30;
            background: rgba(7, 9, 8, 0.86);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border-glass);
            padding: 12px 0;
        }
        .controls-inner {
            max-width: 720px;
            margin: 0 auto;
            padding: 0 16px;
        }
        .search-box {
            position: relative;
            margin-bottom: 10px;
        }
        .search-box input {
            width: 100%;
            padding: 11px 16px 11px 40px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-glass);
            border-radius: 14px;
            color: #fff;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }
        .search-box input:focus {
            outline: none;
            border-color: var(--neon);
            background: rgba(57, 255, 20, 0.04);
            box-shadow: 0 0 0 3px rgba(57, 255, 20, 0.12);
        }
        .search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.9rem;
            color: var(--text-muted);
            pointer-events: none;
        }

        .categorias-nav {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            scrollbar-width: none;
            padding-bottom: 2px;
        }
        .categorias-nav::-webkit-scrollbar { display: none; }

        .cat-pill {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.83rem;
            font-weight: 700;
            white-space: nowrap;
            padding: 7px 15px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--border-glass);
            transition: all 0.2s ease;
        }
        .cat-pill:hover, .cat-pill:active {
            color: #050505;
            background: var(--neon);
            border-color: var(--neon);
            box-shadow: var(--neon-glow);
        }

        /* 4. Listado de Productos */
        main {
            max-width: 720px;
            margin: 0 auto;
            padding: 8px 16px 24px;
        }

        .categoria-seccion {
            scroll-margin-top: 120px;
            margin-top: 22px;
        }

        .categoria-titulo {
            color: var(--text-main);
            font-size: 1.18rem;
            font-weight: 900;
            letter-spacing: -0.4px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .categoria-titulo::after {
            content: "";
            flex: 1;
            height: 1px;
            background: linear-gradient(90deg, var(--neon-border), transparent);
        }

        .producto-card {
            background: var(--bg-card);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 16px;
            margin-bottom: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            border: 1px solid var(--border-glass);
            transition: all 0.22s ease;
            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.35);
        }
        .producto-card.en-carrito {
            border-color: var(--neon);
            background: linear-gradient(145deg, rgba(57, 255, 20, 0.09), rgba(24, 29, 26, 0.88));
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.45), 0 0 18px rgba(57, 255, 20, 0.14);
        }

        .producto-info {
            flex: 1;
            min-width: 0;
        }
        .producto-info h3 {
            margin: 0 0 6px 0;
            color: #fff;
            font-size: 1.02rem;
            font-weight: 800;
            letter-spacing: -0.2px;
        }
        .producto-info p {
            margin: 0 0 12px 0;
            color: var(--text-muted);
            font-size: 0.84rem;
            line-height: 1.4;
        }
        .precio-tag {
            display: inline-block;
            color: var(--neon);
            background: var(--neon-soft);
            border: 1px solid var(--neon-border);
            padding: 4px 11px;
            border-radius: 9px;
            font-weight: 900;
            font-size: 0.96rem;
        }

        .producto-media {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .img-prod {
            width: 84px;
            height: 84px;
            object-fit: cover;
            border-radius: 14px;
            border: 1px solid var(--border-glass);
        }

        .btn-pedir {
            background: var(--neon);
            color: #050505;
            border: none;
            padding: 9px 18px;
            font-weight: 800;
            font-size: 0.84rem;
            border-radius: 11px;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.2s ease;
            box-shadow: 0 6px 16px -4px rgba(57, 255, 20, 0.45);
        }
        .btn-pedir:active { transform: scale(0.94); }

        .qty-control {
            display: inline-flex;
            align-items: center;
            background: rgba(0, 0, 0, 0.65);
            border: 1px solid var(--neon);
            border-radius: 11px;
            overflow: hidden;
            box-shadow: var(--neon-glow);
        }
        .qty-btn {
            background: transparent;
            color: var(--neon);
            border: none;
            width: 32px;
            height: 32px;
            font-size: 1.1rem;
            font-weight: 900;
            cursor: pointer;
        }
        .qty-btn:active { background: var(--neon); color: #000; }
        .qty-num {
            min-width: 26px;
            text-align: center;
            font-weight: 800;
            font-size: 0.9rem;
            color: #fff;
        }

        /* 5. Footer Promocional de la App (SmartMenu SaaS) */
        .smartmenu-promo-footer {
            max-width: 720px;
            margin: 32px auto 10px;
            padding: 0 16px;
        }
        .promo-card {
            background: linear-gradient(135deg, rgba(18, 22, 20, 0.92), rgba(57, 255, 20, 0.08));
            border: 1px solid var(--border-glass);
            border-radius: 20px;
            padding: 20px;
            text-align: center;
            backdrop-filter: blur(12px);
        }
        .promo-card p {
            color: var(--text-muted);
            font-size: 0.84rem;
            margin-bottom: 12px;
        }
        .promo-card strong { color: #fff; }
        .promo-card strong span { color: var(--neon); }
        .btn-promo-saas {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.05);
            color: var(--neon);
            border: 1px solid var(--neon-border);
            padding: 8px 18px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 800;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-promo-saas:hover {
            background: var(--neon);
            color: #050505;
            box-shadow: var(--neon-glow);
        }

        /* 6. Barra Flotante Inferior del Carrito */
        #carrito-flotante {
            position: fixed;
            bottom: 16px;
            left: 50%;
            transform: translate(-50%, 150%);
            width: calc(100% - 28px);
            max-width: 680px;
            background: rgba(15, 19, 17, 0.94);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border: 1px solid var(--neon);
            border-radius: 20px;
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 14px 36px rgba(0, 0, 0, 0.8), var(--neon-glow);
            z-index: 40;
            transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1);
        }
        #carrito-flotante.visible {
            transform: translate(-50%, 0);
        }

        .carrito-resumen {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
        }

        .badge-count {
            background: var(--neon);
            color: #050505;
            font-weight: 900;
            width: 36px;
            height: 36px;
            border-radius: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            transition: transform 0.2s ease;
        }
        .badge-count.pop { transform: scale(1.25); }

        .carrito-texto {
            display: flex;
            flex-direction: column;
            text-align: left;
        }
        .carrito-texto small {
            color: var(--text-muted);
            font-size: 0.74rem;
        }
        .carrito-texto strong {
            color: #fff;
            font-size: 1.08rem;
            font-weight: 900;
        }

        .btn-ver-carrito {
            background: var(--neon);
            color: #050505;
            border: none;
            padding: 11px 18px;
            font-weight: 800;
            font-size: 0.88rem;
            border-radius: 12px;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(57, 255, 20, 0.35);
        }

        /* 7. Bottom Sheets (Carrito Nuevo y Cuenta Acumulada) */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.76);
            backdrop-filter: blur(6px);
            z-index: 60;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s ease;
            display: flex;
            align-items: flex-end;
            justify-content: center;
        }
        .modal-overlay.open {
            opacity: 1;
            pointer-events: auto;
        }

        .bottom-sheet {
            width: 100%;
            max-width: 680px;
            max-height: 84vh;
            background: #111513;
            border-top: 2px solid var(--neon);
            border-radius: 26px 26px 0 0;
            padding: 22px 18px 26px;
            display: flex;
            flex-direction: column;
            transform: translateY(100%);
            transition: transform 0.3s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .modal-overlay.open .bottom-sheet {
            transform: translateY(0);
        }

        .sheet-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-glass);
            padding-bottom: 12px;
            margin-bottom: 12px;
        }
        .sheet-header h3 {
            margin: 0;
            color: var(--neon);
            font-size: 1.15rem;
            font-weight: 800;
        }
        .btn-cerrar-sheet {
            background: rgba(255,255,255,0.08);
            color: #fff;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            font-size: 1rem;
            cursor: pointer;
        }

        .sheet-items {
            overflow-y: auto;
            flex: 1;
            padding-right: 4px;
        }
        .item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-glass);
        }
        .item-row-info h4 {
            margin: 0 0 4px 0;
            font-size: 0.95rem;
        }
        .item-row-info span {
            color: var(--neon);
            font-size: 0.85rem;
            font-weight: 700;
        }

        .sheet-footer {
            margin-top: 16px;
            padding-top: 14px;
            border-top: 1px solid var(--border-glass);
        }
        .sub-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.88rem;
            color: var(--text-muted);
            margin-bottom: 6px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 1.15rem;
            font-weight: 800;
            margin: 8px 0 14px;
        }
        .total-row span:last-child {
            color: var(--neon);
            font-size: 1.35rem;
            font-weight: 900;
        }

        .sheet-actions {
            display: flex;
            gap: 10px;
        }
        .btn-vaciar {
            background: rgba(255, 77, 79, 0.14);
            color: var(--danger);
            border: 1px solid rgba(255, 77, 79, 0.4);
            padding: 13px 16px;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
        }
        .btn-confirmar-final {
            flex: 1;
            background: var(--neon);
            color: #050505;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-weight: 900;
            font-size: 1rem;
            cursor: pointer;
            box-shadow: var(--neon-glow);
            transition: opacity 0.2s;
        }
        .btn-confirmar-final:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Modal de Confirmación de Éxito */
        .success-modal {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.84);
            backdrop-filter: blur(8px);
            z-index: 80;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s ease;
        }
        .success-modal.open {
            opacity: 1;
            pointer-events: auto;
        }
        .success-box {
            background: #141816;
            border: 1px solid var(--neon);
            border-radius: 24px;
            padding: 28px 22px;
            text-align: center;
            max-width: 390px;
            width: 100%;
            box-shadow: var(--neon-glow);
            transform: scale(0.9);
            transition: transform 0.25s ease;
        }
        .success-modal.open .success-box {
            transform: scale(1);
        }
        .check-circle {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            background: var(--neon-soft);
            border: 2px solid var(--neon);
            color: var(--neon);
            font-size: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            box-shadow: var(--neon-glow);
        }

        #toast {
            position: fixed;
            top: 18px;
            left: 50%;
            transform: translate(-50%, -150%);
            background: rgba(18, 22, 20, 0.96);
            border: 1px solid var(--neon);
            color: #fff;
            padding: 10px 18px;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 600;
            z-index: 90;
            transition: transform 0.3s ease;
            box-shadow: var(--neon-glow);
        }
        #toast.show { transform: translate(-50%, 0); }
    </style>
</head>
<body>

    <div id="toast"></div>

    <!-- 1. Topbar con la marca de la plataforma (SmartMenu) -->
    <div class="saas-topbar">
        <a href="<?= htmlspecialchars($basePath) ?>" target="_blank" class="saas-brand">
            <span class="saas-brand-badge">QR</span>
            Smart<span>Menu</span>
        </a>
        <a href="<?= htmlspecialchars($basePath) ?>#acceso" target="_blank" class="saas-cta-link">
            ✨ ¿Tenés un local? Creá tu Carta QR
        </a>
    </div>

    <!-- 2. Hero del Restaurante con su Logo, Nombre, Dirección y Mesa -->
    <header class="resto-hero">
        <div class="resto-hero-card">
            <div class="resto-logo-ring">
                <?php if (!empty($info['logo'])): ?>
                    <img src="<?= htmlspecialchars($basePath . $info['logo']) ?>" alt="Logo <?= htmlspecialchars($nombre_restaurante) ?>">
                <?php else: ?>
                    <span class="resto-logo-fallback"><?= htmlspecialchars($iniciales) ?></span>
                <?php endif; ?>
            </div>
            <div class="resto-hero-info">
                <h1><?= htmlspecialchars($nombre_restaurante) ?></h1>
                <div class="resto-meta">
                    <?php if ($direccion_rest !== ''): ?>
                        <span>📍 <?= htmlspecialchars($direccion_rest) ?></span>
                    <?php endif; ?>
                    <?php if ($telefono_rest !== ''): ?>
                        <span>📞 <?= htmlspecialchars($telefono_rest) ?></span>
                    <?php endif; ?>
                </div>
                <div class="badges-row">
                    <div class="mesa-badge">● Mesa N° <?= $numero_mesa ?></div>
                    <?php if ($cobra_servicio_mesa && $precio_servicio_mesa > 0): ?>
                        <span class="servicio-pill">🍽️ Servicio de mesa: $<?= number_format($precio_servicio_mesa, 2, ',', '.') ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- 2.5 Panel de Cuenta Acumulada de la Mesa (visible si ya pidió algo en esta sesión) -->
    <section class="cuenta-abierta-wrap" id="panel-cuenta-abierta" style="<?= !empty($pedidosAbiertosMesa) ? '' : 'display:none;' ?>">
        <div class="cuenta-abierta-card">
            <div class="cuenta-abierta-info">
                <small>🧾 Cuenta Abierta · Mesa <?= $numero_mesa ?></small>
                <strong id="monto-cuenta-acumulada">$<?= number_format($totalAcumuladoMesa, 2, ',', '.') ?></strong>
                <span id="estado-cuenta-texto">
                    <?= $yaPidioCuenta ? '🔔 Ya solicitaste la cuenta · Enseguida te cobran' : 'Podés seguir agregando productos de la carta' ?>
                </span>
            </div>
            <div class="cuenta-abierta-actions">
                <button type="button" class="btn-ver-cuenta" onclick="abrirModalCuentaMesa()">
                    📋 Ver Consumos
                </button>
                <button type="button" class="btn-pedir-cuenta" id="btn-pedir-cuenta-top" onclick="abrirModalCuentaMesa()">
                    <?= $yaPidioCuenta ? '💳 Ver Total Final' : '💳 Pedir la Cuenta' ?>
                </button>
            </div>
        </div>
    </section>

    <!-- 3. Buscador Instantáneo + Categorías Sticky -->
    <?php if (!empty($menu)): ?>
        <div class="sticky-controls">
            <div class="controls-inner">
                <div class="search-box">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="input-busqueda" placeholder="Buscar plato, cerveza, trago o ingrediente..." oninput="filtrarMenu(this.value)">
                </div>
                <nav class="categorias-nav">
                    <?php foreach ($menu as $cat): ?>
                        <?php if (!empty($cat['productos'])): ?>
                            <a href="#cat-<?= $cat['id'] ?>" class="cat-pill"><?= htmlspecialchars($cat['nombre']) ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
            </div>
        </div>
    <?php endif; ?>

    <!-- 4. Catálogo de Productos -->
    <main>
        <?php foreach ($menu as $cat): ?>
            <?php if (!empty($cat['productos'])): ?>
                <section id="cat-<?= $cat['id'] ?>" class="categoria-seccion">
                    <h2 class="categoria-titulo"><?= htmlspecialchars($cat['nombre']) ?></h2>
                    <?php foreach ($cat['productos'] as $prod): ?>
                        <div class="producto-card" id="card-prod-<?= $prod['id'] ?>" data-search="<?= htmlspecialchars(mb_strtolower($prod['nombre'] . ' ' . $prod['descripcion'] . ' ' . $cat['nombre'])) ?>">
                            <div class="producto-info">
                                <h3><?= htmlspecialchars($prod['nombre']) ?></h3>
                                <p><?= htmlspecialchars($prod['descripcion']) ?></p>
                                <span class="precio-tag">$<?= number_format($prod['precio'], 2, ',', '.') ?></span>
                            </div>
                            <div class="producto-media">
                                <?php if (!empty($prod['imagen'])): ?>
                                    <img src="<?= htmlspecialchars($basePath . $prod['imagen']) ?>" class="img-prod" alt="<?= htmlspecialchars($prod['nombre']) ?>">
                                <?php endif; ?>
                                <div id="action-prod-<?= $prod['id'] ?>">
                                    <button class="btn-pedir" onclick="cambiarCantidad(<?= $prod['id'] ?>, 1)">+ Agregar</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>
        <?php endforeach; ?>
    </main>

    <!-- 5. Pie de Página Promocional de la App (SmartMenu) -->
    <footer class="smartmenu-promo-footer">
        <div class="promo-card">
            <p>⚡ Carta Digital interactiva impulsada por <strong>Smart<span>Menu</span></strong></p>
            <a href="<?= htmlspecialchars($basePath) ?>#acceso" target="_blank" class="btn-promo-saas">
                🚀 Contratá SmartMenu para tu restaurante o bar →
            </a>
        </div>
    </footer>

    <!-- 6. Barra flotante inferior del Carrito (Nueva Ronda) -->
    <div id="carrito-flotante">
        <div class="carrito-resumen" onclick="abrirModalCarrito()">
            <div class="badge-count" id="badge-count">0</div>
            <div class="carrito-texto">
                <small>Nuevos productos a enviar a cocina</small>
                <strong id="texto-total-flotante">$0,00</strong>
            </div>
        </div>
        <button class="btn-ver-carrito" onclick="abrirModalCarrito()">Ver y Enviar →</button>
    </div>

    <!-- 7A. Bottom Sheet Detalle del Carrito (Enviar Nueva Ronda) -->
    <div class="modal-overlay" id="modal-carrito" onclick="if(event.target === this) cerrarModalCarrito()">
        <div class="bottom-sheet">
            <div class="sheet-header">
                <h3>Agregar a Mesa <?= $numero_mesa ?></h3>
                <button class="btn-cerrar-sheet" onclick="cerrarModalCarrito()">✕</button>
            </div>
            <div class="sheet-items" id="lista-items-carrito"></div>
            <div class="sheet-footer">
                <div class="sub-row" id="fila-servicio-carrito" style="display:none;">
                    <span>🍽️ Servicio de mesa (única vez)</span>
                    <span id="valor-servicio-carrito">$0,00</span>
                </div>
                <div class="total-row">
                    <span>Total de este envío</span>
                    <span id="sheet-total-precio">$0,00</span>
                </div>
                <div class="sheet-actions">
                    <button class="btn-vaciar" onclick="vaciarCarrito()">Vaciar</button>
                    <button class="btn-confirmar-final" id="btn-enviar-pedido" onclick="enviarPedido(<?= $id_restaurante ?>, <?= $id_mesa ?>)">
                        Enviar a Cocina 🚀
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 7B. Bottom Sheet de la Cuenta Acumulada / Pedir la Cuenta -->
    <div class="modal-overlay" id="modal-cuenta-mesa" onclick="if(event.target === this) cerrarModalCuentaMesa()">
        <div class="bottom-sheet" style="border-top-color: var(--amber);">
            <div class="sheet-header">
                <h3 style="color: #fcd34d;">🧾 Resumen de Cuenta · Mesa <?= $numero_mesa ?></h3>
                <button class="btn-cerrar-sheet" onclick="cerrarModalCuentaMesa()">✕</button>
            </div>

            <div class="sheet-items">
                <?php if (empty($itemsConsumidosMesa)): ?>
                    <p style="color:var(--text-muted); text-align:center; padding:24px;">Aún no tenés consumos registrados en la mesa.</p>
                <?php else: ?>
                    <?php foreach ($itemsConsumidosMesa as $ic): ?>
                        <div class="item-row">
                            <div class="item-row-info">
                                <h4><?= (int)$ic['cantidad'] ?>x <?= htmlspecialchars($ic['nombre']) ?></h4>
                                <small style="color:var(--text-muted);">
                                    Orden #<?= (int)$ic['id_pedido'] ?> (<?= htmlspecialchars($ic['hora']) ?> hs) · 
                                    <?php if ($ic['estado'] === 'servido'): ?>
                                        <span style="color:var(--neon);">✔ Entregado en mesa</span>
                                    <?php elseif ($ic['estado'] === 'preparando'): ?>
                                        <span style="color:var(--amber);">🔥 En preparación</span>
                                    <?php else: ?>
                                        <span style="color:#94a3b8;">⏳ Recibido en cocina</span>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <strong style="color:#fff;">$<?= number_format($ic['subtotal'], 2, ',', '.') ?></strong>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="sheet-footer">
                <div class="sub-row">
                    <span>Subtotal consumos</span>
                    <span>$<?= number_format($subtotalConsumidoMesa, 2, ',', '.') ?></span>
                </div>
                <?php if ($servicioCobradoMesa > 0): ?>
                    <div class="sub-row" style="color:#fcd34d;">
                        <span>🍽️ Servicio de Mesa / Cubierto</span>
                        <span>$<?= number_format($servicioCobradoMesa, 2, ',', '.') ?></span>
                    </div>
                <?php endif; ?>
                <div class="total-row">
                    <span>Total Final Gastado</span>
                    <span id="cuenta-total-final">$<?= number_format($totalAcumuladoMesa, 2, ',', '.') ?></span>
                </div>

                <div class="sheet-actions">
                    <button type="button" class="btn-ver-cuenta" onclick="cerrarModalCuentaMesa()">
                        + Seguir Pidiendo
                    </button>
                    <button type="button" class="btn-confirmar-final" id="btn-solicitar-cuenta" style="background:var(--amber); box-shadow: 0 6px 20px rgba(245,158,11,0.4);" onclick="solicitarCuentaFinal(<?= $id_restaurante ?>, <?= $id_mesa ?>)">
                        <?= $yaPidioCuenta ? '🔔 Recordar que pedí la cuenta' : '💳 Confirmar y Pedir la Cuenta' ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmación Exitosa -->
    <div class="success-modal" id="modal-exito">
        <div class="success-box">
            <div class="check-circle" id="modal-exito-icono">✓</div>
            <h2 id="modal-exito-titulo" style="margin:0 0 8px 0; color:#fff;">¡Pedido en Cocina!</h2>
            <p id="modal-exito-desc" style="color:var(--text-muted); margin:0 0 16px 0; font-size:0.92rem;"></p>
            <button class="btn-confirmar-final" style="width:100%;" onclick="window.location.reload()">Aceptar y Actualizar Cuenta</button>
        </div>
    </div>

    <script>
        const BASE_URL = <?= json_encode($basePath) ?>;
        const STORAGE_KEY = 'smartmenu_cart_<?= addslashes($hash) ?>';
        const CATALOGO = <?= json_encode($catalogoProductos, JSON_UNESCAPED_UNICODE) ?>;
        const COBRA_SERVICIO_MESA = <?= json_encode($cobra_servicio_mesa) ?>;
        const PRECIO_SERVICIO_MESA = <?= json_encode($precio_servicio_mesa) ?>;
        const TIENE_CUENTA_ABIERTA = <?= json_encode(!empty($pedidosAbiertosMesa)) ?>;
        let carrito = [];

        function filtrarMenu(termino) {
            const q = termino.trim().toLowerCase();
            document.querySelectorAll('.categoria-seccion').forEach(seccion => {
                let visiblesEnCategoria = 0;
                seccion.querySelectorAll('.producto-card').forEach(card => {
                    const texto = card.getAttribute('data-search') || '';
                    const coincide = q === '' || texto.includes(q);
                    card.style.display = coincide ? 'flex' : 'none';
                    if (coincide) visiblesEnCategoria++;
                });
                seccion.style.display = visiblesEnCategoria > 0 ? 'block' : 'none';
            });
        }

        function cargarCarritoGuardado() {
            try {
                const guardado = localStorage.getItem(STORAGE_KEY);
                if (guardado) {
                    const parseado = JSON.parse(guardado);
                    if (Array.isArray(parseado)) {
                        carrito = parseado
                            .filter(item => item && CATALOGO[item.id] && item.cantidad > 0)
                            .map(item => ({
                                ...CATALOGO[item.id],
                                cantidad: Math.min(50, parseInt(item.cantidad, 10) || 1)
                            }));
                    }
                }
            } catch (e) {
                carrito = [];
            }
            actualizarCarritoUI(false);
        }

        function guardarCarrito() {
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(carrito));
            } catch (e) {}
        }

        function formatearMoneda(valor) {
            return '$' + Number(valor).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function mostrarToast(mensaje) {
            const toast = document.getElementById('toast');
            toast.textContent = mensaje;
            toast.classList.add('show');
            clearTimeout(window._toastTimer);
            window._toastTimer = setTimeout(() => toast.classList.remove('show'), 2600);
        }

        function cambiarCantidad(idProducto, delta) {
            const prod = CATALOGO[idProducto];
            if (!prod) return;

            const index = carrito.findIndex(item => item.id === idProducto);
            if (index > -1) {
                carrito[index].cantidad += delta;
                if (carrito[index].cantidad <= 0) {
                    carrito.splice(index, 1);
                } else if (carrito[index].cantidad > 50) {
                    carrito[index].cantidad = 50;
                }
            } else if (delta > 0) {
                carrito.push({ ...prod, cantidad: 1 });
            }

            guardarCarrito();
            actualizarCarritoUI(true);
        }

        function vaciarCarrito() {
            carrito = [];
            guardarCarrito();
            actualizarCarritoUI(false);
            cerrarModalCarrito();
            mostrarToast('Carrito vaciado');
        }

        function actualizarCarritoUI(animarBadge = false) {
            const totalItems = carrito.reduce((acc, item) => acc + item.cantidad, 0);
            const subtotalProductos = carrito.reduce((acc, item) => acc + (item.precio * item.cantidad), 0);

            // Si es el primer pedido de la mesa y el local cobra servicio de mesa, se suma al primer envío
            const cargoServicioEsteEnvio = (!TIENE_CUENTA_ABIERTA && COBRA_SERVICIO_MESA && PRECIO_SERVICIO_MESA > 0)
                ? PRECIO_SERVICIO_MESA
                : 0;
            const totalEsteEnvio = subtotalProductos + cargoServicioEsteEnvio;

            Object.keys(CATALOGO).forEach(idStr => {
                const id = parseInt(idStr, 10);
                const card = document.getElementById('card-prod-' + id);
                const contenedorAccion = document.getElementById('action-prod-' + id);
                if (!card || !contenedorAccion) return;

                const enCarrito = carrito.find(i => i.id === id);
                if (enCarrito) {
                    card.classList.add('en-carrito');
                    contenedorAccion.innerHTML = `
                        <div class="qty-control">
                            <button class="qty-btn" onclick="cambiarCantidad(${id}, -1)">−</button>
                            <span class="qty-num">${enCarrito.cantidad}</span>
                            <button class="qty-btn" onclick="cambiarCantidad(${id}, 1)">+</button>
                        </div>
                    `;
                } else {
                    card.classList.remove('en-carrito');
                    contenedorAccion.innerHTML = `
                        <button class="btn-pedir" onclick="cambiarCantidad(${id}, 1)">+ Agregar</button>
                    `;
                }
            });

            const flotante = document.getElementById('carrito-flotante');
            const badge = document.getElementById('badge-count');
            const textoTotal = document.getElementById('texto-total-flotante');

            if (totalItems > 0) {
                flotante.classList.add('visible');
                badge.textContent = totalItems;
                textoTotal.textContent = formatearMoneda(totalEsteEnvio);

                if (animarBadge) {
                    badge.classList.remove('pop');
                    void badge.offsetWidth;
                    badge.classList.add('pop');
                    setTimeout(() => badge.classList.remove('pop'), 220);
                }
            } else {
                flotante.classList.remove('visible');
                cerrarModalCarrito();
            }

            const filaServicio = document.getElementById('fila-servicio-carrito');
            const valorServicio = document.getElementById('valor-servicio-carrito');
            if (filaServicio && valorServicio) {
                if (cargoServicioEsteEnvio > 0) {
                    filaServicio.style.display = 'flex';
                    valorServicio.textContent = formatearMoneda(cargoServicioEsteEnvio);
                } else {
                    filaServicio.style.display = 'none';
                }
            }

            const listaItems = document.getElementById('lista-items-carrito');
            const sheetTotal = document.getElementById('sheet-total-precio');
            if (listaItems && sheetTotal) {
                sheetTotal.textContent = formatearMoneda(totalEsteEnvio);
                listaItems.innerHTML = carrito.map(item => `
                    <div class="item-row">
                        <div class="item-row-info">
                            <h4>${escapeHtml(item.nombre)}</h4>
                            <span>${formatearMoneda(item.precio * item.cantidad)} ($${Number(item.precio).toLocaleString('es-AR')} c/u)</span>
                        </div>
                        <div class="qty-control">
                            <button class="qty-btn" onclick="cambiarCantidad(${item.id}, -1)">−</button>
                            <span class="qty-num">${item.cantidad}</span>
                            <button class="qty-btn" onclick="cambiarCantidad(${item.id}, 1)">+</button>
                        </div>
                    </div>
                `).join('');
            }
        }

        function abrirModalCarrito() {
            if (carrito.length === 0) return;
            document.getElementById('modal-carrito').classList.add('open');
        }

        function cerrarModalCarrito() {
            document.getElementById('modal-carrito').classList.remove('open');
        }

        function abrirModalCuentaMesa() {
            document.getElementById('modal-cuenta-mesa').classList.add('open');
        }

        function cerrarModalCuentaMesa() {
            document.getElementById('modal-cuenta-mesa').classList.remove('open');
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function enviarPedido(idRestaurante, idMesa) {
            if (carrito.length === 0) return;

            const btn = document.getElementById('btn-enviar-pedido');
            const textoOriginal = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Enviando a cocina...';

            fetch(BASE_URL + 'cliente/procesar_pedido_cliente.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id_restaurante: idRestaurante,
                    id_mesa: idMesa,
                    items: carrito.map(i => ({ id: i.id, cantidad: i.cantidad }))
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    carrito = [];
                    localStorage.removeItem(STORAGE_KEY);
                    cerrarModalCarrito();

                    document.getElementById('modal-exito-titulo').textContent = '¡Pedido Enviado a Cocina!';
                    document.getElementById('modal-exito-desc').innerHTML = `
                        Tu orden <strong style="color:var(--neon);">#${data.id_pedido}</strong> ya se está preparando.<br><br>
                        Total acumulado en tu mesa: <strong style="color:#fff;">${formatearMoneda(data.total_acumulado_mesa)}</strong>
                    `;
                    document.getElementById('modal-exito').classList.add('open');
                } else {
                    mostrarToast('⚠️ ' + (data.message || data.mensaje || 'Error al enviar el pedido'));
                }
            })
            .catch(() => {
                mostrarToast('⚠️ Error de conexión con el servidor');
            })
            .finally(() => {
                btn.disabled = false;
                btn.textContent = textoOriginal;
            });
        }

        function solicitarCuentaFinal(idRestaurante, idMesa) {
            const btn = document.getElementById('btn-solicitar-cuenta');
            btn.disabled = true;
            btn.textContent = 'Solicitando cuenta...';

            fetch(BASE_URL + 'cliente/pedir_cuenta.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id_restaurante: idRestaurante,
                    id_mesa: idMesa
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    cerrarModalCuentaMesa();
                    document.getElementById('modal-exito-icono').textContent = '💳';
                    document.getElementById('modal-exito-titulo').textContent = '¡Pediste la Cuenta!';
                    document.getElementById('modal-exito-desc').innerHTML = `
                        Ya le avisamos a la caja que querés abonar la <strong>Mesa <?= $numero_mesa ?></strong>.<br><br>
                        Total Final a Pagar: <strong style="color:var(--neon); font-size:1.25rem;">${formatearMoneda(data.total_final)}</strong>
                    `;
                    document.getElementById('modal-exito').classList.add('open');
                } else {
                    mostrarToast('⚠️ ' + (data.message || data.mensaje || 'Error al pedir la cuenta'));
                    btn.disabled = false;
                    btn.textContent = '💳 Confirmar y Pedir la Cuenta';
                }
            })
            .catch(() => {
                mostrarToast('⚠️ Error de conexión');
                btn.disabled = false;
                btn.textContent = '💳 Confirmar y Pedir la Cuenta';
            });
        }

        document.addEventListener('DOMContentLoaded', cargarCarritoGuardado);
    </script>
</body>
</html>
