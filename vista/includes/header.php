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
            <i class="bi bi-box-seam"></i> Paquetería CruzValle
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
                <!-- 5. ADMINISTRACIÓN - Solo Admin -->
                <!-- ========================================== -->
                <?php if ($isAdmin): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarAdmin" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-gear"></i> Administración
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navbarAdmin">
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
                    </ul>
                </li>
                <?php endif; ?>

            </ul>

            <!-- ========================================== -->
            <!-- MENÚ DERECHO - Usuario y Ayuda -->
            <!-- ========================================== -->
            <ul class="navbar-nav mb-2 mb-lg-0">
                
                <!-- Documentación API -->
                <li class="nav-item">
                    <a class="nav-link text-muted" href="<?= RUTA_URL ?>/api/doc/" title="Documentación API">
                        <i class="bi bi-code-slash"></i>
                    </a>
                </li>

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