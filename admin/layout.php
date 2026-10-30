<?php
// admin/layout.php - Sistema de Diseño Unificado + Notificaciones Flotantes y Sonido Global en Tiempo Real
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['restaurante_id'])) {
    header("Location: ../index.php");
    exit;
}
header('Content-Type: text/html; charset=utf-8');

function renderAdminHead(string $tituloPagina): void {
    ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tituloPagina) ?> | SmartMenu Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-base: #070908;
            --bg-surface: rgba(18, 22, 20, 0.75);
            --bg-card: rgba(24, 29, 26, 0.68);
            --bg-input: rgba(0, 0, 0, 0.52);
            --neon: #39ff14;
            --neon-soft: rgba(57, 255, 20, 0.13);
            --neon-border: rgba(57, 255, 20, 0.38);
            --neon-glow: 0 0 26px rgba(57, 255, 20, 0.26);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border-glass: rgba(255, 255, 255, 0.09);
            --warning: #ffaa00;
            --warning-soft: rgba(255, 170, 0, 0.14);
            --danger: #ff4d4f;
            --danger-soft: rgba(255, 77, 79, 0.14);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        body {
            background-color: var(--bg-base);
            color: var(--text-main);
            line-height: 1.6;
            min-height: 100vh;
            background-image:
                radial-gradient(circle at 10% 15%, rgba(57, 255, 20, 0.09), transparent 28%),
                radial-gradient(circle at 90% 20%, rgba(57, 255, 20, 0.06), transparent 26%),
                radial-gradient(circle at 50% 90%, rgba(57, 255, 20, 0.05), transparent 32%);
        }

        /* Top Navigation Bar */
        .admin-nav {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(7, 9, 8, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border-glass);
            padding: 13px 4%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .brand-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-logo {
            font-size: 1.3rem;
            font-weight: 900;
            color: var(--text-main);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 9px;
            letter-spacing: -0.5px;
        }
        .brand-badge {
            width: 32px;
            height: 32px;
            border-radius: 9px;
            background: var(--neon-soft);
            border: 1px solid var(--neon);
            color: var(--neon);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 900;
            box-shadow: var(--neon-glow);
        }
        .brand-logo span { color: var(--neon); }

        .tenant-pill {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-glass);
            color: #e2e8f0;
            font-size: 0.78rem;
            font-weight: 600;
            padding: 5px 12px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .tenant-pill::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--neon);
            box-shadow: 0 0 8px var(--neon);
        }

        .nav-menu {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .nav-link {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.84rem;
            font-weight: 600;
            padding: 8px 13px;
            border-radius: 10px;
            transition: all 0.2s ease;
            border: 1px solid transparent;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            position: relative;
        }
        .nav-link:hover {
            color: var(--text-main);
            background: rgba(255, 255, 255, 0.04);
        }
        .nav-link.active {
            color: var(--neon);
            background: var(--neon-soft);
            border-color: var(--neon-border);
            font-weight: 700;
        }

        /* Contador en vivo en la pestaña de Cocina */
        .nav-live-badge {
            background: var(--danger);
            color: #fff;
            font-size: 0.7rem;
            font-weight: 900;
            padding: 1px 7px;
            border-radius: 999px;
            display: none;
            box-shadow: 0 0 10px rgba(255, 77, 79, 0.6);
            animation: navPulse 1.5s infinite;
        }
        .nav-live-badge.visible {
            display: inline-block;
        }
        @keyframes navPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.15); }
        }

        .btn-global-sound {
            background: var(--neon-soft);
            color: var(--neon);
            border: 1px solid var(--neon-border);
            padding: 7px 12px;
            border-radius: 10px;
            font-size: 0.78rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-global-sound.muted {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-muted);
            border-color: var(--border-glass);
        }

        .nav-logout {
            color: #ff6b6b;
            text-decoration: none;
            font-size: 0.82rem;
            font-weight: 700;
            padding: 8px 13px;
            border-radius: 10px;
            background: var(--danger-soft);
            border: 1px solid rgba(255, 77, 79, 0.3);
            transition: all 0.2s ease;
        }
        .nav-logout:hover {
            background: var(--danger);
            color: #fff;
        }

        /* =========================================================
           CONTENEDOR DE NOTIFICACIONES FLOTANTES GLOBALES (TOASTS)
           ========================================================= */
        #smartmenu-floating-alerts {
            position: fixed;
            top: 76px;
            right: 22px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-width: 390px;
            width: calc(100% - 44px);
            pointer-events: none;
        }

        .floating-order-alert {
            pointer-events: auto;
            background: rgba(14, 18, 16, 0.96);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border: 1px solid var(--neon);
            border-left: 6px solid var(--neon);
            border-radius: 18px;
            padding: 16px 18px;
            color: #fff;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.85), 0 0 28px rgba(57, 255, 20, 0.28);
            animation: slideInRight 0.35s cubic-bezier(0.22, 1, 0.36, 1);
            position: relative;
        }
        .floating-order-alert.tipo-cuenta {
            border-color: var(--warning);
            border-left-color: var(--warning);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.85), 0 0 28px rgba(255, 170, 0, 0.32);
        }

        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(120%); }
            to { opacity: 1; transform: translateX(0); }
        }

        .alert-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 6px;
        }
        .alert-tag {
            font-size: 0.72rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: var(--neon);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .floating-order-alert.tipo-cuenta .alert-tag {
            color: var(--warning);
        }
        .alert-close {
            background: rgba(255, 255, 255, 0.08);
            border: none;
            color: var(--text-muted);
            width: 24px;
            height: 24px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .alert-close:hover { color: #fff; background: rgba(255,255,255,0.18); }

        .alert-title {
            font-size: 1.12rem;
            font-weight: 900;
            margin-bottom: 4px;
            color: #fff;
        }
        .alert-items {
            font-size: 0.83rem;
            color: #cbd5e1;
            margin-bottom: 12px;
            line-height: 1.4;
        }
        .alert-actions {
            display: flex;
            gap: 8px;
        }
        .btn-alert-go {
            flex: 1;
            background: var(--neon);
            color: #050505;
            text-decoration: none;
            font-weight: 800;
            font-size: 0.8rem;
            padding: 8px 12px;
            border-radius: 10px;
            text-align: center;
            border: none;
            cursor: pointer;
        }
        .floating-order-alert.tipo-cuenta .btn-alert-go {
            background: var(--warning);
        }
        .btn-alert-print {
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
            border: 1px solid var(--border-glass);
            font-weight: 700;
            font-size: 0.8rem;
            padding: 8px 12px;
            border-radius: 10px;
            cursor: pointer;
        }

        /* Main Container */
        .admin-container {
            max-width: 1260px;
            margin: 0 auto;
            padding: 34px 4% 70px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 28px;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--border-glass);
        }
        .page-title h1 {
            font-size: 1.95rem;
            font-weight: 900;
            letter-spacing: -0.7px;
            color: var(--text-main);
        }
        .page-title h1 span { color: var(--neon); }
        .page-title p {
            color: var(--text-muted);
            font-size: 0.92rem;
            margin-top: 4px;
        }

        /* Glass Cards */
        .glass-card {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: 20px;
            padding: 26px;
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            box-shadow: 0 14px 40px rgba(0, 0, 0, 0.4);
        }
        .glass-card h2, .glass-card h3 {
            font-size: 1.2rem;
            font-weight: 800;
            margin-bottom: 16px;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .glass-card h2 span, .glass-card h3 span { color: var(--neon); }

        /* Forms & Inputs */
        .form-group {
            margin-bottom: 16px;
        }
        .form-label, label {
            display: block;
            margin-bottom: 7px;
            font-size: 0.78rem;
            font-weight: 700;
            color: #cbd5e1;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-control, input[type="text"], input[type="number"], input[type="email"], input[type="password"], input[type="date"], select, textarea {
            width: 100%;
            padding: 12px 15px;
            background: var(--bg-input);
            border: 1px solid var(--border-glass);
            color: var(--text-main);
            border-radius: 12px;
            font-size: 0.94rem;
            transition: all 0.2s ease;
        }
        textarea {
            min-height: 86px;
            resize: vertical;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--neon);
            box-shadow: 0 0 0 3px rgba(57, 255, 20, 0.14);
        }
        input[type="file"] {
            width: 100%;
            padding: 10px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px dashed var(--neon-border);
            border-radius: 12px;
            color: var(--text-muted);
            font-size: 0.86rem;
            cursor: pointer;
        }

        /* Buttons */
        .btn-neon {
            background: var(--neon);
            color: #050505;
            border: none;
            padding: 12px 22px;
            font-size: 0.92rem;
            font-weight: 800;
            border-radius: 12px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 8px 22px -6px rgba(57, 255, 20, 0.45);
        }
        .btn-neon:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px -6px rgba(57, 255, 20, 0.65);
        }
        .btn-neon:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-outline {
            background: rgba(255, 255, 255, 0.04);
            color: var(--text-main);
            border: 1px solid var(--border-glass);
            padding: 10px 18px;
            font-size: 0.86rem;
            font-weight: 700;
            border-radius: 11px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s ease;
        }
        .btn-outline:hover {
            border-color: var(--neon);
            color: var(--neon);
            background: var(--neon-soft);
        }

        .btn-danger {
            background: var(--danger);
            color: #fff;
            border: none;
            padding: 13px 26px;
            font-size: 0.95rem;
            font-weight: 800;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 8px 22px -6px rgba(255, 77, 79, 0.45);
        }
        .btn-danger:hover {
            transform: translateY(-2px);
            filter: brightness(1.08);
        }
        .btn-danger:disabled {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-muted);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Tables */
        .table-wrapper {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: 20px;
            overflow-x: auto;
            backdrop-filter: blur(14px);
            box-shadow: 0 14px 40px rgba(0, 0, 0, 0.4);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 15px 18px;
            text-align: left;
            border-bottom: 1px solid var(--border-glass);
            font-size: 0.92rem;
        }
        th {
            background: rgba(0, 0, 0, 0.45);
            color: var(--neon);
            font-size: 0.78rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }
        tbody tr {
            transition: background 0.15s ease;
        }
        tbody tr:hover {
            background: rgba(255, 255, 255, 0.03);
        }
        tbody tr:last-child td {
            border-bottom: none;
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 11px;
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 700;
        }
        .badge-neon {
            background: var(--neon-soft);
            color: var(--neon);
            border: 1px solid var(--neon-border);
        }
        .badge-warning {
            background: var(--warning-soft);
            color: var(--warning);
            border: 1px solid rgba(255, 170, 0, 0.35);
        }
        .badge-danger {
            background: var(--danger-soft);
            color: var(--danger);
            border: 1px solid rgba(255, 77, 79, 0.35);
        }

        .admin-footer {
            text-align: center;
            padding: 24px 4%;
            border-top: 1px solid var(--border-glass);
            color: var(--text-muted);
            font-size: 0.84rem;
            margin-top: 20px;
        }
        .admin-footer a {
            color: var(--neon);
            text-decoration: none;
            font-weight: 700;
        }
        .admin-footer a:hover {
            text-decoration: underline;
        }
    </style>
    <?php
}

function renderAdminNav(string $paginaActiva): void {
    $nombreRest = $_SESSION['restaurante_nombre'] ?? 'Mi Local';
    $items = [
        'dashboard'     => ['url' => 'dashboard.php',     'label' => '📊 Dashboard'],
        'pedidos'       => ['url' => 'pedidos.php',       'label' => '🔔 Cocina en Vivo'],
        'menu'          => ['url' => 'menu.php',          'label' => '🍔 Carta y Stock'],
        'mesas'         => ['url' => 'mesas.php',         'label' => '📱 Mesas y QR'],
        'caja'          => ['url' => 'caja.php',          'label' => '💰 Caja'],
        'historial'     => ['url' => 'historial.php',     'label' => '📈 Historial'],
        'configuracion' => ['url' => 'configuracion.php', 'label' => '⚙️ Configuración']
    ];
    ?>
    <nav class="admin-nav">
        <div class="brand-wrap">
            <a href="dashboard.php" class="brand-logo">
                <div class="brand-badge">QR</div>
                Smart<span>Menu</span>
            </a>
            <span class="tenant-pill"><?= htmlspecialchars($nombreRest) ?></span>
        </div>

        <div class="nav-menu">
            <?php foreach ($items as $key => $item): ?>
                <a href="<?= $item['url'] ?>" class="nav-link <?= ($paginaActiva === $key) ? 'active' : '' ?>">
                    <?= $item['label'] ?>
                    <?php if ($key === 'pedidos'): ?>
                        <span id="nav-badge-cocina" class="nav-live-badge">0</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
            <button type="button" id="btn-global-sound" class="btn-global-sound" onclick="SmartMenuAlerts.toggleMute()" title="Probar o silenciar timbre de pedidos">
                🔊 Sonido Activo
            </button>
            <a href="logout.php" class="nav-logout">Salir</a>
        </div>
    </nav>

    <!-- Contenedor Global de Notificaciones Flotantes (visible en todas las secciones del Panel) -->
    <div id="smartmenu-floating-alerts"></div>

    <script>
        const SmartMenuAlerts = (function() {
            let audioCtx = null;
            let muted = localStorage.getItem('smartmenu_sound_muted') === '1';
            let sse = null;

            function ensureAudioContext() {
                if (!audioCtx) {
                    const AudioCtx = window.AudioContext || window.webkitAudioContext;
                    if (AudioCtx) audioCtx = new AudioCtx();
                }
                if (audioCtx && audioCtx.state === 'suspended') {
                    audioCtx.resume().catch(() => {});
                }
            }

            // Desbloquear audio automáticamente al primer clic o tecla en cualquier parte del panel
            ['click', 'keydown', 'touchstart'].forEach(evt => {
                document.addEventListener(evt, ensureAudioContext, { passive: true });
            });

            function updateSoundButtonUI() {
                const btn = document.getElementById('btn-global-sound');
                if (!btn) return;
                if (muted) {
                    btn.classList.add('muted');
                    btn.textContent = '🔇 Sonido Silenciado';
                } else {
                    btn.classList.remove('muted');
                    btn.textContent = '🔊 Sonido Activo';
                }
            }

            function playChime(tipo = 'pedido') {
                if (muted) return;
                ensureAudioContext();
                if (!audioCtx) return;

                try {
                    const now = audioCtx.currentTime;
                    // Melodía de 3 notas llamativa para nuevo pedido o solicitud de cuenta
                    const freqs = tipo === 'cuenta'
                        ? [987.77, 1318.51, 1567.98] // Si5 - Mi6 - Sol6 (alerta de caja)
                        : [783.99, 1046.50, 1318.51]; // Sol5 - Do6 - Mi6 (timbre de cocina)

                    freqs.forEach((f, idx) => {
                        const osc = audioCtx.createOscillator();
                        const gain = audioCtx.createGain();
                        osc.type = 'triangle';
                        osc.frequency.setValueAtTime(f, now + idx * 0.14);
                        gain.gain.setValueAtTime(0.32, now + idx * 0.14);
                        gain.gain.exponentialRampToValueAtTime(0.001, now + idx * 0.14 + 0.45);
                        osc.connect(gain);
                        gain.connect(audioCtx.destination);
                        osc.start(now + idx * 0.14);
                        osc.stop(now + idx * 0.14 + 0.46);
                    });
                } catch (e) {}
            }

            function formatMoney(v) {
                return '$' + Number(v).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function escapeHtml(str) {
                const d = document.createElement('div');
                d.textContent = str;
                return d.innerHTML;
            }

            function showFloatingNotification({ tipo, titulo, detalleHtml, urlAccion, textoAccion, onPrint }) {
                const container = document.getElementById('smartmenu-floating-alerts');
                if (!container) return;

                const alertEl = document.createElement('div');
                alertEl.className = 'floating-order-alert ' + (tipo === 'cuenta' ? 'tipo-cuenta' : '');

                const tagTexto = tipo === 'cuenta'
                    ? '💳 SOLICITUD DE CUENTA EN MESA'
                    : '🔥 NUEVO PEDIDO ENTRANTE';

                alertEl.innerHTML = `
                    <div class="alert-top">
                        <span class="alert-tag">${tagTexto}</span>
                        <button type="button" class="alert-close" title="Cerrar notificación">✕</button>
                    </div>
                    <div class="alert-title">${escapeHtml(titulo)}</div>
                    <div class="alert-items">${detalleHtml}</div>
                    <div class="alert-actions">
                        <a href="${urlAccion}" class="btn-alert-go">${escapeHtml(textoAccion)}</a>
                        ${onPrint ? `<button type="button" class="btn-alert-print">🖨️ Ticket</button>` : ''}
                    </div>
                `;

                alertEl.querySelector('.alert-close').onclick = () => alertEl.remove();
                if (onPrint) {
                    const btnPrint = alertEl.querySelector('.btn-alert-print');
                    if (btnPrint) btnPrint.onclick = onPrint;
                }

                container.prepend(alertEl);

                // Auto-cerrar después de 14 segundos
                setTimeout(() => {
                    if (alertEl.parentNode) {
                        alertEl.style.opacity = '0';
                        alertEl.style.transition = 'opacity 0.3s ease';
                        setTimeout(() => alertEl.remove(), 300);
                    }
                }, 14000);
            }

            function processStreamPayload(res) {
                const pedidos = res.data || [];
                const cuentas = res.cuentas_mesas || [];

                // 1. Actualizar badge en la barra de navegación superior
                const pendientesCount = pedidos.filter(p => p.estado === 'pendiente').length;
                const cuentasPidiendoCount = cuentas.filter(c => c.pidio_cuenta).length;
                const totalUrgentes = pendientesCount + cuentasPidiendoCount;

                const navBadge = document.getElementById('nav-badge-cocina');
                if (navBadge) {
                    if (totalUrgentes > 0) {
                        navBadge.textContent = totalUrgentes;
                        navBadge.classList.add('visible');
                    } else {
                        navBadge.classList.remove('visible');
                    }
                }

                // 2. Comparar contra sessionStorage para detectar nuevos pedidos o cuentas sin importar en qué página esté
                const initialized = sessionStorage.getItem('smartmenu_rt_init') === '1';
                let knownOrders = [];
                let knownBills = [];
                try {
                    knownOrders = JSON.parse(sessionStorage.getItem('smartmenu_known_orders') || '[]');
                    knownBills  = JSON.parse(sessionStorage.getItem('smartmenu_known_bills') || '[]');
                } catch (e) {}

                const currentPendingOrders = pedidos.filter(p => p.estado === 'pendiente');
                const currentBills = cuentas.filter(c => c.pidio_cuenta);

                if (initialized) {
                    // Detectar pedidos pendientes nuevos
                    currentPendingOrders.forEach(p => {
                        if (!knownOrders.includes(p.id)) {
                            playChime('pedido');
                            const resumenItems = (p.items || [])
                                .map(i => `<strong>${i.cantidad}x</strong> ${escapeHtml(i.nombre)}`)
                                .join(', ');

                            showFloatingNotification({
                                tipo: 'pedido',
                                titulo: `Mesa ${p.numero_mesa} · Orden #${p.id}`,
                                detalleHtml: `${resumenItems}<br><strong style="color:var(--neon);">Total envío: ${formatMoney(p.total)}</strong>`,
                                urlAccion: 'pedidos.php',
                                textoAccion: 'Ir a Cocina en Vivo →',
                                onPrint: () => window.open('ticket.php?id=' + p.id, 'TicketCocina', 'width=400,height=600,scrollbars=yes')
                            });
                        }
                    });

                    // Detectar nuevas solicitudes de cuenta
                    currentBills.forEach(c => {
                        if (!knownBills.includes(c.id_mesa)) {
                            playChime('cuenta');
                            showFloatingNotification({
                                tipo: 'cuenta',
                                titulo: `¡Mesa ${c.numero_mesa} pidió la cuenta!`,
                                detalleHtml: `Total acumulado a cobrar: <strong style="color:var(--warning); font-size:1rem;">${formatMoney(c.total_acumulado)}</strong>`,
                                urlAccion: 'pedidos.php',
                                textoAccion: 'Ver y Cobrar Mesa →',
                                onPrint: () => window.open('ticket.php?modo=cuenta&id_mesa=' + c.id_mesa, 'TicketCuentaMesa', 'width=400,height=650,scrollbars=yes')
                            });
                        }
                    });
                }

                sessionStorage.setItem('smartmenu_known_orders', JSON.stringify(currentPendingOrders.map(p => p.id)));
                sessionStorage.setItem('smartmenu_known_bills', JSON.stringify(currentBills.map(c => c.id_mesa)));
                sessionStorage.setItem('smartmenu_rt_init', '1');

                // Si estamos en pedidos.php, notificar a la vista de cocina para que renderice las tarjetas
                if (typeof window.onSmartMenuPedidosUpdate === 'function') {
                    window.onSmartMenuPedidosUpdate(pedidos, cuentas);
                }
            }

            function initGlobalSSE() {
                updateSoundButtonUI();
                if (sse) sse.close();

                sse = new EventSource('sse_pedidos.php');

                sse.onopen = () => {
                    if (typeof window.onSmartMenuSSEStatus === 'function') {
                        window.onSmartMenuSSEStatus(true);
                    }
                };

                sse.addEventListener('pedidos', (event) => {
                    try {
                        const res = JSON.parse(event.data);
                        if (res.status === 'success') {
                            processStreamPayload(res);
                        }
                    } catch (e) {}
                });

                sse.onerror = () => {
                    if (typeof window.onSmartMenuSSEStatus === 'function') {
                        window.onSmartMenuSSEStatus(false);
                    }
                };
            }

            document.addEventListener('DOMContentLoaded', () => {
                initGlobalSSE();
                if (!document.querySelector('.admin-footer')) {
                    const footer = document.createElement('footer');
                    footer.className = 'admin-footer';
                    footer.innerHTML = '&copy; 2026 SmartMenu SaaS · Desarrollado por <a href="https://github.com/JoelGaleano" target="_blank" rel="noopener noreferrer">GaleanoJoel</a>';
                    document.body.appendChild(footer);
                }
            });

            return {
                toggleMute: function() {
                    muted = !muted;
                    localStorage.setItem('smartmenu_sound_muted', muted ? '1' : '0');
                    updateSoundButtonUI();
                    if (!muted) {
                        playChime('pedido');
                    }
                },
                playChime: playChime,
                refreshNow: function() {
                    fetch('api_pedidos.php', { headers: { 'Accept': 'application/json' } })
                        .then(r => r.json())
                        .then(res => {
                            if (res.status === 'success') processStreamPayload(res);
                        });
                }
            };
        })();
    </script>
    <?php
}
?>
