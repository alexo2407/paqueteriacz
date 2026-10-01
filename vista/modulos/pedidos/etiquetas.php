<?php
/**
 * vista/modulos/pedidos/etiquetas.php
 * Módulo Especial: Centro de Impresión de Etiquetas (3nStar / 80mm)
 *
 * Filtro exclusivo para pedidos en estado "En bodega" (ID 1).
 * Accesible exclusivamente para Administradores y Clientes (solo sus pedidos).
 */

$usaDataTables = true;
require_once "utils/authorization.php";
require_once "modelo/pedido.php";

$roles = $_SESSION['roles_nombres'] ?? [];
$sessionRol = $_SESSION['rol'] ?? null;
$sessionRoles = $_SESSION['roles'] ?? [];

$isAdmin = in_array(ROL_NOMBRE_ADMIN, $roles, true)
    || in_array('Administrador', $roles, true)
    || in_array('admin', $roles, true)
    || (function_exists('isAdmin') && isAdmin())
    || ($sessionRol == 1)
    || (is_array($sessionRoles) && in_array(1, $sessionRoles));

$isCliente = in_array(ROL_NOMBRE_CLIENTE, $roles, true)
    || in_array('Cliente', $roles, true)
    || in_array(ROL_NOMBRE_PROVEEDOR, $roles, true)
    || in_array('Proveedor', $roles, true)
    || in_array($sessionRol, [4, 5])
    || (function_exists('isCliente') && isCliente())
    || (function_exists('isProveedor') && isProveedor());

if (!$isAdmin && !$isCliente) {
    echo "<script>window.location.href = '" . RUTA_URL . "login';</script>";
    exit;
}

include("vista/includes/header.php");

// Lista de clientes para el filtro (solo para administradores)
$clientesLista = [];
if ($isAdmin) {
    try {
        $clientesLista = PedidosModel::obtenerClientes();
    } catch (Exception $e) {
        $clientesLista = [];
    }
}
?>

<div class="container-fluid px-4 py-3">

    <!-- ── Encabezado Principal ──────────────────────────────────────────────── -->
    <div class="card shadow-sm border-0 mb-4" style="border-radius:12px; background:linear-gradient(135deg, #061C4C 0%, #0B4EA2 100%); color:#fff;">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                <div>
                    <h3 class="fw-bold mb-1 d-flex align-items-center gap-2">
                        <i class="bi bi-printer-fill text-warning"></i>
                        Centro de Impresión de Etiquetas
                    </h3>
                    <p class="mb-0 text-white-50" style="font-size:0.95rem;">
                        Generador de etiquetas 80 mm para impresoras <strong>3nStar</strong> • Exclusivo para pedidos en 
                        <span class="badge bg-warning text-dark px-2 py-1"><i class="bi bi-box-seam me-1"></i>En Bodega</span>
                    </p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" id="btnRecargarTabla" class="btn btn-outline-light btn-sm px-3">
                        <i class="bi bi-arrow-clockwise me-1"></i> Actualizar
                    </button>
                    <a href="<?= RUTA_URL ?>pedidos/listar" class="btn btn-light btn-sm text-primary fw-semibold px-3">
                        <i class="bi bi-arrow-left me-1"></i> Volver a Pedidos
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Panel de Filtros ────────────────────────────────────────────────── -->
    <div class="card shadow-sm border-0 mb-4" style="border-radius:12px;">
        <div class="card-header bg-white py-3 border-0">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                <span class="fw-bold text-dark d-flex align-items-center gap-2">
                    <i class="bi bi-funnel-fill text-primary"></i> Filtros de Búsqueda y Rango de Fechas
                </span>
                <!-- Botones rápidos de rango -->
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary btn-quick-date" data-range="hoy">Hoy</button>
                    <button type="button" class="btn btn-outline-secondary btn-quick-date" data-range="ayer">Ayer</button>
                    <button type="button" class="btn btn-outline-secondary btn-quick-date" data-range="semana">Esta Semana</button>
                    <button type="button" class="btn btn-outline-secondary btn-quick-date" data-range="mes">Este Mes</button>
                    <button type="button" class="btn btn-outline-secondary btn-quick-date active" data-range="todos">Todos</button>
                </div>
            </div>
        </div>
        <div class="card-body bg-light bg-opacity-50 pt-0 pb-4">
            <form id="formFiltrosEtiquetas" class="row g-3 align-items-end">
                
                <!-- Tipo de fecha -->
                <div class="col-md-2 col-sm-6">
                    <label for="filtro_tipo_fecha" class="form-label small fw-bold text-secondary">Criterio de Fecha</label>
                    <select id="filtro_tipo_fecha" class="form-select form-select-sm">
                        <option value="fecha_entrega" selected>Fecha de Entrega</option>
                        <option value="fecha_ingreso">Fecha de Ingreso</option>
                    </select>
                </div>

                <!-- Fecha Desde -->
                <div class="col-md-2 col-sm-6">
                    <label for="filtro_fecha_desde" class="form-label small fw-bold text-secondary">Fecha Desde</label>
                    <input type="date" id="filtro_fecha_desde" class="form-control form-control-sm" value="">
                </div>

                <!-- Fecha Hasta -->
                <div class="col-md-2 col-sm-6">
                    <label for="filtro_fecha_hasta" class="form-label small fw-bold text-secondary">Fecha Hasta</label>
                    <input type="date" id="filtro_fecha_hasta" class="form-control form-control-sm" value="">
                </div>

                <?php if ($isAdmin): ?>
                <!-- Filtro de Cliente/Tienda (Solo Admin) -->
                <div class="col-md-3 col-sm-6">
                    <label for="filtro_cliente" class="form-label small fw-bold text-secondary">Cliente / Comercio</label>
                    <select id="filtro_cliente" class="form-select form-select-sm select2-basic">
                        <option value="">-- Todos los Clientes --</option>
                        <?php foreach ($clientesLista as $cli): ?>
                            <option value="<?= $cli['id'] ?>"><?= htmlspecialchars($cli['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <!-- Buscador / Lector de Código de Barras -->
                <div class="<?= $isAdmin ? 'col-md-3' : 'col-md-6' ?> col-sm-12">
                    <label for="filtro_search" class="form-label small fw-bold text-secondary">
                        <i class="bi bi-upc-scan text-primary me-1"></i> Buscar Orden / Destinatario / Tel.
                    </label>
                    <div class="input-group input-group-sm">
                        <input type="text" id="filtro_search" class="form-control" placeholder="Pasa la pistola lectora o escribe..." autofocus>
                        <button class="btn btn-primary" type="button" id="btnBuscar">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </div>

    <!-- ── Barra de Acciones Masivas y Estadísticas ────────────────────────── -->
    <div class="card shadow-sm border-0 mb-3" style="border-radius:12px;">
        <div class="card-body p-3">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="form-check m-0">
                        <input class="form-check-input" type="checkbox" id="checkSelectAll" style="transform: scale(1.2); cursor:pointer;">
                        <label class="form-check-label fw-bold text-dark ms-1" for="checkSelectAll" style="cursor:pointer;">
                            Seleccionar Visibles
                        </label>
                    </div>
                    <span class="badge bg-primary rounded-pill px-3 py-2" id="badgeTotalBodega">
                        <i class="bi bi-box-seam me-1"></i> <span id="spanTotalCount">0</span> en bodega
                    </span>
                    <span class="badge bg-secondary rounded-pill px-3 py-2" id="badgeSelectedCount" style="display:none;">
                        <i class="bi bi-check2-circle me-1"></i> <span id="spanSelectedNum">0</span> seleccionados
                    </span>
                </div>

                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <!-- Botón Impresión Seleccionados -->
                    <button type="button" id="btnImprimirSeleccionados" class="btn btn-success btn-sm px-3 fw-semibold shadow-sm" disabled>
                        <i class="bi bi-printer-fill me-1"></i> Imprimir Seleccionados (<span id="btnSelCount">0</span>)
                    </button>
                    
                    <!-- Botón Impresión Todo el Filtro -->
                    <button type="button" id="btnImprimirTodoFiltro" class="btn btn-dark btn-sm px-3 fw-semibold shadow-sm">
                        <i class="bi bi-file-earmark-pdf-fill text-warning me-1"></i> Imprimir Todo el Filtro (<span id="btnFiltroTotalCount">0</span>)
                    </button>
                </div>

            </div>
        </div>
    </div>

    <!-- ── Tabla de Pedidos en Bodega ──────────────────────────────────────── -->
    <div class="card shadow-sm border-0 mb-4" style="border-radius:12px; overflow:hidden;">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="tblEtiquetasBodega" class="table table-hover align-middle mb-0" style="width:100%;">
                    <thead style="background:#061C4C; color:#ffffff;">
                        <tr>
                            <th style="width: 40px;" class="text-center">#</th>
                            <th style="width: 140px;">N° Orden</th>
                            <?php if ($isAdmin): ?>
                            <th style="width: 140px;">Cliente / Tienda</th>
                            <?php endif; ?>
                            <th>Destinatario</th>
                            <th>Teléfono</th>
                            <th>Dirección y Referencias</th>
                            <th style="width: 110px;">Fecha Entrega</th>
                            <th style="width: 120px;" class="text-end">Monto (COD)</th>
                            <th style="width: 130px;" class="text-center">Imprimir</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyEtiquetas">
                        <tr>
                            <td colspan="<?= $isAdmin ? 9 : 8 ?>" class="text-center py-5 text-muted">
                                <div class="spinner-border text-primary spinner-border-sm me-2" role="status"></div>
                                Cargando pedidos en bodega...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ── Controles de Paginación ────────────────────────────────────── -->
            <div class="d-flex flex-column flex-md-row align-items-center justify-content-between p-3 border-top bg-light gap-2" id="paginacionContainer" style="display:none;">
                <div class="d-flex align-items-center gap-2">
                    <span class="small text-secondary">Mostrar:</span>
                    <select id="selectPageSize" class="form-select form-select-sm" style="width: auto;">
                        <option value="10">10</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="999999">Todos</option>
                    </select>
                    <span class="small text-secondary" id="labelPaginacionInfo">Mostrando 0 - 0 de 0</span>
                </div>

                <nav aria-label="Navegación de páginas">
                    <ul class="pagination pagination-sm m-0" id="ulPagination">
                        <!-- Botones de página dinámicos -->
                    </ul>
                </nav>
            </div>

        </div>
    </div>

</div>

<!-- ── Estilos Visuales ────────────────────────────────────────────────────── -->
<style>
.table-hover tbody tr:hover {
    background-color: rgba(11, 78, 162, 0.04);
}
.order-badge {
    font-family: monospace;
    font-size: 0.92rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    background: #eef3ff;
    color: #0B4EA2;
    padding: 3px 8px;
    border-radius: 6px;
    border: 1px solid #c7d9ff;
    display: inline-block;
}
.btn-print-single {
    background: #061C4C;
    color: #ffffff;
    font-weight: 600;
    transition: all 0.2s ease;
}
.btn-print-single:hover {
    background: #0B4EA2;
    color: #ffffff;
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(11, 78, 162, 0.25);
}
.price-badge {
    font-size: 0.95rem;
    font-weight: 700;
    color: #198754;
}
.page-link {
    color: #0B4EA2;
    cursor: pointer;
}
.page-item.active .page-link {
    background-color: #0B4EA2;
    border-color: #0B4EA2;
    color: #ffffff;
}
</style>

<!-- ── Script del Módulo de Etiquetas ─────────────────────────────────────── -->
<script>
document.addEventListener('DOMContentLoaded', function () {

    const RUTA_BASE = '<?= RUTA_URL ?>';
    const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;

    let pedidosActuales = [];
    let selectedIds = new Set();
    
    // Paginación
    let currentPage = 1;
    let pageSize = 25;

    // Elementos DOM
    const tbody = document.getElementById('tbodyEtiquetas');
    const spanTotalCount = document.getElementById('spanTotalCount');
    const spanSelectedNum = document.getElementById('spanSelectedNum');
    const badgeSelectedCount = document.getElementById('badgeSelectedCount');
    const btnImprimirSeleccionados = document.getElementById('btnImprimirSeleccionados');
    const btnSelCount = document.getElementById('btnSelCount');
    const btnImprimirTodoFiltro = document.getElementById('btnImprimirTodoFiltro');
    const btnFiltroTotalCount = document.getElementById('btnFiltroTotalCount');
    const checkSelectAll = document.getElementById('checkSelectAll');
    
    // Controles de Paginación
    const paginacionContainer = document.getElementById('paginacionContainer');
    const selectPageSize = document.getElementById('selectPageSize');
    const labelPaginacionInfo = document.getElementById('labelPaginacionInfo');
    const ulPagination = document.getElementById('ulPagination');

    // Filtros
    const inputFechaDesde = document.getElementById('filtro_fecha_desde');
    const inputFechaHasta = document.getElementById('filtro_fecha_hasta');
    const selectTipoFecha = document.getElementById('filtro_tipo_fecha');
    const selectCliente = document.getElementById('filtro_cliente');
    const inputSearch = document.getElementById('filtro_search');
    const btnBuscar = document.getElementById('btnBuscar');
    const btnRecargar = document.getElementById('btnRecargarTabla');

    /**
     * Carga los pedidos en bodega desde la API
     */
    function cargarPedidosBodega() {
        tbody.innerHTML = `
            <tr>
                <td colspan="${IS_ADMIN ? 9 : 8}" class="text-center py-5 text-muted">
                    <div class="spinner-border text-primary spinner-border-sm me-2" role="status"></div>
                    Buscando pedidos en bodega...
                </td>
            </tr>
        `;

        const params = new URLSearchParams({
            fecha_desde: inputFechaDesde.value,
            fecha_hasta: inputFechaHasta.value,
            tipo_fecha: selectTipoFecha.value,
            search: inputSearch.value.trim(),
        });

        if (IS_ADMIN && selectCliente && selectCliente.value) {
            params.append('id_cliente', selectCliente.value);
        }

        fetch(RUTA_BASE + 'api/pedidos/etiquetas_bodega.php?' + params.toString())
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    pedidosActuales = res.data || [];
                    currentPage = 1; // Reiniciar a página 1 al filtrar
                    renderizarTabla();
                } else {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="${IS_ADMIN ? 9 : 8}" class="text-center py-4 text-danger">
                                <i class="bi bi-exclamation-triangle me-1"></i> ${res.error || 'Error al cargar los pedidos.'}
                            </td>
                        </tr>
                    `;
                    paginacionContainer.style.display = 'none';
                }
            })
            .catch(err => {
                console.error(err);
                tbody.innerHTML = `
                    <tr>
                        <td colspan="${IS_ADMIN ? 9 : 8}" class="text-center py-4 text-danger">
                            <i class="bi bi-exclamation-triangle me-1"></i> Error de conexión con el servidor.
                        </td>
                    </tr>
                `;
                paginacionContainer.style.display = 'none';
            });
    }

    /**
     * Renderiza la tabla HTML con soporte de paginación
     */
    function renderizarTabla() {
        const total = pedidosActuales.length;
        
        // Limpiar selecciones que ya no existan en el total filtrado
        const nuevosIds = new Set(pedidosActuales.map(p => parseInt(p.id)));
        selectedIds = new Set([...selectedIds].filter(id => nuevosIds.has(id)));
        
        actualizarContadores();

        if (total === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="${IS_ADMIN ? 9 : 8}" class="text-center py-5">
                        <div class="text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                            <strong>No se encontraron pedidos en bodega con los filtros actuales.</strong>
                            <p class="small text-secondary mb-0">Verifique el rango de fechas o cambie el criterio de búsqueda.</p>
                        </div>
                    </td>
                </tr>
            `;
            paginacionContainer.style.display = 'none';
            return;
        }

        paginacionContainer.style.display = 'flex';

        // Calcular slice para la página actual
        const totalPages = Math.ceil(total / pageSize) || 1;
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const startIndex = (currentPage - 1) * pageSize;
        const endIndex = Math.min(startIndex + pageSize, total);
        const pedidosPagina = pedidosActuales.slice(startIndex, endIndex);

        let html = '';
        pedidosPagina.forEach(p => {
            const id = parseInt(p.id);
            const isChecked = selectedIds.has(id) ? 'checked' : '';
            const numOrden = p.numero_orden || ('ORD-' + p.id);
            const destinatario = p.destinatario || '—';
            const telefono = p.telefono || '—';
            
            // Dirección y referencias
            let dirTexto = p.direccion || '—';
            if (p.betweenStreets) {
                dirTexto += `<div class="small text-muted mt-1"><strong>Ref 1:</strong> ${escapeHtml(p.betweenStreets)}</div>`;
            }
            if (p.comentario && p.comentario !== 'Sin comentarios') {
                dirTexto += `<div class="small text-muted"><strong>Ref 2:</strong> ${escapeHtml(p.comentario)}</div>`;
            }

            // Fecha de entrega formateada
            let fEntrega = p.fecha_entrega ? p.fecha_entrega.substring(0, 10) : '—';

            // Moneda y Monto por País (Código ISO)
            const totalMonto = parseFloat(p.precio_total_local || p.precio_local || 0);
            let monedaCod = (p.moneda_codigo || 'CRC').toUpperCase().trim();
            const idPais = parseInt(p.id_pais || 0, 10);
            if (!monedaCod || monedaCod === 'NI' || monedaCod === 'NIC') {
                monedaCod = (idPais === 1) ? 'NIO' : ((idPais === 2) ? 'CRC' : ((idPais === 6 || idPais === 10) ? 'GTQ' : ((idPais === 3) ? 'COP' : 'CRC')));
            } else if (monedaCod === 'CR' || monedaCod === 'CRI') {
                monedaCod = 'CRC';
            } else if (['GUAT', 'GUATL', 'GT'].includes(monedaCod)) {
                monedaCod = 'GTQ';
            } else if (['CO', 'COL'].includes(monedaCod)) {
                monedaCod = 'COP';
            } else if (['SLV', 'SV', 'PAN', 'PA', 'EC'].includes(monedaCod)) {
                monedaCod = 'USD';
            } else if (['MX', 'MEX'].includes(monedaCod)) {
                monedaCod = 'MXN';
            } else if (['HND', 'HN'].includes(monedaCod)) {
                monedaCod = 'HNL';
            } else if (['URY', 'UY'].includes(monedaCod)) {
                monedaCod = 'UYU';
            } else if (['ARS', 'AR'].includes(monedaCod)) {
                monedaCod = 'ARS';
            }
            if (!monedaCod) monedaCod = 'CRC';

            const montoHtml = totalMonto > 0 
                ? `<span class="price-badge">${escapeHtml(monedaCod)} ${totalMonto.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})}</span>`
                : `<span class="badge bg-light text-secondary border">Sin cobro (${escapeHtml(monedaCod)} 0.00)</span>`;

            html += `
                <tr id="row-pedido-${id}">
                    <td class="text-center">
                        <input class="form-check-input check-row-pedido" type="checkbox" data-id="${id}" ${isChecked} style="cursor:pointer;">
                    </td>
                    <td>
                        <span class="order-badge">${escapeHtml(numOrden)}</span>
                    </td>
                    ${IS_ADMIN ? `<td><span class="fw-semibold text-dark">${escapeHtml(p.remitente_nombre || p.cliente_nombre || '—')}</span></td>` : ''}
                    <td>
                        <div class="fw-bold text-dark">${escapeHtml(destinatario)}</div>
                    </td>
                    <td>
                        <a href="tel:${escapeHtml(telefono)}" class="text-decoration-none fw-semibold text-dark">
                            <i class="bi bi-telephone text-secondary me-1"></i>${escapeHtml(telefono)}
                        </a>
                    </td>
                    <td style="max-width:320px;">
                        <div class="small text-dark text-truncate" title="${escapeHtml(p.direccion || '')}">${escapeHtml(p.direccion || '—')}</div>
                        ${dirTexto.includes('Ref') ? dirTexto : ''}
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border">
                            <i class="bi bi-calendar3 me-1"></i>${fEntrega}
                        </span>
                    </td>
                    <td class="text-end">
                        ${montoHtml}
                    </td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-print-single px-2 py-1" data-id="${id}" title="Imprimir Etiqueta 80mm">
                                <i class="bi bi-printer-fill me-1"></i> 80mm
                            </button>
                            <a href="${RUTA_BASE}pedidos/ver/${id}" target="_blank" class="btn btn-outline-secondary px-2 py-1" title="Ver Detalle">
                                <i class="bi bi-eye"></i>
                            </a>
                        </div>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
        
        // Actualizar info y controles de paginación
        labelPaginacionInfo.textContent = `Mostrando ${startIndex + 1} - ${endIndex} de ${total} pedidos`;
        renderizarPaginador(totalPages);
        actualizarSelectAllCheckbox();
    }

    /**
     * Dibuja los botones del paginador
     */
    function renderizarPaginador(totalPages) {
        let pagHtml = '';

        // Botón Anterior
        pagHtml += `
            <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                <a class="page-link" data-page="${currentPage - 1}">&laquo; Anterior</a>
            </li>
        `;

        // Ventana de páginas visibles
        const maxVisible = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
        let endPage = Math.min(totalPages, startPage + maxVisible - 1);

        if (endPage - startPage + 1 < maxVisible) {
            startPage = Math.max(1, endPage - maxVisible + 1);
        }

        if (startPage > 1) {
            pagHtml += `<li class="page-item"><a class="page-link" data-page="1">1</a></li>`;
            if (startPage > 2) {
                pagHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            pagHtml += `
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" data-page="${i}">${i}</a>
                </li>
            `;
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                pagHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
            pagHtml += `<li class="page-item"><a class="page-link" data-page="${totalPages}">${totalPages}</a></li>`;
        }

        // Botón Siguiente
        pagHtml += `
            <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                <a class="page-link" data-page="${currentPage + 1}">Siguiente &raquo;</a>
            </li>
        `;

        ulPagination.innerHTML = pagHtml;
    }

    /**
     * Actualiza contadores y estados de botones
     */
    function actualizarContadores() {
        const total = pedidosActuales.length;
        const count = selectedIds.size;

        spanTotalCount.textContent = total;
        btnFiltroTotalCount.textContent = total;
        spanSelectedNum.textContent = count;
        btnSelCount.textContent = count;

        if (count > 0) {
            badgeSelectedCount.style.display = 'inline-block';
            btnImprimirSeleccionados.disabled = false;
        } else {
            badgeSelectedCount.style.display = 'none';
            btnImprimirSeleccionados.disabled = true;
        }

        btnImprimirTodoFiltro.disabled = (total === 0);
    }

    function actualizarSelectAllCheckbox() {
        const checkBoxesVisibles = document.querySelectorAll('.check-row-pedido');
        if (checkBoxesVisibles.length === 0) {
            checkSelectAll.checked = false;
            checkSelectAll.indeterminate = false;
            return;
        }

        const todosVisibles = Array.from(checkBoxesVisibles).every(cb => cb.checked);
        const algunoVisible = Array.from(checkBoxesVisibles).some(cb => cb.checked);

        checkSelectAll.checked = todosVisibles;
        checkSelectAll.indeterminate = !todosVisibles && algunoVisible;
    }

    // ── Eventos de Interacción ──────────────────────────────────────────────

    // Clic en paginación
    ulPagination.addEventListener('click', function (e) {
        const link = e.target.closest('.page-link');
        if (link && link.dataset.page) {
            e.preventDefault();
            const page = parseInt(link.dataset.page);
            if (!isNaN(page) && page > 0 && page !== currentPage) {
                currentPage = page;
                renderizarTabla();
                window.scrollTo({ top: 300, behavior: 'smooth' });
            }
        }
    });

    // Cambio en tamaño de página
    selectPageSize.addEventListener('change', function () {
        pageSize = parseInt(this.value);
        currentPage = 1;
        renderizarTabla();
    });

    // Clic en checkbox de fila
    tbody.addEventListener('change', function (e) {
        if (e.target.classList.contains('check-row-pedido')) {
            const id = parseInt(e.target.dataset.id);
            if (e.target.checked) {
                selectedIds.add(id);
            } else {
                selectedIds.delete(id);
            }
            actualizarContadores();
            actualizarSelectAllCheckbox();
        }
    });

    // Seleccionar todos los visibles en la página actual
    checkSelectAll.addEventListener('change', function () {
        const checked = this.checked;
        const checkBoxesVisibles = document.querySelectorAll('.check-row-pedido');
        
        checkBoxesVisibles.forEach(cb => {
            cb.checked = checked;
            const id = parseInt(cb.dataset.id);
            if (checked) {
                selectedIds.add(id);
            } else {
                selectedIds.delete(id);
            }
        });
        
        actualizarContadores();
    });

    // Botón Imprimir Individual (80mm)
    tbody.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-print-single');
        if (btn) {
            const id = btn.dataset.id;
            window.open(RUTA_BASE + 'pedidos/etiqueta/' + id, '_blank');
        }
    });

    // Botón Imprimir Seleccionados
    btnImprimirSeleccionados.addEventListener('click', function () {
        if (selectedIds.size === 0) return;
        
        const idsArray = Array.from(selectedIds);
        
        // Crear formulario dinámico para POST a nueva pestaña
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = RUTA_BASE + 'pedidos/imprimir-etiquetas';
        form.target = '_blank';

        idsArray.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = id;
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    });

    // Botón Imprimir Todo el Filtro
    btnImprimirTodoFiltro.addEventListener('click', function () {
        if (pedidosActuales.length === 0) return;

        const params = new URLSearchParams({
            fecha_desde: inputFechaDesde.value,
            fecha_hasta: inputFechaHasta.value,
            tipo_fecha: selectTipoFecha.value,
            search: inputSearch.value.trim(),
        });

        if (IS_ADMIN && selectCliente && selectCliente.value) {
            params.append('id_cliente', selectCliente.value);
        }

        window.open(RUTA_BASE + 'pedidos/imprimir-etiquetas?' + params.toString(), '_blank');
    });

    // Filtros de Fecha Rápida (Hoy, Ayer, Esta Semana, etc.)
    document.querySelectorAll('.btn-quick-date').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.btn-quick-date').forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            const range = this.dataset.range;
            const hoy = new Date();
            const formatear = (d) => d.toISOString().split('T')[0];

            if (range === 'hoy') {
                inputFechaDesde.value = formatear(hoy);
                inputFechaHasta.value = formatear(hoy);
            } else if (range === 'ayer') {
                const ayer = new Date();
                ayer.setDate(hoy.getDate() - 1);
                inputFechaDesde.value = formatear(ayer);
                inputFechaHasta.value = formatear(ayer);
            } else if (range === 'semana') {
                const primerDiaSemana = new Date(hoy);
                primerDiaSemana.setDate(hoy.getDate() - (hoy.getDay() === 0 ? 6 : hoy.getDay() - 1));
                inputFechaDesde.value = formatear(primerDiaSemana);
                inputFechaHasta.value = formatear(hoy);
            } else if (range === 'mes') {
                const primerDiaMes = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
                inputFechaDesde.value = formatear(primerDiaMes);
                inputFechaHasta.value = formatear(hoy);
            } else if (range === 'todos') {
                inputFechaDesde.value = '';
                inputFechaHasta.value = '';
            }

            cargarPedidosBodega();
        });
    });

    // Cambios en filtros
    inputFechaDesde.addEventListener('change', cargarPedidosBodega);
    inputFechaHasta.addEventListener('change', cargarPedidosBodega);
    selectTipoFecha.addEventListener('change', cargarPedidosBodega);
    if (selectCliente) selectCliente.addEventListener('change', cargarPedidosBodega);
    
    // Búsqueda con enter (pistola lectora de código de barras)
    inputSearch.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            cargarPedidosBodega();
        }
    });
    btnBuscar.addEventListener('click', cargarPedidosBodega);
    btnRecargar.addEventListener('click', cargarPedidosBodega);

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.toString().replace(/[&<>"']/g, m => map[m]);
    }

    // Carga inicial
    cargarPedidosBodega();
});
</script>

<?php include("vista/includes/footer.php"); ?>
