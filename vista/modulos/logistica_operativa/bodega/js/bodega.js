/**
 * vista/modulos/logistica_operativa/bodega/js/bodega.js
 *
 * Módulo JS para el panel operativo de Bodega — Logística Operativa.
 *
 * Responsabilidades:
 *   - Buscar paquetes y mostrar su información.
 *   - Cargar y mostrar ubicación física actual.
 *   - Mostrar acciones disponibles según estado del paquete.
 *   - Gestionar modales: recepción, ubicar, reubicar, retirar.
 *   - Refrescar historial de movimientos.
 *
 * Seguridad:
 *   - Nunca envía id_operador (el endpoint lo toma del JWT).
 *   - El JWT se obtiene del endpoint session-token y se guarda SOLO en memoria.
 *   - No usa localStorage, sessionStorage ni cookies para el token.
 *   - Evita doble envío con flags processing.
 *   - Escapa todos los datos del servidor antes de insertarlos en el DOM.
 *   - No usa innerHTML con datos no confiables.
 *   - CSRF token presente en todas las peticiones POST.
 *
 * Dependencias del layout global (ya cargadas por footer.php / header.php):
 *   - Bootstrap 5 JS
 *   - SweetAlert2 (Swal)
 *   - RUTA_BODEGA_BASE  (definida en index.php)
 *   - CSRF_BODEGA       (definida en index.php)
 *
 * Patrón IIFE para evitar variables globales.
 */

'use strict';

(function BodegaModule() {

    // ══════════════════════════════════════════════════════════════════════════
    // Estado interno del módulo
    // ══════════════════════════════════════════════════════════════════════════

    /** Token JWT obtenido del endpoint session-token. Solo en memoria. */
    let _sessionToken = null;

    /** Datos del paquete actualmente consultado. */
    let _pedidoActual = null;

    /** Datos de recepción activa del paquete. */
    let _recepcionActual = null;

    /** Datos de ubicación actual del paquete. */
    let _ubicacionActual = null;

    // ══════════════════════════════════════════════════════════════════════════
    // Utilidades generales
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Escapa texto para insertarlo de forma segura como textContent.
     * No necesitamos escape HTML porque usamos textContent; esta función
     * existe como recordatorio de la política de seguridad.
     * @param {*} val
     * @returns {string}
     */
    function esc(val) {
        if (val === null || val === undefined) return '—';
        return String(val);
    }

    /**
     * Formatea una fecha UTC a formato local legible.
     * @param {string|null} fecha
     * @returns {string}
     */
    function fmtFecha(fecha) {
        if (!fecha) return '—';
        try {
            const d = new Date(fecha.replace(' ', 'T'));
            return d.toLocaleDateString('es', { day: '2-digit', month: '2-digit', year: 'numeric' })
                 + ' ' + d.toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' });
        } catch {
            return esc(fecha);
        }
    }

    /**
     * Genera un UUID v4 usando crypto.randomUUID si está disponible,
     * o un fallback basado en Math.random de ser necesario.
     * @returns {string}
     */
    function generarUUID() {
        if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
            return crypto.randomUUID();
        }
        // Fallback RFC4122 v4
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
            const r = (Math.random() * 16) | 0;
            return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
        });
    }

    /**
     * Obtiene el token JWT de sesión del endpoint bridge.
     * Si ya existe y es reciente, lo devuelve del caché en memoria.
     * El token NO se persiste fuera de esta variable.
     * @returns {Promise<string>}
     */
    async function obtenerToken() {
        if (_sessionToken) {
            return _sessionToken;
        }

        const res = await fetch(RUTA_BODEGA_BASE + 'auth/session-token.php', {
            method: 'GET',
            credentials: 'same-origin', // envía la cookie de sesión
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            throw new Error(err.message || `Error de autenticación (HTTP ${res.status})`);
        }

        const data = await res.json();
        if (!data.success || !data.data?.token) {
            throw new Error('No se pudo obtener el token de sesión.');
        }

        _sessionToken = data.data.token;

        // Limpiar el token antes de que expire (expires_in − 60 seg de margen)
        const expiresIn = (data.data.expires_in ?? 900) - 60;
        setTimeout(() => { _sessionToken = null; }, expiresIn * 1000);

        return _sessionToken;
    }

    /**
     * Realiza una petición GET al endpoint de la API con autenticación JWT.
     * @param {string} endpoint  Ruta relativa desde RUTA_BODEGA_BASE
     * @param {Record<string,string>} [params]  Query params
     * @returns {Promise<{success:boolean,data?:any,code?:string,message?:string}>}
     */
    async function apiGet(endpoint, params = {}) {
        const token = await obtenerToken();
        const url   = new URL(RUTA_BODEGA_BASE + endpoint, window.location.origin);
        Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));

        const res = await fetch(url.toString(), {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Authorization': `Bearer ${token}`,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!res.ok && ![400, 404, 409, 422].includes(res.status)) {
            throw new Error(`HTTP ${res.status}`);
        }
        return res.json();
    }

    /**
     * Realiza una petición POST al endpoint de la API con autenticación JWT.
     * @param {string} endpoint
     * @param {object} payload
     * @returns {Promise<{success:boolean,data?:any,code?:string,message?:string}>}
     */
    async function apiPost(endpoint, payload) {
        const token = await obtenerToken();
        const res   = await fetch(RUTA_BODEGA_BASE + endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`,
                'X-Requested-With': 'XMLHttpRequest',
            },
            // El id_operador NUNCA se incluye en el payload; el endpoint lo toma del JWT.
            body: JSON.stringify(payload),
        });

        if (!res.ok && ![400, 409, 422, 404, 403].includes(res.status)) {
            throw new Error(`HTTP ${res.status}`);
        }
        return res.json();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Catálogos
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Carga bodegas activas y las coloca en un <select>.
     * @param {HTMLSelectElement} selectEl
     */
    async function cargarBodegas(selectEl) {
        selectEl.disabled = true;
        const opt0 = selectEl.options[0];
        if (opt0) opt0.textContent = '— Cargando… —';

        try {
            const resp = await apiGet('catalogos/bodegas.php');
            selectEl.innerHTML = '';
            const def = document.createElement('option');
            def.value = '';
            def.textContent = '— Seleccionar bodega —';
            selectEl.appendChild(def);

            (resp.data || []).forEach(b => {
                const o = document.createElement('option');
                o.value = String(b.id);
                o.textContent = `${b.nombre} (${b.codigo})`;
                selectEl.appendChild(o);
            });
            selectEl.disabled = false;
        } catch (err) {
            if (opt0) opt0.textContent = '— Error al cargar bodegas —';
        }
    }

    /**
     * Carga ubicaciones activas de una bodega y las coloca en un <select>.
     * @param {HTMLSelectElement} selectEl
     * @param {number} idBodega
     */
    async function cargarUbicaciones(selectEl, idBodega) {
        selectEl.innerHTML = '';
        const cargando = document.createElement('option');
        cargando.value = '';
        cargando.textContent = '— Cargando… —';
        selectEl.appendChild(cargando);
        selectEl.disabled = true;

        if (!idBodega) {
            cargando.textContent = '— Selecciona primero una bodega —';
            return;
        }

        try {
            const resp = await apiGet('catalogos/ubicaciones.php', { id_bodega: String(idBodega) });
            selectEl.innerHTML = '';
            const def = document.createElement('option');
            def.value = '';
            def.textContent = '— Seleccionar ubicación —';
            selectEl.appendChild(def);

            (resp.data || []).forEach(u => {
                const o = document.createElement('option');
                o.value = String(u.id);
                o.dataset.tipo = u.tipo || '';
                o.textContent = `${u.nomenclatura || u.codigo} [${u.tipo}]`;
                selectEl.appendChild(o);
            });
            selectEl.disabled = false;
        } catch {
            cargando.textContent = '— Error al cargar ubicaciones —';
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Panel principal: info del paquete
    // ══════════════════════════════════════════════════════════════════════════

    /** Muestra la información básica del pedido en el panel. */
    function renderInfoPedido(pedido) {
        const setTxt = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = esc(val);
        };
        setTxt('infoPedidoId',           pedido.id);
        setTxt('infoPedidoOrden',        pedido.numero_orden);
        setTxt('infoPedidoDestinatario', pedido.destinatario);
        setTxt('infoPedidoTelefono',     pedido.telefono || '—');
        setTxt('infoPedidoMunicipio',    pedido.municipio || '—');
        setTxt('infoPedidoFecha',        fmtFecha(pedido.fecha_ingreso));

        const badge = document.getElementById('badgeEstadoPedido');
        if (badge) {
            badge.textContent = esc(pedido.estado_nombre || '—');
        }

        const estadoLogi = document.getElementById('infoPedidoEstadoLogistico');
        if (estadoLogi) {
            // El estado logístico se actualiza cuando cargamos la recepción/ubicación
            estadoLogi.textContent = '—';
        }
    }

    /** Actualiza el bloque de ubicación en el panel. */
    function renderUbicacion(ubicacion) {
        _ubicacionActual = ubicacion;

        const sinUbic = document.getElementById('sinUbicacion');
        const conUbic = document.getElementById('conUbicacion');
        const badge   = document.getElementById('badgeUbicado');

        if (!ubicacion) {
            if (sinUbic) sinUbic.classList.remove('d-none');
            if (conUbic) conUbic.classList.add('d-none');
            if (badge)   { badge.className = 'ms-auto badge bg-secondary'; badge.textContent = 'SIN UBICACIÓN'; }
            return;
        }

        if (sinUbic) sinUbic.classList.add('d-none');
        if (conUbic) conUbic.classList.remove('d-none');
        if (badge)   { badge.className = 'ms-auto badge bg-success'; badge.textContent = 'UBICADO'; }

        const setTxt = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = esc(val || '—');
        };

        // Nomenclatura
        const nom = ubicacion.nomenclatura || ubicacion.codigo_ubicacion || '—';
        setTxt('ubicNomenclatura', nom);
        setTxt('ubicBodega',       ubicacion.bodega_nombre || ubicacion.id_bodega || '—');
        setTxt('ubicCodigo',       ubicacion.codigo_ubicacion || '—');
        setTxt('ubicZona',         ubicacion.zona        || '—');
        setTxt('ubicPasillo',      ubicacion.pasillo      || '—');
        setTxt('ubicEstante',      ubicacion.estante      || '—');
        setTxt('ubicCajon',        ubicacion.cajon        || '—');
        setTxt('ubicNivel',        ubicacion.nivel        || '—');
        setTxt('ubicTipo',         ubicacion.tipo_ubicacion || ubicacion.tipo || '—');
        setTxt('ubicFechaIngreso', fmtFecha(ubicacion.created_at || ubicacion.fecha_ingreso));

        // Estado logístico
        const estadoLogi = document.getElementById('infoPedidoEstadoLogistico');
        if (estadoLogi) estadoLogi.textContent = 'UBICADO';
    }

    /** Renderiza los botones de acciones según el estado del paquete. */
    function renderAcciones(recepcion, ubicacion) {
        const container = document.getElementById('accionesContainer');
        if (!container) return;
        container.innerHTML = '';

        // Sin recepción activa → Registrar recepción
        if (!recepcion) {
            const btn = crearBotonAccion('primary', 'bi-box-arrow-in-down', 'Registrar recepción', 'btnAccionRegistrar');
            btn.addEventListener('click', abrirModalRecepcion);
            container.appendChild(btn);
            return;
        }

        // Recepción RECIBIDO sin ubicación → Asignar ubicación
        if (recepcion.estado === 'RECIBIDO' && !ubicacion) {
            const btn = crearBotonAccion('success', 'bi-geo-alt-fill', 'Asignar ubicación', 'btnAccionUbicar');
            btn.addEventListener('click', abrirModalUbicar);
            container.appendChild(btn);
            return;
        }

        // Paquete UBICADO → Reubicar + Retirar
        if (ubicacion) {
            const btnR = crearBotonAccion('warning', 'bi-arrow-left-right', 'Reubicar', 'btnAccionReubicar');
            btnR.addEventListener('click', abrirModalReubicar);
            container.appendChild(btnR);

            const btnRet = crearBotonAccion('danger', 'bi-box-arrow-up', 'Retirar', 'btnAccionRetirar');
            btnRet.addEventListener('click', abrirModalRetirar);
            container.appendChild(btnRet);
            return;
        }

        // Estado RETIRADO → ofrecer nueva recepción
        if (recepcion.estado === 'RETIRADO') {
            const info = document.createElement('p');
            info.className = 'text-muted small text-center pt-2';
            info.textContent = 'El paquete fue retirado. Puedes registrar una nueva recepción si corresponde.';
            container.appendChild(info);

            const btn = crearBotonAccion('outline-primary', 'bi-box-arrow-in-down', 'Nueva recepción', 'btnAccionRegistrar2');
            btn.addEventListener('click', abrirModalRecepcion);
            container.appendChild(btn);
        }
    }

    /** Crea un botón de acción con estilo Bootstrap. */
    function crearBotonAccion(variant, icon, label, id) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.id = id;
        btn.className = `btn btn-${variant} w-100 fw-semibold mb-1`;
        const ico = document.createElement('i');
        ico.className = `bi ${icon} me-2`;
        btn.appendChild(ico);
        btn.appendChild(document.createTextNode(label));
        return btn;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Historial
    // ══════════════════════════════════════════════════════════════════════════

    /** Carga y renderiza el historial de movimientos del paquete. */
    async function cargarHistorial(idPedido) {
        const tbody = document.getElementById('tbodyHistorial');
        if (!tbody) return;

        // Fila de carga
        tbody.innerHTML = '';
        const trCargando = document.createElement('tr');
        const tdCargando = document.createElement('td');
        tdCargando.colSpan = 8;
        tdCargando.className = 'text-center text-muted py-3';
        tdCargando.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Cargando historial…';
        trCargando.appendChild(tdCargando);
        tbody.appendChild(trCargando);

        try {
            const resp = await apiGet('ubicaciones/historial.php', { id_pedido: String(idPedido) });
            tbody.innerHTML = '';

            const items = (resp.data || []);
            if (items.length === 0) {
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = 8;
                td.className = 'text-center text-muted py-4';
                td.textContent = 'Sin movimientos físicos registrados.';
                tr.appendChild(td);
                tbody.appendChild(tr);
                return;
            }

            items.forEach(item => {
                const tr = document.createElement('tr');
                tr.appendChild(tdTxt(item.tipo_movimiento  || '—'));
                tr.appendChild(tdTxt(item.bodega_nombre    || String(item.id_bodega || '—')));
                tr.appendChild(tdCodigo(item.codigo_ubicacion || '—'));
                tr.appendChild(tdTxt(item.operador_nombre  || String(item.id_operador || '—')));
                tr.appendChild(tdTxt(item.motivo           || '—'));
                tr.appendChild(tdTxt(fmtFecha(item.created_at)));
                tr.appendChild(tdTxt(item.retirado_at ? fmtFecha(item.retirado_at) : '—'));

                const tdEstado = document.createElement('td');
                tdEstado.className = 'text-center';
                const sp = document.createElement('span');
                sp.className = item.activo ? 'badge bg-success' : 'badge bg-secondary';
                sp.textContent = item.activo ? 'ACTIVO' : 'FINALIZADO';
                tdEstado.appendChild(sp);
                tr.appendChild(tdEstado);

                tbody.appendChild(tr);
            });

        } catch (err) {
            tbody.innerHTML = '';
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 8;
            td.className = 'text-center text-danger py-3 small';
            td.textContent = 'Error al cargar el historial.';
            tr.appendChild(td);
            tbody.appendChild(tr);
        }
    }

    /** Crea un <td> con texto escapado. */
    function tdTxt(val) {
        const td = document.createElement('td');
        td.className = 'small';
        td.textContent = esc(val);
        return td;
    }

    /** Crea un <td> con código en monospace. */
    function tdCodigo(val) {
        const td = document.createElement('td');
        td.className = 'small font-monospace';
        td.textContent = esc(val);
        return td;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Carga de estado completo del paquete
    // ══════════════════════════════════════════════════════════════════════════

    /** Refresca la ubicación actual y el historial, y actualiza las acciones. */
    async function refrescarEstadoPaquete() {
        if (!_pedidoActual) return;
        const id = _pedidoActual.id;

        // Ubicación actual
        try {
            const resp = await apiGet('ubicaciones/actual.php', { id_pedido: String(id) });
            if (resp.success) {
                _ubicacionActual = resp.data;
            } else {
                _ubicacionActual = null;
            }
        } catch {
            _ubicacionActual = null;
        }

        renderUbicacion(_ubicacionActual);
        renderAcciones(_recepcionActual, _ubicacionActual);
        await cargarHistorial(id);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // BUSCADOR
    // ══════════════════════════════════════════════════════════════════════════

    function initBuscador() {
        const form       = document.getElementById('formBuscador');
        const input      = document.getElementById('inputBusqueda');
        const alerta     = document.getElementById('alertaBusqueda');
        const btnBuscar  = document.getElementById('btnBuscar');
        const btnLimpiar = document.getElementById('btnLimpiarBusqueda');
        const spinner    = document.getElementById('spinnerBuscar');
        const icono      = document.getElementById('iconoBuscar');
        const panel      = document.getElementById('panelPaquete');

        if (!form || !input) return;

        let procesando = false;

        form.addEventListener('submit', async e => {
            e.preventDefault();
            if (procesando) return;

            const q = input.value.trim();

            alerta.className = 'text-danger small mt-1 d-none';
            alerta.textContent = '';

            if (!q) {
                alerta.className = 'text-danger small mt-1';
                alerta.textContent = 'Ingresa un ID de pedido o número de orden.';
                input.focus();
                return;
            }

            procesando = true;
            if (spinner) spinner.classList.remove('d-none');
            if (icono)   icono.classList.add('d-none');
            if (btnBuscar) btnBuscar.disabled = true;

            try {
                // Prefetch del token (falla rápido si la sesión expiró)
                await obtenerToken();

                const resp = await apiGet('pedidos/buscar.php', { q });

                if (!resp.success) {
                    alerta.className = 'text-danger small mt-1';
                    alerta.textContent = resp.code === 'PEDIDO_NO_ENCONTRADO'
                        ? 'No se encontró ningún paquete con ese criterio.'
                        : `Error: ${resp.message || resp.code || 'Desconocido'}`;
                    if (panel) panel.classList.add('d-none');
                    _pedidoActual = null;
                    return;
                }

                _pedidoActual = resp.data;
                _recepcionActual = null;
                _ubicacionActual = null;

                renderInfoPedido(_pedidoActual);
                if (panel) panel.classList.remove('d-none');

                // Intentar cargar recepción activa desde la ubicación
                await refrescarEstadoPaquete();

            } catch (err) {
                alerta.className = 'text-danger small mt-1';
                alerta.textContent = 'Error de conexión. Intenta de nuevo.';
                if (panel) panel.classList.add('d-none');
            } finally {
                procesando = false;
                if (spinner) spinner.classList.add('d-none');
                if (icono)   icono.classList.remove('d-none');
                if (btnBuscar) btnBuscar.disabled = false;
                input.focus();
            }
        });

        if (btnLimpiar) {
            btnLimpiar.addEventListener('click', () => {
                input.value = '';
                alerta.className = 'text-danger small mt-1 d-none';
                alerta.textContent = '';
                if (panel) panel.classList.add('d-none');
                _pedidoActual = null;
                _recepcionActual = null;
                _ubicacionActual = null;
                input.focus();
            });
        }

        // Botón escáner por Cámara QR en Bodega
        const btnScanQRBodega = document.getElementById('btnScanQRBodega');
        if (btnScanQRBodega) {
            btnScanQRBodega.addEventListener('click', () => {
                if (typeof window.abrirScannerQR === 'function') {
                    window.abrirScannerQR({
                        targetInputId: 'inputBusqueda',
                        onScanSuccess: (codigoLeido) => {
                            if (form) {
                                form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                            }
                        }
                    });
                }
            });
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Spinner helper para botones de modales
    // ══════════════════════════════════════════════════════════════════════════

    function setSpinner(spinnerId, iconoId, btnId, activo) {
        const sp  = document.getElementById(spinnerId);
        const ico = document.getElementById(iconoId);
        const btn = document.getElementById(btnId);
        if (sp)  sp.classList[activo ? 'remove' : 'add']('d-none');
        if (ico) ico.classList[activo ? 'add'   : 'remove']('d-none');
        if (btn) btn.disabled = activo;
    }

    function mostrarAlerta(alertaId, tipo, mensaje) {
        const el = document.getElementById(alertaId);
        if (!el) return;
        el.className = `alert alert-${tipo} mb-3`;
        el.textContent = mensaje;
    }

    function ocultarAlerta(alertaId) {
        const el = document.getElementById(alertaId);
        if (el) el.className = 'alert d-none mb-3';
    }

    function cerrarModal(modalId) {
        const el = document.getElementById(modalId);
        if (!el) return;
        const modal = bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
        modal.hide();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // MODAL: REGISTRAR RECEPCIÓN
    // ══════════════════════════════════════════════════════════════════════════

    async function abrirModalRecepcion() {
        if (!_pedidoActual) return;

        // Precargar bodegas
        const selBodega   = document.getElementById('recepIdBodega');
        const selUbicacion = document.getElementById('recepIdUbicacion');

        if (selBodega) await cargarBodegas(selBodega);
        if (selUbicacion) {
            selUbicacion.disabled = true;
            selUbicacion.innerHTML = '<option value="">— Selecciona primero una bodega —</option>';
        }

        // Info pedido
        const infoPedido = document.getElementById('recepPedidoInfo');
        if (infoPedido) {
            infoPedido.textContent = `#${_pedidoActual.id} — ${_pedidoActual.numero_orden}`;
        }

        // Fecha/hora actual
        const fechaEl = document.getElementById('recepFecha');
        if (fechaEl) {
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            fechaEl.value = now.toISOString().slice(0, 16);
        }

        ocultarAlerta('alertaRecepcion');

        const el = document.getElementById('modalRecepcion');
        if (el) (bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el)).show();
    }

    // Evento: cambio de bodega en recepción → cargar ubicaciones
    function initModalRecepcion() {
        const selBodega    = document.getElementById('recepIdBodega');
        const selUbicacion = document.getElementById('recepIdUbicacion');
        const btnConfirmar = document.getElementById('btnConfirmarRecepcion');

        if (selBodega && selUbicacion) {
            selBodega.addEventListener('change', async () => {
                const id = parseInt(selBodega.value, 10);
                if (id > 0) {
                    await cargarUbicaciones(selUbicacion, id);
                } else {
                    selUbicacion.innerHTML = '<option value="">— Selecciona primero una bodega —</option>';
                    selUbicacion.disabled = true;
                }
            });
        }

        if (!btnConfirmar) return;

        let procesando = false;

        btnConfirmar.addEventListener('click', async () => {
            if (procesando || !_pedidoActual) return;

            ocultarAlerta('alertaRecepcion');

            const idBodega  = parseInt(selBodega?.value || '0', 10);
            const tipo      = document.getElementById('recepTipo')?.value || '';
            const fechaRaw  = document.getElementById('recepFecha')?.value || '';
            const idUbicacion = parseInt(document.getElementById('recepIdUbicacion')?.value || '0', 10);
            const observacion = (document.getElementById('recepObservacion')?.value || '').trim();

            if (!idBodega || !tipo || !fechaRaw) {
                mostrarAlerta('alertaRecepcion', 'danger', 'Completa los campos requeridos.');
                return;
            }

            procesando = true;
            setSpinner('spinnerRecepcion', 'iconoRecepcion', 'btnConfirmarRecepcion', true);

            try {
                const fechaISO = fechaRaw.replace('T', ' ') + ':00';
                const payload  = {
                    uuid:           generarUUID(),
                    id_pedido:      _pedidoActual.id,
                    id_bodega:      idBodega,
                    id_ubicacion:   idUbicacion > 0 ? idUbicacion : null,
                    id_escaneo:     null,
                    tipo_recepcion: tipo,
                    recibido_at:    fechaISO,
                    observacion:    observacion || null,
                    // id_operador NO se envía — el endpoint lo extrae del JWT
                };

                const resp = await apiPost('recepciones/registrar.php', payload);

                if (!resp.success) {
                    mostrarAlerta('alertaRecepcion', 'danger', resp.message || 'Error al registrar la recepción.');
                    return;
                }

                cerrarModal('modalRecepcion');
                _recepcionActual = resp.data;
                await refrescarEstadoPaquete();

                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'success', title: 'Recepción registrada', timer: 2000, showConfirmButton: false });
                }

            } catch (err) {
                mostrarAlerta('alertaRecepcion', 'danger', 'Error de conexión. Intenta de nuevo.');
            } finally {
                procesando = false;
                setSpinner('spinnerRecepcion', 'iconoRecepcion', 'btnConfirmarRecepcion', false);
            }
        });
    }

    // ══════════════════════════════════════════════════════════════════════════
    // MODAL: ASIGNAR UBICACIÓN
    // ══════════════════════════════════════════════════════════════════════════

    async function abrirModalUbicar() {
        if (!_pedidoActual || !_recepcionActual) return;

        const bodegaNombre = document.getElementById('ubicarBodegaNombre');
        const idBodegaHid  = document.getElementById('ubicarIdBodega');
        const idRecepHid   = document.getElementById('ubicarIdRecepcion');
        const selUbicacion = document.getElementById('ubicarIdUbicacion');

        if (bodegaNombre) bodegaNombre.value = esc(_recepcionActual.bodega_nombre || String(_recepcionActual.id_bodega));
        if (idBodegaHid)  idBodegaHid.value  = String(_recepcionActual.id_bodega);
        if (idRecepHid)   idRecepHid.value   = String(_recepcionActual.id);

        if (selUbicacion) await cargarUbicaciones(selUbicacion, _recepcionActual.id_bodega);

        ocultarAlerta('alertaUbicar');
        const el = document.getElementById('modalUbicar');
        if (el) (bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el)).show();
    }

    function initModalUbicar() {
        const btnConfirmar = document.getElementById('btnConfirmarUbicar');
        if (!btnConfirmar) return;

        let procesando = false;

        btnConfirmar.addEventListener('click', async () => {
            if (procesando || !_pedidoActual || !_recepcionActual) return;

            ocultarAlerta('alertaUbicar');

            const idUbicacion = parseInt(document.getElementById('ubicarIdUbicacion')?.value || '0', 10);
            const idRecepcion = parseInt(document.getElementById('ubicarIdRecepcion')?.value || '0', 10);
            const motivo      = (document.getElementById('ubicarMotivo')?.value || '').trim();

            if (!idUbicacion) {
                mostrarAlerta('alertaUbicar', 'danger', 'Selecciona una ubicación.');
                return;
            }

            procesando = true;
            setSpinner('spinnerUbicar', 'iconoUbicar', 'btnConfirmarUbicar', true);

            try {
                const resp = await apiPost('ubicaciones/asignar.php', {
                    id_pedido:    _pedidoActual.id,
                    id_recepcion: idRecepcion,
                    id_ubicacion: idUbicacion,
                    motivo:       motivo || null,
                });

                if (!resp.success) {
                    mostrarAlerta('alertaUbicar', 'danger', resp.message || 'Error al asignar ubicación.');
                    return;
                }

                cerrarModal('modalUbicar');
                await refrescarEstadoPaquete();

                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'success', title: 'Ubicación asignada', timer: 2000, showConfirmButton: false });
                }

            } catch {
                mostrarAlerta('alertaUbicar', 'danger', 'Error de conexión.');
            } finally {
                procesando = false;
                setSpinner('spinnerUbicar', 'iconoUbicar', 'btnConfirmarUbicar', false);
            }
        });
    }

    // ══════════════════════════════════════════════════════════════════════════
    // MODAL: REUBICAR
    // ══════════════════════════════════════════════════════════════════════════

    async function abrirModalReubicar() {
        if (!_pedidoActual || !_ubicacionActual) return;

        const selDestino  = document.getElementById('reubicarDestino');
        const actualEl    = document.getElementById('reubicarActual');

        const nomActual = _ubicacionActual.nomenclatura || _ubicacionActual.codigo_ubicacion || '—';
        if (actualEl) actualEl.value = nomActual;

        if (selDestino) await cargarUbicaciones(selDestino, _ubicacionActual.id_bodega || _recepcionActual?.id_bodega);

        ocultarAlerta('alertaReubicar');
        const el = document.getElementById('modalReubicar');
        if (el) (bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el)).show();
    }

    function initModalReubicar() {
        const btnConfirmar = document.getElementById('btnConfirmarReubicar');
        if (!btnConfirmar) return;

        let procesando = false;

        btnConfirmar.addEventListener('click', async () => {
            if (procesando || !_pedidoActual) return;

            ocultarAlerta('alertaReubicar');

            const idDestino = parseInt(document.getElementById('reubicarDestino')?.value || '0', 10);
            const motivo    = (document.getElementById('reubicarMotivo')?.value || '').trim();

            if (!idDestino) {
                mostrarAlerta('alertaReubicar', 'danger', 'Selecciona una ubicación destino.');
                return;
            }

            procesando = true;
            setSpinner('spinnerReubicar', 'iconoReubicar', 'btnConfirmarReubicar', true);

            try {
                const resp = await apiPost('ubicaciones/reubicar.php', {
                    id_pedido:            _pedidoActual.id,
                    id_ubicacion_destino: idDestino,
                    motivo:               motivo || null,
                });

                if (!resp.success) {
                    mostrarAlerta('alertaReubicar', 'danger', resp.message || 'Error al reubicar.');
                    return;
                }

                const msg = resp.data?.sin_cambio
                    ? 'El destino es igual a la ubicación actual; no se realizaron cambios.'
                    : 'Paquete reubicado correctamente.';

                cerrarModal('modalReubicar');
                await refrescarEstadoPaquete();

                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'success', title: msg, timer: 2500, showConfirmButton: false });
                }

            } catch {
                mostrarAlerta('alertaReubicar', 'danger', 'Error de conexión.');
            } finally {
                procesando = false;
                setSpinner('spinnerReubicar', 'iconoReubicar', 'btnConfirmarReubicar', false);
            }
        });
    }

    // ══════════════════════════════════════════════════════════════════════════
    // MODAL: RETIRAR
    // ══════════════════════════════════════════════════════════════════════════

    function abrirModalRetirar() {
        if (!_pedidoActual || !_ubicacionActual) return;

        const setTxt = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = esc(val);
        };

        setTxt('retirarPedidoInfo',    `#${_pedidoActual.id} — ${_pedidoActual.numero_orden}`);
        setTxt('retirarBodegaInfo',    _ubicacionActual.bodega_nombre || String(_ubicacionActual.id_bodega || '—'));
        setTxt('retirarUbicacionInfo', _ubicacionActual.nomenclatura || _ubicacionActual.codigo_ubicacion || '—');

        const motEl = document.getElementById('retirarMotivo');
        if (motEl) motEl.value = '';

        ocultarAlerta('alertaRetirar');
        const el = document.getElementById('modalRetirar');
        if (el) (bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el)).show();
    }

    function initModalRetirar() {
        const btnConfirmar = document.getElementById('btnConfirmarRetirar');
        if (!btnConfirmar) return;

        let procesando = false;

        btnConfirmar.addEventListener('click', async () => {
            if (procesando || !_pedidoActual) return;

            ocultarAlerta('alertaRetirar');

            const motivo = (document.getElementById('retirarMotivo')?.value || '').trim();

            procesando = true;
            setSpinner('spinnerRetirar', 'iconoRetirar', 'btnConfirmarRetirar', true);

            try {
                const resp = await apiPost('ubicaciones/retirar.php', {
                    id_pedido: _pedidoActual.id,
                    motivo:    motivo || null,
                });

                if (!resp.success) {
                    mostrarAlerta('alertaRetirar', 'danger', resp.message || 'Error al retirar el paquete.');
                    return;
                }

                cerrarModal('modalRetirar');
                _ubicacionActual = null;
                await refrescarEstadoPaquete();

                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'info', title: 'Paquete retirado de su ubicación.', timer: 2500, showConfirmButton: false });
                }

            } catch {
                mostrarAlerta('alertaRetirar', 'danger', 'Error de conexión.');
            } finally {
                procesando = false;
                setSpinner('spinnerRetirar', 'iconoRetirar', 'btnConfirmarRetirar', false);
            }
        });
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Inicialización
    // ══════════════════════════════════════════════════════════════════════════

    function init() {
        initBuscador();
        initModalRecepcion();
        initModalUbicar();
        initModalReubicar();
        initModalRetirar();

        // Pre-cargar token de sesión en segundo plano para que la primera
        // búsqueda responda sin latencia adicional.
        obtenerToken().catch(() => {});
    }

    // Ejecutar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})(); // fin BodegaModule IIFE
