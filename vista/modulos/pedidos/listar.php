<?php
$usaDataTables = true;
require_once "utils/authorization.php";
require_role([ROL_NOMBRE_ADMIN, ROL_NOMBRE_PROVEEDOR, ROL_NOMBRE_REPARTIDOR]);

include("vista/includes/header_materialize.php");
?>

<?php
// Mostrar errores de importación guardados en sesión (si existen)
if (session_status() === PHP_SESSION_NONE) {
    if (function_exists('start_secure_session')) start_secure_session();
}
if (!empty($_SESSION['import_errors'])): ?>
    <div class="row mt-2">
        <div class="col-12">
            <div class="alert alert-danger">
                <h5>Errores en la importación (<?= count($_SESSION['import_errors']) ?>)</h5>
                <pre id="importErrorsPre" style="white-space:pre-wrap; text-align:left;"><?= htmlspecialchars(implode("\n", $_SESSION['import_errors'])) ?></pre>
                <button id="downloadErrorsBtn" class="btn btn-sm btn-outline-secondary mt-2">Descargar errores</button>
            </div>
        </div>
    </div>
<?php
    // Limpiar errores de sesión después de mostrarlos
    unset($_SESSION['import_errors']);
endif;
?>







<!-- Modal de Importación CSV -->
<div class="modal fade" id="modalImportCSV" tabindex="-1" aria-labelledby="modalImportCSVLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="modalImportCSVLabel">
                    <i class="bi bi-file-earmark-arrow-up"></i> Importar Pedidos desde CSV
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Formulario de importación -->
                <form id="formImportCSV" action="<?= RUTA_URL ?>pedidos/importar" method="POST" enctype="multipart/form-data">
                    
                    <!-- Selector de archivo -->
                    <div class="mb-3">
                        <label for="csv_file" class="form-label fw-bold">Seleccionar archivo CSV</label>
                        <input type="file" name="csv_file" id="csv_file" accept=".csv,.txt" class="form-control" required>
                        <div class="form-text">Formatos aceptados: .csv, .txt (máximo 10MB)</div>
                    </div>

                    <!-- Opciones Avanzadas (Colapsable) -->
                    <div class="accordion mb-3" id="accordionAdvanced">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingAdvanced">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdvanced" aria-expanded="false" aria-controls="collapseAdvanced">
                                    <i class="bi bi-gear me-2"></i> Opciones Avanzadas
                                </button>
                            </h2>
                            <div id="collapseAdvanced" class="accordion-collapse collapse" aria-labelledby="headingAdvanced" data-bs-parent="#accordionAdvanced">
                                <div class="accordion-body">
                                    <div class="row">
                                        <!-- Valores por defecto -->
                                        <div class="col-md-6 mb-3">
                                            <label for="default_estado" class="form-label">Estado por defecto</label>
                                            <select name="default_estado" id="default_estado" class="form-select select2-searchable" data-placeholder="Seleccionar estado...">
                                                <option value="">-- No especificar --</option>
                                                <?php
                                                $ctrl = new PedidosController();
                                                $estados = $ctrl->obtenerEstados();
                                                foreach ($estados as $e):
                                                ?>
                                                    <option value="<?= $e['id'] ?>" <?= $e['id'] == 1 ? 'selected' : '' ?>><?= htmlspecialchars($e['nombre_estado']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="form-text">Se aplicará si la fila no especifica estado</div>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="default_proveedor" class="form-label">Proveedor por defecto</label>
                                            <select name="default_proveedor" id="default_proveedor" class="form-select select2-searchable" data-placeholder="Buscar proveedor...">
                                                <option value="">-- No especificar --</option>
                                                <?php
                                                $proveedores = $ctrl->obtenerProveedores();
                                                foreach ($proveedores as $p):
                                                ?>
                                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="default_moneda" class="form-label">Moneda por defecto</label>
                                            <select name="default_moneda" id="default_moneda" class="form-select select2-searchable" data-placeholder="Seleccionar moneda...">
                                                <option value="">-- No especificar --</option>
                                                <?php
                                                $monedas = $ctrl->obtenerMonedas();
                                                foreach ($monedas as $m):
                                                ?>
                                                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nombre']) ?> (<?= $m['codigo'] ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="default_vendedor" class="form-label">Vendedor/Repartidor por defecto</label>
                                            <select name="default_vendedor" id="default_vendedor" class="form-select select2-searchable" data-placeholder="Buscar repartidor...">
                                                <option value="">-- No especificar --</option>
                                                <?php
                                                $vendedores = $ctrl->obtenerRepartidores();
                                                foreach ($vendedores as $v):
                                                ?>
                                                    <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['nombre']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <!-- Opciones adicionales -->
                                        <div class="col-12">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="auto_create_products" name="auto_create_products" value="1" checked>
                                                <label class="form-check-label" for="auto_create_products">
                                                    Crear productos automáticamente si no existen
                                                </label>
                                                <div class="form-text">Si desactivas esta opción, las filas con productos inexistentes serán rechazadas</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Barra de progreso -->
                    <div id="uploadProgress" class="progress mt-2 d-none" style="height: 24px;">
                        <div id="uploadProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;">0%</div>
                    </div>
                    <div id="uploadStatus" class="small text-muted mt-1 d-none"></div>

                    <!-- Documentación de campos al estilo API -->
                    <div class="mt-3 border rounded overflow-hidden" style="font-size:0.82rem;">
                        <!-- Header -->
                        <div class="d-flex align-items-center justify-content-between px-3 py-2 text-white" style="background:linear-gradient(135deg,#667eea,#764ba2)">
                            <span class="fw-bold"><i class="bi bi-table me-1"></i> Referencia de Campos CSV</span>
                            <a href="<?= RUTA_URL ?>Pedidos/referencia" target="_blank" class="text-white small opacity-75">
                                <i class="bi bi-box-arrow-up-right me-1"></i>Ver IDs disponibles
                            </a>
                        </div>

                        <div class="p-3">
                            <!-- Tabla de campos -->
                            <table class="table table-sm table-bordered mb-2" style="font-size:0.8rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th style="min-width:130px">Campo CSV</th>
                                        <th>Tipo</th>
                                        <th>Descripción / Valores aceptados</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- REQUERIDOS (ESTRICTOS) -->
                                    <tr class="table-danger bg-opacity-25">
                                        <td><code>numero_orden</code>&nbsp;<span class="badge bg-danger">REQ</span></td>
                                        <td class="text-muted">entero</td>
                                        <td>ID externo del pedido. Único por cliente.</td>
                                    </tr>
                                    <tr class="table-danger bg-opacity-25">
                                        <td><code>destinatario</code>&nbsp;<span class="badge bg-danger">REQ</span></td>
                                        <td class="text-muted">texto</td>
                                        <td>Nombre completo del destinatario.</td>
                                    </tr>
                                    <tr class="table-danger bg-opacity-25">
                                        <td><code>id_producto</code>&nbsp;<span class="badge bg-danger">REQ</span></td>
                                        <td class="text-muted">entero</td>
                                        <td>ID del producto (ver Referencia).</td>
                                    </tr>
                                    <tr class="table-danger bg-opacity-25">
                                        <td><code>id_cliente</code>&nbsp;<span class="badge bg-danger">REQ</span></td>
                                        <td class="text-muted">entero</td>
                                        <td>ID del cliente dueño del pedido. Se auto-completa en la plantilla.</td>
                                    </tr>
                                    <tr class="table-danger bg-opacity-25">
                                        <td><code>id_proveedor</code>&nbsp;<span class="badge bg-danger">REQ</span></td>
                                        <td class="text-muted">entero</td>
                                        <td>ID del proveedor de mensajería asignado.</td>
                                    </tr>
                                    <tr class="table-danger bg-opacity-25">
                                        <td><code>telefono</code>&nbsp;<span class="badge bg-danger">REQ</span></td>
                                        <td class="text-muted">texto</td>
                                        <td>Teléfono de contacto (mínimo 7 dígitos).</td>
                                    </tr>
                                    <tr class="table-danger bg-opacity-25">
                                        <td><code>direccion</code>&nbsp;<span class="badge bg-danger">REQ</span></td>
                                        <td class="text-muted">texto</td>
                                        <td>Dirección completa de entrega (mínimo 5 caracteres).</td>
                                    </tr>
                                    <tr class="table-danger bg-opacity-25">
                                        <td><code>comentario</code>&nbsp;<span class="badge bg-danger">REQ</span></td>
                                        <td class="text-muted">texto</td>
                                        <td>Notas de entrega obligatorias.</td>
                                    </tr>
                                    <tr class="table-danger bg-opacity-25">
                                        <td><code>precio_total_local</code>&nbsp;<span class="badge bg-danger">REQ</span></td>
                                        <td class="text-muted">decimal</td>
                                        <td>Precio total local (debe ser mayor a 0).</td>
                                    </tr>
                                    <tr class="table-danger bg-opacity-25">
                                        <td><code>es_combo</code>&nbsp;<span class="badge bg-danger">REQ</span></td>
                                        <td class="text-muted">entero</td>
                                        <td>1 si es combo, 0 si es estándar.</td>
                                    </tr>
                                    <tr class="table-info bg-opacity-25">
                                        <td><code>codigo_postal</code></td>
                                        <td class="text-muted">texto</td>
                                        <td>Código postal de la zona de entrega.</td>
                                    </tr>
                                    <tr class="table-info bg-opacity-25">
                                        <td><code>cantidad</code></td>
                                        <td class="text-muted">entero</td>
                                        <td>Cantidad del producto solicitado (default 1).</td>
                                    </tr>
                                    <tr class="table-info bg-opacity-25">
                                        <td><code>id_estado</code></td>
                                        <td class="text-muted">entero</td>
                                        <td>ID del estado inicial (1=Pendiente, etc).</td>
                                    </tr>
                                    <tr class="table-info bg-opacity-25">
                                        <td><code>zona</code></td>
                                        <td class="text-muted">texto</td>
                                        <td>Nombre de la zona de entrega.</td>
                                    </tr>
                                </tbody>
                            </table>

                            <!-- Ejemplo mínimo (Campos Estrictos) -->
                            <div class="bg-dark rounded px-3 py-2" style="font-family:monospace;font-size:0.75rem;color:#e8e8e8;overflow-x:auto;white-space:nowrap;">
                                <span class="text-success"># Ejemplo con los campos principales (Incluido en la descarga):</span><br>
                                <span class="text-warning">numero_orden</span>,<span class="text-warning">destinatario</span>,<span class="text-warning">id_producto</span>,<span class="text-warning">id_cliente</span>,<span class="text-warning">id_proveedor</span>,<span class="text-warning">telefono</span>,<span class="text-warning">direccion</span>,<span class="text-warning">comentario</span>,<span class="text-warning">precio_total_local</span>,<span class="text-warning">es_combo</span>,<span class="text-warning">codigo_postal</span>,<span class="text-warning">cantidad</span>,<span class="text-warning">id_estado</span>,<span class="text-warning">zona</span><br>
                                1001,Juan Pérez,1,<?= $_SESSION['user_id'] ?? '7' ?>,1,88112233,Colinas C-14,Entregar hoy,150.00,0,10000,1,1,Norte
                            </div>

                            <div class="d-flex gap-2 mt-2 flex-wrap align-items-center">
                                <span class="text-muted" style="font-size:0.75rem;"><i class="bi bi-lightbulb me-1"></i>Descarga la plantilla oficial (Incluye ejemplos y tu ID):</span>
                                <a href="<?= RUTA_URL ?>public/pedidos_template.php" class="btn btn-xs btn-outline-success btn-sm py-1 px-3" style="font-size:0.8rem;" download>
                                    <i class="bi bi-file-earmark-arrow-down-fill me-1"></i>Descargar Plantilla CSV (14 Campos)
                                </a>
                                <span class="badge bg-warning text-dark ms-1"><i class="bi bi-exclamation-triangle me-1"></i>Usa Vista Previa antes de importar</span>
                            </div>
                        </div>
                    </div>



                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Cancelar
                </button>
                <button type="button" id="btnPreview" class="btn btn-outline-primary">
                    <i class="bi bi-eye"></i> Vista Previa
                </button>
                <button type="submit" form="formImportCSV" id="btnImport" class="btn btn-success">
                    <i class="bi bi-upload"></i> Importar Ahora
                </button>
            </div>
        </div>
    </div>
</div>


<!-- Modal de Vista Previa -->
<div class="modal fade" id="modalPreview" tabindex="-1" aria-labelledby="modalPreviewLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalPreviewLabel">Vista Previa de Importación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Resumen de validación -->
                <div id="previewSummary" class="mb-3"></div>
                
                <!-- Advertencias -->
                <div id="previewWarnings" class="mb-3"></div>
                
                <!-- Errores -->
                <div id="previewErrors" class="mb-3"></div>
                
                <!-- Productos a crear -->
                <div id="previewProductos" class="mb-3"></div>
                
                <!-- Tabla con primeras filas -->
                <div id="previewTable" class="table-responsive"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btnConfirmImport" class="btn btn-primary" disabled>
                    <i class="bi bi-check-circle"></i> Confirmar e Importar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Resultados -->
<div class="modal fade" id="modalResults" tabindex="-1" aria-labelledby="modalResultsLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalResultsLabel">Resultados de Importación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="resultsContent">
                <!-- Se llenará dinámicamente -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>






<?php
// Obtener estadísticas de pedidos
$listarPedidos = new PedidosController();
$estados = $listarPedidos->obtenerEstados();
$pedidos = $listarPedidos->listarPedidosExtendidos();
$totalPedidos = count($pedidos);

// Contar por estado
$pendientes = 0;
$enProceso = 0;
$entregados = 0;
foreach ($pedidos as $p) {
    $estado = strtolower($p['Estado'] ?? '');
    if (strpos($estado, 'pend') !== false || strpos($estado, 'nuevo') !== false) $pendientes++;
    elseif (strpos($estado, 'entreg') !== false) $entregados++;
    else $enProceso++;
}
?>

<style>
.pedidos-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    overflow: hidden;
}
.pedidos-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1.75rem 2rem;
}
.pedidos-header h3 {
    margin: 0;
    font-weight: 600;
}
.stat-mini {
    background: rgba(255,255,255,0.15);
    border-radius: 10px;
    padding: 0.75rem 1rem;
    text-align: center;
    backdrop-filter: blur(10px);
    min-width: 100px;
}
.stat-mini .stat-value {
    font-size: 1.5rem;
    font-weight: 700;
}
.stat-mini .stat-label {
    font-size: 0.75rem;
    opacity: 0.9;
}
.import-toolbar {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 1rem 1.5rem;
    margin-bottom: 1rem;
}
.btn-import {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    border: none;
    color: white;
    padding: 0.6rem 1.25rem;
    border-radius: 10px;
    font-weight: 500;
}
.btn-import:hover {
    color: white;
    box-shadow: 0 4px 15px rgba(17, 153, 142, 0.3);
}
.btn-new-order {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 10px;
    font-weight: 600;
}
.btn-new-order:hover {
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}
#tblPedidos {
    border-collapse: separate;
    border-spacing: 0;
}
#tblPedidos thead th {
    background: #f8f9fa;
    font-weight: 600;
    color: #1a1a2e;
    border-bottom: 2px solid #e9ecef;
    padding: 1rem 0.75rem;
}
#tblPedidos tbody tr {
    transition: all 0.2s ease;
}
#tblPedidos tbody tr:hover {
    background-color: #f8f9ff;
}
#tblPedidos td {
    padding: 0.875rem 0.75rem;
    vertical-align: middle;
}
.btn-action-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.8rem;
    border-radius: 6px;
}
.badge-estado {
    padding: 0.4em 0.8em;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 500;
}
</style>

<div class="container-fluid py-3">
    <!-- Card Principal -->
    <div class="card pedidos-card mb-4">
        <div class="pedidos-header">
            <div class="row align-items-center">
                <div class="col-md-5">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-white bg-opacity-25 rounded-circle p-3">
                            <i class="bi bi-box-seam fs-3"></i>
                        </div>
                        <div>
                            <h3>Gestión de Pedidos</h3>
                            <p class="mb-0 opacity-75">Administra todos los pedidos del sistema</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-7">
                    <div class="d-flex justify-content-end gap-3">
                        <div class="stat-mini">
                            <div class="stat-value"><?= $totalPedidos ?></div>
                            <div class="stat-label">Total</div>
                        </div>
                        <div class="stat-mini">
                            <div class="stat-value"><?= $pendientes ?></div>
                            <div class="stat-label">Pendientes</div>
                        </div>
                        <div class="stat-mini">
                            <div class="stat-value"><?= $enProceso ?></div>
                            <div class="stat-label">En Proceso</div>
                        </div>
                        <div class="stat-mini">
                            <div class="stat-value"><?= $entregados ?></div>
                            <div class="stat-label">Entregados</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card-body p-4">
            <!-- Toolbar de Importación -->
            <div class="import-toolbar d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-import" data-bs-toggle="modal" data-bs-target="#modalImportCSV">
                        <i class="bi bi-file-earmark-arrow-up me-1"></i> Importar CSV
                    </button>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-download me-1"></i> Plantillas
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?= RUTA_URL ?>public/pedidos_template.php?mode=basico" download>
                                <i class="bi bi-file-earmark me-2"></i>Plantilla Básica
                            </a></li>
                            <li><a class="dropdown-item" href="<?= RUTA_URL ?>public/pedidos_template.php?mode=avanzado" download>
                                <i class="bi bi-file-earmark-text me-2"></i>Plantilla Avanzada
                            </a></li>
                            <li><a class="dropdown-item" href="<?= RUTA_URL ?>public/pedidos_template.php?mode=ejemplo" download>
                                <i class="bi bi-file-earmark-check me-2"></i>Con Ejemplos
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= RUTA_URL ?>public/pedidos_export_current.php" download>
                                <i class="bi bi-file-earmark-spreadsheet me-2"></i>Exportar Actuales
                            </a></li>
                        </ul>
                    </div>
                    <a href="<?= RUTA_URL ?>Pedidos/referencia" class="btn btn-outline-info">
                        <i class="bi bi-book me-1"></i> Referencia
                    </a>
                </div>
                <a href="<?= RUTA_URL ?>pedidos/crearPedido" class="btn btn-new-order">
                    <i class="bi bi-plus-circle me-2"></i>Nuevo Pedido
                </a>
            </div>

    <div class="row mt-2 caja">
    <div class="col-sm-12">
        

        <div class="table-responsive">
            <table id="tblPedidos" class="table table-striped">
                <thead>
                    <tr>
                        <th>Número de Orden</th>
                        <th>Cliente</th>
                        <th>Comentario</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $listarPedidos = new PedidosController();
                    $estados = $listarPedidos->obtenerEstados(); // Obtener lista de estados
                    $pedidos = $listarPedidos->listarPedidosExtendidos();

                    // Determinar si el usuario tiene permiso para cambiar el estado.
                    // Los Proveedores solo lectura, pero Repartidores y Clientes (Logística) pueden cambiarlo.
                    $rolesNombres = $_SESSION['roles_nombres'] ?? [];
                    $isAdmin = in_array(ROL_NOMBRE_ADMIN, $rolesNombres, true);
                    $isRepartidor = in_array(ROL_NOMBRE_REPARTIDOR, $rolesNombres, true);
                    // FIX: Check for 'Cliente' string explicitly as config constant might be swapped/confusing
                    $isCliente = in_array('Cliente', $rolesNombres, true) || in_array('cliente', $rolesNombres, true);
                    
                    // Solo deshabilitar si es Proveedor y NO es nada más (ni Admin ni Cliente ni Repartidor)
                    $isProveedorOnly = in_array(ROL_NOMBRE_PROVEEDOR, $rolesNombres, true) && !$isAdmin && !$isRepartidor && !$isCliente;
                    $disabledAttr = $isProveedorOnly ? 'disabled' : '';

                    // IDs de estados a los que un Cliente puede cambiar (Reprogramado=4, Devuelto=7)
                    $ESTADOS_PERMITIDOS_CLIENTE = [4, 7];
                    // ¿Es cliente sin privilegios de admin? Aplica restricción de opciones
                    $isClienteRestricto = $isCliente && !$isAdmin;

                    foreach ($pedidos as $pedido): ?>
                        <tr data-id="<?= $pedido['ID_Pedido'] ?>">
                            <td><?= htmlspecialchars($pedido['Numero_Orden']) ?></td>
                            <td><?= htmlspecialchars($pedido['Cliente']) ?></td>
                            <td><?= htmlspecialchars($pedido['Comentario']) ?></td>

                            <!-- Celda Editable para Estado -->
                            <td class="editable" data-campo="estado">
                                 <?php
                                    // Calcular el ID del estado actual del pedido para data-estado
                                    $idEstadoActualPedido = '';
                                    foreach ($estados as $_e) {
                                        if ($_e['nombre_estado'] == $pedido['Estado']) {
                                            $idEstadoActualPedido = $_e['id'];
                                            break;
                                        }
                                    }
                                 ?>
                                 <select class="form-select actualizarEstado"
                                         data-id="<?= $pedido['ID_Pedido']; ?>"
                                         data-estado="<?= htmlspecialchars($idEstadoActualPedido); ?>"
                                         <?= $disabledAttr ?>>
                                    <?php foreach ($estados as $estado):
                                        $esEstadoActual = ($pedido['Estado'] == $estado['nombre_estado']);
                                        $esPermitidoCliente = in_array((int)$estado['id'], $ESTADOS_PERMITIDOS_CLIENTE, true);
                                        // Para cliente: deshabilitar opciones que NO son el estado actual NI los permitidos
                                        $disabledOpt = ($isClienteRestricto && !$esEstadoActual && !$esPermitidoCliente) ? 'disabled' : '';
                                    ?>
                                        <option value="<?= $estado['id']; ?>"
                                                <?= $esEstadoActual ? 'selected' : ''; ?>
                                                <?= $disabledOpt; ?>>
                                            <?= htmlspecialchars($estado['nombre_estado']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($isClienteRestricto): ?>
                                    <small class="text-muted d-block mt-1" style="font-size:0.75rem;">
                                        <i class="bi bi-info-circle"></i>
                                        Solo puedes cambiar a <strong>Reprogramado</strong> o <strong>Devuelto</strong>.
                                    </small>
                                <?php endif; ?>
                            </td>

                            <td>
                                <!-- <a href="<?= RUTA_URL ?>pedidos/ver/<?php echo $pedido['ID_Pedido']; ?>" class="btn btn-primary btn-sm">Ver</a> -->
                                <?php if ($isCliente && !$isAdmin): ?>
                                    <a href="<?= RUTA_URL ?>pedidos/ver/<?php echo $pedido['ID_Pedido']; ?>" class="btn btn-info btn-sm text-white"><i class="bi bi-eye"></i> Ver Detalle</a>
                                <?php else: ?>
                                    <a href="<?= RUTA_URL ?>pedidos/editar/<?php echo $pedido['ID_Pedido']; ?>" class="btn btn-warning btn-sm">Editar</a>
                                <?php endif; ?>
                                

                                <?php if (!empty($pedido['latitud']) && !empty($pedido['longitud'])): ?>
                                    <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $pedido['latitud'] ?>,<?= $pedido['longitud'] ?>&travelmode=driving"
                                        target="_blank" class="btn btn-success btn-sm">
                                        <i class="bi bi-geo-alt"></i> Ir a Ruta
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-sm" disabled>
                                        <i class="bi bi-geo-alt"></i> Sin Coordenadas
                                    </button>
                                <?php endif; ?>


                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

        </div><!-- card-body -->
    </div><!-- card pedidos-card -->
</div><!-- container-fluid -->

<?php include("vista/includes/footer_materialize.php"); ?>



<script>
    $(document).ready(function() {
    $('#tblPedidos').DataTable({
        responsive: true, // Activa la capacidad responsive
        dom: 'Bfrtip', // Controles de exportación
        buttons: [
            'excel', 'pdf', 'print'
        ],
        order: [
            [1, 'asc']
        ],
        language: {
            search: "Buscar por Número de Orden o Cliente:",
            lengthMenu: "Mostrar _MENU_ registros por página",
            zeroRecords: "No se encontraron resultados",
            info: "Mostrando página _PAGE_ de _PAGES_",
            infoEmpty: "No hay registros disponibles",
            infoFiltered: "(filtrado de _MAX_ registros totales)",
            paginate: {
                first: "Primero",
                last: "Último",
                next: "Siguiente",
                previous: "Anterior"
            }
        }
    });

});

</script>

<script>
    // Estados a los que el rol Cliente puede cambiar (IDs de BD: 4=Reprogramado, 7=Devuelto)
    const ESTADOS_PERMITIDOS_CLIENTE = <?= json_encode($ESTADOS_PERMITIDOS_CLIENTE ?? []) ?>;
    const IS_CLIENTE_RESTRICTO = <?= ($isClienteRestricto ?? false) ? 'true' : 'false' ?>;

    $(document).ready(function() {
        $(".actualizarEstado").change(function() {
            let select = $(this);
            let idPedido = select.data("id");
            let nuevoEstado = parseInt(select.val(), 10);
            let estadoAnterior = select.data("estado") || select.find("option[selected]").val();
            let nombreEstado = select.find("option:selected").text().trim();

            // Guard: si es cliente y el estado destino no está en la lista permitida, revertir
            if (IS_CLIENTE_RESTRICTO && !ESTADOS_PERMITIDOS_CLIENTE.includes(nuevoEstado)) {
                // Revertir al estado anterior (o al option que tenía el atributo selected en el HTML)
                let valorOriginal = estadoAnterior || select.find('option[selected]').val();
                select.val(valorOriginal);
                Swal.fire({
                    title: 'Acción no permitida',
                    text: 'Solo puedes cambiar el estado a Reprogramado o Devuelto.',
                    icon: 'warning',
                    confirmButtonText: 'Entendido'
                });
                return;
            }

            // Preguntar por comentarios/observaciones usando SweetAlert2
            Swal.fire({
                title: 'Procesar Pedido',
                text: '¿Deseas agregar algún comentario para el cambio a: ' + nombreEstado + '?',
                input: 'textarea',
                inputPlaceholder: 'Escribe tus observaciones aquí...',
                showCancelButton: true,
                confirmButtonText: 'Actualizar Estado',
                cancelButtonText: 'Cancelar',
                inputAttributes: {
                    'aria-label': 'Escribe tus observaciones aquí'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    let observaciones = result.value;

                    // Deshabilita el select mientras se procesa
                    select.prop("disabled", true);

                    $.ajax({
                        url: "<?= RUTA_URL ?>cambiarEstados",
                        type: "POST",
                        data: {
                            id_pedido: idPedido,
                            estado: nuevoEstado,
                            observaciones: observaciones
                        },
                        dataType: "json",
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    title: "¡Éxito!",
                                    text: "Estado actualizado correctamente.",
                                    icon: "success",
                                    confirmButtonText: "OK"
                                });
                                select.data("estado", nuevoEstado);
                                select.prop("disabled", false);
                            } else {
                                Swal.fire({
                                    title: "Error",
                                    text: response.message,
                                    icon: "error",
                                    confirmButtonText: "OK"
                                });
                                select.val(estadoAnterior);
                                select.prop("disabled", false);
                            }
                        },
                        error: function(xhr, status, error) {
                            Swal.fire({
                                title: "Error",
                                text: "Hubo un problema al procesar la solicitud.",
                                icon: "error"
                            });
                            select.val(estadoAnterior);
                            select.prop("disabled", false);
                        }
                    });
                } else {
                    // Si cancela, revertir el select al estado anterior
                    select.val(estadoAnterior);
                }
            });
        });
    });
</script>

<!-- JS para manejar CSV import con Vista Previa y Import mejorado -->
<script>
document.addEventListener('DOMContentLoaded', function(){
    const form = document.getElementById('formImportCSV');
    const fileInput = document.getElementById('csv_file');
    const btnPreview = document.getElementById('btnPreview');
    const btnImport = document.getElementById('btnImport');
    const btnConfirmImport = document.getElementById('btnConfirmImport');
    const progress = document.getElementById('uploadProgress');
    const progressBar = document.getElementById('uploadProgressBar');
    const status = document.getElementById('uploadStatus');
    
    if (!form) return;
    
    let previewData = null; // Guardar datos de preview
    
    // ================== PREVIEW FLOW ==================
    btnPreview.addEventListener('click', function(){
        if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Sin archivo',
                text: 'Por favor selecciona un archivo CSV primero',
                confirmButtonText: 'OK'
            });
            return;
        }
        
        // Preparar FormData con preview=1
        const fd = new FormData(form);
        fd.append('preview', '1');
        
        // Mostrar loading
        Swal.fire({
            title: 'Validando CSV...',
            text: 'Analizando el archivo',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Enviar petición AJAX
        fetch(form.getAttribute('action'), {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: fd,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            Swal.close();
            
            if (!data.success) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error al analizar CSV',
                    text: data.message || 'Error desconocido',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            // Guardar datos de preview
            previewData = data;
            
            // Llenar modal con resumen
            mostrarPreview(data);
            
            // Abrir modal
            const modalPreview = new bootstrap.Modal(document.getElementById('modalPreview'));
            modalPreview.show();
        })
        .catch(error => {
            Swal.close();
            console.error('Preview error:', error);
            const actionUrl = form.getAttribute('action');
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                html: `No se pudo comunicar con el servidor.<br>
                       <small class="text-muted">URL: ${actionUrl}</small><br>
                       <small class="text-danger">Detalle: ${error.message}</small>`,
                confirmButtonText: 'OK'
            });
        });
    });
    
    // ================== CONFIRMAR DESDE PREVIEW ==================
    btnConfirmImport.addEventListener('click', function(){
        // Cerrar modal preview
        bootstrap.Modal.getInstance(document.getElementById('modalPreview')).hide();
        
        // Ejecutar importación
        ejecutarImportacion(false); // preview=false
    });
    
    // ================== IMPORT DIRECTO ==================
    form.addEventListener('submit', function(e){
        e.preventDefault();
        
        if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Sin archivo',
                text: 'Por favor selecciona un archivo CSV',
                confirmButtonText: 'OK'
            });
            return;
        }
        
        ejecutarImportacion(false);
    });
    
    // ================== FUNCIÓN EJECUTAR IMPORTACIÓN ==================
    function ejecutarImportacion(isPreview)  {
        btnImport.disabled = true;
        btnPreview.disabled = true;
        
        const fd = new FormData(form);
        if (isPreview) {
            fd.append('preview', '1');
        }
        
        const xhr = new XMLHttpRequest();
        xhr.open('POST', form.getAttribute('action'), true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        
        // Progreso de subida
        xhr.upload.addEventListener('progress', function(ev){
            if (ev.lengthComputable) {
                const percent = Math.round((ev.loaded / ev.total) * 100);
                progress.classList.remove('d-none');
                progressBar.style.width = percent + '%';
                progressBar.textContent = percent + '%';
                status.classList.remove('d-none');
                status.textContent = 'Subiendo y procesando... ' + percent + '%';
            }
        });
        
        xhr.onreadystatechange = function(){
            if (xhr.readyState === 4) {
                btnImport.disabled = false;
                btnPreview.disabled = false;
                progress.classList.add('d-none');
                status.classList.add('d-none');
                
                let json = null;
                try {
                    json = JSON.parse(xhr.responseText);
                } catch (e) {
                    console.error('No JSON response', xhr.responseText);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de respuesta',
                        html: `El servidor devolvió una respuesta inválida.<br>
                               <small class="text-danger">Status: ${xhr.status}</small><br>
                               <small class="text-muted">Ver consola para detalles</small>`,
                        confirmButtonText: 'OK'
                    });
                    return;
                }
                
                if (xhr.status >= 200 && xhr.status < 400) {
                    if (json && json.success) {
                        mostrarResultados(json);
                    } else if (json && !json.success) {
                        const errorList = json.errors_list || json.errores || [];
                        let errorHtml = '<p>' + (json.message || 'Error desconocido') + '</p>';
                        
                        if (errorList.length > 0) {
                            errorHtml += '<div class="alert alert-danger text-start small mt-2" style="max-height:200px;overflow:auto;"><ul class="mb-0 ps-3">';
                            errorList.slice(0, 10).forEach(err => {
                                errorHtml += '<li>' + err + '</li>';
                            });
                            if (errorList.length > 10) {
                                errorHtml += '<li>... y ' + (errorList.length - 10) + ' más</li>';
                            }
                            errorHtml += '</ul></div>';
                        }
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'Error en la importación',
                            html: errorHtml,
                            confirmButtonText: 'OK'
                        });
                    } else {
                        // Fallback: recargar página
                        window.location.reload();
                    }
                } else {
                    console.error('Import failed:', xhr.status, xhr.responseText);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error del servidor',
                        html: '<p>Código: ' + xhr.status + '</p>' +
                              '<pre class="small">' + (xhr.responseText || 'Sin respuesta').substring(0, 500) + '</pre>',
                        confirmButtonText: 'OK'
                    });
                }
            }
        };
        
        xhr.open('POST', form.getAttribute('action'), true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.withCredentials = true;
        xhr.send(fd);
    }
    
    // ================== MOSTRAR PREVIEW ==================
    function mostrarPreview(data) {
        const resumen = data.resumen || {};
        
        // Resumen estadístico
        let summaryHtml = `
            <div class="alert alert-${resumen.puede_importar ? 'success' : 'danger'}">
                <h6 class="alert-heading mb-3">
                    <i class="bi bi-${resumen.puede_importar ? 'check-circle' : 'x-circle'}"></i>
                    Resumen de Validación
                </h6>
                <div class="row text-center">
                    <div class="col">
                        <div class="fs-4 fw-bold">${resumen.total || 0}</div>
                        <div class="small text-muted">Total Filas</div>
                    </div>
                    <div class="col">
                        <div class="fs-4 fw-bold text-success">${resumen.validas || 0}</div>
                        <div class="small text-muted">Válidas</div>
                    </div>
                    <div class="col">
                        <div class="fs-4 fw-bold text-danger">${resumen.con_errores || 0}</div>
                        <div class="small text-muted">Con Errores</div>
                    </div>
                    <div class="col">
                        <div class="fs-4 fw-bold text-warning">${resumen.con_advertencias || 0}</div>
                        <div class="small text-muted">Con Advertencias</div>
                    </div>
                </div>
            </div>
        `;
        document.getElementById('previewSummary').innerHTML = summaryHtml;
        
        // Advertencias
        if (resumen.advertencias && resumen.advertencias.length > 0) {
            let warningsHtml = `
                <div class="alert alert-warning">
                    <h6 class="alert-heading"><i class="bi bi-exclamation-triangle"></i> Advertencias (${resumen.advertencias.length})</h6>
                    <ul class="mb-0 small" style="max-height:200px;overflow:auto;">
                        ${resumen.advertencias.slice(0, 10).map(w => '<li>' + w + '</li>').join('')}
                    </ul>
                    ${resumen.advertencias.length > 10 ? '<p class="mb-0 small mt-2">...y ' + (resumen.advertencias.length - 10) + ' más</p>' : ''}
                </div>
            `;
            document.getElementById('previewWarnings').innerHTML = warningsHtml;
        } else {
            document.getElementById('previewWarnings').innerHTML = '';
        }
        
        // Errores
        if (resumen.errores && resumen.errores.length > 0) {
            let errorsHtml = `
                <div class="alert alert-danger">
                    <h6 class="alert-heading"><i class="bi bi-x-circle"></i> Errores (${resumen.errores.length})</h6>
                    <pre class="mb-0 small" style="max-height:200px;overflow:auto;">${resumen.errores.slice(0, 15).join('\n')}</pre>
                    ${resumen.errores.length > 15 ? '<p class="mb-0 small mt-2">...y ' + (resumen.errores.length - 15) + ' más errores</p>' : ''}
                </div>
            `;
            document.getElementById('previewErrors').innerHTML = errorsHtml;
        } else {
            document.getElementById('previewErrors').innerHTML = '';
        }
        
        // Vista previa de primeras filas
        if (data.primeras_filas && data.primeras_filas.length > 0) {
            const cols = Object.keys(data.primeras_filas[0]);
            let tableHtml = `
                <h6>Primeras ${data.primeras_filas.length} filas del CSV</h6>
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr>${cols.map(c => '<th class="small">' + c + '</th>').join('')}</tr>
                    </thead>
                    <tbody>
                        ${data.primeras_filas.map(r => 
                            '<tr>' + cols.map(c => '<td class="small">' + (r[c] || '') + '</td>').join('') + '</tr>'
                        ).join('')}
                    </tbody>
                </table>
            `;
            document.getElementById('previewTable').innerHTML = tableHtml;
        }
        
        // Habilitar/deshabilitar botón de confirmar
        btnConfirmImport.disabled = !resumen.puede_importar;
    }
    
    // ================== MOSTRAR RESULTADOS ==================
    function mostrarResultados(data) {
        const stats = data.stats || {};
        const estado = data.estado || 'completado';
        const productosCreados = data.productos_creados || [];
        
        let iconType = estado === 'completado' ? 'success' : (estado === 'parcial' ? 'warning' : 'error');
        
        let html = `
            <div class="alert alert-${iconType === 'success' ? 'success' : (iconType === 'warning' ? 'warning' : 'danger')}">
                <h5 class="alert-heading">
                    <i class="bi bi-${iconType === 'success' ? 'check-circle' : (iconType === 'warning' ? 'exclamation-triangle' : 'x-circle')}"></i>
                    ${data.message}
                </h5>
            </div>
            
            <div class="row text-center mb-3">
                <div class="col">
                    <div class="card">
                        <div class="card-body">
                            <div class="fs-3 fw-bold">${stats.total || 0}</div>
                            <div class="small text-muted">Total Filas</div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card border-success">
                        <div class="card-body">
                            <div class="fs-3 fw-bold text-success">${stats.inserted || 0}</div>
                            <div class="small text-muted">Importadas</div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card border-danger">
                        <div class="card-body">
                            <div class="fs-3 fw-bold text-danger">${stats.errors || 0}</div>
                            <div class="small text-muted">Errores</div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card border-warning">
                        <div class="card-body">
                            <div class="fs-3 fw-bold text-warning">${stats.warnings || 0}</div>
                            <div class="small text-muted">Advertencias</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <p class="small text-muted">Tiempo de procesamiento: ${stats.tiempo_segundos || 0} segundos</p>
        `;
        
        // Productos creados
        if (productosCreados.length > 0) {
            html += `
                <div class="alert alert-info mt-3">
                    <h6 class="alert-heading"><i class="bi bi-plus-circle"></i> Productos nuevos creados (${productosCreados.length})</h6>
                    <ul class="mb-0 small">
                        ${productosCreados.map(p => '<li>' + p + '</li>').join('')}
                    </ul>
                </div>
            `;
        }
        
        // Lista de errores (si hay)
        if (data.errors_list && data.errors_list.length > 0) {
            html += `
                <div class="alert alert-danger mt-3">
                    <h6 class="alert-heading"><i class="bi bi-exclamation-triangle"></i> Errores detectados</h6>
                    <ul class="mb-0 small">
                        ${data.errors_list.map(e => '<li>' + e + '</li>').join('')}
                    </ul>
                </div>
            `;
        }
        
        // Enlace a CSV de errores
        if (data.error_file_url) {
            html += `
                <div class="mt-3">
                    <a href="<?= RUTA_URL ?>${data.error_file_url}" class="btn btn-danger btn-sm" download>
                        <i class="bi bi-download"></i> Descargar CSV con Filas Erróneas
                    </a>
                </div>
            `;
        }
        
        document.getElementById('resultsContent').innerHTML = html;
        
        // Abrir modal de resultados
        const modalResults = new bootstrap.Modal(document.getElementById('modalResults'));
        modalResults.show();
        
        // Al cerrar modal, recargar página
        document.getElementById('modalResults').addEventListener('hidden.bs.modal', function () {
            window.location.reload();
        }, { once: true });
    }
});
</script>

<script>
// Descargar errores mostrados en la página (legacy)
document.addEventListener('DOMContentLoaded', function(){
    var btn = document.getElementById('downloadErrorsBtn');
    if (!btn) return;
    btn.addEventListener('click', function(){
        var pre = document.getElementById('importErrorsPre');
        if (!pre) return;
        var text = pre.textContent || pre.innerText;
        var blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'import_errors.txt';
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
    });
});
</script>