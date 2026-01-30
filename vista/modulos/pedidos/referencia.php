<?php
/**
 * Vista de Referencia de Valores
 * 
 * Muestra todos los valores válidos para claves foráneas
 * Estados, Proveedores, Monedas, Vendedores, Países
 */

// Incluir Header del template
include "vista/includes/header.php";

// Nota: $ctrl y $db deberían estar disponibles o instanciarse aquí si no lo están.
// En el contexto de la plantilla, ya se han cargado controladores.
// Pero para estar seguros, instanciamos el controlador de pedidos.

// require_once __DIR__ . '/../../../controlador/pedido.php'; // Ya incluido en index.php
// require_once __DIR__ . '/../../../modelo/conexion.php'; // Ya incluido en index.php

try {
    $ctrl = new PedidosController();
    $db = (new Conexion())->conectar();
    
    ?>
    <style>
        .nav-pills .nav-link.active { background-color: #0d6efd; }
        .copy-btn { cursor: pointer; transition: all 0.2s; }
        .copy-btn:hover { transform: scale(1.05); }
        .table-hover tbody tr:hover { background-color: rgba(13, 110, 253, 0.05); }
        .search-box { position: relative; }
        .search-box i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #6c757d; }
        .search-box input { padding-left: 40px; border-radius: 20px; }
        .badge-id { font-family: monospace; font-size: 0.9em; }
        
        @media print {
            .no-print { display: none !important; }
            .card { border: none !important; shadow: none !important; }
            .tab-pane { display: block !important; opacity: 1 !important; }
            .tab-content { border: none; }
            /* Ocultar elementos del template principal si tienen estas clases comunes */
            header, nav, footer, .sidebar { display: none !important; }
        }
    </style>

    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1"><i class="bi bi-database-check text-primary"></i> Referencia de Valores</h1>
                <p class="text-muted mb-0 small">IDs y Nombres válidos para importación CSV</p>
            </div>
            <div class="d-flex gap-2 no-print">
                <button class="btn btn-outline-primary btn-sm" onclick="window.print()">
                    <i class="bi bi-printer"></i> Imprimir
                </button>
                <a href="<?= RUTA_URL ?>pedidos/listar" class="btn btn-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>
        </div>


        <!-- Search & Filters -->
        <div class="row mb-4 no-print">
            <div class="col-md-6">
                <div class="alert alert-info py-2 px-3 mb-0 small border-0 shadow-sm">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    Puedes usar el <strong>ID</strong> (numérico) o el <strong>Nombre</strong> (texto) en tu archivo CSV.
                    Ejemplo: <code>id_estado=1</code> es igual a <code>estado_nombre=Pendiente</code>.
                </div>
            </div>
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-globe"></i></span>
                    <select id="paisFilter" class="form-select">
                        <option value="">Todos los países</option>
                        <?php
                        $paisesStmt = $db->query("SELECT id, nombre FROM paises ORDER BY nombre");
                        $paisesList = $paisesStmt->fetchAll();
                        foreach ($paisesList as $pais):
                        ?>
                            <option value="<?= $pais['id'] ?>"><?= htmlspecialchars($pais['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" id="searchInput" class="form-control" placeholder="Buscar valor...">
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <ul class="nav nav-pills mb-3 no-print" id="pills-tab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="pills-estados-tab" data-bs-toggle="pill" data-bs-target="#pills-estados" type="button" role="tab">
                    <i class="bi bi-tag"></i> Estados
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pills-proveedores-tab" data-bs-toggle="pill" data-bs-target="#pills-proveedores" type="button" role="tab">
                    <i class="bi bi-building"></i> Proveedores
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pills-monedas-tab" data-bs-toggle="pill" data-bs-target="#pills-monedas" type="button" role="tab">
                    <i class="bi bi-currency-exchange"></i> Monedas
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pills-vendedores-tab" data-bs-toggle="pill" data-bs-target="#pills-vendedores" type="button" role="tab">
                    <i class="bi bi-person-badge"></i> Vendedores
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pills-paises-tab" data-bs-toggle="pill" data-bs-target="#pills-paises" type="button" role="tab">
                    <i class="bi bi-globe"></i> Países
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pills-departamentos-tab" data-bs-toggle="pill" data-bs-target="#pills-departamentos" type="button" role="tab">
                    <i class="bi bi-map"></i> Departamentos
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pills-municipios-tab" data-bs-toggle="pill" data-bs-target="#pills-municipios" type="button" role="tab">
                    <i class="bi bi-geo-alt"></i> Municipios
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pills-barrios-tab" data-bs-toggle="pill" data-bs-target="#pills-barrios" type="button" role="tab">
                    <i class="bi bi-signpost"></i> Barrios
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pills-productos-tab" data-bs-toggle="pill" data-bs-target="#pills-productos" type="button" role="tab">
                    <i class="bi bi-box-seam"></i> Productos
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="pills-tabContent">
            
            <!-- ESTADOS -->
            <div class="tab-pane fade show active" id="pills-estados" role="tabpanel">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 100px;">ID</th>
                                    <th>Nombre del Estado</th>
                                    <th class="text-end no-print">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $estados = $ctrl->obtenerEstados();
                                foreach ($estados as $e):
                                ?>
                                <tr class="searchable-row">
                                    <td><span class="badge bg-secondary badge-id"><?= $e['id'] ?></span></td>
                                    <td class="fw-medium"><?= htmlspecialchars($e['nombre_estado']) ?></td>
                                    <td class="text-end no-print">
                                        <button class="btn btn-sm btn-light copy-btn text-muted" onclick="copyToClipboard('<?= $e['id'] ?>', this)" title="Copiar ID">
                                            <i class="bi bi-hash"></i>
                                        </button>
                                        <button class="btn btn-sm btn-light copy-btn text-primary" onclick="copyToClipboard('<?= htmlspecialchars($e['nombre_estado'], ENT_QUOTES) ?>', this)" title="Copiar Nombre">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- PROVEEDORES -->
            <div class="tab-pane fade" id="pills-proveedores" role="tabpanel">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 100px;">ID</th>
                                    <th>Nombre del Proveedor</th>
                                    <th class="text-end no-print">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $proveedores = $ctrl->obtenerProveedores();
                                foreach ($proveedores as $p):
                                ?>
                                <tr class="searchable-row">
                                    <td><span class="badge bg-secondary badge-id"><?= $p['id'] ?></span></td>
                                    <td class="fw-medium"><?= htmlspecialchars($p['nombre']) ?></td>
                                    <td class="text-end no-print">
                                        <button class="btn btn-sm btn-light copy-btn text-muted" onclick="copyToClipboard('<?= $p['id'] ?>', this)" title="Copiar ID">
                                            <i class="bi bi-hash"></i>
                                        </button>
                                        <button class="btn btn-sm btn-light copy-btn text-success" onclick="copyToClipboard('<?= htmlspecialchars($p['nombre'], ENT_QUOTES) ?>', this)" title="Copiar Nombre">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- MONEDAS -->
            <div class="tab-pane fade" id="pills-monedas" role="tabpanel">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 100px;">ID</th>
                                    <th>Código</th>
                                    <th>Nombre</th>
                                    <th class="text-end no-print">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $monedas = $ctrl->obtenerMonedas();
                                foreach ($monedas as $m):
                                ?>
                                <tr class="searchable-row">
                                    <td><span class="badge bg-secondary badge-id"><?= $m['id'] ?></span></td>
                                    <td><span class="badge bg-warning text-dark"><?= $m['codigo'] ?></span></td>
                                    <td><?= htmlspecialchars($m['nombre']) ?></td>
                                    <td class="text-end no-print">
                                        <button class="btn btn-sm btn-light copy-btn text-muted" onclick="copyToClipboard('<?= $m['id'] ?>', this)" title="Copiar ID">
                                            <i class="bi bi-hash"></i>
                                        </button>
                                        <button class="btn btn-sm btn-light copy-btn text-warning" onclick="copyToClipboard('<?= $m['codigo'] ?>', this)" title="Copiar Código">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- VENDEDORES -->
            <div class="tab-pane fade" id="pills-vendedores" role="tabpanel">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 100px;">ID</th>
                                    <th>Nombre</th>
                                    <th class="text-end no-print">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $vendedores = $ctrl->obtenerRepartidores();
                                foreach ($vendedores as $v):
                                ?>
                                <tr class="searchable-row">
                                    <td><span class="badge bg-secondary badge-id"><?= $v['id'] ?></span></td>
                                    <td><?= htmlspecialchars($v['nombre']) ?></td>
                                    <td class="text-end no-print">
                                        <button class="btn btn-sm btn-light copy-btn text-muted" onclick="copyToClipboard('<?= $v['id'] ?>', this)" title="Copiar ID">
                                            <i class="bi bi-hash"></i>
                                        </button>
                                        <button class="btn btn-sm btn-light copy-btn text-info" onclick="copyToClipboard('<?= htmlspecialchars($v['nombre'], ENT_QUOTES) ?>', this)" title="Copiar Nombre">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- PAISES -->
            <div class="tab-pane fade" id="pills-paises" role="tabpanel">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 100px;">ID</th>
                                    <th>Nombre del País</th>
                                    <th class="text-end no-print">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $db->query("SELECT id, nombre FROM paises ORDER BY nombre");
                                $paises = $stmt->fetchAll();
                                foreach ($paises as $p):
                                ?>
                                <tr class="searchable-row">
                                    <td><span class="badge bg-secondary badge-id"><?= $p['id'] ?></span></td>
                                    <td><?= htmlspecialchars($p['nombre']) ?></td>
                                    <td class="text-end no-print">
                                        <button class="btn btn-sm btn-light copy-btn text-muted" onclick="copyToClipboard('<?= $p['id'] ?>', this)" title="Copiar ID">
                                            <i class="bi bi-hash"></i>
                                        </button>
                                        <button class="btn btn-sm btn-light copy-btn text-dark" onclick="copyToClipboard('<?= htmlspecialchars($p['nombre'], ENT_QUOTES) ?>', this)" title="Copiar Nombre">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- DEPARTAMENTOS -->
            <div class="tab-pane fade" id="pills-departamentos" role="tabpanel">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 100px;">ID</th>
                                    <th>Nombre del Departamento</th>
                                    <th style="width: 150px;">País</th>
                                    <th class="text-end no-print">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $db->query("SELECT d.id, d.nombre, d.id_pais, p.nombre as pais_nombre FROM departamentos d LEFT JOIN paises p ON d.id_pais = p.id ORDER BY d.nombre");
                                $departamentos = $stmt->fetchAll();
                                foreach ($departamentos as $d):
                                ?>
                                <tr class="searchable-row filterable-row" data-pais="<?= $d['id_pais'] ?? '' ?>">
                                    <td><span class="badge bg-secondary badge-id"><?= $d['id'] ?></span></td>
                                    <td><?= htmlspecialchars($d['nombre']) ?></td>
                                    <td><span class="badge bg-light text-dark"><?= htmlspecialchars($d['pais_nombre'] ?? 'N/A') ?></span></td>
                                    <td class="text-end no-print">
                                        <button class="btn btn-sm btn-light copy-btn text-muted" onclick="copyToClipboard('<?= $d['id'] ?>', this)" title="Copiar ID">
                                            <i class="bi bi-hash"></i>
                                        </button>
                                        <button class="btn btn-sm btn-light copy-btn text-dark" onclick="copyToClipboard('<?= htmlspecialchars($d['nombre'], ENT_QUOTES) ?>', this)" title="Copiar Nombre">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- MUNICIPIOS -->
            <div class="tab-pane fade" id="pills-municipios" role="tabpanel">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 100px;">ID</th>
                                    <th>Nombre del Municipio</th>
                                    <th style="width: 150px;">Código Postal</th>
                                    <th style="width: 150px;">País</th>
                                    <th class="text-end no-print">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $db->query("SELECT m.id, m.nombre, m.codigo_postal, d.id_pais, p.nombre as pais_nombre FROM municipios m LEFT JOIN departamentos d ON m.id_departamento = d.id LEFT JOIN paises p ON d.id_pais = p.id ORDER BY m.nombre");
                                $municipios = $stmt->fetchAll();
                                foreach ($municipios as $m):
                                ?>
                                <tr class="searchable-row filterable-row" data-pais="<?= $m['id_pais'] ?? '' ?>">
                                    <td><span class="badge bg-secondary badge-id"><?= $m['id'] ?></span></td>
                                    <td><?= htmlspecialchars($m['nombre']) ?></td>
                                    <td>
                                        <?php if (!empty($m['codigo_postal'])): ?>
                                            <span class="badge bg-info"><?= htmlspecialchars($m['codigo_postal']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-light text-dark"><?= htmlspecialchars($m['pais_nombre'] ?? 'N/A') ?></span></td>
                                    <td class="text-end no-print">
                                        <button class="btn btn-sm btn-light copy-btn text-muted" onclick="copyToClipboard('<?= $m['id'] ?>', this)" title="Copiar ID">
                                            <i class="bi bi-hash"></i>
                                        </button>
                                        <button class="btn btn-sm btn-light copy-btn text-dark" onclick="copyToClipboard('<?= htmlspecialchars($m['nombre'], ENT_QUOTES) ?>', this)" title="Copiar Nombre">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- BARRIOS -->
            <div class="tab-pane fade" id="pills-barrios" role="tabpanel">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 100px;">ID</th>
                                    <th>Nombre del Barrio</th>
                                    <th style="width: 150px;">Código Postal</th>
                                    <th style="width: 150px;">País</th>
                                    <th class="text-end no-print">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $db->query("SELECT b.id, b.nombre, b.codigo_postal, d.id_pais, p.nombre as pais_nombre FROM barrios b LEFT JOIN municipios m ON b.id_municipio = m.id LEFT JOIN departamentos d ON m.id_departamento = d.id LEFT JOIN paises p ON d.id_pais = p.id ORDER BY b.nombre");
                                $barrios = $stmt->fetchAll();
                                foreach ($barrios as $b):
                                ?>
                                <tr class="searchable-row filterable-row" data-pais="<?= $b['id_pais'] ?? '' ?>">
                                    <td><span class="badge bg-secondary badge-id"><?= $b['id'] ?></span></td>
                                    <td><?= htmlspecialchars($b['nombre']) ?></td>
                                    <td>
                                        <?php if (!empty($b['codigo_postal'])): ?>
                                            <span class="badge bg-info"><?= htmlspecialchars($b['codigo_postal']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-light text-dark"><?= htmlspecialchars($b['pais_nombre'] ?? 'N/A') ?></span></td>
                                    <td class="text-end no-print">
                                        <button class="btn btn-sm btn-light copy-btn text-muted" onclick="copyToClipboard('<?= $b['id'] ?>', this)" title="Copiar ID">
                                            <i class="bi bi-hash"></i>
                                        </button>
                                        <button class="btn btn-sm btn-light copy-btn text-dark" onclick="copyToClipboard('<?= htmlspecialchars($b['nombre'], ENT_QUOTES) ?>', this)" title="Copiar Nombre">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- PRODUCTOS -->
            <div class="tab-pane fade" id="pills-productos" role="tabpanel">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 100px;">ID</th>
                                    <th>Nombre del Producto</th>
                                    <th class="text-end no-print">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $db->query("SELECT id, nombre FROM productos ORDER BY nombre");
                                $productos = $stmt->fetchAll();
                                foreach ($productos as $prod):
                                ?>
                                <tr class="searchable-row">
                                    <td><span class="badge bg-secondary badge-id"><?= $prod['id'] ?></span></td>
                                    <td><?= htmlspecialchars($prod['nombre']) ?></td>
                                    <td class="text-end no-print">
                                        <button class="btn btn-sm btn-light copy-btn text-muted" onclick="copyToClipboard('<?= $prod['id'] ?>', this)" title="Copiar ID">
                                            <i class="bi bi-hash"></i>
                                        </button>
                                        <button class="btn btn-sm btn-light copy-btn text-dark" onclick="copyToClipboard('<?= htmlspecialchars($prod['nombre'], ENT_QUOTES) ?>', this)" title="Copiar Nombre">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        // Copiar al portapapeles
        function copyToClipboard(text, btn) {
            navigator.clipboard.writeText(text).then(() => {
                const originalIcon = btn.innerHTML;
                btn.innerHTML = '<i class="bi bi-check-lg"></i>';
                btn.classList.add('text-success');
                
                setTimeout(() => {
                    btn.innerHTML = originalIcon;
                    btn.classList.remove('text-success');
                }, 1500);
            });
        }

        // Variables globales para paginación
        const itemsPerPage = 50;
        let currentPages = {};

        // Función de filtrado combinado (búsqueda + país) - solo para el tab activo
        function applyFilters() {
            // Obtener el tab activo
            const activeTab = document.querySelector('.tab-pane.active');
            if (!activeTab) return;
            
            const tabId = activeTab.id;
            const searchText = document.getElementById('searchInput').value.toLowerCase();
            const selectedPais = document.getElementById('paisFilter').value;
            
            // Solo procesar filas del tab activo
            const rows = activeTab.querySelectorAll('.searchable-row');
            
            let visibleRows = [];
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const paisId = row.getAttribute('data-pais') || '';
                
                // Verificar búsqueda de texto
                const matchesSearch = text.includes(searchText);
                
                // Verificar filtro de país (solo para filas con data-pais)
                const matchesPais = !selectedPais || !row.classList.contains('filterable-row') || paisId === selectedPais;
                
                if (matchesSearch && matchesPais) {
                    visibleRows.push(row);
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Aplicar paginación a las filas visibles del tab activo
            paginateVisibleRows(tabId, visibleRows);
        }

        // Función de paginación - recibe el tabId y las filas visibles
        function paginateVisibleRows(tabId, visibleRows) {
            const currentPage = currentPages[tabId] || 1;
            
            // Calcular índices
            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;
            
            // Mostrar/ocultar filas según la página
            visibleRows.forEach((row, index) => {
                if (index >= startIndex && index < endIndex) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Actualizar controles de paginación
            updatePaginationControls(tabId, visibleRows.length, currentPage);
        }

        // Actualizar controles de paginación
        function updatePaginationControls(tabId, totalItems, currentPage) {
            const totalPages = Math.ceil(totalItems / itemsPerPage);
            let paginationContainer = document.querySelector(`#${tabId} .pagination-container`);
            
            // Crear contenedor si no existe
            if (!paginationContainer) {
                const cardBody = document.querySelector(`#${tabId} .card-body`);
                if (cardBody) {
                    paginationContainer = document.createElement('div');
                    paginationContainer.className = 'pagination-container p-3 border-top d-flex justify-content-between align-items-center';
                    cardBody.appendChild(paginationContainer);
                }
            }
            
            if (!paginationContainer) return;
            
            // Limpiar contenido anterior
            paginationContainer.innerHTML = '';
            
            if (totalPages <= 1) {
                paginationContainer.style.display = 'none';
                return;
            }
            
            paginationContainer.style.display = 'flex';
            
            // Información de página
            const startItem = (currentPage - 1) * itemsPerPage + 1;
            const endItem = Math.min(currentPage * itemsPerPage, totalItems);
            const info = document.createElement('div');
            info.className = 'text-muted small';
            info.textContent = `Mostrando ${startItem}-${endItem} de ${totalItems} registros`;
            paginationContainer.appendChild(info);
            
            // Botones de navegación
            const nav = document.createElement('nav');
            const ul = document.createElement('ul');
            ul.className = 'pagination pagination-sm mb-0';
            
            // Botón anterior
            const prevLi = document.createElement('li');
            prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
            const prevA = document.createElement('a');
            prevA.className = 'page-link';
            prevA.href = '#';
            prevA.innerHTML = '<i class="bi bi-chevron-left"></i>';
            prevA.onclick = (e) => {
                e.preventDefault();
                if (currentPage > 1) {
                    currentPages[tabId] = currentPage - 1;
                    applyFilters();
                }
            };
            prevLi.appendChild(prevA);
            ul.appendChild(prevLi);
            
            // Números de página (mostrar máximo 5)
            const maxButtons = 5;
            let startPage = Math.max(1, currentPage - Math.floor(maxButtons / 2));
            let endPage = Math.min(totalPages, startPage + maxButtons - 1);
            
            if (endPage - startPage < maxButtons - 1) {
                startPage = Math.max(1, endPage - maxButtons + 1);
            }
            
            for (let i = startPage; i <= endPage; i++) {
                const li = document.createElement('li');
                li.className = `page-item ${i === currentPage ? 'active' : ''}`;
                const a = document.createElement('a');
                a.className = 'page-link';
                a.href = '#';
                a.textContent = i;
                a.onclick = (e) => {
                    e.preventDefault();
                    currentPages[tabId] = i;
                    applyFilters();
                };
                li.appendChild(a);
                ul.appendChild(li);
            }
            
            // Botón siguiente
            const nextLi = document.createElement('li');
            nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
            const nextA = document.createElement('a');
            nextA.className = 'page-link';
            nextA.href = '#';
            nextA.innerHTML = '<i class="bi bi-chevron-right"></i>';
            nextA.onclick = (e) => {
                e.preventDefault();
                if (currentPage < totalPages) {
                    currentPages[tabId] = currentPage + 1;
                    applyFilters();
                }
            };
            nextLi.appendChild(nextA);
            ul.appendChild(nextLi);
            
            nav.appendChild(ul);
            paginationContainer.appendChild(nav);
        }

        // Event listeners
        document.getElementById('searchInput').addEventListener('keyup', applyFilters);
        document.getElementById('paisFilter').addEventListener('change', function() {
            // Resetear páginas al cambiar filtro
            currentPages = {};
            applyFilters();
        });

        // Aplicar filtros al cambiar de tab
        document.querySelectorAll('button[data-bs-toggle="pill"]').forEach(button => {
            button.addEventListener('shown.bs.tab', function() {
                applyFilters();
            });
        });

        // Inicializar paginación al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            applyFilters();
        });
    </script>
    <?php

} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
}

// Incluir Footer del template
include "vista/includes/footer.php";
