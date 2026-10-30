<?php
require 'conexion.php';
require 'layout.php';

$id_restaurante = (int) $_SESSION['restaurante_id'];

// Obtener datos del restaurante (nombre, logo, telefono, direccion)
$stmtRest = $pdo->prepare("SELECT nombre, alias, telefono, direccion, logo FROM restaurantes WHERE id = ?");
$stmtRest->execute([$id_restaurante]);
$restaurante = $stmtRest->fetch();

// Obtener todas las mesas del restaurante para poder cambiar de mesa en vivo desde el editor
$stmtMesas = $pdo->prepare("SELECT id, numero_mesa, hash_qr FROM mesas WHERE id_restaurante = ? ORDER BY numero_mesa ASC");
$stmtMesas->execute([$id_restaurante]);
$mesas = $stmtMesas->fetchAll();

if (empty($mesas)) {
    header("Location: mesas.php");
    exit;
}

// Mesa seleccionada inicialmente por GET (?id_mesa=... o ?hash=...)
$hashSeleccionado = $_GET['hash'] ?? $mesas[0]['hash_qr'];
$mesaActual = $mesas[0];
foreach ($mesas as $m) {
    if ($m['hash_qr'] === $hashSeleccionado) {
        $mesaActual = $m;
        break;
    }
}

$logoActualUrl = !empty($restaurante['logo']) ? '../' . $restaurante['logo'] : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php renderAdminHead("Diseñador de Cartel QR (.PDF)"); ?>
    <!-- Librerías ligeras para exportar el diseño exacto a archivo .PDF descargable -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        .editor-layout {
            display: grid;
            grid-template-columns: 420px 1fr;
            gap: 28px;
            align-items: start;
        }

        .controles-panel {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .control-section {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: 20px;
            padding: 22px;
            backdrop-filter: blur(14px);
        }
        .control-section h3 {
            font-size: 1rem;
            font-weight: 800;
            margin-bottom: 14px;
            color: var(--neon);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .presets-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-bottom: 16px;
        }
        .preset-btn {
            background: rgba(0, 0, 0, 0.45);
            border: 1px solid var(--border-glass);
            color: var(--text-main);
            padding: 9px 10px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        .preset-btn:hover {
            border-color: var(--neon);
        }
        .preset-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .color-pickers {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        .color-field {
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid var(--border-glass);
            border-radius: 12px;
            padding: 8px 10px;
            text-align: center;
        }
        .color-field label {
            font-size: 0.7rem;
            margin-bottom: 5px;
        }
        .color-field input[type="color"] {
            width: 100%;
            height: 34px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            background: transparent;
            padding: 0;
        }

        /* Área de Vista Previa en Vivo */
        .preview-stage {
            background: radial-gradient(circle at center, rgba(255,255,255,0.05), rgba(0,0,0,0.65));
            border: 1px solid var(--border-glass);
            border-radius: 24px;
            padding: 40px 24px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 680px;
            position: sticky;
            top: 90px;
        }

        .preview-toolbar {
            display: flex;
            gap: 12px;
            margin-bottom: 26px;
            flex-wrap: wrap;
            justify-content: center;
        }

        /* =====================================================
           CARTEL IMPRIMIBLE DE MESA (TENT CARD)
           ===================================================== */
        #cartel-qr {
            width: 380px;
            min-height: 540px;
            background-color: #0b0e0c;
            color: #ffffff;
            border: 3px solid #39ff14;
            border-radius: 26px;
            padding: 32px 26px 26px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            text-align: center;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.75);
            position: relative;
            overflow: hidden;
            transition: width 0.25s ease, min-height 0.25s ease;
        }

        #cartel-qr.formato-cuadrado {
            width: 420px;
            min-height: 440px;
            padding: 24px;
        }

        .cartel-decoracion-top {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 8px;
            background-color: #39ff14;
        }

        .cartel-logo-wrap {
            width: 78px;
            height: 78px;
            border-radius: 50%;
            border: 2px solid #39ff14;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.06);
            margin-bottom: 10px;
        }
        .cartel-logo-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .cartel-nombre-local {
            font-size: 1.55rem;
            font-weight: 900;
            letter-spacing: -0.5px;
            line-height: 1.15;
            margin: 4px 0 8px;
        }

        .cartel-mesa-badge {
            background-color: #39ff14;
            color: #050505;
            font-weight: 900;
            font-size: 0.95rem;
            padding: 6px 22px;
            border-radius: 999px;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin-bottom: 14px;
            display: inline-block;
        }

        .cartel-cta {
            font-size: 0.92rem;
            font-weight: 600;
            opacity: 0.9;
            max-width: 280px;
            margin-bottom: 14px;
        }

        .cartel-qr-box {
            background: #ffffff;
            padding: 14px;
            border-radius: 20px;
            border: 3px solid #39ff14;
            width: 215px;
            height: 215px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 4px auto 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
        }
        .cartel-qr-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .cartel-pasos {
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            opacity: 0.8;
            margin-bottom: 12px;
        }

        .cartel-redes {
            width: 100%;
            padding-top: 12px;
            border-top: 1px dashed rgba(255, 255, 255, 0.22);
            font-size: 0.82rem;
            font-weight: 600;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        @media (max-width: 980px) {
            .editor-layout {
                grid-template-columns: 1fr;
            }
            .preview-stage {
                position: static;
            }
        }

        /* Estilos limpios por si el usuario usa Imprimir nativo del navegador */
        @media print {
            body * { visibility: hidden; }
            #cartel-qr, #cartel-qr * {
                visibility: visible;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            #cartel-qr {
                position: fixed;
                left: 50%;
                top: 50%;
                transform: translate(-50%, -50%);
                box-shadow: none !important;
            }
        }
    </style>
</head>
<body>
    <?php renderAdminNav('mesas'); ?>

    <main class="admin-container">
        <div class="page-header">
            <div class="page-title">
                <h1>Diseñador de <span>Cartel QR Imprimible (.PDF)</span></h1>
                <p>Personaliza tu porta-menú de mesa con tu logo, colores, redes sociales o WiFi y descárgalo en PDF listo para imprimir.</p>
            </div>
            <a href="mesas.php" class="btn-outline">← Volver a Mesas</a>
        </div>

        <div class="editor-layout">
            
            <!-- PANEL IZQUIERDO: CONTROLES DE PERSONALIZACIÓN -->
            <div class="controles-panel">
                
                <!-- 1. Selección de Mesa y Formato -->
                <div class="control-section">
                    <h3>1. Mesa y Formato</h3>
                    <div class="form-group">
                        <label>Seleccionar Mesa</label>
                        <select id="select-mesa" onchange="cambiarMesaActiva(this)">
                            <?php foreach ($mesas as $m): ?>
                                <option value="<?= htmlspecialchars($m['hash_qr']) ?>" data-numero="<?= (int)$m['numero_mesa'] ?>" <?= ($m['hash_qr'] === $mesaActual['hash_qr']) ? 'selected' : '' ?>>
                                    Mesa N° <?= (int)$m['numero_mesa'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <label>Formato del Cartel</label>
                        <select id="select-formato" onchange="cambiarFormato(this.value)">
                            <option value="vertical">Vertical Acrílico (10 x 15 cm)</option>
                            <option value="cuadrado">Tarjeta Cuadrada (12 x 12 cm)</option>
                        </select>
                    </div>
                </div>

                <!-- 2. Colores y Temas -->
                <div class="control-section">
                    <h3>2. Paleta de Colores</h3>
                    <label>Temas Rápidos</label>
                    <div class="presets-grid">
                        <button type="button" class="preset-btn" onclick="aplicarTema('#0b0e0c', '#39ff14', '#ffffff')">
                            <span class="preset-dot" style="background:#39ff14;"></span> Neón Dark
                        </button>
                        <button type="button" class="preset-btn" onclick="aplicarTema('#18120b', '#f59e0b', '#fef3c7')">
                            <span class="preset-dot" style="background:#f59e0b;"></span> Cervecería Ámbar
                        </button>
                        <button type="button" class="preset-btn" onclick="aplicarTema('#0f172a', '#38bdf8', '#f8fafc')">
                            <span class="preset-dot" style="background:#38bdf8;"></span> Lounge Azul
                        </button>
                        <button type="button" class="preset-btn" onclick="aplicarTema('#ffffff', '#111827', '#111827')">
                            <span class="preset-dot" style="background:#ffffff; border:1px solid #888;"></span> Minimal Blanco
                        </button>
                    </div>

                    <div class="color-pickers">
                        <div class="color-field">
                            <label>Fondo</label>
                            <input type="color" id="color-bg" value="#0b0e0c" oninput="actualizarColores()">
                        </div>
                        <div class="color-field">
                            <label>Acento / Borde</label>
                            <input type="color" id="color-accent" value="#39ff14" oninput="actualizarColores()">
                        </div>
                        <div class="color-field">
                            <label>Texto</label>
                            <input type="color" id="color-text" value="#ffffff" oninput="actualizarColores()">
                        </div>
                    </div>
                </div>

                <!-- 3. Marca, Textos y Redes -->
                <div class="control-section">
                    <h3>3. Logo, Textos y Redes</h3>

                    <div class="form-group">
                        <label>Subir / Cambiar Logo para el Cartel</label>
                        <input type="file" accept="image/*" onchange="cargarLogoLocal(event)">
                        <div style="margin-top:8px; display:flex; align-items:center; gap:8px;">
                            <input type="checkbox" id="check-mostrar-logo" <?= $logoActualUrl ? 'checked' : '' ?> onchange="toggleLogo(this.checked)" style="width:auto;">
                            <span style="font-size:0.82rem; color:var(--text-muted);">Mostrar logo en la cabecera</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Nombre del Lugar</label>
                        <input type="text" id="input-nombre" value="<?= htmlspecialchars($restaurante['nombre']) ?>" oninput="actualizarTextos()">
                    </div>

                    <div class="form-group">
                        <label>Texto de la Mesa</label>
                        <input type="text" id="input-mesa" value="MESA <?= (int)$mesaActual['numero_mesa'] ?>" oninput="actualizarTextos()">
                    </div>

                    <div class="form-group">
                        <label>Frase / Llamado a la Acción</label>
                        <input type="text" id="input-cta" value="¡Escaneá el código QR para ver la carta y pedir desde tu celular!" oninput="actualizarTextos()">
                    </div>

                    <div class="form-group">
                        <label>Instagram / Redes Sociales</label>
                        <input type="text" id="input-redes" value="📸 @<?= htmlspecialchars($restaurante['alias'] ?? 'milocal') ?>" oninput="actualizarTextos()">
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <label>WiFi para Clientes / Pie de Página</label>
                        <input type="text" id="input-wifi" value="📶 WiFi Gratis · Consultá la clave en barra" oninput="actualizarTextos()">
                    </div>
                </div>

            </div>

            <!-- PANEL DERECHO: VISTA PREVIA Y DESCARGA PDF -->
            <div class="preview-stage">
                <div class="preview-toolbar">
                    <button type="button" id="btn-descargar-pdf" class="btn-neon" onclick="descargarPDF()">
                        📄 Descargar Cartel en .PDF
                    </button>
                    <button type="button" class="btn-outline" onclick="window.print()">
                        🖨️ Imprimir Directo
                    </button>
                </div>

                <!-- CARTEL RENDERIZADO -->
                <div id="cartel-qr">
                    <div class="cartel-decoracion-top" id="view-topbar"></div>

                    <div style="display:flex; flex-direction:column; align-items:center; width:100%;">
                        <div class="cartel-logo-wrap" id="view-logo-wrap" style="<?= $logoActualUrl ? '' : 'display:none;' ?>">
                            <img id="view-logo-img" src="<?= htmlspecialchars($logoActualUrl) ?>" alt="Logo">
                        </div>

                        <div class="cartel-nombre-local" id="view-nombre">
                            <?= htmlspecialchars($restaurante['nombre']) ?>
                        </div>

                        <div class="cartel-mesa-badge" id="view-mesa">
                            MESA <?= (int)$mesaActual['numero_mesa'] ?>
                        </div>

                        <div class="cartel-cta" id="view-cta">
                            ¡Escaneá el código QR para ver la carta y pedir desde tu celular!
                        </div>
                    </div>

                    <div>
                        <div class="cartel-qr-box" id="view-qr-box">
                            <img id="view-qr-img" src="generar_qr.php?hash=<?= urlencode($mesaActual['hash_qr']) ?>" crossorigin="anonymous" alt="Código QR">
                        </div>
                        <div class="cartel-pasos">
                            1. Escaneá &nbsp;•&nbsp; 2. Elegí &nbsp;•&nbsp; 3. Pedí
                        </div>
                    </div>

                    <div class="cartel-redes" id="view-footer">
                        <span id="view-redes">📸 @<?= htmlspecialchars($restaurante['alias'] ?? 'milocal') ?></span>
                        <span id="view-wifi" style="opacity:0.8;">📶 WiFi Gratis · Consultá la clave en barra</span>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script>
        function cambiarMesaActiva(select) {
            const option = select.options[select.selectedIndex];
            const numMesa = option.getAttribute('data-numero');
            const hash = select.value;

            document.getElementById('input-mesa').value = 'MESA ' + numMesa;
            document.getElementById('view-mesa').textContent = 'MESA ' + numMesa;
            document.getElementById('view-qr-img').src = 'generar_qr.php?hash=' + encodeURIComponent(hash);
        }

        function cambiarFormato(formato) {
            const cartel = document.getElementById('cartel-qr');
            cartel.classList.toggle('formato-cuadrado', formato === 'cuadrado');
        }

        function aplicarTema(bg, accent, text) {
            document.getElementById('color-bg').value = bg;
            document.getElementById('color-accent').value = accent;
            document.getElementById('color-text').value = text;
            actualizarColores();
        }

        function actualizarColores() {
            const bg = document.getElementById('color-bg').value;
            const accent = document.getElementById('color-accent').value;
            const text = document.getElementById('color-text').value;

            const cartel = document.getElementById('cartel-qr');
            cartel.style.backgroundColor = bg;
            cartel.style.borderColor = accent;
            cartel.style.color = text;

            document.getElementById('view-topbar').style.backgroundColor = accent;
            document.getElementById('view-logo-wrap').style.borderColor = accent;
            document.getElementById('view-qr-box').style.borderColor = accent;

            const badge = document.getElementById('view-mesa');
            badge.style.backgroundColor = accent;
            // Contraste automático para el texto dentro del badge de mesa
            badge.style.color = esColorClaro(accent) ? '#050505' : '#ffffff';
        }

        function esColorClaro(hex) {
            const c = hex.substring(1);
            const rgb = parseInt(c, 16);
            const r = (rgb >> 16) & 0xff;
            const g = (rgb >>  8) & 0xff;
            const b = (rgb >>  0) & 0xff;
            const luma = 0.299 * r + 0.587 * g + 0.114 * b;
            return luma > 140;
        }

        function actualizarTextos() {
            document.getElementById('view-nombre').textContent = document.getElementById('input-nombre').value;
            document.getElementById('view-mesa').textContent = document.getElementById('input-mesa').value;
            document.getElementById('view-cta').textContent = document.getElementById('input-cta').value;
            document.getElementById('view-redes').textContent = document.getElementById('input-redes').value;
            document.getElementById('view-wifi').textContent = document.getElementById('input-wifi').value;
        }

        function cargarLogoLocal(event) {
            const file = event.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.getElementById('view-logo-img');
                img.src = e.target.result;
                document.getElementById('check-mostrar-logo').checked = true;
                toggleLogo(true);
            };
            reader.readAsDataURL(file);
        }

        function toggleLogo(mostrar) {
            const wrap = document.getElementById('view-logo-wrap');
            wrap.style.display = mostrar ? 'flex' : 'none';
        }

        async function descargarPDF() {
            const btn = document.getElementById('btn-descargar-pdf');
            const textoOriginal = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '⏳ Generando PDF en alta calidad...';

            try {
                const cartel = document.getElementById('cartel-qr');
                const canvas = await html2canvas(cartel, {
                    scale: 3, // Alta resolución (300 DPI aprox)
                    useCORS: true,
                    backgroundColor: null
                });

                const imgData = canvas.toDataURL('image/png');
                const { jsPDF } = window.jspdf;

                const formato = document.getElementById('select-formato').value;
                // Dimensiones reales en milímetros (100x150mm para vertical o 120x120mm para cuadrado)
                const anchoMM = formato === 'cuadrado' ? 120 : 105;
                const altoMM  = formato === 'cuadrado' ? 125 : 150;

                const pdf = new jsPDF({
                    orientation: 'portrait',
                    unit: 'mm',
                    format: [anchoMM, altoMM]
                });

                pdf.addImage(imgData, 'PNG', 0, 0, anchoMM, altoMM);

                const nombreMesa = document.getElementById('input-mesa').value.replace(/[^a-zA-Z0-9_-]/g, '_');
                pdf.save(`Cartel_QR_${nombreMesa}.pdf`);
            } catch (e) {
                alert('No se pudo generar el PDF automáticamente. Se abrirá el diálogo de impresión a PDF.');
                window.print();
            } finally {
                btn.disabled = false;
                btn.innerHTML = textoOriginal;
            }
        }
    </script>
</body>
</html>
