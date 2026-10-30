<?php
require 'conexion.php';
require 'layout.php';

$id_restaurante = (int) $_SESSION['restaurante_id'];

// Obtener los datos actuales del restaurante incluyendo servicio de mesa y api_token
$stmt = $pdo->prepare("SELECT nombre, alias, email, telefono, direccion, logo, cobra_servicio_mesa, precio_servicio_mesa, api_token FROM restaurantes WHERE id = ?");
$stmt->execute([$id_restaurante]);
$restaurante = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php renderAdminHead("Configuración del Local"); ?>
    <style>
        .config-grid {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 24px;
            align-items: start;
        }
        .logo-preview {
            width: 120px;
            height: 120px;
            object-fit: cover;
            background: rgba(0, 0, 0, 0.45);
            border: 2px dashed var(--neon-border);
            border-radius: 20px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .logo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .mono-box {
            background: rgba(0, 0, 0, 0.55);
            padding: 12px 15px;
            border-radius: 12px;
            border: 1px solid var(--border-glass);
            font-family: monospace;
            font-size: 0.85rem;
            color: var(--neon);
            word-break: break-all;
            margin-bottom: 8px;
        }
        .servicio-mesa-box {
            background: linear-gradient(135deg, rgba(57, 255, 20, 0.08), rgba(0, 0, 0, 0.45));
            border: 1px solid var(--neon-border);
            border-radius: 16px;
            padding: 18px;
            margin-bottom: 20px;
        }
        .switch-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
        }
        .switch-row label {
            margin: 0;
            font-size: 0.88rem;
            color: #fff;
            text-transform: none;
            cursor: pointer;
        }
        @media (max-width: 900px) {
            .config-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php renderAdminNav('configuracion'); ?>

    <main class="admin-container">
        <div class="page-header">
            <div class="page-title">
                <h1>Configuración de <span>Mi Local</span></h1>
                <p>Personaliza la identidad visual de tu carta QR, el valor del servicio de mesa y tus credenciales móviles.</p>
            </div>
        </div>

        <div class="config-grid">
            <!-- Formulario Principal -->
            <div class="glass-card">
                <h2>Identidad, Datos y <span>Servicio de Mesa</span></h2>
                <form action="procesar_configuracion.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Logo del Restaurante (PNG, JPG o WEBP máx. 2MB)</label>
                        <div class="logo-preview">
                            <?php if (!empty($restaurante['logo'])): ?>
                                <img src="../<?= htmlspecialchars($restaurante['logo']) ?>" alt="Logo">
                            <?php else: ?>
                                <span style="color:var(--text-muted); font-size:0.78rem; text-align:center;">Sin<br>Logo</span>
                            <?php endif; ?>
                        </div>
                        <input type="file" name="logo" accept="image/png, image/jpeg, image/webp">
                    </div>

                    <div class="form-group">
                        <label>Nombre Comercial del Local</label>
                        <input type="text" name="nombre" value="<?= htmlspecialchars($restaurante['nombre']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Teléfono / WhatsApp</label>
                        <input type="text" name="telefono" value="<?= htmlspecialchars($restaurante['telefono'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Dirección Física</label>
                        <input type="text" name="direccion" value="<?= htmlspecialchars($restaurante['direccion'] ?? '') ?>" required>
                    </div>

                    <!-- Configuración de Servicio de Mesa / Cubierto -->
                    <div class="servicio-mesa-box">
                        <div class="switch-row">
                            <label for="check-servicio">🍽️ <strong>Cobrar Servicio de Mesa / Cubierto</strong> (se suma 1 vez por cuenta abierta)</label>
                            <input type="checkbox" id="check-servicio" name="cobra_servicio_mesa" value="1" <?= !empty($restaurante['cobra_servicio_mesa']) ? 'checked' : '' ?> onchange="toggleInputServicio(this.checked)" style="width:20px; height:20px; accent-color: var(--neon); cursor:pointer;">
                        </div>
                        <div id="wrap-precio-servicio" style="<?= !empty($restaurante['cobra_servicio_mesa']) ? '' : 'opacity:0.5;' ?>">
                            <label for="input-precio-servicio">Valor del Servicio de Mesa ($)</label>
                            <input type="number" step="0.01" min="0" id="input-precio-servicio" name="precio_servicio_mesa" value="<?= number_format((float)($restaurante['precio_servicio_mesa'] ?? 0), 2, '.', '') ?>" placeholder="Ej: 1200.00">
                            <small style="color:var(--text-muted); font-size:0.78rem; display:block; margin-top:5px;">
                                Si está activo, el cliente lo verá detallado en la cuenta acumulada de su mesa al pedir.
                            </small>
                        </div>
                    </div>

                    <button type="submit" class="btn-neon" style="width:100%;">💾 Guardar Configuración</button>
                </form>
            </div>

            <!-- Columna Derecha: Alias & API Token Android -->
            <div style="display:flex; flex-direction:column; gap:24px;">
                <div class="glass-card">
                    <h3>Enlace Amigable <span>Multi-Tenant</span></h3>
                    <label>URL Pública de tu Carta Digital</label>
                    <div class="mono-box">
                        <a href="../carta/<?= urlencode($restaurante['alias'] ?? '') ?>" target="_blank" style="color:var(--neon); text-decoration:none;">
                            /resto/carta/<?= htmlspecialchars($restaurante['alias'] ?? '') ?> ↗
                        </a>
                    </div>
                    <small style="color:var(--text-muted); font-size:0.83rem;">
                        Correo administrativo: <strong><?= htmlspecialchars($restaurante['email']) ?></strong>
                    </small>
                </div>

                <div class="glass-card">
                    <h3>API Token <span>Android / Retrofit</span></h3>
                    <p style="color:var(--text-muted); font-size:0.86rem; margin-bottom:14px;">
                        Usa este Bearer Token en la cabecera <code>Authorization: Bearer &lt;token&gt;</code> de la app móvil nativa en Kotlin.
                    </p>
                    <label>Bearer Token Activo</label>
                    <div class="mono-box" id="api-token-text"><?= htmlspecialchars($restaurante['api_token'] ?? 'Inicia sesión nuevamente para generarlo') ?></div>
                    <button type="button" class="btn-outline" style="width:100%; margin-top:6px;" onclick="copiarToken()">
                        📋 Copiar API Token
                    </button>
                </div>
            </div>
        </div>
    </main>

    <script>
        function toggleInputServicio(activo) {
            const wrap = document.getElementById('wrap-precio-servicio');
            wrap.style.opacity = activo ? '1' : '0.5';
        }

        function copiarToken() {
            const token = document.getElementById('api-token-text').innerText.trim();
            navigator.clipboard.writeText(token).then(() => {
                alert('API Token copiado al portapapeles.');
            });
        }
    </script>
</body>
</html>
