<?php
require 'layout.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php renderAdminHead("Historial y Reportes Excel"); ?>
    <style>
        .filter-bar {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 18px;
            margin-bottom: 24px;
        }
        .filter-controls {
            display: flex;
            align-items: flex-end;
            gap: 12px;
            flex-wrap: wrap;
        }
        .kpi-banner {
            background: linear-gradient(135deg, rgba(57, 255, 20, 0.12), rgba(24, 29, 26, 0.85));
            border: 1px solid var(--neon-border);
            border-radius: 20px;
            padding: 24px 28px;
            margin-bottom: 26px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .kpi-banner h2 {
            font-size: 1rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin: 0;
        }
        .total-valor {
            font-size: 2.3rem;
            color: var(--neon);
            font-weight: 900;
            letter-spacing: -1px;
        }
        .mozo-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(56, 189, 248, 0.14);
            color: #38bdf8;
            border: 1px solid rgba(56, 189, 248, 0.35);
            padding: 4px 11px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
        }
        .resumen-items {
            max-width: 280px;
            font-size: 0.84rem;
            color: #cbd5e1;
            line-height: 1.45;
        }
        .resumen-items span.qty {
            color: var(--neon);
            font-weight: 800;
        }
        .btn-ver-detalle {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(57, 255, 20, 0.1);
            border: 1px solid var(--neon-border);
            color: var(--neon);
            padding: 6px 12px;
            border-radius: 9px;
            font-size: 0.78rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-ver-detalle:hover {
            background: var(--neon);
            color: #050505;
            box-shadow: var(--neon-glow);
        }

        /* Modal Detalle de Pedido en Historial */
        .modal-detalle-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.82);
            backdrop-filter: blur(8px);
            z-index: 250;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.22s ease;
        }
        .modal-detalle-overlay.open {
            opacity: 1;
            pointer-events: auto;
        }
        .modal-detalle-box {
            background: #141816;
            border: 1px solid var(--neon);
            border-radius: 22px;
            padding: 26px 24px;
            max-width: 520px;
            width: 100%;
            max-height: 88vh;
            overflow-y: auto;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.85), var(--neon-glow);
        }
        .modal-detalle-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 16px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border-glass);
        }
        .modal-detalle-header h3 {
            font-size: 1.3rem;
            font-weight: 900;
            color: #fff;
            margin: 0;
        }
        .modal-detalle-header h3 span {
            color: var(--neon);
        }
        .detalle-meta-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 16px;
        }
        .detalle-meta-item {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-glass);
            border-radius: 12px;
            padding: 10px 12px;
        }
        .detalle-meta-item small {
            display: block;
            font-size: 0.7rem;
            text-transform: uppercase;
            color: var(--text-muted);
            font-weight: 700;
            margin-bottom: 2px;
        }
        .detalle-meta-item strong {
            font-size: 0.92rem;
            color: #fff;
        }
        .detalle-items-list {
            list-style: none;
            padding: 0;
            margin: 0 0 16px 0;
            border: 1px solid var(--border-glass);
            border-radius: 14px;
            overflow: hidden;
        }
        .detalle-items-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding: 11px 14px;
            border-bottom: 1px solid var(--border-glass);
            font-size: 0.9rem;
        }
        .detalle-items-list li:last-child {
            border-bottom: none;
        }
        .detalle-item-qty {
            background: var(--neon-soft);
            color: var(--neon);
            border: 1px solid var(--neon-border);
            padding: 2px 8px;
            border-radius: 7px;
            font-weight: 800;
            font-size: 0.78rem;
            margin-right: 8px;
        }
        .detalle-item-unit {
            display: block;
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 2px;
        }
        .detalle-total-box {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(57, 255, 20, 0.08);
            border: 1px solid var(--neon-border);
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 18px;
        }
        .detalle-total-box span {
            font-weight: 800;
            color: #e2e8f0;
        }
        .detalle-total-box strong {
            font-size: 1.4rem;
            font-weight: 900;
            color: var(--neon);
        }
    </style>
</head>
<body>
    <?php renderAdminNav('historial'); ?>

    <main class="admin-container">
        <div class="page-header">
            <div class="page-title">
                <h1>Historial y <span>Reportes de Ventas</span></h1>
                <p>Filtra tus pedidos por fecha, consulta el detalle completo de productos consumidos, verifica el mozo asignado y exporta a Excel.</p>
            </div>
            <a href="exportar_excel.php" class="btn-neon">📊 Exportar Todo a Excel (.CSV)</a>
        </div>

        <div class="glass-card filter-bar">
            <div class="filter-controls">
                <div>
                    <label for="fecha-filtro">Seleccionar Fecha de Consulta</label>
                    <input type="date" id="fecha-filtro" value="<?= date('Y-m-d') ?>" style="min-width:210px;">
                </div>
                <button type="button" class="btn-neon" onclick="cargarHistorial()">🔍 Filtrar Día</button>
            </div>
            <span class="badge badge-neon" id="contador-ventas-dia">Consultando...</span>
        </div>

        <div class="kpi-banner">
            <div>
                <h2>Recaudación de la Fecha Seleccionada</h2>
                <small style="color:var(--text-muted);">Suma total de pedidos completados en estado Servido</small>
            </div>
            <div class="total-valor" id="recaudacion-total">$0,00</div>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID Pedido</th>
                        <th>Mesa</th>
                        <th>Mozo que Tomó el Pedido</th>
                        <th>Resumen de Productos</th>
                        <th>Hora</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tabla-cuerpo"></tbody>
            </table>
        </div>
    </main>

    <!-- MODAL PARA VER EL DETALLE COMPLETO DEL PEDIDO -->
    <div class="modal-detalle-overlay" id="modal-detalle" onclick="if(event.target === this) cerrarModalDetalle()">
        <div class="modal-detalle-box">
            <div class="modal-detalle-header">
                <div>
                    <h3 id="det-titulo">Pedido <span>#0</span></h3>
                    <small style="color:var(--text-muted);" id="det-subtitulo">Desglose de productos e importes</small>
                </div>
                <span id="det-estado-badge"></span>
            </div>

            <div class="detalle-meta-grid">
                <div class="detalle-meta-item">
                    <small>Mesa</small>
                    <strong id="det-mesa">Mesa -</strong>
                </div>
                <div class="detalle-meta-item">
                    <small>Hora del Pedido</small>
                    <strong id="det-hora">--:-- hs</strong>
                </div>
                <div class="detalle-meta-item" style="grid-column: 1 / -1;">
                    <small>Mozo que Tomó el Pedido</small>
                    <strong id="det-mozo" style="color:#38bdf8;">👤 -</strong>
                </div>
            </div>

            <ul class="detalle-items-list" id="det-lista-items"></ul>

            <div class="detalle-total-box">
                <span>Total del Pedido:</span>
                <strong id="det-total">$0,00</strong>
            </div>

            <div style="display:flex; gap:10px;">
                <button type="button" class="btn-outline" style="flex:1;" onclick="cerrarModalDetalle()">Cerrar</button>
                <button type="button" class="btn-neon" style="flex:1.2;" id="btn-reimprimir-det">🖨️ Imprimir Ticket (58mm)</button>
            </div>
        </div>
    </div>

    <script>
        let pedidosCargados = [];

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function formatearPrecio(valor) {
            return '$' + Number(valor).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function construirResumenItems(items, servicioMesa) {
            if (!items || items.length === 0) {
                return '<span style="color:var(--text-muted);">Sin detalle de productos</span>';
            }
            const primeros = items.slice(0, 2).map(it =>
                `<span class="qty">${it.cantidad}x</span> ${escapeHtml(it.nombre)}`
            ).join(', ');
            const extras = items.length > 2 ? ` <span style="color:var(--text-muted); font-weight:700;">(+${items.length - 2} más)</span>` : '';
            const servTag = servicioMesa > 0 ? ` <span style="color:#fcd34d; font-size:0.75rem;">+ Serv. Mesa</span>` : '';
            return `<div class="resumen-items">${primeros}${extras}${servTag}</div>`;
        }

        function cargarHistorial() {
            const fecha = document.getElementById('fecha-filtro').value;
            const tabla = document.getElementById('tabla-cuerpo');
            const recaTotal = document.getElementById('recaudacion-total');
            const contador = document.getElementById('contador-ventas-dia');

            tabla.innerHTML = '<tr><td colspan="8" style="text-align:center; padding:28px; color:var(--text-muted);">Cargando historial...</td></tr>';

            fetch('api_historial.php?fecha=' + encodeURIComponent(fecha), {
                headers: { 'Accept': 'application/json' }
            })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        pedidosCargados = data.data || [];
                        recaTotal.innerText = formatearPrecio(data.recaudacion);

                        const cantServidos = pedidosCargados.filter(p => p.estado === 'servido').length;
                        const cantCancelados = pedidosCargados.filter(p => p.estado === 'cancelado').length;
                        contador.innerText = cantCancelados > 0
                            ? `${cantServidos} servidos · ${cantCancelados} cancelados`
                            : `${cantServidos} pedidos servidos`;

                        tabla.innerHTML = '';
                        if (pedidosCargados.length === 0) {
                            tabla.innerHTML = '<tr><td colspan="8" style="text-align:center; color:var(--text-muted); padding:32px;">No hay pedidos registrados en esta fecha.</td></tr>';
                            return;
                        }

                        pedidosCargados.forEach((pedido, idx) => {
                            const mozoNombre = escapeHtml(pedido.nombre_mozo || 'Sin asignar');
                            const resumenHTML = construirResumenItems(pedido.items, pedido.cargo_servicio_mesa);
                            const esCancelado = pedido.estado === 'cancelado';

                            const badgeEstado = esCancelado
                                ? `<span class="badge badge-danger">❌ Cancelado</span>`
                                : `<span class="badge badge-neon">✔ Servido</span>`;

                            const colorMonto = esCancelado ? 'color:var(--text-muted); text-decoration:line-through;' : 'color:var(--neon);';

                            tabla.innerHTML += `
                                <tr>
                                    <td><strong>#${pedido.id}</strong></td>
                                    <td>Mesa ${pedido.numero_mesa}</td>
                                    <td><span class="mozo-pill">👤 ${mozoNombre}</span></td>
                                    <td>${resumenHTML}</td>
                                    <td style="color:var(--text-muted);">${pedido.hora} hs</td>
                                    <td style="font-weight:800; ${colorMonto}">${formatearPrecio(pedido.total)}</td>
                                    <td>${badgeEstado}</td>
                                    <td>
                                        <button type="button" class="btn-ver-detalle" onclick="verDetallePedido(${idx})">
                                            🔍 Ver Detalle
                                        </button>
                                    </td>
                                </tr>
                            `;
                        });
                    }
                })
                .catch(() => {
                    tabla.innerHTML = '<tr><td colspan="8" style="text-align:center; color:var(--danger); padding:28px;">Error al cargar los datos.</td></tr>';
                });
        }

        function verDetallePedido(idx) {
            const p = pedidosCargados[idx];
            if (!p) return;

            document.getElementById('det-titulo').innerHTML = `Pedido <span>#${p.id}</span>`;
            document.getElementById('det-mesa').textContent = `Mesa ${p.numero_mesa}`;
            document.getElementById('det-hora').textContent = `${p.hora} hs`;
            document.getElementById('det-mozo').textContent = `👤 ${p.nombre_mozo || 'Sin asignar'}`;
            document.getElementById('det-total').textContent = formatearPrecio(p.total);

            const badgeCont = document.getElementById('det-estado-badge');
            badgeCont.innerHTML = p.estado === 'cancelado'
                ? `<span class="badge badge-danger">❌ Cancelado</span>`
                : `<span class="badge badge-neon">✔ Servido</span>`;

            const lista = document.getElementById('det-lista-items');
            let htmlItems = '';

            if (p.items && p.items.length > 0) {
                htmlItems = p.items.map(it => `
                    <li>
                        <div>
                            <span class="detalle-item-qty">${it.cantidad}x</span>
                            <strong>${escapeHtml(it.nombre)}</strong>
                            <span class="detalle-item-unit">Precio unitario: ${formatearPrecio(it.precio_unitario)}</span>
                        </div>
                        <strong style="color:#fff;">${formatearPrecio(it.subtotal)}</strong>
                    </li>
                `).join('');
            } else {
                htmlItems = `<li style="color:var(--text-muted); justify-content:center;">No hay productos registrados en este pedido.</li>`;
            }

            if (p.cargo_servicio_mesa > 0) {
                htmlItems += `
                    <li style="background:rgba(255, 170, 0, 0.07);">
                        <div>
                            <span class="detalle-item-qty" style="background:var(--warning-soft); color:var(--warning); border-color:var(--warning);">1x</span>
                            <strong style="color:#fcd34d;">🍽️ Servicio de Mesa</strong>
                        </div>
                        <strong style="color:#fcd34d;">${formatearPrecio(p.cargo_servicio_mesa)}</strong>
                    </li>
                `;
            }

            lista.innerHTML = htmlItems;

            const btnPrint = document.getElementById('btn-reimprimir-det');
            btnPrint.onclick = function() {
                window.open('ticket.php?id=' + p.id, 'TicketDetalle', 'width=400,height=600,scrollbars=yes');
            };

            document.getElementById('modal-detalle').classList.add('open');
        }

        function cerrarModalDetalle() {
            document.getElementById('modal-detalle').classList.remove('open');
        }

        window.onload = cargarHistorial;
    </script>
</body>
</html>
