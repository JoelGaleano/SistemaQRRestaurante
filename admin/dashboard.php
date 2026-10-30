<?php
require 'conexion.php';
require 'layout.php';

$id_restaurante = (int) $_SESSION['restaurante_id'];
$nombre_restaurante = $_SESSION['restaurante_nombre'];

// 1. Métrica: Ventas del turno actual (pedidos servidos y cobrados sin cerrar caja)
$stmtCaja = $pdo->prepare("
    SELECT COUNT(id) AS pedidos_turno, COALESCE(SUM(total), 0) AS total_turno
    FROM pedidos
    WHERE id_restaurante = ? AND estado = 'servido' AND cuenta_cerrada = 1 AND id_cierre IS NULL
");
$stmtCaja->execute([$id_restaurante]);
$metricaCaja = $stmtCaja->fetch();

// 2. Métrica: Pedidos activos en cocina ahora mismo (pendiente o preparando)
$stmtActivos = $pdo->prepare("
    SELECT COUNT(id) FROM pedidos
    WHERE id_restaurante = ? AND estado IN ('pendiente', 'preparando')
");
$stmtActivos->execute([$id_restaurante]);
$pedidosEnCocina = (int) $stmtActivos->fetchColumn();

// 3. Métrica: Productos activos vs totales
$stmtProd = $pdo->prepare("
    SELECT 
        COUNT(p.id) AS total_platos,
        COALESCE(SUM(CASE WHEN p.disponible = 1 THEN 1 ELSE 0 END), 0) AS platos_activos
    FROM productos p
    JOIN categorias c ON p.id_categoria = c.id
    WHERE c.id_restaurante = ?
");
$stmtProd->execute([$id_restaurante]);
$metricaPlatos = $stmtProd->fetch();

// 4. Métrica: Mesas registradas y primera mesa para vista previa
$stmtMesas = $pdo->prepare("SELECT id, numero_mesa, hash_qr FROM mesas WHERE id_restaurante = ? ORDER BY numero_mesa ASC");
$stmtMesas->execute([$id_restaurante]);
$listaMesas = $stmtMesas->fetchAll();
$totalMesas = count($listaMesas);
$primeraMesaHash = $listaMesas[0]['hash_qr'] ?? null;

// 5. Últimos 6 pedidos del restaurante
$stmtRecientes = $pdo->prepare("
    SELECT p.id, p.estado, p.total, p.nombre_mozo, DATE_FORMAT(p.fecha_creacion, '%d/%m %H:%i') AS fecha_hora, m.numero_mesa
    FROM pedidos p
    JOIN mesas m ON p.id_mesa = m.id
    WHERE p.id_restaurante = ?
    ORDER BY p.fecha_creacion DESC
    LIMIT 6
");
$stmtRecientes->execute([$id_restaurante]);
$pedidosRecientes = $stmtRecientes->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php renderAdminHead("Dashboard - $nombre_restaurante"); ?>
    <style>
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        .kpi-card {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: 20px;
            padding: 24px;
            backdrop-filter: blur(14px);
            position: relative;
            overflow: hidden;
            transition: transform 0.25s ease, border-color 0.25s ease;
        }
        .kpi-card:hover {
            transform: translateY(-4px);
            border-color: var(--neon-border);
        }
        .kpi-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .kpi-label {
            color: var(--text-muted);
            font-size: 0.82rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .kpi-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: var(--neon-soft);
            border: 1px solid var(--neon-border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        .kpi-value {
            font-size: 2rem;
            font-weight: 900;
            color: var(--text-main);
            letter-spacing: -0.8px;
            line-height: 1.15;
        }
        .kpi-value.neon { color: var(--neon); }
        .kpi-sub {
            margin-top: 8px;
            font-size: 0.82rem;
            color: var(--text-muted);
        }

        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 36px;
        }
        .module-card {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: 20px;
            padding: 26px;
            text-decoration: none;
            color: var(--text-main);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.25s ease;
            backdrop-filter: blur(14px);
        }
        .module-card:hover {
            border-color: var(--neon);
            transform: translateY(-5px);
            box-shadow: 0 16px 38px rgba(0, 0, 0, 0.5), var(--neon-glow);
        }
        .module-card-head {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 12px;
        }
        .module-emoji {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: var(--neon-soft);
            border: 1px solid var(--neon-border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }
        .module-card h3 {
            font-size: 1.15rem;
            font-weight: 800;
        }
        .module-card p {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 18px;
        }
        .module-cta {
            color: var(--neon);
            font-size: 0.85rem;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
    </style>
</head>
<body>
    <?php renderAdminNav('dashboard'); ?>

    <main class="admin-container">
        <div class="page-header">
            <div class="page-title">
                <h1>Panel de Control · <span><?= htmlspecialchars($nombre_restaurante) ?></span></h1>
                <p>Resumen operativo en tiempo real de tus ventas, cocina, carta digital y mesas QR.</p>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <?php if ($primeraMesaHash): ?>
                    <a href="../m/<?= urlencode($primeraMesaHash) ?>" target="_blank" class="btn-outline">
                        📱 Ver Carta QR en Vivo
                    </a>
                <?php endif; ?>
                <a href="pedidos.php" class="btn-neon">🔔 Abrir Monitor de Cocina</a>
            </div>
        </div>

        <!-- KPIs Principales -->
        <section class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-top">
                    <span class="kpi-label">Caja del Turno Actual</span>
                    <div class="kpi-icon">💰</div>
                </div>
                <div class="kpi-value neon">$<?= number_format((float)$metricaCaja['total_turno'], 2, ',', '.') ?></div>
                <div class="kpi-sub"><?= (int)$metricaCaja['pedidos_turno'] ?> pedidos servidos sin cerrar</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-top">
                    <span class="kpi-label">Pedidos en Cocina</span>
                    <div class="kpi-icon">🔥</div>
                </div>
                <div class="kpi-value"><?= $pedidosEnCocina ?></div>
                <div class="kpi-sub">En estado pendiente o preparando</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-top">
                    <span class="kpi-label">Platos Activos</span>
                    <div class="kpi-icon">🍔</div>
                </div>
                <div class="kpi-value"><?= (int)$metricaPlatos['platos_activos'] ?> <span style="font-size:1.1rem; color:var(--text-muted); font-weight:600;">/ <?= (int)$metricaPlatos['total_platos'] ?></span></div>
                <div class="kpi-sub">Productos con stock habilitado</div>
            </div>

            <div class="kpi-card">
                <div class="kpi-top">
                    <span class="kpi-label">Mesas con QR</span>
                    <div class="kpi-icon">📱</div>
                </div>
                <div class="kpi-value"><?= $totalMesas ?></div>
                <div class="kpi-sub">Códigos QR únicos activos</div>
            </div>
        </section>

        <!-- Accesos Rápidos a Módulos -->
        <section class="modules-grid">
            <a href="pedidos.php" class="module-card">
                <div>
                    <div class="module-card-head">
                        <div class="module-emoji">🔔</div>
                        <h3>Comandera de Cocina (SSE)</h3>
                    </div>
                    <p>Recibe pedidos al instante con alerta sonora, cambia estados de preparación e imprime tickets térmicos de 58mm.</p>
                </div>
                <span class="module-cta">Ir a Cocina →</span>
            </a>

            <a href="menu.php" class="module-card">
                <div>
                    <div class="module-card-head">
                        <div class="module-emoji">🍺</div>
                        <h3>Gestión de Carta y Stock</h3>
                    </div>
                    <p>Crea categorías, sube fotos de tus platos o bebidas y pausa o activa el stock en vivo con un solo clic.</p>
                </div>
                <span class="module-cta">Administrar Menú →</span>
            </a>

            <a href="mesas.php" class="module-card">
                <div>
                    <div class="module-card-head">
                        <div class="module-emoji">🎯</div>
                        <h3>Mesas y Códigos QR</h3>
                    </div>
                    <p>Genera nuevas mesas con hashes encriptados, descarga los QRs listos para imprimir y prueba la vista del cliente.</p>
                </div>
                <span class="module-cta">Gestionar Mesas →</span>
            </a>

            <a href="caja.php" class="module-card">
                <div>
                    <div class="module-card-head">
                        <div class="module-emoji">💵</div>
                        <h3>Control y Cierre de Caja</h3>
                    </div>
                    <p>Visualiza la recaudación acumulada del turno abierto y ejecuta cierres contables seguros por transacción.</p>
                </div>
                <span class="module-cta">Abrir Caja →</span>
            </a>

            <a href="historial.php" class="module-card">
                <div>
                    <div class="module-card-head">
                        <div class="module-emoji">📊</div>
                        <h3>Historial y Exportación Excel</h3>
                    </div>
                    <p>Consulta las ventas completadas por fecha y descarga el reporte general en formato CSV/Excel.</p>
                </div>
                <span class="module-cta">Ver Reportes →</span>
            </a>

            <a href="configuracion.php" class="module-card">
                <div>
                    <div class="module-card-head">
                        <div class="module-emoji">⚙️</div>
                        <h3>Configuración y API Token</h3>
                    </div>
                    <p>Personaliza el logo, teléfono y dirección de tu local y consulta tu Token para la App Android.</p>
                </div>
                <span class="module-cta">Configurar Local →</span>
            </a>
        </section>

        <!-- Últimos Pedidos -->
        <div class="glass-card">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h2 style="margin:0;">Últimos <span>Pedidos Recibidos</span></h2>
                <a href="pedidos.php" class="btn-outline" style="padding:7px 14px; font-size:0.8rem;">Ver Monitor Completo →</a>
            </div>
            <div class="table-wrapper" style="border:none; box-shadow:none; background:transparent;">
                <table>
                    <thead>
                        <tr>
                            <th>Pedido</th>
                            <th>Mesa</th>
                            <th>Mozo</th>
                            <th>Fecha / Hora</th>
                            <th>Total</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pedidosRecientes)): ?>
                            <tr>
                                <td colspan="6" style="text-align:center; color:var(--text-muted); padding:28px;">
                                    Aún no se registraron pedidos. ¡Abre una de tus mesas QR para hacer un pedido de prueba!
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pedidosRecientes as $ped): ?>
                                <tr>
                                    <td><strong>#<?= (int)$ped['id'] ?></strong></td>
                                    <td>Mesa <?= (int)$ped['numero_mesa'] ?></td>
                                    <td>
                                        <?php if (!empty($ped['nombre_mozo'])): ?>
                                            <span style="color:var(--neon); font-weight:700;">👤 <?= htmlspecialchars($ped['nombre_mozo']) ?></span>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted); font-size:0.84rem;">— Sin asignar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="color:var(--text-muted);"><?= htmlspecialchars($ped['fecha_hora']) ?></td>
                                    <td style="font-weight:800; color:var(--neon);">$<?= number_format((float)$ped['total'], 2, ',', '.') ?></td>
                                    <td>
                                        <?php if ($ped['estado'] === 'servido'): ?>
                                            <span class="badge badge-neon">✔ Servido</span>
                                        <?php elseif ($ped['estado'] === 'preparando'): ?>
                                            <span class="badge badge-warning">🔥 Preparando</span>
                                        <?php elseif ($ped['estado'] === 'pendiente'): ?>
                                            <span class="badge badge-danger">⏳ Pendiente</span>
                                        <?php else: ?>
                                            <span class="badge"><?= htmlspecialchars($ped['estado']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>
