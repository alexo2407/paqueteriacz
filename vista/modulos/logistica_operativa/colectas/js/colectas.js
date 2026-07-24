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
    const res = await fetch(RUTA_URL + endpoint, {
        method:  'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(payload),
    });

    if (!res.ok && res.status !== 400 && res.status !== 409 && res.status !== 422) {
        throw new Error(`HTTP ${res.status}`);
    }

    return res.json();
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
 * Genera un hash QR mock para pruebas (sha256-like placeholder).
 * En producción, el hash real vendría del QR escaneado.
 */
function hashMock(valor) {
    let h = 0;
    for (let i = 0; i < valor.length; i++) {
        h = ((h << 5) - h + valor.charCodeAt(i)) | 0;
    }
    return Math.abs(h).toString(16).padStart(8, '0') + 'mock';
}


// ══════════════════════════════════════════════════════════
// Actualizar contadores en ver.php
// ══════════════════════════════════════════════════════════

function actualizarContadores(nuevos) {
    if (!nuevos) return;
    if (nuevos.ESPERADO !== undefined) {
        document.getElementById('cntEsperado').textContent = nuevos.ESPERADO;
        contadores.ESPERADO = nuevos.ESPERADO;
    }
    if (nuevos.RECIBIDO !== undefined) {
        document.getElementById('cntRecibido').textContent = nuevos.RECIBIDO;
        contadores.RECIBIDO = nuevos.RECIBIDO;
    }
    if (nuevos.FALTANTE !== undefined) {
        document.getElementById('cntFaltante').textContent = nuevos.FALTANTE;
        contadores.FALTANTE = nuevos.FALTANTE;
    }
    if (nuevos.EXTRA !== undefined) {
        document.getElementById('cntExtra').textContent = nuevos.EXTRA;
        contadores.EXTRA = nuevos.EXTRA;
    }
}

/**
 * Actualiza el badge de resultado en la fila de un pedido.
 */
function actualizarFilaPedido(idPedido, resultado, escaneadoAt) {
    const fila = document.getElementById('fila-pedido-' + idPedido);
    if (!fila) return;

    const tdResultado = fila.querySelector('td:nth-child(3)');
    const tdFecha     = fila.querySelector('td:nth-child(4)');

    if (tdResultado) {
        tdResultado.innerHTML = badgeResultadoJS(resultado);
    }
    if (tdFecha && escaneadoAt) {
        const d = new Date(escaneadoAt.replace(' ', 'T'));
        tdFecha.textContent = d.toLocaleDateString('es', { day:'2-digit', month:'2-digit' })
                            + ' ' + d.toLocaleTimeString('es', { hour:'2-digit', minute:'2-digit' });
    }
}

function badgeResultadoJS(resultado) {
    switch (resultado) {
        case 'RECIBIDO': return '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Recibido</span>';
        case 'FALTANTE': return '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Faltante</span>';
        case 'EXTRA':    return '<span class="badge bg-warning text-dark"><i class="bi bi-plus-circle me-1"></i>Extra</span>';
        case 'ESPERADO': return '<span class="badge bg-light text-dark border"><i class="bi bi-clock me-1"></i>Esperado</span>';
        default:         return '<span class="badge bg-secondary">' + resultado + '</span>';
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

        // Validar formulario
        const form       = document.getElementById('formAbrirColecta');
        const idCliente  = document.getElementById('abrirIdCliente')?.value;
        const fecha      = document.getElementById('abrirFecha')?.value;
        const turnoEl    = form.querySelector('input[name="turno"]:checked');
        const turno      = turnoEl?.value ?? '';
        const alerta     = document.getElementById('alertaAbrirColecta');

        alerta.className = 'alert d-none mb-3';
        alerta.textContent = '';

        if (!idCliente || !fecha || !turno) {
            alerta.className = 'alert alert-danger mb-3';
            alerta.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>Completa todos los campos requeridos.';
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
            const resp = await apiPost('api/logistica-operativa/colectas/abrir', {
                id_cliente: parseInt(idCliente, 10),
                fecha,
                turno,
            });

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

    // Reset del modal al cerrarse
    const modalEl = document.getElementById('modalAbrirColecta');
    if (modalEl) {
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

                // Actualizar fila de la tabla si existe
                actualizarFilaPedido(idPedido, r.resultado_pedido, r.escaneado_at);

                // Actualizar contadores con los del resumen actualizado
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
        resultadoDiv.className = `alert alert-${tipo} py-2`;
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

})();
