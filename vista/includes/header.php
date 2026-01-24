<?php
    // Determinar la URL del logo según el rol del usuario
    $rolesNombres = $_SESSION['roles_nombres'] ?? [];
    $isRepartidor = in_array(ROL_NOMBRE_REPARTIDOR, $rolesNombres, true);
    $isAdmin = in_array(ROL_NOMBRE_ADMIN, $rolesNombres, true);
    $isProveedor = in_array(ROL_NOMBRE_PROVEEDOR, $rolesNombres, true);  // Logística
    
    // CRM roles - usar constantes si están definidas, sino usar strings directos
    $rolProveedorCRM = defined('ROL_NOMBRE_PROVEEDOR_CRM') ? ROL_NOMBRE_PROVEEDOR_CRM : 'Proveedor CRM';
    $rolClienteCRM = defined('ROL_NOMBRE_CLIENTE_CRM') ? ROL_NOMBRE_CLIENTE_CRM : 'Cliente CRM';
    $isProveedorCRM = in_array($rolProveedorCRM, $rolesNombres, true);
    $isClienteCRM = in_array($rolClienteCRM, $rolesNombres, true);
    $isClienteId = in_array(ROL_CLIENTE, $_SESSION['roles'] ?? [], true) || ($_SESSION['rol'] ?? null) == ROL_CLIENTE;
    $isCliente = $isClienteId || in_array(ROL_NOMBRE_CLIENTE, $rolesNombres, true); // Logística
    
    // Redirección del home según rol
    if ($isRepartidor && !$isAdmin) {
        $homeUrl = RUTA_URL . 'seguimiento/listar';
    } elseif (($isProveedorCRM || $isClienteCRM) && !$isAdmin) {
        $homeUrl = RUTA_URL . 'crm/notificaciones';
    } elseif ($isCliente && !$isAdmin) {
        $homeUrl = RUTA_URL . 'pedidos/listar';
    } else {
        $homeUrl = RUTA_URL . 'dashboard';
    }
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="<?= $homeUrl ?>">
            <i class="bi bi-box-seam"></i> App RutaEx-Latam
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <!-- ========================================== -->
                <!-- 1. INICIO - Dashboard (todos los roles excepto Clientes de Logística) -->
                <!-- ========================================== -->
                <?php if ((!$isRepartidor || $isAdmin) && !$isCliente): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= RUTA_URL ?>dashboard">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <?php endif; ?>

                <!-- ========================================== -->
                <!-- 2. OPERACIONES - Pedidos y Seguimiento -->
                <!-- ========================================== -->
                <?php if ($isAdmin || $isProveedor || $isCliente): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarPedidos" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-clipboard-check"></i> <?= $isCliente ? 'Procesar mis Pedidos' : 'Pedidos' ?>
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navbarPedidos">
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>pedidos/listar">
                                <i class="bi bi-list-check"></i> Listado General
                            </a>
                        </li>
                        <?php if ($isAdmin): ?>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>pedidos/crearPedido">
                                <i class="bi bi-plus-circle"></i> Nuevo Pedido
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li class="dropdown-header">Gestión de Actores</li>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>usuarios/listar?rol=Cliente">
                                <i class="bi bi-people"></i> Clientes
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>usuarios/listar?rol=Proveedor">
                                <i class="bi bi-building"></i> Proveedores
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if ($isRepartidor || $isAdmin): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= RUTA_URL ?>seguimiento/listar">
                        <i class="bi bi-geo-alt"></i> Seguimiento
                    </a>
                </li>
                <?php endif; ?>

                <!-- ========================================== -->
                <!-- 3. INVENTARIO - Productos y Stock -->
                <!-- ========================================== -->
                <?php if ($isAdmin || $isProveedor): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarInventario" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-box-seam"></i> Inventario
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navbarInventario">
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>productos/listar">
                                <i class="bi bi-grid"></i> Productos
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>categorias/listar">
                                <i class="bi bi-folder2"></i> Categorías
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>stock/listar">
                                <i class="bi bi-arrow-down-up"></i> Movimientos de Stock
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>stock/kardex">
                                <i class="bi bi-file-earmark-text"></i> Kardex
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>stock/crear">
                                <i class="bi bi-plus-circle"></i> Nuevo Movimiento
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- ========================================== -->
                <!-- 4. CATÁLOGOS - Datos geográficos y monedas -->
                <!-- ========================================== -->
                <?php if ($isAdmin || $isProveedor): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarCatalogos" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-journal-text"></i> Catálogos
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navbarCatalogos">
                        <li class="dropdown-header">Ubicaciones</li>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>paises/listar">
                                <i class="bi bi-globe"></i> Países
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>departamentos/listar">
                                <i class="bi bi-map"></i> Departamentos
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>municipios/listar">
                                <i class="bi bi-pin-map"></i> Municipios
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>barrios/listar">
                                <i class="bi bi-building"></i> Barrios
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li class="dropdown-header">Configuración</li>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>monedas/listar">
                                <i class="bi bi-currency-exchange"></i> Monedas
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>


                <!-- ========================================== -->
                <!-- 6. CRM - Admin y Roles CRM -->
                <!-- ========================================== -->
                <?php if ($isAdmin || $isProveedorCRM || $isClienteCRM): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarCRM" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-diagram-3"></i> CRM Relay
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navbarCRM">
                        <?php if ($isAdmin || $isProveedorCRM): ?>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>crm/dashboard">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>crm/listar">
                                <i class="bi bi-people-fill"></i> Leads
                            </a>
                        </li>
                        <?php endif; ?>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>crm/notificaciones">
                                <i class="bi bi-bell"></i> Notificaciones
                                <?php
                                    require_once "modelo/crm_notification.php";
                                    $navUserId = $_SESSION['user_id'] ?? $_SESSION['idUsuario'] ?? 0;
                                    $unreadMenuCount = $navUserId > 0 ? CrmNotificationModel::contarNoLeidas($navUserId) : 0;
                                    if ($unreadMenuCount > 0):
                                ?>
                                    <span class="badge bg-danger ms-1"><?= $unreadMenuCount ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <?php if ($isAdmin): ?>
                        <li><hr class="dropdown-divider"></li>
                        <li class="dropdown-header">Configuración</li>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>crm/integraciones">
                                <i class="bi bi-plug"></i> Integraciones
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>crm/monitor">
                                <i class="bi bi-activity"></i> Monitor Worker
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>crm/reportes">
                                <i class="bi bi-graph-up"></i> Reportes
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>


            </ul>

            <!-- ========================================== -->
            <!-- MENÚ DERECHO - Administración, Docs y Usuario -->
            <!-- ========================================== -->
            <ul class="navbar-nav mb-2 mb-lg-0">

                <!-- Administración (Solo Admin) -->
                <?php if ($isAdmin): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarAdmin" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-gear"></i> Administración
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarAdmin">
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>usuarios/listar">
                                <i class="bi bi-people"></i> Usuarios
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>auditoria/historial">
                                <i class="bi bi-clock-history"></i> Historial de Auditoría
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li class="dropdown-header">Documentación API</li>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>api/doc/">
                                <i class="bi bi-book"></i> Documentación General
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>api/doc/crmdoc.php">
                                <i class="bi bi-diagram-3"></i> Documentación CRM
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>crm/manual">
                                <i class="bi bi-book-half"></i> Manual CRM
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>crm/database_doc">
                                <i class="bi bi-database"></i> CRM Database Doc
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>crm/logistics_worker_doc">
                                <i class="bi bi-gear-wide-connected"></i> Logistics Worker Doc
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- Notificaciones (Para usuarios excepto repartidores básicos) -->
                <?php if (!$isRepartidor || $isAdmin): ?>
                <?php
                    require_once "modelo/crm_notification.php";
                    require_once "modelo/crm_lead.php"; // Asegurar modelo cargado
                    $navUserId = $_SESSION['user_id'] ?? $_SESSION['idUsuario'] ?? 0;
                    
                    // Mostrar "Leads" solo si es proveedor Y NO es administrador (prioridad admin = notificaciones)
                    $showProviderLeads = $isProveedor && !$isAdmin;
                    
                    if ($showProviderLeads) {
                        $unreadCount = $navUserId > 0 ? CrmLead::contarPendientesProveedor($navUserId) : 0;
                    } else {
                        $unreadCount = $navUserId > 0 ? CrmNotificationModel::contarNoLeidas($navUserId) : 0;
                    }
                ?>
                <li class="nav-item">
                    <a class="nav-link position-relative" href="<?= RUTA_URL ?>crm/notificaciones" title="<?= $showProviderLeads ? 'Leads' : 'Notificaciones' ?>">
                        <?php if ($showProviderLeads): ?>
                            <i class="bi bi-people-fill"></i> Leads
                        <?php else: ?>
                            <i class="bi bi-bell" style="font-size: 1.2rem;"></i>
                        <?php endif; ?>
                        
                        <?php if ($unreadCount > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notification-badge">
                                <?= $unreadCount ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Usuario -->
                <?php
                $userName = $_SESSION['nombre'] ?? null;
                if ($userName):
                ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarUserDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle"></i> <?= htmlspecialchars($userName) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarUserDropdown">
                        <li class="dropdown-header">
                            <small class="text-muted">
                                <?= implode(', ', $rolesNombres) ?>
                            </small>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>usuarios/perfil">
                                <i class="bi bi-person-gear"></i> Editar Perfil
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="<?= RUTA_URL ?>salir">
                                <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>

            </ul>

        </div>
    </div>
</nav>

<div class="container mt-4 caja">