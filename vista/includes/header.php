<?php
    // Determinar la URL del logo según el rol del usuario
    $rolesNombres = $_SESSION['roles_nombres'] ?? [];
    $isRepartidor = in_array(ROL_NOMBRE_REPARTIDOR, $rolesNombres, true);
    $isAdmin = in_array(ROL_NOMBRE_ADMIN, $rolesNombres, true);
    $isProveedor = in_array(ROL_NOMBRE_PROVEEDOR, $rolesNombres, true);
    
    // Si es repartidor (y no es admin), ir a seguimiento; caso contrario, ir a dashboard
    $homeUrl = ($isRepartidor && !$isAdmin) ? RUTA_URL . 'seguimiento/listar' : RUTA_URL . 'dashboard';
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
                <!-- 1. INICIO - Dashboard (todos los roles) -->
                <!-- ========================================== -->
                <?php if (!$isRepartidor || $isAdmin): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= RUTA_URL ?>dashboard">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <?php endif; ?>

                <!-- ========================================== -->
                <!-- 2. OPERACIONES - Pedidos y Seguimiento -->
                <!-- ========================================== -->
                <?php if ($isAdmin || $isProveedor): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= RUTA_URL ?>pedidos/listar">
                        <i class="bi bi-clipboard-check"></i> Pedidos
                    </a>
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
                <!-- 6. CRM - Solo Admin (Nuevo Módulo) -->
                <!-- ========================================== -->
                <?php if ($isAdmin): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarCRM" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-diagram-3"></i> CRM Relay
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navbarCRM">
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
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>crm/notificaciones">
                                <i class="bi bi-bell"></i> Notificaciones
                                <?php
                                    require_once "modelo/crm_notification.php";
                                    $userId = $_SESSION['usuario_id'] ?? 0;
                                    $unreadMenuCount = $userId > 0 ? CrmNotificationModel::contarNoLeidas($userId) : 0;
                                    if ($unreadMenuCount > 0):
                                ?>
                                    <span class="badge bg-danger ms-1"><?= $unreadMenuCount ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
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
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>crm/reportes">
                                <i class="bi bi-graph-up"></i> Reportes
                            </a>
                        </li>
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
                    </ul>
                </li>
                <?php endif; ?>

                <!-- Notificaciones (Para usuarios excepto repartidores básicos) -->
                <?php if (!$isRepartidor || $isAdmin): ?>
                <?php
                    require_once "modelo/crm_notification.php";
                    $userId = $_SESSION['usuario_id'] ?? 0;
                    $unreadCount = $userId > 0 ? CrmNotificationModel::contarNoLeidas($userId) : 0;
                ?>
                <li class="nav-item">
                    <a class="nav-link position-relative" href="<?= RUTA_URL ?>crm/notificaciones" title="Notificaciones">
                        <i class="bi bi-bell" style="font-size: 1.2rem;"></i>
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