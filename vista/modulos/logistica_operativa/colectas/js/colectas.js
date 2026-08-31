/**
 * vista/modulos/logistica_operativa/colectas/js/colectas.js
 *
 * Cliente JS para el módulo de Colectas (Logística Operativa).
 *
 * Responsabilidades:
 *   - Abrir colecta (modal → POST /api/logistica-operativa/colectas/abrir)
 *   - Escanear paquete (input → POST /api/logistica-operativa/colectas/escanear)
 *   - Cerrar y conciliar (botón → POST /api/logistica-operativa/colectas/cerrar)
 *   - Actualizar contadores y tabla de pedidos sin recargar la página.
 *
 * Seguridad:
 *   - Nunca envía id_operador (el endpoint lo toma del JWT en la sesión).
 *   - Evita doble envío con flag `processing`.
 *   - Usa fetch() con Content-Type: application/json.
 *
 * Dependencias (del layout global):
 *   - Bootstrap 5 JS (ya cargado en footer.php)
 *   - SweetAlert2 (Swal, ya cargado en footer.php)
 *   - jQuery (cargado en footer.php, pero NO se usa aquí intencionalmente)
 *   - RUTA_URL (definida en header.php como const global)
 *   - CSRF_TOKEN_COLECTAS (definida inline en la vista)
 *   - COLECTA_ID / COLECTA_ABIERTA (definidas inline en ver.php)
 *   - contadores (objeto global inicializado en ver.php)
 */

'use strict';

// ══════════════════════════════════════════════════════════
// Utilidades compartidas
// ══════════════════════════════════════════════════════════

/**
 * POST JSON al endpoint interno.
 * @param {string} endpoint  ruta relativa (ej: 'api/logistica-operativa/colectas/abrir')
 * @param {object} payload
 * @returns {Promise<{success:boolean, data?:any, code?:string, message?:string}>}
 */
async function apiPost(endpoint, payload) {
    try {
        const res = await fetch(RUTA_URL + endpoint, {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });

        const data = await res.json();
        return data;
    } catch (e) {
        return { success: false, message: 'Error de red o respuesta no válida del servidor.' };
    }
}

/**
 * Genera un UUID v4 simple para el campo uuid de escaneo.
 */
function uuidv4() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
        const r = (Math.random() * 16) | 0;
        return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    });
}

/**
 * Genera un hash QR mock para pruebas (sha256-like 64 hex characters).
 * En producción, el hash real vendría del QR escaneado.
 */
function hashMock(valor) {
    let hex = '';
    const str = String(valor);
    for (let i = 0; i < str.length; i++) {
        hex += str.charCodeAt(i).toString(16);
    }
    return hex.padStart(64, '0').substring(0, 64);
}


// ══════════════════════════════════════════════════════════
// Actualizar contadores en ver.php
// ══════════════════════════════════════════════════════════

function actualizarContadores(nuevos) {
    if (!nuevos) return;
    if (nuevos.ESPERADO !== undefined) {
        const el = document.getElementById('cntEsperado');
        if (el) el.textContent = nuevos.ESPERADO;
        contadores.ESPERADO = nuevos.ESPERADO;
    }
    if (nuevos.RECIBIDO !== undefined) {
        const el = document.getElementById('cntRecibido');
        if (el) el.textContent = nuevos.RECIBIDO;
        contadores.RECIBIDO = nuevos.RECIBIDO;
    }
    if (nuevos.FALTANTE !== undefined) {
        const el = document.getElementById('cntFaltante');
        if (el) el.textContent = nuevos.FALTANTE;
        contadores.FALTANTE = nuevos.FALTANTE;
    }
    if (nuevos.EXTRA !== undefined) {
        const el = document.getElementById('cntExtra');
        if (el) el.textContent = nuevos.EXTRA;
        contadores.EXTRA = nuevos.EXTRA;
    }

    // Actualizar barra de progreso de recolección
    const esp = contadores.ESPERADO || 0;
    const rec = contadores.RECIBIDO || 0;
    const pct = esp > 0 ? Math.min(100, Math.round((rec / esp) * 100)) : 0;

    const bar = document.getElementById('progressBarRecoleccion');
    const lblProg = document.getElementById('lblConteoProgreso');
    const lblPct = document.getElementById('lblPorcentajeRecoleccion');

    if (bar) {
        bar.style.width = pct + '%';
        bar.setAttribute('aria-valuenow', pct);
    }
    if (lblProg) lblProg.textContent = rec;
    if (lblPct) {
        lblPct.innerHTML = `<span id="lblConteoProgreso">${rec}</span> de ${esp} esperados (${pct}%)`;
    }
}

/**
 * Actualiza el badge de resultado, estado de escaneado y fecha en la fila de un pedido.
 */
function actualizarFilaPedido(idPedido, resultado, escaneadoAt) {
    let fila = document.getElementById('fila-pedido-' + idPedido);
    if (!fila) {
        fila = document.querySelector(`.fila-pedido-item[data-numero-orden="${idPedido}"]`) ||
               document.querySelector(`.fila-pedido-item[data-id-pedido="${idPedido}"]`);
    }
    if (!fila) return;

    const tdResultado = fila.querySelector('td:nth-child(3)');
    const tdEscaneado = fila.querySelector('td:nth-child(4)');
    const tdFecha     = fila.querySelector('td:nth-child(5)');

    if (tdResultado) {
        tdResultado.innerHTML = badgeResultadoJS(resultado);
    }
    if (tdEscaneado) {
        tdEscaneado.innerHTML = '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check me-1"></i>Sí</span>';
    }
    if (tdFecha && escaneadoAt) {
        const d = new Date(escaneadoAt.replace(' ', 'T'));
        tdFecha.textContent = d.toLocaleDateString('es', { day:'2-digit', month:'2-digit', year:'numeric' })
                            + ' ' + d.toLocaleTimeString('es', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
    }

    // Efecto visual de resaltado suave
    fila.classList.add('table-success');
    setTimeout(() => {
        fila.classList.remove('table-success');
    }, 2500);
}

/**
 * Renderiza o refresca asíncronamente la tabla completa de pedidos si se recibe la lista actualizada.
 */
function actualizarTablaPedidosCompleta(pedidos) {
    if (!Array.isArray(pedidos)) return;
    const tbody = document.getElementById('tbodyPedidos');
    if (!tbody) return;

    if (pedidos.length === 0) {
        tbody.innerHTML = `
            <tr id="trSinPedidos">
                <td colspan="${typeof COLECTA_ABIERTA !== 'undefined' && COLECTA_ABIERTA ? '6' : '5'}" class="text-center text-muted py-5">
                    <i class="bi bi-inbox display-5 opacity-25 d-block mb-2"></i>
                    Sin pedidos en esta colecta.
                </td>
            </tr>`;
        return;
    }

    const trSinPedidos = document.getElementById('trSinPedidos');
    if (trSinPedidos) trSinPedidos.remove();

    pedidos.forEach(p => {
        const idPed = parseInt(p.id_pedido, 10);
        let fila = document.getElementById('fila-pedido-' + idPed);
        const res = p.resultado_pedido || 'ESPERADO';
        const escAt = p.escaneado_at || null;
        const orden = p.numero_orden || ('#' + idPed);
        const dest = p.destinatario || '—';

        if (fila) {
            actualizarFilaPedido(idPed, res, escAt);
        } else {
            // Es un pedido nuevo (ej. EXTRA): crear la fila dinámicamente
            fila = document.createElement('tr');
            fila.id = 'fila-pedido-' + idPed;
            fila.className = 'fila-pedido-item table-warning';
            fila.setAttribute('data-id-pedido', idPed);
            fila.setAttribute('data-numero-orden', orden);

            const formattedDate = escAt ? (new Date(escAt.replace(' ', 'T')).toLocaleDateString('es', { day:'2-digit', month:'2-digit', year:'numeric' }) + ' ' + new Date(escAt.replace(' ', 'T')).toLocaleTimeString('es', { hour:'2-digit', minute:'2-digit', second:'2-digit' })) : '—';
            const escBadge = escAt ? '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check me-1"></i>Sí</span>' : '<span class="badge bg-light text-muted border">No</span>';

            let accionTd = '';
            if (typeof COLECTA_ABIERTA !== 'undefined' && COLECTA_ABIERTA) {
                let btn = '';
                if (res === 'EXTRA') {
                    btn = `<button class="btn btn-sm btn-outline-danger py-1 px-2.5 text-nowrap rounded-3"
                                   onclick="eliminarPedidoExtra(${idPed}, '${orden.replace(/'/g, "\\'")}')"
                                   title="Quitar este paquete extra">
                               <i class="bi bi-trash me-1"></i>Quitar
                           </button>`;
                }
                accionTd = `<td class="text-end pe-4">${btn}</td>`;
            }

            fila.innerHTML = `
                <td class="fw-bold font-monospace ps-4">${orden}</td>
                <td class="small fw-semibold text-dark">${dest}</td>
                <td>${badgeResultadoJS(res)}</td>
                <td>${escBadge}</td>
                <td class="small text-muted font-monospace">${formattedDate}</td>
                ${accionTd}
            `;

            tbody.prepend(fila);
            setTimeout(() => fila.classList.remove('table-warning'), 3000);
        }
    });
}

function badgeResultadoJS(resultado) {
    switch (resultado) {
        case 'RECIBIDO':  return '<span class="badge badge-outline-success"><i class="bi bi-check-circle me-1"></i>RECIBIDO</span>';
        case 'FALTANTE':  return '<span class="badge badge-outline-danger"><i class="bi bi-x-circle me-1"></i>FALTANTE</span>';
        case 'EXTRA':     return '<span class="badge badge-outline-warning"><i class="bi bi-plus-circle me-1"></i>EXTRA</span>';
        case 'ESPERADO':
        case 'PENDIENTE': return '<span class="badge badge-outline-secondary"><i class="bi bi-clock me-1"></i>PENDIENTE</span>';
        default:          return '<span class="badge bg-secondary">' + resultado + '</span>';
    }
}


// ══════════════════════════════════════════════════════════
// MÓDULO: ABRIR COLECTA (index.php)
// ══════════════════════════════════════════════════════════

(function initAbrirColecta() {
    const btnConfirmar = document.getElementById('btnConfirmarAbrir');
    if (!btnConfirmar) return; // no estamos en index.php

    let processing = false;

    btnConfirmar.addEventListener('click', async () => {
        if (processing) return;

        const form          = document.getElementById('formAbrirColecta');
        const idCliente     = document.getElementById('abrirIdCliente')?.value;
        const idProveedorEl = document.getElementById('abrirIdProveedor');
        const fecha         = document.getElementById('abrirFecha')?.value;
        const turnoEl       = form.querySelector('input[name="turno"]:checked');
        const turno         = turnoEl?.value ?? '';
        const alerta        = document.getElementById('alertaAbrirColecta');

        alerta.className = 'alert d-none mb-3';
        alerta.textContent = '';

        if (!idCliente || !fecha || !turno) {
            alerta.className = 'alert alert-danger mb-3';
            alerta.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>Completa todos los campos requeridos.';
            return;
        }

        if (idProveedorEl && !idProveedorEl.value) {
            alerta.className = 'alert alert-danger mb-3';
            alerta.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>Selecciona un proveedor válido.';
            return;
        }

        // Validar fecha formato YYYY-MM-DD
        if (!/^\d{4}-\d{2}-\d{2}$/.test(fecha)) {
            alerta.className = 'alert alert-danger mb-3';
            alerta.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>Fecha inválida.';
            return;
        }

        processing = true;
        const spinner = document.getElementById('spinnerAbrir');
        const icono   = document.getElementById('iconoAbrir');
        if (spinner) spinner.classList.remove('d-none');
        if (icono)   icono.classList.add('d-none');
        btnConfirmar.disabled = true;

        try {
            const payload = {
                id_cliente: parseInt(idCliente, 10),
                fecha,
                turno,
            };
            if (idProveedorEl && idProveedorEl.value) {
                payload.id_proveedor = parseInt(idProveedorEl.value, 10);
            }

            const resp = await apiPost('api/logistica-operativa/colectas/abrir', payload);

            if (resp.success) {
                alerta.className = 'alert alert-success mb-3';
                alerta.innerHTML = `<i class="bi bi-check-circle me-2"></i>
                    Colecta abierta: <strong>#${resp.data?.id_colecta}</strong>
                    con <strong>${resp.data?.cantidad_esperada ?? 0}</strong> pedidos esperados.`;

                // Redirigir al detalle tras 1.5s
                setTimeout(() => {
                    window.location.href = RUTA_URL + 'logistica-operativa/colectas/ver/' + resp.data.id_colecta;
                }, 1500);
            } else {
                alerta.className = 'alert alert-danger mb-3';
                alerta.innerHTML = `<i class="bi bi-exclamation-triangle me-2"></i>${resp.message ?? 'Error al abrir la colecta.'}`;
                processing = false;
                if (spinner) spinner.classList.add('d-none');
                if (icono)   icono.classList.remove('d-none');
                btnConfirmar.disabled = false;
            }
        } catch (err) {
            alerta.className = 'alert alert-danger mb-3';
            alerta.innerHTML = '<i class="bi bi-wifi-off me-2"></i>Error de conexión. Intenta de nuevo.';
            processing = false;
            if (spinner) spinner.classList.add('d-none');
            if (icono)   icono.classList.remove('d-none');
            btnConfirmar.disabled = false;
        }
    });

    // Auto-seleccionar proveedor cuando cambia el cliente en el modal
    const selectCliente   = document.getElementById('abrirIdCliente');
    const selectProveedor = document.getElementById('abrirIdProveedor');

    function autoSeleccionarProveedor() {
        if (!selectCliente || !selectProveedor || typeof MAPEO_CLIENTE_PROVEEDOR === 'undefined') return;
        const cliId = parseInt(selectCliente.value, 10);
        if (cliId && MAPEO_CLIENTE_PROVEEDOR[cliId]) {
            selectProveedor.value = MAPEO_CLIENTE_PROVEEDOR[cliId];
        }
    }

    if (selectCliente && selectProveedor) {
        selectCliente.addEventListener('change', autoSeleccionarProveedor);
    }

    // Reset del modal al cerrarse / Evento al abrirse
    const modalEl = document.getElementById('modalAbrirColecta');
    if (modalEl) {
        modalEl.addEventListener('shown.bs.modal', autoSeleccionarProveedor);
        modalEl.addEventListener('hidden.bs.modal', () => {
            const alerta = document.getElementById('alertaAbrirColecta');
            if (alerta) {
                alerta.className = 'alert d-none mb-3';
                alerta.textContent = '';
            }
            processing = false;
            const spinner = document.getElementById('spinnerAbrir');
            const icono   = document.getElementById('iconoAbrir');
            if (spinner) spinner.classList.add('d-none');
            if (icono)   icono.classList.remove('d-none');
            btnConfirmar.disabled = false;
        });
    }
})();


// ══════════════════════════════════════════════════════════
// MÓDULO: ESCANEO (ver.php)
// ══════════════════════════════════════════════════════════

(function initEscaneo() {
    const inputEscaneo  = document.getElementById('inputEscaneo');
    const btnEscanear   = document.getElementById('btnEscanear');
    const resultadoDiv  = document.getElementById('resultadoEscaneo');
    const listaHistorial = document.getElementById('listaHistorial');

    if (!inputEscaneo || typeof COLECTA_ID === 'undefined') return; // no en ver.php

    let processing = false;
    const historialLocal = []; // máx 10 recientes en esta sesión de página

    // Foco automático al cargar
    inputEscaneo.focus();

    // Enviar con Enter
    inputEscaneo.addEventListener('keydown', e => {
        if (e.key === 'Enter') {
            e.preventDefault();
            enviarEscaneo();
        }
    });

    // Enviar con botón
    if (btnEscanear) {
        btnEscanear.addEventListener('click', enviarEscaneo);
    }

    // Escáner por Cámara QR
    const btnAbrirCamaraQR = document.getElementById('btnAbrirCamaraQR');
    if (btnAbrirCamaraQR) {
        btnAbrirCamaraQR.addEventListener('click', () => {
            if (typeof window.abrirScannerQR === 'function') {
                window.abrirScannerQR({
                    targetInputId: 'inputEscaneo',
                    onScanSuccess: (codigoLeido) => {
                        enviarEscaneo();
                    }
                });
            }
        });
    }

    async function enviarEscaneo() {
        if (processing || !COLECTA_ABIERTA) return;

        const codigo = inputEscaneo.value.trim();
        if (!codigo) {
            mostrarResultado('warning', '<i class="bi bi-exclamation-triangle me-2"></i>Ingresa un código.');
            return;
        }

        processing = true;
        inputEscaneo.disabled = true;
        if (btnEscanear) btnEscanear.disabled = true;
        mostrarResultado('info', '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...');

        // El id_pedido se extrae del código. En esta primera versión
        // el operador escribe el ID de pedido directamente.
        // En versiones futuras, el código QR llevará el id_pedido embebido.
        const idPedido = parseInt(codigo, 10);

        if (isNaN(idPedido) || idPedido <= 0) {
            mostrarResultado('danger', '<i class="bi bi-x-circle me-2"></i>Código inválido. Ingresa el ID numérico del pedido.');
            resetInput();
            return;
        }

        try {
            const resp = await apiPost('api/logistica-operativa/colectas/escanear', {
                uuid:          uuidv4(),
                id_colecta:    COLECTA_ID,
                id_pedido:     idPedido,
                tipo_evento:   'COLECTA_RECEPCION',
                qr_hash:       hashMock(String(idPedido)),
                dispositivo:   'web-manual',
                escaneado_at:  new Date().toISOString().replace('T', ' ').substring(0, 19),
                metadata_json: { fuente: 'web', codigo_raw: codigo },
            });

            if (resp.success) {
                const r = resp.data;
                let msg = '';
                let tipo = 'success';

                if (r.idempotente) {
                    tipo = 'warning';
                    msg = `<i class="bi bi-arrow-repeat me-2"></i>
                        <strong>Ya escaneado</strong> — Pedido #${idPedido}
                        (resultado: <strong>${r.resultado_pedido}</strong>).`;
                } else {
                    switch (r.resultado_pedido) {
                        case 'RECIBIDO':
                            tipo = 'success';
                            msg = `<i class="bi bi-check-circle me-2"></i>
                                <strong>Recibido</strong> — Pedido #${idPedido} confirmado.`;
                            break;
                        case 'EXTRA':
                            tipo = 'warning';
                            msg = `<i class="bi bi-plus-circle me-2"></i>
                                <strong>Extra</strong> — Pedido #${idPedido} no pertenece a esta colecta.`;
                            break;
                        default:
                            msg = `<i class="bi bi-info-circle me-2"></i>
                                Pedido #${idPedido}: <strong>${r.resultado_pedido}</strong>.`;
                    }
                }

                mostrarResultado(tipo, msg);

                // Actualizar tabla de pedidos (asíncronamente sin recargar página)
                if (r.pedidos && Array.isArray(r.pedidos)) {
                    actualizarTablaPedidosCompleta(r.pedidos);
                } else {
                    actualizarFilaPedido(idPedido, r.resultado_pedido, r.escaneado_at);
                }

                // Actualizar contadores y barra de progreso
                if (r.conteos) actualizarContadores(r.conteos);

                // Agregar al historial local
                agregarHistorial(idPedido, r.resultado_pedido, r.idempotente);

            } else {
                let tipo = 'danger';
                let icono = 'bi-x-circle';
                let textoExtra = '';

                if (resp.code === 'COLECTA_CERRADA' || resp.code === 'CONFLICT') {
                    textoExtra = ' La colecta ya fue cerrada.';
                } else if (resp.code === 'NOT_FOUND') {
                    textoExtra = ' Pedido no encontrado en el sistema.';
                }

                mostrarResultado(tipo,
                    `<i class="bi ${icono} me-2"></i>${resp.message ?? 'Error al escanear.'}${textoExtra}`);
            }

        } catch (err) {
            mostrarResultado('danger', '<i class="bi bi-wifi-off me-2"></i>Error de conexión. Intenta de nuevo.');
        }

        resetInput();
    }

    function resetInput() {
        processing = false;
        inputEscaneo.value    = '';
        inputEscaneo.disabled = false;
        if (btnEscanear) btnEscanear.disabled = false;
        inputEscaneo.focus();
    }

    function mostrarResultado(tipo, html) {
        if (!resultadoDiv) return;
        const textClass = (tipo === 'warning' || tipo === 'info') ? 'text-dark fw-semibold' : 'fw-semibold';
        resultadoDiv.className = `alert alert-${tipo} ${textClass} py-2.5 px-3 rounded-3 shadow-sm border-0 d-flex align-items-center mb-3`;
        resultadoDiv.innerHTML = html;
        resultadoDiv.classList.remove('d-none');
    }

    function agregarHistorial(idPedido, resultado, idempotente) {
        if (!listaHistorial) return;

        historialLocal.unshift({ idPedido, resultado, idempotente, ts: new Date() });
        if (historialLocal.length > 10) historialLocal.pop();

        // Reconstruir lista
        listaHistorial.innerHTML = historialLocal.map(h => {
            const hora = h.ts.toLocaleTimeString('es', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
            const badge = badgeResultadoJS(h.resultado);
            const rep   = h.idempotente ? ' <span class="text-muted">(ya escaneado)</span>' : '';
            return `<li class="list-group-item py-1 px-2 small d-flex justify-content-between align-items-center">
                <span><span class="text-muted me-1">${hora}</span> Pedido #${h.idPedido}${rep}</span>
                ${badge}
            </li>`;
        }).join('');
    }

})();


// ══════════════════════════════════════════════════════════
// MÓDULO: CERRAR Y CONCILIAR (ver.php)
// ══════════════════════════════════════════════════════════

(function initCerrar() {
    const btnCerrar = document.getElementById('btnCerrarColecta');
    if (!btnCerrar || typeof COLECTA_ID === 'undefined') return;

    let processing = false;

    btnCerrar.addEventListener('click', async () => {
        if (processing) return;

        // Confirmación con SweetAlert2
        const confirmacion = await Swal.fire({
            title:              'Cerrar y conciliar colecta',
            icon:               'warning',
            showCancelButton:   true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor:  '#6c757d',
            confirmButtonText:  '<i class="bi bi-check2-all me-1"></i> Sí, cerrar',
            cancelButtonText:   'Cancelar',
            html: `
                <div class="text-start small">
                    <p class="mb-2">Se calcularán los resultados finales:</p>
                    <ul class="list-unstyled mb-3">
                        <li><span class="badge bg-light text-dark border me-2">Esperados</span>
                            <strong id="swEsp">${contadores.ESPERADO}</strong></li>
                        <li><span class="badge bg-success me-2">Recibidos</span>
                            <strong id="swRec">${contadores.RECIBIDO}</strong></li>
                        <li><span class="badge bg-danger me-2">Faltantes</span>
                            <strong id="swFal">${contadores.FALTANTE}</strong></li>
                        <li><span class="badge bg-warning text-dark me-2">Extras</span>
                            <strong id="swExt">${contadores.EXTRA}</strong></li>
                    </ul>
                    <div class="alert alert-light border-warning-subtle py-2">
                        <i class="bi bi-shield-check text-warning me-1"></i>
                        <small>No se modificarán estados de pedidos, inventario ni stock.</small>
                    </div>
                </div>`,
        });

        if (!confirmacion.isConfirmed) return;

        processing = true;
        btnCerrar.disabled = true;
        btnCerrar.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Cerrando...';

        try {
            const resp = await apiPost('api/logistica-operativa/colectas/cerrar', {
                id_colecta: COLECTA_ID,
            });

            if (resp.success) {
                // Actualizar contadores finales
                if (resp.data?.conteos) actualizarContadores(resp.data.conteos);

                // Actualizar badge de estado
                const badgeEstado = document.querySelector('.badge.fs-6');
                if (badgeEstado) {
                    badgeEstado.textContent = 'CONCILIADA';
                    badgeEstado.className   = 'badge bg-secondary fs-6 px-3 py-2';
                }

                // Deshabilitar escaneo
                const inputEscaneo = document.getElementById('inputEscaneo');
                const btnEscanear  = document.getElementById('btnEscanear');
                if (inputEscaneo) inputEscaneo.disabled = true;
                if (btnEscanear)  btnEscanear.disabled  = true;

                // Actualizar filas con resultados finales
                if (resp.data?.pedidos) {
                    resp.data.pedidos.forEach(p => {
                        actualizarFilaPedido(p.id_pedido, p.resultado_pedido, p.escaneado_at);
                    });
                }

                // Ocultar bloque de cierre
                btnCerrar.closest('.card')?.remove();

                await Swal.fire({
                    icon:  'success',
                    title: 'Colecta conciliada',
                    text:  `La colecta #${COLECTA_ID} fue cerrada correctamente.`,
                    confirmButtonColor: '#198754',
                });

            } else {
                await Swal.fire({
                    icon:  'error',
                    title: 'Error al cerrar',
                    text:  resp.message ?? 'No se pudo cerrar la colecta.',
                });
                processing = false;
                btnCerrar.disabled = false;
                btnCerrar.innerHTML = '<i class="bi bi-check2-all me-1"></i>Cerrar y conciliar';
            }

        } catch (err) {
            await Swal.fire({
                icon:  'error',
                title: 'Error de conexión',
                text:  'No se pudo comunicar con el servidor.',
            });
            processing = false;
            btnCerrar.disabled = false;
            btnCerrar.innerHTML = '<i class="bi bi-check2-all me-1"></i>Cerrar y conciliar';
        }
    });

    // Filtro en tiempo real para la tabla de pedidos
    const inputFiltro = document.getElementById('inputFiltroTablaPedidos');
    if (inputFiltro) {
        inputFiltro.addEventListener('input', function () {
            const val = this.value.toLowerCase().trim();
            const filas = document.querySelectorAll('#tbodyPedidos .fila-pedido-item');
            filas.forEach(f => {
                const text = f.textContent.toLowerCase();
                f.style.display = text.includes(val) ? '' : 'none';
            });
        });
    }
})();

/**
 * Elimina un pedido con resultado EXTRA de la colecta activa.
 */
async function eliminarPedidoExtra(idPedido, numeroOrden) {
    if (typeof COLECTA_ID === 'undefined') return;

    const confirmacion = await Swal.fire({
        title: '¿Quitar paquete extra?',
        text: `¿Deseas remover el pedido #${numeroOrden} de los extras de esta colecta?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, quitar extra',
        cancelButtonText: 'Cancelar'
    });

    if (!confirmacion.isConfirmed) return;

    try {
        const resp = await apiPost('api/logistica-operativa/colectas/eliminar-extra', {
            id_colecta: COLECTA_ID,
            id_pedido:  idPedido
        });

        if (resp.success) {
            const fila = document.getElementById(`fila-pedido-${idPedido}`);
            if (fila) fila.remove();

            if (resp.data?.conteos) {
                actualizarContadores(resp.data.conteos);
            }

            Swal.fire({
                icon: 'success',
                title: 'Extra removido',
                text: `El paquete #${numeroOrden} fue retirado de la colecta.`,
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            Swal.fire('Error', resp.message || 'No se pudo eliminar el extra.', 'error');
        }
    } catch (e) {
        Swal.fire('Error', 'Error de comunicación con el servidor.', 'error');
    }
}
