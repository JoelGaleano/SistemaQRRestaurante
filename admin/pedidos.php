<?php
require 'layout.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php renderAdminHead("Monitor de Cocina y Cuentas de Mesa"); ?>
    <style>
        .status-controls {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .sse-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--neon-soft);
            border: 1px solid var(--neon);
            color: var(--neon);
            padding: 8px 16px;
            border-radius: 999px;
            font-size: 0.84rem;
            font-weight: 800;
            box-shadow: var(--neon-glow);
        }
        .sse-badge.reconectando {
            background: var(--warning-soft);
            border-color: var(--warning);
            color: var(--warning);
            box-shadow: none;
        }
        .dot-live {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: currentColor;
            box-shadow: 0 0 8px currentColor;
        }

        .seccion-bloque {
            margin-bottom: 36px;
        }
        .seccion-subtitulo {
            font-size: 1.25rem;
            font-weight: 900;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #fff;
        }
        .seccion-subtitulo span { color: var(--neon); }

        #contenedor-cuentas, #contenedor-pedidos {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(330px, 1fr));
            gap: 22px;
        }

        .tarjeta-pedido {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: 20px;
            padding: 22px;
            position: relative;
            backdrop-filter: blur(14px);
            box-shadow: 0 14px 36px rgba(0, 0, 0, 0.45);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .tarjeta-pedido.pendiente {
            border-left: 5px solid var(--danger);
            animation: pulse-rojo 2s infinite;
        }
        .tarjeta-pedido.preparando {
            border-left: 5px solid var(--neon);
        }

        .tarjeta-cuenta {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-left: 5px solid #38bdf8;
            border-radius: 20px;
            padding: 22px;
            backdrop-filter: blur(14px);
            box-shadow: 0 14px 36px rgba(0, 0, 0, 0.45);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .tarjeta-cuenta.pidio-cuenta {
            border-color: var(--warning);
            border-left: 6px solid var(--warning);
            background: linear-gradient(145deg, rgba(255, 170, 0, 0.14), rgba(24, 29, 26, 0.92));
            animation: pulse-dorado 1.8s infinite;
        }

        @keyframes pulse-rojo {
            0% { box-shadow: 0 0 0 0 rgba(255, 77, 79, 0.4); }
            70% { box-shadow: 0 0 12px 12px rgba(255, 77, 79, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 77, 79, 0); }
        }
        @keyframes pulse-dorado {
            0% { box-shadow: 0 0 0 0 rgba(255, 170, 0, 0.45); }
            70% { box-shadow: 0 0 14px 14px rgba(255, 170, 0, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 170, 0, 0); }
        }

        .pedido-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .mesa-info {
            font-size: 1.45rem;
            font-weight: 900;
            color: #fff;
            margin: 0;
        }
        .hora-info {
            color: var(--text-muted);
            font-size: 0.82rem;
            font-weight: 600;
            display: block;
        }
        .mozo-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 6px;
            background: rgba(56, 189, 248, 0.14);
            color: #38bdf8;
            border: 1px solid rgba(56, 189, 248, 0.35);
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 700;
        }

        .lista-items {
            list-style: none;
            padding: 12px 0;
            margin: 8px 0 14px;
            border-top: 1px solid var(--border-glass);
            border-bottom: 1px solid var(--border-glass);
        }
        .lista-items li {
            font-size: 0.92rem;
            padding: 5px 0;
            color: #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
        }
        .item-qty-badge {
            background: var(--neon-soft);
            color: var(--neon);
            border: 1px solid var(--neon-border);
            padding: 2px 8px;
            border-radius: 7px;
            font-weight: 800;
            font-size: 0.8rem;
            margin-right: 6px;
        }

        .total-cuenta-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 1.15rem;
            font-weight: 900;
            margin-bottom: 14px;
            color: #fff;
        }
        .total-cuenta-row strong {
            color: var(--neon);
            font-size: 1.35rem;
        }

        .acciones-pedido {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .btn-preparar {
            background: var(--warning);
            color: #050505;
            border: none;
            padding: 11px;
            font-weight: 800;
            border-radius: 11px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .btn-servir {
            background: var(--neon);
            color: #050505;
            border: none;
            padding: 11px;
            font-weight: 800;
            border-radius: 11px;
            cursor: pointer;
            font-size: 0.9rem;
            box-shadow: var(--neon-glow);
        }
        .btn-cobrar-mesa {
            background: var(--neon);
            color: #050505;
            border: none;
            padding: 12px;
            font-weight: 900;
            border-radius: 11px;
            cursor: pointer;
            font-size: 0.92rem;
            box-shadow: var(--neon-glow);
        }
        .btn-imprimir {
            background: rgba(255, 255, 255, 0.06);
            color: var(--text-main);
            border: 1px solid var(--border-glass);
            padding: 10px;
            font-weight: 700;
            border-radius: 11px;
            cursor: pointer;
            font-size: 0.85rem;
        }
        .btn-imprimir:hover {
            border-color: var(--neon);
            color: var(--neon);
        }
        .btn-cancelar-pedido {
            background: rgba(255, 77, 79, 0.1);
            color: var(--danger);
            border: 1px solid rgba(255, 77, 79, 0.35);
            padding: 9px;
            font-weight: 800;
            border-radius: 11px;
            cursor: pointer;
            font-size: 0.84rem;
            transition: all 0.2s ease;
        }
        .btn-cancelar-pedido:hover {
            background: var(--danger);
            color: #fff;
            border-color: var(--danger);
            box-shadow: 0 0 16px rgba(255, 77, 79, 0.4);
        }
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 38px 20px;
        }

        /* Modal para Asignar Nombre del Mozo al Aceptar Pedido */
        .modal-mozo-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.82);
            backdrop-filter: blur(8px);
            z-index: 200;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.22s ease;
        }
        .modal-mozo-overlay.open {
            opacity: 1;
            pointer-events: auto;
        }
        .modal-mozo-box {
            background: #141816;
            border: 1px solid var(--neon);
            border-radius: 22px;
            padding: 26px 24px;
            max-width: 420px;
            width: 100%;
            box-shadow: 0 24px 60px rgba(0,0,0,0.8), var(--neon-glow);
        }
        .modal-mozo-box h3 {
            color: var(--neon);
            font-size: 1.25rem;
            margin-bottom: 6px;
        }
        .modal-mozo-box p {
            color: var(--text-muted);
            font-size: 0.88rem;
            margin-bottom: 16px;
        }
        .mozos-recientes {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 16px;
        }
        .chip-mozo {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid var(--border-glass);
            color: #e2e8f0;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .chip-mozo:hover {
            background: var(--neon);
            color: #050505;
            border-color: var(--neon);
        }
    </style>
</head>
<body>
    <?php renderAdminNav('pedidos'); ?>

    <main class="admin-container">
        <div class="page-header">
            <div class="page-title">
                <h1>Monitor de <span>Cocina y Cuentas de Mesa</span></h1>
                <p>Acepta comandas asignando el mozo responsable y controla las cuentas acumuladas abiertas por mesa.</p>
            </div>
            <div class="status-controls">
                <div id="sse-status" class="sse-badge">
                    <span class="dot-live"></span>
                    <span id="sse-texto">Conectando SSE...</span>
                </div>
            </div>
        </div>

        <!-- SECCIÓN 1: CUENTAS ABIERTAS POR MESA Y SOLICITUDES DE CUENTA -->
        <section class="seccion-bloque">
            <h2 class="seccion-subtitulo">🧾 Cuentas Abiertas en Mesas <span>(Acumulado hasta cobrar)</span></h2>
            <div id="contenedor-cuentas"></div>
        </section>

        <!-- SECCIÓN 2: COMANDAS DE COCINA (PENDIENTES / PREPARANDO) -->
        <section class="seccion-bloque">
            <h2 class="seccion-subtitulo">🔥 Comandas de Cocina <span>(Platos por preparar / entregar)</span></h2>
            <div id="contenedor-pedidos"></div>
        </section>
    </main>

    <!-- MODAL PARA INGRESAR EL NOMBRE DEL MOZO AL EMPEZAR A PREPARAR -->
    <div class="modal-mozo-overlay" id="modal-mozo" onclick="if(event.target === this) cerrarModalMozo()">
        <div class="modal-mozo-box">
            <h3>👤 ¿Qué mozo toma el pedido?</h3>
            <p id="modal-mozo-subtitulo">Ingresa o selecciona el nombre del mozo que acepta esta comanda para registrarlo en el historial.</p>

            <form onsubmit="confirmarAceptarConMozo(event)">
                <input type="hidden" id="modal-id-pedido" value="">
                <div class="form-group">
                    <label for="input-nombre-mozo">Nombre del Mozo / Camarero</label>
                    <input type="text" id="input-nombre-mozo" placeholder="Ej: Lucas, Sofía, Martín..." required autocomplete="off">
                </div>

                <div id="contenedor-mozos-recientes" style="display:none;">
                    <label style="font-size:0.72rem; color:var(--text-muted);">Selección rápida (mozos recientes):</label>
                    <div class="mozos-recientes" id="lista-chips-mozos"></div>
                </div>

                <div style="display:flex; gap:10px; margin-top:16px;">
                    <button type="button" class="btn-outline" style="flex:0.8;" onclick="cerrarModalMozo()">Cancelar</button>
                    <button type="submit" class="btn-neon" style="flex:1.2;">🔥 Aceptar y Preparar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL PARA CONFIRMAR CANCELACIÓN DE UN PEDIDO EN COCINA EN VIVO -->
    <div class="modal-mozo-overlay" id="modal-cancelar" onclick="if(event.target === this) cerrarModalCancelar()">
        <div class="modal-mozo-box" style="border-color: var(--danger); box-shadow: 0 24px 60px rgba(0,0,0,0.85), 0 0 24px rgba(255, 77, 79, 0.35);">
            <h3 style="color: var(--danger);">❌ ¿Cancelar este pedido?</h3>
            <p id="modal-cancelar-subtitulo">Esta acción anulará la comanda de cocina y descontará su importe de la cuenta abierta de la mesa.</p>
            <input type="hidden" id="modal-cancelar-id" value="">

            <div style="display:flex; gap:10px; margin-top:18px;">
                <button type="button" class="btn-outline" style="flex:1;" onclick="cerrarModalCancelar()">Volver</button>
                <button type="button" class="btn-danger" style="flex:1.2; padding:12px;" onclick="confirmarCancelacionPedido()">❌ Sí, Cancelar Pedido</button>
            </div>
        </div>
    </div>

    <script>
        const STORAGE_MOZOS_KEY = 'smartmenu_mozos_recientes';

        function obtenerMozosRecientes() {
            try {
                const guardados = JSON.parse(localStorage.getItem(STORAGE_MOZOS_KEY) || '[]');
                return Array.isArray(guardados) ? guardados : [];
            } catch (e) {
                return [];
            }
        }

        function guardarMozoReciente(nombre) {
            const limpio = nombre.trim();
            if (!limpio) return;
            let lista = obtenerMozosRecientes().filter(m => m.toLowerCase() !== limpio.toLowerCase());
            lista.unshift(limpio);
            lista = lista.slice(0, 8);
            try {
                localStorage.setItem(STORAGE_MOZOS_KEY, JSON.stringify(lista));
            } catch (e) {}
        }

        function formatearMoneda(valor) {
            return '$' + Number(valor).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        window.onSmartMenuSSEStatus = function(conectado) {
            const badge = document.getElementById('sse-status');
            const texto = document.getElementById('sse-texto');
            if (!badge || !texto) return;
            if (conectado) {
                badge.classList.remove('reconectando');
                texto.textContent = 'Tiempo Real (SSE Activo)';
            } else {
                badge.classList.add('reconectando');
                texto.textContent = 'Sincronizando stream...';
            }
        };

        window.onSmartMenuPedidosUpdate = function(pedidos, cuentasMesas) {
            renderizarCuentasMesas(cuentasMesas || []);
            renderizarPedidos(pedidos || []);
        };

        function renderizarCuentasMesas(cuentas) {
            const contenedor = document.getElementById('contenedor-cuentas');
            contenedor.innerHTML = '';

            if (cuentas.length === 0) {
                contenedor.innerHTML = `
                    <div class="glass-card empty-state">
                        <p style="color:var(--text-muted);">No hay mesas con cuentas abiertas en este momento.</p>
                    </div>
                `;
                return;
            }

            cuentas.forEach(c => {
                let itemsHTML = c.items.map(item => `
                    <li>
                        <div><span class="item-qty-badge">${item.cantidad}x</span> ${escapeHtml(item.nombre)}</div>
                        <strong>${formatearMoneda(item.subtotal)}</strong>
                    </li>
                `).join('');

                if (c.servicio_mesa > 0) {
                    itemsHTML += `
                        <li style="color:#fcd34d;">
                            <div><span class="item-qty-badge">1x</span> 🍽️ Servicio de Mesa</div>
                            <strong>${formatearMoneda(c.servicio_mesa)}</strong>
                        </li>
                    `;
                }

                let badgeEstado = c.pidio_cuenta
                    ? `<span class="badge badge-warning">💳 ¡PIDIÓ LA CUENTA!</span>`
                    : `<span class="badge badge-neon">MESA OCUPADA (${c.cantidad_rondas} envío/s)</span>`;

                let mozoBadge = c.nombre_mozo
                    ? `<span class="mozo-tag">👤 Mozo: ${escapeHtml(c.nombre_mozo)}</span>`
                    : '';

                let card = document.createElement('div');
                card.className = `tarjeta-cuenta ${c.pidio_cuenta ? 'pidio-cuenta' : ''}`;
                card.innerHTML = `
                    <div>
                        <div class="pedido-head">
                            <div>
                                <p class="mesa-info">Mesa ${c.numero_mesa}</p>
                                <span class="hora-info">Abierta desde las ${c.hora_apertura} hs</span>
                                ${mozoBadge}
                            </div>
                            ${badgeEstado}
                        </div>
                        <ul class="lista-items">${itemsHTML}</ul>
                        <div class="total-cuenta-row">
                            <span>Total Acumulado:</span>
                            <strong>${formatearMoneda(c.total_acumulado)}</strong>
                        </div>
                    </div>
                    <div class="acciones-pedido">
                        <button class="btn-imprimir" onclick="abrirTicketCuentaMesa(${c.id_mesa})">
                            🖨️ Imprimir Ticket Final de Cuenta (58mm)
                        </button>
                        <button class="btn-cobrar-mesa" onclick="cerrarCuentaMesa(${c.id_mesa}, ${c.numero_mesa}, '${formatearMoneda(c.total_acumulado)}')">
                            💵 Cobrar y Cerrar Cuenta de Mesa ${c.numero_mesa}
                        </button>
                    </div>
                `;
                contenedor.appendChild(card);
            });
        }

        function renderizarPedidos(pedidos) {
            const contenedor = document.getElementById('contenedor-pedidos');
            contenedor.innerHTML = '';

            if (pedidos.length === 0) {
                contenedor.innerHTML = `
                    <div class="glass-card empty-state">
                        <p style="color:var(--text-muted);">No hay comandas pendientes de preparación en cocina.</p>
                    </div>
                `;
                return;
            }

            pedidos.forEach(pedido => {
                let itemsHTML = pedido.items.map(item => 
                    `<li><div><span class="item-qty-badge">${item.cantidad}x</span> ${escapeHtml(item.nombre)}</div></li>`
                ).join('');

                let badgeEstado = pedido.estado === 'pendiente'
                    ? `<span class="badge badge-danger">NUEVO · PENDIENTE</span>`
                    : `<span class="badge badge-neon">EN PREPARACIÓN</span>`;

                let mozoBadge = pedido.nombre_mozo
                    ? `<span class="mozo-tag">👤 Mozo: ${escapeHtml(pedido.nombre_mozo)}</span>`
                    : '';

                let botonesHTML = '';
                if (pedido.estado === 'pendiente') {
                    botonesHTML = `<button class="btn-preparar" onclick="abrirModalMozo(${pedido.id}, ${pedido.numero_mesa}, '${escapeHtml(pedido.nombre_mozo || '')}')">🔥 Empezar a Preparar (Asignar Mozo)</button>`;
                } else if (pedido.estado === 'preparando') {
                    botonesHTML = `<button class="btn-servir" onclick="cambiarEstado(${pedido.id}, 'servido', '')">✔ Entregado en Mesa (Sigue Cuenta Abierta)</button>`;
                }

                botonesHTML += `
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:8px;">
                        <button class="btn-imprimir" onclick="abrirTicket(${pedido.id})">🖨️ Imprimir Comanda</button>
                        <button class="btn-cancelar-pedido" onclick="abrirModalCancelar(${pedido.id}, ${pedido.numero_mesa})">❌ Cancelar Pedido</button>
                    </div>
                `;

                let tarjeta = document.createElement('div');
                tarjeta.className = `tarjeta-pedido ${pedido.estado}`;
                tarjeta.innerHTML = `
                    <div>
                        <div class="pedido-head">
                            <div>
                                <p class="mesa-info">Mesa ${pedido.numero_mesa}</p>
                                <span class="hora-info">Comanda #${pedido.id} · ${pedido.hora} hs</span>
                                ${mozoBadge}
                            </div>
                            ${badgeEstado}
                        </div>
                        <ul class="lista-items">${itemsHTML}</ul>
                    </div>
                    <div class="acciones-pedido">
                        ${botonesHTML}
                    </div>
                `;
                contenedor.appendChild(tarjeta);
            });
        }

        function abrirModalMozo(idPedido, numeroMesa, mozoActual) {
            document.getElementById('modal-id-pedido').value = idPedido;
            document.getElementById('modal-mozo-subtitulo').textContent = `Mesa ${numeroMesa} · Comanda #${idPedido}. Indica el nombre del mozo que toma el pedido:`;

            const input = document.getElementById('input-nombre-mozo');
            const recientes = obtenerMozosRecientes();
            input.value = mozoActual || (recientes[0] || '');

            const wrapRecientes = document.getElementById('contenedor-mozos-recientes');
            const listaChips = document.getElementById('lista-chips-mozos');
            if (recientes.length > 0) {
                wrapRecientes.style.display = 'block';
                listaChips.innerHTML = recientes.map(m =>
                    `<button type="button" class="chip-mozo" onclick="seleccionarChipMozo('${escapeHtml(m)}')">👤 ${escapeHtml(m)}</button>`
                ).join('');
            } else {
                wrapRecientes.style.display = 'none';
            }

            document.getElementById('modal-mozo').classList.add('open');
            setTimeout(() => {
                input.focus();
                input.select();
            }, 80);
        }

        function seleccionarChipMozo(nombre) {
            const input = document.getElementById('input-nombre-mozo');
            input.value = nombre;
            input.focus();
        }

        function cerrarModalMozo() {
            document.getElementById('modal-mozo').classList.remove('open');
        }

        function confirmarAceptarConMozo(e) {
            e.preventDefault();
            const idPedido = parseInt(document.getElementById('modal-id-pedido').value, 10);
            const nombreMozo = document.getElementById('input-nombre-mozo').value.trim();
            if (!idPedido || !nombreMozo) return;

            guardarMozoReciente(nombreMozo);
            cerrarModalMozo();
            cambiarEstado(idPedido, 'preparando', nombreMozo);
        }

        function abrirModalCancelar(idPedido, numeroMesa) {
            document.getElementById('modal-cancelar-id').value = idPedido;
            document.getElementById('modal-cancelar-subtitulo').textContent =
                `¿Confirmas cancelar la Comanda #${idPedido} de la Mesa ${numeroMesa}? El pedido se retirará de la cocina y no se cobrará en la cuenta de la mesa.`;
            document.getElementById('modal-cancelar').classList.add('open');
        }

        function cerrarModalCancelar() {
            document.getElementById('modal-cancelar').classList.remove('open');
        }

        function confirmarCancelacionPedido() {
            const idPedido = parseInt(document.getElementById('modal-cancelar-id').value, 10);
            if (!idPedido) return;
            cerrarModalCancelar();
            cambiarEstado(idPedido, 'cancelado', '');
        }

        function cambiarEstado(idPedido, nuevoEstado, nombreMozo = '') {
            fetch('actualizar_pedido.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    accion: 'cambiar_estado_pedido',
                    id_pedido: idPedido,
                    estado: nuevoEstado,
                    nombre_mozo: nombreMozo
                })
            }).then(() => {
                SmartMenuAlerts.refreshNow();
            });
        }

        function cerrarCuentaMesa(idMesa, numeroMesa, montoFormateado) {
            if (!confirm(`¿Confirmas que ya le llevaste la cuenta y cobraste ${montoFormateado} a la Mesa ${numeroMesa}?\n\nEsto cerrará el pedido de la mesa, sumará el total a la Caja del Turno y dejará la Mesa ${numeroMesa} en $0,00 para el próximo cliente.`)) {
                return;
            }

            fetch('actualizar_pedido.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    accion: 'cerrar_cuenta_mesa',
                    id_mesa: idMesa
                })
            }).then(() => {
                SmartMenuAlerts.refreshNow();
            });
        }

        function abrirTicket(idPedido) {
            window.open('ticket.php?id=' + idPedido, 'TicketCocina', 'width=400,height=600,scrollbars=yes');
        }

        function abrirTicketCuentaMesa(idMesa) {
            window.open('ticket.php?modo=cuenta&id_mesa=' + idMesa, 'TicketCuentaMesa', 'width=400,height=650,scrollbars=yes');
        }
    </script>
</body>
</html>
