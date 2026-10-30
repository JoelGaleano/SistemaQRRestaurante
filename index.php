<?php
// Si el usuario entra a la portada principal, cerramos cualquier sesión previa abierta
// para que siempre pueda probar la experiencia completa de iniciar sesión desde cero.
session_start();
$_SESSION = [];
session_destroy();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartMenu | SaaS de Cartas QR y Comandas en Tiempo Real</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-base: #070908;
            --bg-surface: rgba(18, 22, 20, 0.72);
            --bg-card: rgba(24, 29, 26, 0.65);
            --neon: #39ff14;
            --neon-soft: rgba(57, 255, 20, 0.14);
            --neon-border: rgba(57, 255, 20, 0.38);
            --neon-glow: 0 0 28px rgba(57, 255, 20, 0.28);
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
            scroll-behavior: smooth;
        }

        body {
            background-color: var(--bg-base);
            color: var(--text-main);
            line-height: 1.6;
            overflow-x: hidden;
            background-image:
                radial-gradient(circle at 12% 18%, rgba(57, 255, 20, 0.11), transparent 30%),
                radial-gradient(circle at 88% 22%, rgba(57, 255, 20, 0.07), transparent 28%),
                radial-gradient(circle at 50% 85%, rgba(57, 255, 20, 0.06), transparent 35%);
        }

        /* Navbar Glassmorphism */
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 6%;
            background: rgba(7, 9, 8, 0.78);
            border-bottom: 1px solid var(--border-glass);
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }

        .logo {
            font-size: 1.45rem;
            font-weight: 900;
            color: var(--text-main);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.5px;
        }
        .logo-badge {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: var(--neon-soft);
            border: 1px solid var(--neon);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--neon);
            font-weight: 900;
            box-shadow: var(--neon-glow);
        }
        .logo span { color: var(--neon); }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 28px;
        }
        .nav-links a {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.25s ease;
        }
        .nav-links a:hover { color: var(--neon); }

        .btn-nav-cta {
            background: var(--neon);
            color: #050505 !important;
            padding: 9px 22px;
            border-radius: 999px;
            font-weight: 800 !important;
            box-shadow: var(--neon-glow);
        }
        .btn-nav-cta:hover {
            transform: translateY(-1px);
            filter: brightness(1.08);
        }

        /* Hero Section */
        .hero {
            max-width: 1240px;
            margin: 0 auto;
            padding: 76px 6% 60px;
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 48px;
            align-items: center;
        }

        .badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 16px;
            background: var(--neon-soft);
            border: 1px solid var(--neon-border);
            color: var(--neon);
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin-bottom: 22px;
        }
        .badge-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--neon);
            box-shadow: 0 0 10px var(--neon);
        }

        .hero h1 {
            font-size: clamp(2.5rem, 4.6vw, 4.1rem);
            font-weight: 900;
            line-height: 1.07;
            letter-spacing: -1.5px;
            margin-bottom: 22px;
        }
        .hero h1 span {
            color: var(--neon);
            text-shadow: 0 0 32px rgba(57, 255, 20, 0.32);
        }

        .hero p {
            font-size: 1.12rem;
            color: var(--text-muted);
            max-width: 580px;
            margin-bottom: 32px;
            font-weight: 400;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            margin-bottom: 38px;
        }

        .btn-primary {
            background: var(--neon);
            color: #050505;
            text-decoration: none;
            padding: 15px 32px;
            font-size: 1rem;
            font-weight: 800;
            border-radius: 14px;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: none;
            cursor: pointer;
            box-shadow: 0 10px 28px -6px rgba(57, 255, 20, 0.45);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 34px -6px rgba(57, 255, 20, 0.65);
        }
        .btn-primary:disabled {
            opacity: 0.65;
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.04);
            color: var(--text-main);
            text-decoration: none;
            padding: 15px 26px;
            font-size: 0.98rem;
            font-weight: 700;
            border-radius: 14px;
            border: 1px solid var(--border-glass);
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-secondary:hover {
            border-color: var(--neon);
            color: var(--neon);
            background: var(--neon-soft);
        }

        .hero-metrics {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            padding-top: 24px;
            border-top: 1px solid var(--border-glass);
        }
        .metric-item strong {
            display: block;
            font-size: 1.5rem;
            font-weight: 900;
            color: #fff;
        }
        .metric-item strong span { color: var(--neon); }
        .metric-item small {
            color: var(--text-muted);
            font-size: 0.82rem;
        }

        /* Hero Interactive Mockup Card (Ilustración de Plataforma) */
        .hero-visual {
            background: var(--bg-surface);
            border: 1px solid var(--border-glass);
            border-radius: 24px;
            padding: 24px;
            backdrop-filter: blur(18px);
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.65), 0 0 40px rgba(57, 255, 20, 0.08);
            position: relative;
        }
        .visual-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 16px;
            margin-bottom: 16px;
            border-bottom: 1px solid var(--border-glass);
        }
        .visual-dots {
            display: flex;
            gap: 6px;
        }
        .visual-dots span {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
        }
        .visual-dots span:first-child { background: #ff5f56; }
        .visual-dots span:nth-child(2) { background: #ffbd2e; }
        .visual-dots span:nth-child(3) { background: #27c93f; }

        .mock-order {
            background: rgba(12, 15, 13, 0.85);
            border: 1px solid var(--border-glass);
            border-left: 4px solid var(--neon);
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 12px;
        }
        .mock-order-top {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .mock-tag {
            background: var(--neon-soft);
            color: var(--neon);
            padding: 2px 10px;
            border-radius: 999px;
            font-size: 0.74rem;
        }
        .mock-items {
            color: var(--text-muted);
            font-size: 0.84rem;
            list-style: none;
        }
        .mock-items li { margin: 4px 0; }
        .mock-items b { color: var(--neon); }

        /* Features Section */
        .features {
            padding: 70px 6%;
            max-width: 1240px;
            margin: 0 auto;
        }
        .section-header {
            text-align: center;
            max-width: 680px;
            margin: 0 auto 50px;
        }
        .section-header h2 {
            font-size: 2.3rem;
            font-weight: 900;
            letter-spacing: -0.8px;
            margin-bottom: 12px;
        }
        .section-header h2 span { color: var(--neon); }
        .section-header p { color: var(--text-muted); }

        .grid-features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
        }
        .card {
            background: var(--bg-card);
            padding: 30px 26px;
            border-radius: 20px;
            border: 1px solid var(--border-glass);
            backdrop-filter: blur(12px);
            transition: all 0.3s ease;
        }
        .card:hover {
            border-color: var(--neon-border);
            transform: translateY(-6px);
            box-shadow: 0 14px 35px rgba(0, 0, 0, 0.5), 0 0 25px rgba(57, 255, 20, 0.12);
        }
        .icon-box {
            width: 48px;
            height: 48px;
            background: var(--neon-soft);
            border: 1px solid var(--neon-border);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 18px;
        }
        .card h3 {
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .card p {
            color: var(--text-muted);
            font-size: 0.92rem;
            line-height: 1.6;
        }

        /* Portal de Acceso y Registro */
        .auth-wrapper {
            max-width: 1140px;
            margin: 20px auto 90px;
            padding: 0 6%;
        }

        .auth-container {
            display: grid;
            grid-template-columns: 0.95fr 1.05fr;
            background: var(--bg-surface);
            border: 1px solid var(--border-glass);
            border-radius: 28px;
            overflow: hidden;
            backdrop-filter: blur(20px);
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.7);
        }

        .auth-sidebar {
            padding: 46px 40px;
            background:
                radial-gradient(circle at top left, rgba(57, 255, 20, 0.16), transparent 55%),
                linear-gradient(180deg, rgba(15, 20, 17, 0.95), rgba(10, 12, 11, 0.98));
            border-right: 1px solid var(--border-glass);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .auth-sidebar h3 {
            font-size: 1.75rem;
            font-weight: 900;
            line-height: 1.2;
            margin-bottom: 14px;
        }
        .auth-sidebar h3 span { color: var(--neon); }
        .auth-sidebar > div > p {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin-bottom: 24px;
        }

        .feature-checklist {
            list-style: none;
            margin-bottom: 28px;
        }
        .feature-checklist li {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            color: #e2e8f0;
            margin-bottom: 12px;
        }
        .check-icon {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: var(--neon-soft);
            border: 1px solid var(--neon);
            color: var(--neon);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 900;
            flex-shrink: 0;
        }

        .credenciales-box {
            background: rgba(0, 0, 0, 0.55);
            border: 1px dashed var(--neon-border);
            border-radius: 16px;
            padding: 18px;
        }
        .credenciales-box .cred-title {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: var(--neon);
            font-weight: 800;
            margin-bottom: 8px;
        }
        .cred-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            margin-bottom: 6px;
            color: var(--text-muted);
        }
        .cred-row code {
            color: #fff;
            font-weight: 700;
            background: rgba(255,255,255,0.07);
            padding: 2px 7px;
            border-radius: 6px;
            user-select: all;
        }

        /* Columna Derecha: Tabs Login / Registro */
        .auth-main {
            padding: 42px 44px;
        }

        .auth-tabs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: rgba(0, 0, 0, 0.45);
            padding: 5px;
            border-radius: 14px;
            border: 1px solid var(--border-glass);
            margin-bottom: 28px;
        }
        .tab-btn {
            background: transparent;
            color: var(--text-muted);
            border: none;
            padding: 11px;
            font-size: 0.92rem;
            font-weight: 700;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.25s ease;
        }
        .tab-btn.active {
            background: var(--neon);
            color: #050505;
            box-shadow: 0 4px 16px rgba(57, 255, 20, 0.28);
        }

        .auth-panel {
            display: none;
            animation: fadeIn 0.25s ease;
        }
        .auth-panel.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .panel-heading {
            margin-bottom: 22px;
        }
        .panel-heading h2 {
            font-size: 1.55rem;
            font-weight: 800;
            margin-bottom: 4px;
        }
        .panel-heading p {
            color: var(--text-muted);
            font-size: 0.88rem;
        }

        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-size: 0.78rem;
            font-weight: 700;
            color: #cbd5e1;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-icon {
            position: absolute;
            left: 14px;
            color: var(--text-muted);
            font-size: 1rem;
            pointer-events: none;
        }
        .form-control {
            width: 100%;
            padding: 13px 16px 13px 42px;
            background: rgba(0, 0, 0, 0.52);
            border: 1px solid var(--border-glass);
            color: #fff;
            border-radius: 12px;
            transition: all 0.2s ease;
            font-size: 0.95rem;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--neon);
            background: rgba(57, 255, 20, 0.03);
            box-shadow: 0 0 0 3px rgba(57, 255, 20, 0.14);
        }

        .toggle-pass {
            position: absolute;
            right: 12px;
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            padding: 4px 6px;
        }
        .toggle-pass:hover { color: var(--neon); }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .respuesta {
            margin-top: 16px;
            font-weight: 600;
            font-size: 0.88rem;
            text-align: center;
            padding: 12px 14px;
            border-radius: 12px;
            display: none;
        }
        .text-success {
            background: var(--neon-soft);
            color: var(--neon);
            border: 1px solid var(--neon-border);
            display: block;
        }
        .text-error {
            background: rgba(255, 77, 79, 0.12);
            color: #ff6b6b;
            border: 1px solid rgba(255, 77, 79, 0.35);
            display: block;
        }

        footer {
            text-align: center;
            padding: 36px 6%;
            border-top: 1px solid var(--border-glass);
            color: var(--text-muted);
            font-size: 0.88rem;
        }

        @media (max-width: 960px) {
            .hero {
                grid-template-columns: 1fr;
                padding-top: 48px;
            }
            .auth-container {
                grid-template-columns: 1fr;
            }
            .auth-sidebar {
                border-right: none;
                border-bottom: 1px solid var(--border-glass);
            }
        }

        @media (max-width: 600px) {
            .nav-links a:not(.btn-nav-cta) { display: none; }
            .form-row { grid-template-columns: 1fr; gap: 0; }
            .auth-main, .auth-sidebar { padding: 28px 22px; }
            .hero-metrics { grid-template-columns: 1fr; gap: 12px; }
        }
    </style>
</head>
<body>

    <nav>
        <a href="#" class="logo">
            <div class="logo-badge">QR</div>
            Smart<span>Menu</span>
        </a>
        <div class="nav-links">
            <a href="#caracteristicas">Módulos</a>
            <a href="#acceso" onclick="cambiarTab('registro')">Registrar Local</a>
            <a href="#acceso" class="btn-nav-cta" onclick="cambiarTab('login')">Iniciar Sesión</a>
        </div>
    </nav>

    <header class="hero">
        <div>
            <div class="badge-pill">
                <span class="badge-dot"></span>
                SaaS Gastronómico Multi-Tenant · SSE Tiempo Real
            </div>
            <h1>Digitaliza tu local con <span>Cartas QR y Cocina en Vivo.</span></h1>
            <p>Tus clientes escanean el QR en su mesa, arman su pedido desde el celular y la comanda suena al instante en la pantalla de cocina e impresora térmica.</p>

            <div class="hero-actions">
                <a href="#acceso" class="btn-primary" onclick="cambiarTab('login')">Ingresar a mi Cuenta →</a>
                <a href="#acceso" class="btn-secondary" onclick="cambiarTab('registro')">Crear Cuenta Gratis</a>
            </div>

            <div class="hero-metrics">
                <div class="metric-item">
                    <strong>&lt; 0.2<span>s</span></strong>
                    <small>Latencia en Cocina (SSE)</small>
                </div>
                <div class="metric-item">
                    <strong>100<span>%</span></strong>
                    <small>Aislamiento Multi-Tenant</small>
                </div>
                <div class="metric-item">
                    <strong>REST<span> API</span></strong>
                    <small>Listo para Android / Retrofit</small>
                </div>
            </div>
        </div>

        <div class="hero-visual">
            <div class="visual-header">
                <div class="visual-dots">
                    <span></span><span></span><span></span>
                </div>
                <span style="font-size: 0.8rem; color: var(--neon); font-weight: 700;">● Vista Previa · Monitor de Cocina SSE</span>
            </div>

            <div class="mock-order">
                <div class="mock-order-top">
                    <span>Mesa 1 · Sector Terraza</span>
                    <span class="mock-tag">NUEVA COMANDA</span>
                </div>
                <ul class="mock-items">
                    <li><b>2x</b> Pinta Artesanal Session IPA</li>
                    <li><b>1x</b> Papas Bravas con Cheddar &amp; Bacon</li>
                    <li><b>1x</b> Burger Doble Smash Bourbon BBQ</li>
                </ul>
            </div>

            <div class="mock-order" style="border-left-color: #ffaa00; opacity: 0.9;">
                <div class="mock-order-top">
                    <span>Mesa 4 · Salón Principal</span>
                    <span style="color: #ffaa00; font-size: 0.75rem;">EN PREPARACIÓN</span>
                </div>
                <ul class="mock-items">
                    <li><b>2x</b> Gin Tonic Botánico de Autor</li>
                    <li><b>1x</b> Tequeños con Dip de Maracuyá</li>
                </ul>
            </div>

            <div class="mock-order" style="border-left-color: #39ff14; opacity: 0.75; margin-bottom: 0;">
                <div class="mock-order-top">
                    <span>Mesa 2 · Barra</span>
                    <span style="color: var(--neon); font-size: 0.75rem;">✔ SERVIDO · TICKET 58MM</span>
                </div>
                <ul class="mock-items">
                    <li><b>1x</b> Tabla Degustación para Compartir</li>
                </ul>
            </div>
        </div>
    </header>

    <section id="caracteristicas" class="features">
        <div class="section-header">
            <h2>Todo lo que tu local necesita en <span>un solo panel</span></h2>
            <p>Diseñado para cervecerías, bares, hamburgueserías y restaurantes de alto volumen.</p>
        </div>
        <div class="grid-features">
            <div class="card">
                <div class="icon-box">📱</div>
                <h3>Carta QR con Carrito Persistente</h3>
                <p>Experiencia Mobile-First sin descargar apps. El carrito se guarda en el celular del cliente y valida precios y stock en vivo.</p>
            </div>
            <div class="card">
                <div class="icon-box">🔔</div>
                <h3>Cocina en Tiempo Real (SSE)</h3>
                <p>Comandas instantáneas por Server-Sent Events con timbre sonoro integrado y formato de ticket térmico de 58mm.</p>
            </div>
            <div class="card">
                <div class="icon-box">💰</div>
                <h3>Control de Caja y Reportes</h3>
                <p>Cierres de turno transaccionales, métricas diarias en vivo e historial exportable a Excel/CSV con un clic.</p>
            </div>
        </div>
    </section>

    <section id="acceso" class="auth-wrapper">
        <div class="auth-container">
            
            <!-- Sidebar Informativo + Credenciales de Prueba -->
            <div class="auth-sidebar">
                <div>
                    <h3>Gestiona tu local en <span>tiempo real</span></h3>
                    <p>Ingresa a tu panel administrativo o registra tu restaurante desde el formulario.</p>

                    <ul class="feature-checklist">
                        <li><span class="check-icon">✓</span> Pausa o activa platos y bebidas con 1 clic</li>
                        <li><span class="check-icon">✓</span> Generador automático de QRs descargables por mesa</li>
                        <li><span class="check-icon">✓</span> Impresión de comandas térmicas de 58mm</li>
                        <li><span class="check-icon">✓</span> API Token incluido para la App Android</li>
                    </ul>
                </div>

                <div class="credenciales-box">
                    <div class="cred-title">🔑 Credenciales Demo para Probar el Login</div>
                    <div class="cred-row">
                        <span>Usuario (Email):</span>
                        <code>cerveceria@smartmenu.com</code>
                    </div>
                    <div class="cred-row" style="margin-bottom:0;">
                        <span>Contraseña:</span>
                        <code>cerveza123</code>
                    </div>
                </div>
            </div>

            <!-- Formulario con Pestañas (Login / Registro) -->
            <div class="auth-main">
                <div class="auth-tabs">
                    <button type="button" class="tab-btn active" id="tab-btn-login" onclick="cambiarTab('login')">
                        Iniciar Sesión
                    </button>
                    <button type="button" class="tab-btn" id="tab-btn-registro" onclick="cambiarTab('registro')">
                        Crear Cuenta Gratis
                    </button>
                </div>

                <!-- PANEL LOGIN -->
                <div class="auth-panel active" id="panel-login">
                    <div class="panel-heading">
                        <h2>Ingresar al Panel</h2>
                        <p>Introduce tu correo y contraseña para acceder a la cuenta de tu restaurante.</p>
                    </div>

                    <form id="form-login" onsubmit="procesarLogin(event)" autocomplete="off">
                        <div class="form-group">
                            <label for="login-email">Correo Electrónico</label>
                            <div class="input-wrapper">
                                <span class="input-icon">✉️</span>
                                <input type="email" id="login-email" class="form-control" placeholder="tu@restaurante.com" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="login-password">Contraseña</label>
                            <div class="input-wrapper">
                                <span class="input-icon">🔒</span>
                                <input type="password" id="login-password" class="form-control" placeholder="••••••••" required>
                                <button type="button" class="toggle-pass" onclick="togglePassword('login-password', this)">Mostrar</button>
                            </div>
                        </div>

                        <button type="submit" id="btn-submit-login" class="btn-primary" style="width: 100%; margin-top: 8px;">
                            Iniciar Sesión →
                        </button>
                        <div id="msj-login" class="respuesta"></div>
                    </form>
                </div>

                <!-- PANEL REGISTRO -->
                <div class="auth-panel" id="panel-registro">
                    <div class="panel-heading">
                        <h2>Registra tu Restaurante</h2>
                        <p>Configura tu carta digital y empieza a recibir pedidos por QR hoy mismo.</p>
                    </div>

                    <form id="form-registro" onsubmit="procesarRegistro(event)" autocomplete="off">
                        <div class="form-group">
                            <label for="reg-nombre">Nombre Comercial del Local</label>
                            <div class="input-wrapper">
                                <span class="input-icon">🏪</span>
                                <input type="text" id="reg-nombre" class="form-control" placeholder="Ej: Mi Restaurante" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="reg-telefono">Teléfono / WhatsApp</label>
                                <div class="input-wrapper">
                                    <span class="input-icon">📞</span>
                                    <input type="text" id="reg-telefono" class="form-control" placeholder="+54 9 11..." required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="reg-direccion">Dirección del Local</label>
                                <div class="input-wrapper">
                                    <span class="input-icon">📍</span>
                                    <input type="text" id="reg-direccion" class="form-control" placeholder="Calle y Número" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="reg-email">Correo Electrónico de Acceso</label>
                            <div class="input-wrapper">
                                <span class="input-icon">✉️</span>
                                <input type="email" id="reg-email" class="form-control" placeholder="admin@milocal.com" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="reg-password">Contraseña Segura</label>
                            <div class="input-wrapper">
                                <span class="input-icon">🔑</span>
                                <input type="password" id="reg-password" class="form-control" placeholder="Mínimo 6 caracteres" minlength="6" required>
                                <button type="button" class="toggle-pass" onclick="togglePassword('reg-password', this)">Mostrar</button>
                            </div>
                        </div>

                        <button type="submit" id="btn-submit-registro" class="btn-primary" style="width: 100%; margin-top: 8px;">
                            Crear Cuenta de Restaurante →
                        </button>
                        <div id="msj-registro" class="respuesta"></div>
                    </form>
                </div>

            </div>
        </div>
    </section>

    <footer>
        &copy; 2026 SmartMenu SaaS · Plataforma Multi-Tenant de Cartas QR y Comandas en Tiempo Real · Desarrollado por <a href="https://github.com/JoelGaleano" target="_blank" rel="noopener noreferrer" style="color: var(--neon); text-decoration: none; font-weight: 700;">GaleanoJoel</a>
    </footer>

    <script>
        function cambiarTab(tab) {
            document.getElementById('tab-btn-login').classList.toggle('active', tab === 'login');
            document.getElementById('tab-btn-registro').classList.toggle('active', tab === 'registro');
            document.getElementById('panel-login').classList.toggle('active', tab === 'login');
            document.getElementById('panel-registro').classList.toggle('active', tab === 'registro');
        }

        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
                btn.textContent = 'Ocultar';
            } else {
                input.type = 'password';
                btn.textContent = 'Mostrar';
            }
        }

        function mostrarMensaje(elementoId, mensaje, tipo) {
            const div = document.getElementById(elementoId);
            div.innerText = mensaje;
            div.className = `respuesta text-${tipo}`;
        }

        function procesarLogin(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-submit-login');
            btn.disabled = true;
            btn.innerText = "Verificando credenciales...";

            const datos = new URLSearchParams();
            datos.append('email', document.getElementById('login-email').value.trim());
            datos.append('password', document.getElementById('login-password').value);

            fetch('admin/login.php', { method: 'POST', body: datos })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    mostrarMensaje('msj-login', '¡Acceso correcto! Redirigiendo a tu panel...', 'success');
                    window.location.href = 'admin/' + data.redirect;
                } else {
                    mostrarMensaje('msj-login', data.message || data.mensaje || 'Credenciales incorrectas', 'error');
                    btn.disabled = false;
                    btn.innerText = "Iniciar Sesión →";
                }
            }).catch(() => {
                mostrarMensaje('msj-login', 'Error de red al conectar con el servidor.', 'error');
                btn.disabled = false;
                btn.innerText = "Iniciar Sesión →";
            });
        }

        function procesarRegistro(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-submit-registro');
            btn.disabled = true;
            btn.innerText = "Creando tu restaurante...";

            const emailReg = document.getElementById('reg-email').value.trim();
            const passReg = document.getElementById('reg-password').value;

            const datos = new URLSearchParams();
            datos.append('nombre', document.getElementById('reg-nombre').value.trim());
            datos.append('telefono', document.getElementById('reg-telefono').value.trim());
            datos.append('direccion', document.getElementById('reg-direccion').value.trim());
            datos.append('email', emailReg);
            datos.append('password', passReg);

            fetch('admin/registro.php', { method: 'POST', body: datos })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    mostrarMensaje('msj-registro', data.message || data.mensaje, 'success');
                    document.getElementById('form-registro').reset();
                    setTimeout(() => {
                        cambiarTab('login');
                        mostrarMensaje('msj-login', '¡Cuenta creada! Ingresa tu correo y contraseña para iniciar sesión.', 'success');
                    }, 1200);
                } else {
                    mostrarMensaje('msj-registro', data.message || data.mensaje || 'Error al registrar', 'error');
                }
                btn.disabled = false;
                btn.innerText = "Crear Cuenta de Restaurante →";
            }).catch(() => {
                mostrarMensaje('msj-registro', 'Error de red al conectar con el servidor.', 'error');
                btn.disabled = false;
                btn.innerText = "Crear Cuenta de Restaurante →";
            });
        }
    </script>
</body>
</html>
