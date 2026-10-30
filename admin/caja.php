<?php
require 'conexion.php';
require 'layout.php';

$id_restaurante = (int) $_SESSION['restaurante_id'];

// 1. Calcular el dinero de la caja actual (pedidos 'servidos' con cuenta cerrada sin id_cierre)
$stmtCajaActual = $pdo->prepare("
    SELECT COUNT(id) as cantidad_pedidos, COALESCE(SUM(total), 0) as total_caja 
    FROM pedidos 
    WHERE id_restaurante = ? AND estado = 'servido' AND cuenta_cerrada = 1 AND id_cierre IS NULL
");
$stmtCajaActual->execute([$id_restaurante]);
$cajaActual = $stmtCajaActual->fetch();

$total_actual = (float) ($cajaActual['total_caja'] ?? 0);
$pedidos_actuales = (int) ($cajaActual['cantidad_pedidos'] ?? 0);

// 2. Obtener el historial de cierres anteriores
$stmtHistorial = $pdo->prepare("
    SELECT id, fecha_cierre, total_recaudado, cantidad_pedidos 
    FROM cierres_caja 
    WHERE id_restaurante = ? 
    ORDER BY fecha_cierre DESC 
    LIMIT 30
");
$stmtHistorial->execute([$id_restaurante]);
$cierres = $stmtHistorial->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php renderAdminHead("Control y Cierre de Caja"); ?>
    <style>
        .caja-hero {
            background: radial-gradient(circle at top, rgba(57, 255, 20, 0.14), rgba(24, 29, 26, 0.85));
            border: 1px solid var(--neon-border);
            border-radius: 24px;
            padding: 38px 28px;
            text-align: center;
            margin-bottom: 34px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.55), var(--neon-glow);
        }
        .monto-grande {
            font-size: clamp(2.6rem, 5vw, 3.8rem);
            color: var(--neon);
            font-weight: 900;
            letter-spacing: -1.5px;
            margin: 12px 0;
            text-shadow: 0 0 28px rgba(57, 255, 20, 0.3);
        }
        .info-caja {
            color: var(--text-muted);
            font-size: 1.05rem;
            margin-bottom: 24px;
        }
        .info-caja strong { color: #fff; }
    </style>
</head>
<body>
    <?php renderAdminNav('caja'); ?>

    <main class="admin-container">
        <div class="page-header">
            <div class="page-title">
                <h1>Control y <span>Cierre de Caja</span></h1>
                <p>Totaliza los pedidos servidos del turno actual mediante transacciones SQL seguras.</p>
            </div>
            <span class="badge badge-neon">Turno Abierto</span>
        </div>

        <div class="caja-hero">
            <span class="badge badge-neon">RECAUDACIÓN DEL TURNO ACTUAL</span>
            <p class="monto-grande">$<?= number_format($total_actual, 2, ',', '.') ?></p>
            <p class="info-caja">Ventas servidas listas para arquear: <strong><?= $pedidos_actuales ?> pedidos completados</strong></p>

            <form action="procesar_cierre.php" method="POST" onsubmit="return confirm('¿Confirmas cerrar la caja de este turno? Se agruparán los pedidos servidos e iniciará un nuevo turno en $0,00.');">
                <button type="submit" class="btn-danger" <?= ($pedidos_actuales === 0) ? 'disabled' : '' ?>>
                    🔒 Ejecutar Cierre de Turno Ahora
                </button>
            </form>
            <?php if ($pedidos_actuales === 0): ?>
                <p style="color:var(--text-muted); font-size:0.84rem; margin-top:12px;">
                    No hay pedidos en estado "Servido" pendientes de cierre en este momento.
                </p>
            <?php endif; ?>
        </div>

        <div class="glass-card">
            <h2>Historial de <span>Cierres de Turno</span></h2>
            <div class="table-wrapper" style="border:none; box-shadow:none; background:transparent;">
                <table>
                    <thead>
                        <tr>
                            <th>N° Cierre</th>
                            <th>Fecha y Hora de Cierre</th>
                            <th>Pedidos Incluidos</th>
                            <th>Total Recaudado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cierres)): ?>
                            <tr>
                                <td colspan="4" style="text-align:center; color:var(--text-muted); padding:28px;">
                                    Aún no has ejecutado ningún cierre de caja.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($cierres as $cierre): ?>
                                <tr>
                                    <td><strong>#<?= (int)$cierre['id'] ?></strong></td>
                                    <td><?= date('d/m/Y · H:i \h\s', strtotime($cierre['fecha_cierre'])) ?></td>
                                    <td><span class="badge badge-neon"><?= (int)$cierre['cantidad_pedidos'] ?> pedidos</span></td>
                                    <td style="font-weight:900; color:var(--neon); font-size:1.02rem;">
                                        $<?= number_format((float)$cierre['total_recaudado'], 2, ',', '.') ?>
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
