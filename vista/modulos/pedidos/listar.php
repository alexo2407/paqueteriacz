<?php
$usaDataTables = true;
require_once "utils/authorization.php";
require_role([ROL_NOMBRE_ADMIN, ROL_NOMBRE_PROVEEDOR, ROL_NOMBRE_REPARTIDOR]);

include("vista/includes/header.php");
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

                                        <!-- Es Combo por defecto -->
                                        <div class="col-12 mb-1">
                                            <div class="border rounded-2 px-3 py-2 d-flex align-items-center gap-3" style="background:rgba(99,102,241,.06);">
                                                <div class="form-check form-switch mb-0">
                                                    <input class="form-check-input" type="checkbox" role="switch"
                                                           id="default_es_combo" name="default_es_combo" value="1">
                                                    <label class="form-check-label fw-semibold" for="default_es_combo">
                                                        Es Combo por defecto
                                                    </label>
                                                </div>
                                                <small class="text-muted">
                                                    <i class="bi bi-info-circle me-1"></i>
                                                    Activa <code>es_combo&nbsp;=&nbsp;1</code> en filas que no traigan ese valor en el CSV.
                                                </small>
                                            </div>
                                        </div>

                                        <!-- Nota: productos inexistentes generan error de validación y la fila es rechazada -->
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
                        <div class="d-flex align-items-center justify-content-between px-3 py-2 text-white" style="background:linear-gradient(135deg,#0B4EA2,#061C4C)">
                            <span class="fw-bold"><i class="bi bi-file-earmark-excel me-1"></i> Referencia de Campos XLSX — Orden de Columnas</span>
                            <a href="<?= RUTA_URL ?>Pedidos/referencia" target="_blank" class="text-white small opacity-75">
                                <i class="bi bi-box-arrow-up-right me-1"></i>Ver IDs disponibles
                            </a>
                        </div>

                        <div class="p-3">
                            <!-- Leyenda de colores -->
                            <div class="d-flex gap-3 mb-2 flex-wrap" style="font-size:0.75rem;">
                                <span><span class="badge bg-danger me-1">REQ</span>Requerido</span>
                                <span><span class="badge bg-warning text-dark me-1">OPT</span>Opcional</span>
                                <span><span class="badge me-1" style="background:#1D6A3A;color:#fff;">PROD</span>Multi-producto</span>
                            </div>

                            <!-- Tabla de campos -->
                            <table class="table table-sm table-bordered mb-2" style="font-size:0.78rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th style="min-width:40px;">Col.</th>
                                        <th style="min-width:145px;">Columna XLSX</th>
                                        <th>Tipo</th>
                                        <th>Descripción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="table-danger bg-opacity-25">
                                        <td class="text-muted text-center fw-bold">A</td>
                                        <td><code>numero_orden</code> <span class="badge bg-danger">REQ</span></td>
                                        <td class="text-muted">entero</td>
                                        <td>ID externo del pedido. Único por cliente.</td>
                                    </tr>
                                    <tr class="table-warning bg-opacity-25">
                                        <td class="text-muted text-center fw-bold">B</td>
                                        <td><code>fecha_ingreso</code> <span class="badge bg-warning text-dark">OPT</span></td>
                                        <td class="text-muted">fecha</td>
                                        <td>Formato <code>DD/MM/YYYY</code>. Ej: <code>26/05/2025</code></td>
                                    </tr>
                                    <tr class="table-danger bg-opacity-25">
                                        <td class="text-muted text-center fw-bold">C</td>
                                        <td><code>destinatario</code> <span class="badge bg-danger">REQ</span></td>
                                        <td class="text-muted">texto</td>
                                        <td>Nombre completo del destinatario.</td>
                                    </tr>
                                    <tr class="table-danger bg-opacity-25">
                                        <td class="text-muted text-center fw-bold">D</td>
                                        <td><code>telefono</code> <span class="badge bg-danger">REQ</span></td>
                                        <td class="text-muted">texto</td>
                                        <td>Con código de país. Ej: <code>50245173646</code></td>
                                    </tr>
                                    <tr class="table-danger bg-opacity-25">
                                        <td class="text-muted text-center fw-bold">E</td>
                                        <td><code>direccion</code> <span class="badge bg-danger">REQ</span></td>
                                        <td class="text-muted">texto</td>
                                        <td>Dirección completa de entrega.</td>
                                    </tr>
                                    <tr class="table-danger bg-opacity-25">
                                        <td class="text-muted text-center fw-bold">F</td>
                                        <td><code>comentario</code> <span class="badge bg-danger">REQ</span></td>
                                        <td class="text-muted">texto</td>
                                        <td>Notas / coordenadas GPS. Ej: <code>14.302022, -90.799585</code></td>
                                    </tr>
                                    <tr class="table-warning bg-opacity-25">
                                        <td class="text-muted text-center fw-bold">G</td>
                                        <td><code>zona</code> <span class="badge bg-warning text-dark">OPT</span></td>
                                        <td class="text-muted">texto</td>
                                        <td>Zona de reparto. Ej: <code>Norte</code></td>
                                    </tr>
                                    <tr class="table-warning bg-opacity-25">
                                        <td class="text-muted text-center fw-bold">H</td>
                                        <td><code>codigo_postal</code> <span class="badge bg-warning text-dark">OPT</span></td>
                                        <td class="text-muted">texto</td>
                                        <td>Ej: <code>GT3155</code></td>
                                    </tr>
                                    <tr class="table-warning bg-opacity-25">
                                        <td class="text-muted text-center fw-bold">I</td>
                                        <td><code>pais</code> <span class="badge bg-warning text-dark">OPT</span></td>
                                        <td class="text-muted">texto</td>
                                        <td>País en texto libre. Ej: <code>Guatemala</code></td>
                                    </tr>
                                    <tr class="table-warning bg-opacity-25">
                                        <td class="text-muted text-center fw-bold">J</td>
                                        <td><code>departamento</code> <span class="badge bg-warning text-dark">OPT</span></td>
                                        <td class="text-muted">texto</td>
                                        <td>Departamento en texto libre.</td>
                                    </tr>
                                    <tr class="table-warning bg-opacity-25">
                                        <td class="text-muted text-center fw-bold">K</td>
                                        <td><code>municipio</code> <span class="badge bg-warning text-dark">OPT</span></td>
                                        <td class="text-muted">texto</td>
                                        <td>Municipio en texto libre.</td>
                                    </tr>
                                    <tr class="table-warning bg-opacity-25">
                                        <td class="text-muted text-center fw-bold">L</td>
                                        <td><code>barrio</code> <span class="badge bg-warning text-dark">OPT</span></td>
                                        <td class="text-muted">texto</td>
                                        <td>Barrio o colonia en texto libre.</td>
                                    </tr>
                                    <tr class="table-warning bg-opacity-25">
                                        <td class="text-muted text-center fw-bold">M</td>
                                        <td><code>entre_calles</code> <span class="badge bg-warning text-dark">OPT</span></td>
                                        <td class="text-muted">texto</td>
                                        <td>Referencia de calles cruzadas.</td>
                                    </tr>
                                    <tr class="table-warning bg-opacity-25">
                                        <td class="text-muted text-center fw-bold">N</td>
                                        <td><code>estado</code> <span class="badge bg-warning text-dark">OPT</span></td>
                                        <td class="text-muted">texto</td>
                                        <td>Nombre del estado. Ej: <code>En ruta o proceso</code></td>
                                    </tr>
                                    <tr class="table-warning bg-opacity-25">
                                        <td class="text-muted text-center fw-bold">O</td>
                                        <td><code>fecha_entrega</code> <span class="badge bg-warning text-dark">OPT</span></td>
                                        <td class="text-muted">fecha</td>
                                        <td>Fecha prometida en <code>DD/MM/YYYY</code>.</td>
                                    </tr>
                                    <tr class="table-danger bg-opacity-25">
                                        <td class="text-muted text-center fw-bold">P</td>
                                        <td><code>precio_total_local</code> <span class="badge bg-danger">REQ</span></td>
                                        <td class="text-muted">decimal</td>
                                        <td>Total en moneda local (mayor a 0). Ej: <code>870</code></td>
                                    </tr>
                                    <tr class="table-warning bg-opacity-25">
                                        <td class="text-muted text-center fw-bold">Q</td>
                                        <td><code>moneda</code> <span class="badge bg-warning text-dark">OPT</span></td>
                                        <td class="text-muted">texto</td>
                                        <td>Código de moneda. Ej: <code>GTQ</code>, <code>USD</code></td>
                                    </tr>
                                    <tr class="table-danger bg-opacity-25">
                                        <td class="text-muted text-center fw-bold">R</td>
                                        <td><code>cliente</code> <span class="badge bg-danger">REQ</span></td>
                                        <td class="text-muted">entero</td>
                                        <td>ID del cliente. La plantilla lo pre-rellena con tu sesión.</td>
                                    </tr>
                                    <tr class="table-danger bg-opacity-25">
                                        <td class="text-muted text-center fw-bold">S</td>
                                        <td><code>id_proveedor</code> <span class="badge bg-danger">REQ</span></td>
                                        <td class="text-muted">entero</td>
                                        <td>ID del proveedor de mensajería.</td>
                                    </tr>
                                    <tr class="table-danger bg-opacity-25">
                                        <td class="text-muted text-center fw-bold">T</td>
                                        <td><code>es_combo</code> <span class="badge bg-danger">REQ</span></td>
                                        <td class="text-muted">0 / 1</td>
                                        <td><code>1</code> = multi-producto &nbsp;|&nbsp; <code>0</code> = un solo producto.</td>
                                    </tr>
                                    <!-- MULTI-PRODUCTO -->
                                    <tr>
                                        <td colspan="4" class="py-1 px-2 fw-bold text-white" style="background:#1D6A3A;font-size:0.74rem;">
                                            <i class="bi bi-boxes me-1"></i> PRODUCTOS — se repiten hasta 5 veces: <code style="color:#a8ffbf;">Producto 1 / Cantidad 1</code> … <code style="color:#a8ffbf;">Producto 5 / Cantidad 5</code>
                                        </td>
                                    </tr>
                                    <tr style="background:rgba(29,106,58,0.07);">
                                        <td class="text-muted text-center fw-bold">U, W…</td>
                                        <td><code>Producto <em>N</em></code> <span class="badge" style="background:#1D6A3A;color:#fff;">PROD</span></td>
                                        <td class="text-muted">texto</td>
                                        <td>Nombre <strong>exacto</strong> del producto (debe existir). Si no se encuentra → fila <strong class="text-danger">rechazada</strong>.<br><small class="text-muted">Ver productos disponibles en "Ver IDs disponibles".</small></td>
                                    </tr>
                                    <tr style="background:rgba(29,106,58,0.07);">
                                        <td class="text-muted text-center fw-bold">V, X…</td>
                                        <td><code>Cantidad <em>N</em></code> <span class="badge bg-warning text-dark">OPT</span></td>
                                        <td class="text-muted">entero</td>
                                        <td>Cantidad del producto N (default <code>1</code>). Vacío = se omite ese producto.</td>
                                    </tr>
                                </tbody>
                            </table>

                            <!-- Ejemplo visual XLSX -->
                            <div class="bg-dark rounded px-3 py-2 mb-2" style="font-family:monospace;font-size:0.71rem;color:#e8e8e8;overflow-x:auto;white-space:nowrap;">
                                <span class="text-success"># Orden de columnas del XLSX (A → AD):</span><br>
                                <span class="text-warning">A:num_orden</span> | <span class="text-info">B:fecha_ing</span> | <span class="text-warning">C:destinatario</span> | <span class="text-warning">D:telefono</span> | <span class="text-warning">E:direccion</span> | <span class="text-warning">F:comentario</span> | <span class="text-info">G:zona</span> | <span class="text-info">H:cod_postal</span> | <span class="text-info">I:pais</span> | <span class="text-info">J:depto</span> | <span class="text-info">K:municipio</span> | <span class="text-info">L:barrio</span> | <span class="text-info">M:entre_calles</span> | <span class="text-info">N:estado</span> | <span class="text-info">O:fecha_ent</span> | <span class="text-warning">P:total</span> | <span class="text-info">Q:moneda</span> | <span class="text-warning">R:cliente</span> | <span class="text-warning">S:proveedor</span> | <span class="text-warning">T:es_combo</span> | <span style="color:#7dff7d;">U:Producto 1</span> | <span style="color:#7dff7d;">V:Cantidad 1</span> | <span style="color:#aaffaa;">W:Producto 2</span> | <span style="color:#aaffaa;">X:Cantidad 2</span> | …<br>
                                <span class="text-secondary">28028424</span> | <span class="text-secondary">26/05/2025</span> | <span class="text-secondary">Juan Pérez</span> | <span class="text-secondary">50245173646</span> | <span class="text-secondary">Zona 5, calle principal…</span> | <span class="text-secondary">14.302022,-90.799585</span> | <span class="text-secondary">Norte</span> | <span class="text-secondary">GT3155</span> | <span class="text-secondary">Guatemala</span> | <span class="text-secondary">Guatemala</span> | <span class="text-secondary">Guatemala</span> | | | <span class="text-secondary">En ruta o proceso</span> | | <span class="text-secondary">870</span> | <span class="text-secondary">GTQ</span> | <span class="text-secondary"><?= $_SESSION['user_id'] ?? '7' ?></span> | <span class="text-secondary">12</span> | <span class="text-secondary">1</span> | <span style="color:#7dff7d;">INMUSTEN</span> | <span style="color:#7dff7d;">2</span> | <span style="color:#aaffaa;">FLEXOSAMINE CAPSULAS</span> | <span style="color:#aaffaa;">1</span>
                            </div>

                            <div class="d-flex gap-2 mt-2 flex-wrap align-items-center">
                                <span class="text-muted" style="font-size:0.75rem;"><i class="bi bi-lightbulb me-1"></i>Descarga la plantilla oficial (Excel con instrucciones y tu ID pre-rellenado):</span>
                                <a href="<?= RUTA_URL ?>public/pedidos_template.php" class="btn btn-xs btn-outline-success btn-sm py-1 px-3" style="font-size:0.8rem;">
                                    <i class="bi bi-file-earmark-excel-fill me-1"></i>Descargar Plantilla XLSX (Multi-Producto)
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
                <!-- Valores por defecto (checkin) -->
                <div id="previewDefaults" class="mb-3"></div>

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


<!-- ============================================================
     Modal: Reasignar Proveedor en Pedidos
     Modo A: proveedor único  + Excel con solo numero_orden
     Modo B: Excel con numero_orden + id_proveedor por fila
     ============================================================ -->
<div class="modal fade" id="modalReasignarPedido" tabindex="-1" aria-labelledby="modalReasignarPedidoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                <h5 class="modal-title" id="modalReasignarPedidoLabel">
                    <i class="bi bi-arrow-repeat me-2"></i> Reasignar Proveedor en Pedidos
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formReasignar" enctype="multipart/form-data">

                    <p class="text-muted mb-3 small"><i class="bi bi-info-circle me-1"></i> Elige cómo asignar el nuevo proveedor a los pedidos afectados:</p>

                    <!-- Selector de modo -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100 modo-card active" id="cardModoA" onclick="switchModoReasignar('A')">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                         style="width:38px;height:38px;background:linear-gradient(135deg,#3b82f6,#2563eb);">
                                        <i class="bi bi-person-check text-white"></i>
                                    </div>
                                    <strong>Proveedor único</strong>
                                    <span class="badge ms-auto" id="badgeModoA" style="background:#3b82f6;">Seleccionado</span>
                                </div>
                                <p class="small text-muted mb-0">Selecciona <strong>un solo proveedor</strong> y sube un Excel con la lista de <code>numero_orden</code>. Se aplica a todos los pedidos del archivo.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100 modo-card" id="cardModoB" onclick="switchModoReasignar('B')">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                                         style="width:38px;height:38px;background:linear-gradient(135deg,#0B4EA2,#061C4C);">
                                        <i class="bi bi-table text-white"></i>
                                    </div>
                                    <strong>Proveedor por fila</strong>
                                    <span class="badge ms-auto d-none" id="badgeModoB" style="background:#061C4C;">Seleccionado</span>
                                </div>
                                <p class="small text-muted mb-0">Sube un Excel con <code>numero_orden</code> + <code>id_proveedor</code>. Cada fila puede tener un proveedor distinto.</p>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="modo" id="reasignar_modo_input" value="A">

                    <!-- Modo A: selector de proveedor -->
                    <div id="seccionModoA">
                        <div class="mb-3">
                            <label for="reasignar_proveedor_global" class="form-label fw-bold">
                                <i class="bi bi-person-badge me-1"></i> Nuevo Proveedor <span class="text-danger">*</span>
                            </label>
                            <select name="id_proveedor_global" id="reasignar_proveedor_global" class="form-select select2-searchable" data-placeholder="Buscar proveedor...">
                                <option value="">-- Seleccionar proveedor --</option>
                                <?php
                                $proveedoresReasignar = (new PedidosController())->obtenerProveedores();
                                foreach ($proveedoresReasignar as $pR):
                                ?>
                                    <option value="<?= $pR['id'] ?>"><?= htmlspecialchars($pR['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Este proveedor se asignará a <strong>todos</strong> los pedidos del archivo.</div>
                        </div>
                    </div>

                    <!-- Modo B: info -->
                    <div id="seccionModoB" class="d-none">
                        <div class="alert alert-info py-2 mb-3 small">
                            <i class="bi bi-table me-1"></i>
                            El Excel debe tener exactamente 2 columnas: <code>numero_orden</code> (col. A) e <code>id_proveedor</code> (col. B).
                        </div>
                    </div>

                    <!-- Archivo -->
                    <div class="mb-3">
                        <label for="reasignar_file" class="form-label fw-bold">
                            <i class="bi bi-file-earmark-excel me-1"></i> Archivo Excel / CSV <span class="text-danger">*</span>
                        </label>
                        <input type="file" name="reasignar_file" id="reasignar_file" accept=".xlsx,.csv,.txt" class="form-control" required>
                        <div class="form-text">Formatos: .xlsx, .csv (máximo 10MB)</div>
                    </div>

                    <!-- Referencia de columnas dinámica -->
                    <div class="border rounded overflow-hidden mb-3" style="font-size:0.82rem;">
                        <div class="px-3 py-2 text-white fw-bold d-flex justify-content-between align-items-center"
                             style="background:linear-gradient(135deg,#3b82f6,#2563eb)">
                            <span><i class="bi bi-file-earmark-excel me-1"></i> Formato del archivo</span>
                            <a href="<?= RUTA_URL ?>public/reasignacion_template.php?modo=A" id="linkPlantillaReasignar"
                               class="text-white small opacity-75" download>
                                <i class="bi bi-download me-1"></i>Descargar plantilla
                            </a>
                        </div>
                        <div class="p-3">
                            <div id="referenciaColumnasModoA">
                                <table class="table table-sm table-bordered mb-0" style="font-size:0.78rem;">
                                    <thead class="table-light"><tr><th>Col.</th><th>Campo</th><th>Tipo</th><th>Descripción</th></tr></thead>
                                    <tbody>
                                        <tr class="table-danger bg-opacity-25">
                                            <td class="text-center fw-bold">A</td>
                                            <td><code>numero_orden</code> <span class="badge bg-danger">REQ</span></td>
                                            <td class="text-muted">entero</td>
                                            <td>Número de orden del pedido a reasignar</td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div class="mt-2 bg-dark rounded px-3 py-2" style="font-family:monospace;font-size:0.71rem;color:#e8e8e8;">
                                    <span class="text-warning">A:numero_orden</span><br>
                                    <span class="text-secondary">28028424</span><br>
                                    <span class="text-secondary">28028425</span><br>
                                    <span class="text-secondary">28028426</span>
                                </div>
                            </div>
                            <div id="referenciaColumnasModoB" class="d-none">
                                <table class="table table-sm table-bordered mb-0" style="font-size:0.78rem;">
                                    <thead class="table-light"><tr><th>Col.</th><th>Campo</th><th>Tipo</th><th>Descripción</th></tr></thead>
                                    <tbody>
                                        <tr class="table-danger bg-opacity-25">
                                            <td class="text-center fw-bold">A</td>
                                            <td><code>numero_orden</code> <span class="badge bg-danger">REQ</span></td>
                                            <td class="text-muted">entero</td>
                                            <td>Número de orden del pedido</td>
                                        </tr>
                                        <tr class="table-danger bg-opacity-25">
                                            <td class="text-center fw-bold">B</td>
                                            <td><code>id_proveedor</code> <span class="badge bg-danger">REQ</span></td>
                                            <td class="text-muted">entero</td>
                                            <td>ID del nuevo proveedor de mensajería</td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div class="mt-2 bg-dark rounded px-3 py-2" style="font-family:monospace;font-size:0.71rem;color:#e8e8e8;">
                                    <span class="text-warning">A:numero_orden</span> | <span class="text-warning">B:id_proveedor</span><br>
                                    <span class="text-secondary">28028424</span> | <span class="text-secondary">12</span><br>
                                    <span class="text-secondary">28028425</span> | <span class="text-secondary">15</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Progreso -->
                    <div id="reasignarProgress" class="progress d-none" style="height:22px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated w-100" style="background:linear-gradient(135deg,#3b82f6,#2563eb);">Procesando...</div>
                    </div>

                    <!-- Resultados -->
                    <div id="reasignarResultados" class="d-none mt-3"></div>

                </form>
            </div>
            <div class="modal-footer flex-wrap gap-2">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Cancelar
                </button>
                <button type="button" id="btnProcesarReasignar" class="btn text-white"
                        style="background:linear-gradient(135deg,#3b82f6,#2563eb);border:none;">
                    <i class="bi bi-arrow-repeat me-1"></i> Procesar Reasignación
                </button>
            </div>
        </div>
    </div>
</div>






<?php
// Obtener estadísticas de pedidos mediante aggregación rápida (sin traer todos los registros a PHP)
require_once __DIR__ . '/../../../modelo/pedido.php';
$listarPedidos = new PedidosController();
$estados = $listarPedidos->obtenerEstados();

// Contadores via query de agregación con filtro por rol
// Misma lógica que PedidoQueryService y datatable.php
try {
    require_once __DIR__ . '/../../../utils/permissions.php';
    $_db_stats   = (new Conexion())->conectar();
    $_roles_s    = $_SESSION['roles_nombres'] ?? [];
    $_uid_s      = (int)($_SESSION['user_id'] ?? 0);
    $_isAdmin_s  = isAdmin();
    $_isRep_s    = isRepartidor();
    $_esCli_s    = in_array('Cliente',   $_roles_s, true) || in_array('cliente',   $_roles_s, true);
    $_esProv_s   = in_array('Proveedor', $_roles_s, true) || in_array('proveedor', $_roles_s, true);

    // Construir WHERE según rol
    $_whereStats = '';
    $_paramsStats = [];
    if (!$_isAdmin_s && !$_isRep_s) {
        if ($_esCli_s) {
            $_whereStats = 'WHERE p.id_cliente = :uid';
            $_paramsStats[':uid'] = $_uid_s;
        } elseif ($_esProv_s) {
            $_whereStats = 'WHERE p.id_proveedor = :uid';
            $_paramsStats[':uid'] = $_uid_s;
        }
    }

    $_stmt_stats = $_db_stats->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN ep.nombre_estado LIKE '%pend%' OR ep.nombre_estado LIKE '%nuevo%' THEN 1 ELSE 0 END) AS pendientes,
            SUM(CASE WHEN ep.nombre_estado LIKE '%entreg%' THEN 1 ELSE 0 END) AS entregados
        FROM pedidos p
        LEFT JOIN estados_pedidos ep ON p.id_estado = ep.id
        $_whereStats
    ");
    $_stmt_stats->execute($_paramsStats);
    $_counts      = $_stmt_stats->fetch(PDO::FETCH_ASSOC);
    $totalPedidos = (int)($_counts['total']      ?? 0);
    $pendientes   = (int)($_counts['pendientes'] ?? 0);
    $entregados   = (int)($_counts['entregados'] ?? 0);
    $enProceso    = $totalPedidos - $pendientes - $entregados;
} catch (Exception $_e) {
    $totalPedidos = $pendientes = $enProceso = $entregados = 0;
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
    background: linear-gradient(135deg, #0B4EA2 0%, #061C4C 100%);
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
    background: linear-gradient(135deg, #0B4EA2 0%, #38bdf8 100%);
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
.btn-reasignar {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    border: none;
    color: white;
    padding: 0.6rem 1.25rem;
    border-radius: 10px;
    font-weight: 500;
    transition: all 0.2s ease;
}
.btn-reasignar:hover {
    color: white;
    box-shadow: 0 4px 15px rgba(37, 99, 235, 0.35);
    transform: translateY(-1px);
}
.modo-card {
    border: 2px solid #dee2e6 !important;
    transition: all 0.2s ease;
    cursor: pointer;
}
.modo-card.active {
    border-color: #3b82f6 !important;
    background: rgba(59, 130, 246, 0.06);
}
.modo-card:hover {
    border-color: #3b82f6 !important;
    background: rgba(59, 130, 246, 0.04);
}
.btn-new-order {
    background: linear-gradient(135deg, #0B4EA2 0%, #061C4C 100%);
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
    color: #061C4C;
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

<?php
require_once __DIR__ . '/../../../utils/permissions.php';
?>



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
                    <button type="button" class="btn btn-reasignar" data-bs-toggle="modal" data-bs-target="#modalReasignarPedido">
                        <i class="bi bi-arrow-repeat me-1"></i> Reasignar Pedido
                    </button>
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
                    <!-- Filas generadas por DataTables SSP vía AJAX -->
                </tbody>
            </table>
        </div>
    </div>
</div>

        </div><!-- card-body -->
    </div><!-- card pedidos-card -->
</div><!-- container-fluid -->

<?php include("vista/includes/footer.php"); ?>



<?php
// Datos de roles y estados para JS (seguros, sin lógica PHP en JS)
$rolesNombres       = $_SESSION['roles_nombres'] ?? [];
$isAdmin            = in_array(ROL_NOMBRE_ADMIN,     $rolesNombres, true);
$isCliente          = in_array('Cliente', $rolesNombres, true) || in_array('cliente', $rolesNombres, true);
$isClienteRestricto = $isCliente && !$isAdmin;
$ESTADOS_TERMINALES_CLIENTE = [3, 7];
$ESTADOS_BLOQUEADOS_CLIENTE = [3, 7];
?>
<script>
const RUTA_BASE          = '<?= RUTA_URL ?>';
const SESSION_USER_NAME  = '<?= htmlspecialchars($_SESSION['nombre'] ?? '') ?>';
const ESTADOS_OPCIONES   = <?= json_encode($estados, JSON_UNESCAPED_UNICODE) ?>;
const ESTADOS_BLOQUEADOS_CLIENTE = <?= json_encode($ESTADOS_BLOQUEADOS_CLIENTE) ?>;
const ESTADOS_TERMINALES_CLIENTE = <?= json_encode($ESTADOS_TERMINALES_CLIENTE) ?>;
const IS_CLIENTE_RESTRICTO = <?= ($isClienteRestricto) ? 'true' : 'false' ?>;
const IS_ADMIN           = <?= ($isAdmin) ? 'true' : 'false' ?>;
const IS_SOLO_CLIENTE    = <?= ($isCliente && !$isAdmin) ? 'true' : 'false' ?>;

/**
 * Construye el HTML del <select> de estado para una fila de DataTables SSP.
 */
function buildEstadoSelect(idPedido, idEstadoActual, nombreEstado) {
    const esPedidoTerminal = IS_CLIENTE_RESTRICTO && ESTADOS_TERMINALES_CLIENTE.includes(parseInt(idEstadoActual));
    const disabled = esPedidoTerminal ? 'disabled' : '';

    let opts = '';
    ESTADOS_OPCIONES.forEach(function(e) {
        const esBloqueado = ESTADOS_BLOQUEADOS_CLIENTE.includes(parseInt(e.id));
        const esActual    = parseInt(e.id) === parseInt(idEstadoActual);
        // Para cliente restringido: ocultar bloqueados salvo que sea el actual
        if (IS_CLIENTE_RESTRICTO && esBloqueado && !esActual) return;
        opts += `<option value="${e.id}" ${esActual ? 'selected' : ''}>${e.nombre_estado}</option>`;
    });

    let hint = '';
    if (IS_CLIENTE_RESTRICTO) {
        hint = esPedidoTerminal
            ? `<small class="text-muted d-block mt-1" style="font-size:.75rem;"><i class="bi bi-lock-fill text-danger"></i> Este pedido no puede ser modificado.</small>`
            : `<small class="text-muted d-block mt-1" style="font-size:.75rem;"><i class="bi bi-info-circle"></i> No puedes cambiar a <strong>Entregado</strong> ni <strong>Devuelto</strong>.</small>`;
    }

    return `<select class="form-select actualizarEstado" data-id="${idPedido}" data-estado="${idEstadoActual}" ${disabled}>${opts}</select>${hint}`;
}

/**
 * Construye el HTML de los botones de acción para una fila.
 */
function buildAcciones(row) {
    const id  = row.ID_Pedido;
    const lat = row.latitud;
    const lng = row.longitud;
    let html = '';

    if (IS_SOLO_CLIENTE) {
        html += `<a href="${RUTA_BASE}pedidos/ver/${id}" class="btn btn-info btn-sm text-white"><i class="bi bi-eye"></i> Ver Detalle</a> `;
    } else {
        html += `<a href="${RUTA_BASE}pedidos/editar/${id}" class="btn btn-warning btn-sm">Editar</a> `;
    }

    if (lat && lng && parseFloat(lat) !== 0 && parseFloat(lng) !== 0) {
        html += `<a href="https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}&travelmode=driving" target="_blank" class="btn btn-success btn-sm"><i class="bi bi-geo-alt"></i> Ir a Ruta</a>`;
    } else {
        html += `<button class="btn btn-secondary btn-sm" disabled><i class="bi bi-geo-alt"></i> Sin Coord.</button>`;
    }
    return html;
}

$(document).ready(function() {
    const table = $('#tblPedidos').DataTable({
        processing : true,
        serverSide : true,
        responsive : true,
        ajax: {
            url  : RUTA_BASE + 'api/pedidos/datatable.php',
            type : 'POST',
            error: function(xhr, err, thrown) {
                console.error('DataTables SSP error:', err, thrown, xhr.responseText);
            }
        },
        columns: [
            { data: 'Numero_Orden',  title: 'Nº Orden' },
            { data: 'Cliente',       title: 'Destinatario' },
            { data: 'Comentario',    title: 'Comentario', orderable: false },
            {
                data: null,
                title: 'Estado',
                orderable: false,
                render: function(data, type, row) {
                    return buildEstadoSelect(row.ID_Pedido, row.id_estado, row.Estado);
                }
            },
            {
                data: null,
                title: 'Acciones',
                orderable: false,
                render: function(data, type, row) {
                    return buildAcciones(row);
                }
            }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        dom: 'Bfrtip',
        buttons: [
            { extend: 'excel', text: '<i class="bi bi-file-earmark-excel"></i> Excel', className: 'btn btn-sm btn-success' },
            { extend: 'pdf',   text: '<i class="bi bi-file-earmark-pdf"></i> PDF',   className: 'btn btn-sm btn-danger' },
            { extend: 'print', text: '<i class="bi bi-printer"></i> Imprimir',        className: 'btn btn-sm btn-secondary' }
        ],
        language: {
            processing:  '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div>',
            search:      'Buscar:',
            lengthMenu:  'Mostrar _MENU_ registros',
            zeroRecords: 'No se encontraron resultados',
            info:        'Mostrando _START_ a _END_ de _TOTAL_ pedidos',
            infoEmpty:   'No hay registros disponibles',
            infoFiltered:'(filtrado de _MAX_ totales)',
            paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' }
        }
    });
});
</script>

<script>
// Event delegation: captura el change en selects generados dinámicamente por DataTables SSP
$(document).on('change', '.actualizarEstado', function() {
    const select         = $(this);
    const idPedido       = select.data('id');
    const nuevoEstado    = parseInt(select.val(), 10);
    const estadoAnterior = parseInt(select.data('estado'), 10);
    const nombreEstado   = select.find('option:selected').text().trim();

    // Guard: pedido en estado terminal (cliente restringido)
    if (IS_CLIENTE_RESTRICTO && ESTADOS_TERMINALES_CLIENTE.includes(estadoAnterior)) {
        select.val(estadoAnterior);
        Swal.fire({ title: 'Acción no permitida', text: 'Este pedido no puede ser modificado.', icon: 'warning', confirmButtonText: 'Entendido' });
        return;
    }

    // Guard: destino bloqueado para cliente
    if (IS_CLIENTE_RESTRICTO && ESTADOS_BLOQUEADOS_CLIENTE.includes(nuevoEstado)) {
        select.val(estadoAnterior);
        Swal.fire({ title: 'Acción no permitida', text: 'No puedes cambiar el pedido a ese estado.', icon: 'warning', confirmButtonText: 'Entendido' });
        return;
    }

    const ES_REPROGRAMADO = (nuevoEstado === 4);

    // Construir HTML del modal según el estado
    const htmlModal = ES_REPROGRAMADO
        ? `<p class="text-muted mb-2" style="font-size:.9rem;">Cambio a: <strong>${nombreEstado}</strong></p>
           <div class="mb-3 text-start">
               <label class="form-label fw-semibold">📅 Nueva fecha de entrega <span class="text-danger">*</span></label>
               <input type="date" id="swal-fecha-entrega" class="form-control" min="${new Date().toISOString().split('T')[0]}" required>
           </div>
           <div class="text-start">
               <label class="form-label fw-semibold">💬 Observaciones</label>
               <textarea id="swal-obs" class="form-control" rows="3" placeholder="Escribe tus observaciones aquí..."></textarea>
           </div>`
        : `<p class="text-muted mb-2" style="font-size:.9rem;">Cambio a: <strong>${nombreEstado}</strong></p>
           <div class="text-start">
               <label class="form-label fw-semibold">💬 Observaciones</label>
               <textarea id="swal-obs" class="form-control" rows="3" placeholder="Escribe tus observaciones aquí..."></textarea>
           </div>`;

    Swal.fire({
        title: 'Procesar Pedido',
        html: htmlModal,
        showCancelButton: true,
        confirmButtonText: 'Actualizar Estado',
        cancelButtonText: 'Cancelar',
        focusConfirm: false,
        preConfirm: () => {
            const obs = document.getElementById('swal-obs')?.value?.trim() || '';
            if (ES_REPROGRAMADO) {
                const fecha = document.getElementById('swal-fecha-entrega')?.value || '';
                if (!fecha) {
                    Swal.showValidationMessage('La fecha de entrega es obligatoria para Reprogramado.');
                    return false;
                }
                return { obs, fecha_entrega: fecha };
            }
            return { obs, fecha_entrega: '' };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            select.prop('disabled', true);
            $.ajax({
                url: RUTA_BASE + 'cambiarEstados',
                type: 'POST',
                data: {
                    id_pedido: idPedido,
                    estado: nuevoEstado,
                    observaciones: result.value.obs,
                    fecha_entrega: result.value.fecha_entrega
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({ title: '¡Éxito!', text: 'Estado actualizado correctamente.', icon: 'success', confirmButtonText: 'OK' });
                        select.data('estado', nuevoEstado);
                        select.prop('disabled', false);
                    } else {
                        Swal.fire({ title: 'Error', text: response.message, icon: 'error' });
                        select.val(estadoAnterior);
                        select.prop('disabled', false);
                    }
                },
                error: function() {
                    Swal.fire({ title: 'Error', text: 'Hubo un problema al procesar la solicitud.', icon: 'error' });
                    select.val(estadoAnterior);
                    select.prop('disabled', false);
                }
            });
        } else {
            select.val(estadoAnterior);
        }
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
        
        // Obtener nombres de los defaults seleccionados en el formulario
        const defaultEstado    = $('#default_estado').val()    ? $('#default_estado option:selected').text().trim()    : null;
        const defaultProveedor = $('#default_proveedor').val() ? $('#default_proveedor option:selected').text().trim() : null;
        const defaultMoneda    = $('#default_moneda').val()    ? $('#default_moneda option:selected').text().trim()    : null;
        const defaultVendedor  = $('#default_vendedor').val()  ? $('#default_vendedor option:selected').text().trim()  : null;
        const defaultEsCombo   = document.getElementById('default_es_combo')?.checked ? true : false;

        let defaultsHtml = '';
        // El cliente creador es siempre el usuario logueado en la sesión
        const defaultCliente = SESSION_USER_NAME;
        
        defaultsHtml = `
            <div class="card border-info bg-light mb-3">
                <div class="card-header bg-info text-white py-1 px-3 d-flex align-items-center" style="font-size: 0.85rem; font-weight: bold;">
                    <i class="bi bi-gear-fill me-1"></i> Configuración de Importación Aplicada
                </div>
                <div class="card-body py-2 px-3" style="font-size: 0.82rem;">
                    <div class="row">
                        <div class="col-md-3 col-sm-6 mb-1">
                            <strong>Asociar a Cliente:</strong> <span class="badge bg-dark text-wrap">${defaultCliente}</span>
                        </div>
        `;
        if (defaultEstado) {
            defaultsHtml += `
                <div class="col-md-2 col-sm-6 mb-1">
                    <strong>Estado:</strong> <span class="badge bg-primary text-wrap">${defaultEstado}</span>
                </div>
            `;
        }
        if (defaultProveedor) {
            defaultsHtml += `
                <div class="col-md-3 col-sm-6 mb-1">
                    <strong>Proveedor:</strong> <span class="badge bg-success text-wrap">${defaultProveedor}</span>
                </div>
            `;
        }
        if (defaultMoneda) {
            defaultsHtml += `
                <div class="col-md-2 col-sm-6 mb-1">
                    <strong>Moneda:</strong> <span class="badge bg-warning text-dark text-wrap">${defaultMoneda}</span>
                </div>
            `;
        }
        if (defaultVendedor) {
            defaultsHtml += `
                <div class="col-md-2 col-sm-6 mb-1">
                    <strong>Vendedor/Repartidor:</strong> <span class="badge bg-secondary text-wrap">${defaultVendedor}</span>
                </div>
            `;
        }
        if (defaultEsCombo) {
            defaultsHtml += `
                <div class="col-md-2 col-sm-6 mb-1">
                    <strong>Es Combo:</strong> <span class="badge text-wrap" style="background:#061C4C;">✔ Sí (forzado)</span>
                </div>
            `;
        }
        defaultsHtml += `
                    </div>
                </div>
            </div>
        `;
        const previewDefaultsEl = document.getElementById('previewDefaults');
        if (previewDefaultsEl) {
            previewDefaultsEl.innerHTML = defaultsHtml;
        }
        
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
            // Columnas internas del sistema que no se muestran en la tabla preview
            const HIDDEN_COLS = ['_productos', 'producto_nombre', 'cantidad'];

            // Determinar columnas visibles (excluir internas)
            const allCols = Object.keys(data.primeras_filas[0]);
            const hasMultiProd = data.primeras_filas.some(r => Array.isArray(r['_productos']) && r['_productos'].length > 0);
            const cols = allCols.filter(c => !HIDDEN_COLS.includes(c));

            // Función para renderizar el valor de una celda de forma segura
            function renderCell(val, col) {
                if (val === null || val === undefined || val === '') return '<span class="text-muted">—</span>';
                if (typeof val === 'object') return '<code class="small">' + JSON.stringify(val) + '</code>';
                // Resaltar booleanos 0/1
                if (val === '1' || val === 1) return '<span class="badge bg-success">1</span>';
                if (val === '0' || val === 0) return '<span class="badge bg-secondary">0</span>';
                return String(val);
            }

            // Función para renderizar el bloque de productos de una fila
            function renderProductos(row) {
                if (Array.isArray(row['_productos']) && row['_productos'].length > 0) {
                    return row['_productos'].map((p, i) =>
                        `<span class="badge me-1" style="background:#1D6A3A;font-size:0.75rem;">
                            ${i+1}. ${p.nombre} × ${p.cantidad}
                         </span>`
                    ).join('');
                }
                // Un solo producto
                const nombre = row['producto_nombre'] || '';
                const cant   = row['cantidad'] || 1;
                return nombre
                    ? `<span class="badge bg-secondary">${nombre} × ${cant}</span>`
                    : '<span class="text-muted">—</span>';
            }

            let tableHtml = `
                <h6 class="mb-2">Primeras ${data.primeras_filas.length} filas del archivo</h6>
                <div class="table-responsive" style="max-height:340px;overflow:auto;">
                <table class="table table-sm table-bordered align-middle" style="font-size:0.75rem;white-space:nowrap;">
                    <thead class="table-dark sticky-top">
                        <tr>
                            <th>Productos</th>
                            ${cols.map(c => '<th>' + c + '</th>').join('')}
                        </tr>
                    </thead>
                    <tbody>
                        ${data.primeras_filas.map(r =>
                            '<tr>' +
                            '<td style="white-space:normal;min-width:180px;">' + renderProductos(r) + '</td>' +
                            cols.map(c => '<td>' + renderCell(r[c], c) + '</td>').join('') +
                            '</tr>'
                        ).join('')}
                    </tbody>
                </table>
                </div>
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

<script>
// ============================================================
// REASIGNAR PROVEEDOR EN PEDIDOS
// ============================================================
function switchModoReasignar(modo) {
    document.getElementById('reasignar_modo_input').value = modo;

    const cardA  = document.getElementById('cardModoA');
    const cardB  = document.getElementById('cardModoB');
    const badgeA = document.getElementById('badgeModoA');
    const badgeB = document.getElementById('badgeModoB');
    const secA   = document.getElementById('seccionModoA');
    const secB   = document.getElementById('seccionModoB');
    const refA   = document.getElementById('referenciaColumnasModoA');
    const refB   = document.getElementById('referenciaColumnasModoB');
    const linkPlantilla = document.getElementById('linkPlantillaReasignar');

    if (modo === 'A') {
        cardA.classList.add('active');    cardB.classList.remove('active');
        badgeA.classList.remove('d-none'); badgeB.classList.add('d-none');
        secA.classList.remove('d-none'); secB.classList.add('d-none');
        refA.classList.remove('d-none'); refB.classList.add('d-none');
        if (linkPlantilla) linkPlantilla.href = RUTA_BASE + 'public/reasignacion_template.php?modo=A';
    } else {
        cardB.classList.add('active');    cardA.classList.remove('active');
        badgeB.classList.remove('d-none'); badgeA.classList.add('d-none');
        secB.classList.remove('d-none'); secA.classList.add('d-none');
        refB.classList.remove('d-none'); refA.classList.add('d-none');
        if (linkPlantilla) linkPlantilla.href = RUTA_BASE + 'public/reasignacion_template.php?modo=B';
    }
}

document.addEventListener('DOMContentLoaded', function () {

    // Procesar reasignación
    document.getElementById('btnProcesarReasignar').addEventListener('click', function () {
        const modo     = document.getElementById('reasignar_modo_input').value;
        const fileInp  = document.getElementById('reasignar_file');
        const progress = document.getElementById('reasignarProgress');
        const results  = document.getElementById('reasignarResultados');
        const btn      = this;

        if (!fileInp.files.length) {
            Swal.fire('Archivo requerido', 'Por favor selecciona un archivo Excel o CSV.', 'warning');
            return;
        }

        if (modo === 'A') {
            const prov = document.getElementById('reasignar_proveedor_global').value;
            if (!prov) {
                Swal.fire('Proveedor requerido', 'Selecciona el nuevo proveedor antes de continuar.', 'warning');
                return;
            }
        }

        const formData = new FormData(document.getElementById('formReasignar'));
        progress.classList.remove('d-none');
        results.classList.add('d-none');
        btn.disabled = true;

        fetch(RUTA_BASE + 'pedidos/reasignarProveedor', {
            method:  'POST',
            body:    formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            progress.classList.add('d-none');
            btn.disabled = false;
            results.classList.remove('d-none');

            const s          = data.stats || {};
            const isOk       = data.success && (s.actualizados || 0) > 0;
            const bgColor    = isOk ? '#d1fae5' : (s.actualizados > 0 ? '#fef3c7' : '#fee2e2');
            const bdrColor   = isOk ? '#10b981' : (s.actualizados > 0 ? '#f59e0b' : '#ef4444');
            const icon       = isOk ? '✅' : (s.actualizados > 0 ? '⚠️' : '❌');

            let html = `
                <div class="rounded-3 p-3" style="background:${bgColor};border:2px solid ${bdrColor};">
                    <h6 class="fw-bold mb-3">${icon} Resultado de la Reasignación</h6>
                    <div class="row g-2 mb-3">
                        <div class="col-6 col-md-3">
                            <div class="text-center rounded-2 p-2 bg-white shadow-sm">
                                <div class="fs-3 fw-bold text-primary">${s.total ?? 0}</div>
                                <div class="small text-muted">Total filas</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-center rounded-2 p-2 bg-white shadow-sm">
                                <div class="fs-3 fw-bold text-success">${s.actualizados ?? 0}</div>
                                <div class="small text-muted">Actualizados</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-center rounded-2 p-2 bg-white shadow-sm">
                                <div class="fs-3 fw-bold text-warning">${s.no_encontrados ?? 0}</div>
                                <div class="small text-muted">No encontrados</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="text-center rounded-2 p-2 bg-white shadow-sm">
                                <div class="fs-3 fw-bold text-danger">${s.errores ?? 0}</div>
                                <div class="small text-muted">Errores</div>
                            </div>
                        </div>
                    </div>`;

            if (data.detalle_no_encontrados && data.detalle_no_encontrados.length > 0) {
                html += `<div class="mt-2"><strong class="small">⚠️ No encontrados (max 30):</strong>
                    <div class="small text-muted mt-1">${data.detalle_no_encontrados.join(' · ')}</div></div>`;
            }
            if (data.detalle_errores && data.detalle_errores.length > 0) {
                html += `<div class="mt-2"><strong class="small">❌ Errores:</strong>
                    <ul class="small text-danger mb-0 mt-1">`
                    + data.detalle_errores.map(e => `<li>${e}</li>`).join('')
                    + `</ul></div>`;
            }
            html += `<div class="small text-muted mt-2">⏱ Tiempo: ${s.tiempo_segundos ?? '?'}s</div></div>`;
            results.innerHTML = html;

            // Recargar DataTable si hubo actualizaciones
            if ((s.actualizados ?? 0) > 0 && typeof $ !== 'undefined' && $.fn.DataTable) {
                $('#tblPedidos').DataTable().ajax.reload(null, false);
            }
        })
        .catch(err => {
            progress.classList.add('d-none');
            btn.disabled = false;
            Swal.fire('Error de conexión', 'No se pudo procesar la reasignación. Intenta de nuevo.', 'error');
            console.error('[Reasignar]', err);
        });
    });

    // Reset al cerrar modal
    document.getElementById('modalReasignarPedido').addEventListener('hidden.bs.modal', function () {
        document.getElementById('formReasignar').reset();
        document.getElementById('reasignarResultados').classList.add('d-none');
        document.getElementById('reasignarProgress').classList.add('d-none');
        switchModoReasignar('A');
    });
});
</script>