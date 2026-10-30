<?php
require 'conexion.php';
require 'layout.php';

$id_restaurante = (int) $_SESSION['restaurante_id'];

// Obtener las mesas del restaurante
$stmtMesas = $pdo->prepare("SELECT * FROM mesas WHERE id_restaurante = ? ORDER BY numero_mesa ASC");
$stmtMesas->execute([$id_restaurante]);
$mesas = $stmtMesas->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php renderAdminHead("Mesas y Códigos QR"); ?>
    <style>
        .crear-mesa-bar {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }
        .crear-mesa-form {
            display: flex;
            align-items: flex-end;
            gap: 14px;
            flex-wrap: wrap;
            flex: 1;
        }
        .grid-mesas {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
            gap: 22px;
        }
        .tarjeta-mesa {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: 22px;
            padding: 24px 20px;
            text-align: center;
            backdrop-filter: blur(14px);
            transition: all 0.25s ease;
            box-shadow: 0 14px 36px rgba(0, 0, 0, 0.45);
        }
        .tarjeta-mesa:hover {
            border-color: var(--neon-border);
            transform: translateY(-5px);
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.55), var(--neon-glow);
        }
        .mesa-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
        }
        .mesa-header h2 {
            font-size: 1.35rem;
            font-weight: 900;
            color: #fff;
            margin: 0;
        }
        .qr-frame {
            width: 176px;
            height: 176px;
            margin: 10px auto 14px;
            background: #ffffff;
            padding: 12px;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .qr-frame img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .hash-code {
            font-family: monospace;
            font-size: 0.78rem;
            color: var(--text-muted);
            background: rgba(0, 0, 0, 0.45);
            padding: 5px 10px;
            border-radius: 8px;
            display: inline-block;
            margin-bottom: 16px;
            border: 1px solid var(--border-glass);
        }
        .mesa-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 8px;
        }
        .btn-pdf-full {
            width: 100%;
            padding: 11px 14px;
            font-size: 0.86rem;
        }
    </style>
</head>
<body>
    <?php renderAdminNav('mesas'); ?>

    <main class="admin-container">
        <div class="page-header">
            <div class="page-title">
                <h1>Mesas y <span>Códigos QR</span></h1>
                <p>Genera códigos QR únicos por mesa, personaliza tu cartel con logo/redes y descárgalo en .PDF para imprimir.</p>
            </div>
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <span class="badge badge-neon"><?= count($mesas) ?> mesas activas</span>
                <?php if (!empty($mesas)): ?>
                    <a href="disenar_qr" class="btn-neon">🎨 Abrir Diseñador de Carteles .PDF</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="glass-card crear-mesa-bar">
            <form action="procesar_mesa.php" method="POST" class="crear-mesa-form">
                <div style="flex: 1; min-width: 220px;">
                    <label>Número de Nueva Mesa</label>
                    <input type="number" name="numero_mesa" min="1" placeholder="Ej: 5" required>
                </div>
                <button type="submit" class="btn-neon">+ Generar Mesa y QR</button>
            </form>
        </div>

        <div class="grid-mesas">
            <?php foreach ($mesas as $mesa): ?>
                <div class="tarjeta-mesa">
                    <div class="mesa-header">
                        <h2>Mesa <?= (int)$mesa['numero_mesa'] ?></h2>
                        <span class="badge badge-neon">QR Activo</span>
                    </div>

                    <div class="qr-frame">
                        <img src="generar_qr.php?hash=<?= urlencode($mesa['hash_qr']) ?>" alt="QR Mesa <?= (int)$mesa['numero_mesa'] ?>">
                    </div>

                    <div class="hash-code">
                        ID: <?= htmlspecialchars(substr($mesa['hash_qr'], 0, 16)) ?>...
                    </div>

                    <div class="mesa-actions">
                        <a href="../m/<?= urlencode($mesa['hash_qr']) ?>" target="_blank" class="btn-outline">
                            📱 Abrir Carta
                        </a>
                        <a href="generar_qr.php?hash=<?= urlencode($mesa['hash_qr']) ?>&descargar=1" class="btn-outline" download="QR_Mesa_<?= (int)$mesa['numero_mesa'] ?>.png">
                            ⬇ Solo QR (.PNG)
                        </a>
                    </div>

                    <a href="disenar_qr?hash=<?= urlencode($mesa['hash_qr']) ?>" class="btn-neon btn-pdf-full">
                        🎨 Personalizar y Descargar .PDF
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
</body>
</html>
