<?php
    // Determinar la URL del logo según el rol del usuario
    $rolesNombres = $_SESSION['roles_nombres'] ?? [];
    $isRepartidor = in_array(ROL_NOMBRE_REPARTIDOR, $rolesNombres, true);
    $isAdmin = in_array(ROL_NOMBRE_ADMIN, $rolesNombres, true);
    
    // Si es repartidor (y no es admin), ir a seguimiento; caso contrario, ir a dashboard
    $homeUrl = ($isRepartidor && !$isAdmin) ? RUTA_URL . 'seguimiento/listar' : RUTA_URL . 'dashboard';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="<?= $homeUrl ?>">Paqueteria CruzValle</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <?php $rolesNombres = $_SESSION['roles_nombres'] ?? []; if (!is_array($rolesNombres)) { $rolesNombres = []; } ?>
                <?php if (in_array(ROL_NOMBRE_ADMIN, $rolesNombres, true)): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Administración
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>usuarios/listar">Usuarios</a>
                        </li>
                  
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>monedas/listar">Monedas</a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>paises/listar">Países</a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>departamentos/listar">Departamentos</a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>municipios/listar">Municipios</a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>barrios/listar">Barrios</a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>

                <!-- Menú de Catálogos para Proveedores -->
                <?php if (in_array(ROL_NOMBRE_PROVEEDOR, $rolesNombres, true) && !in_array(ROL_NOMBRE_ADMIN, $rolesNombres, true)): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarCatalogos" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Catálogos
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navbarCatalogos">
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>monedas/listar">Monedas</a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>paises/listar">Países</a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>departamentos/listar">Departamentos</a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>municipios/listar">Municipios</a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>barrios/listar">Barrios</a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>


                <!-- Pedidos: Admin y Proveedor -->
                <?php if (in_array(ROL_NOMBRE_ADMIN, $rolesNombres, true) || in_array(ROL_NOMBRE_PROVEEDOR, $rolesNombres, true)): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= RUTA_URL ?>pedidos/listar">Pedidos</a>
                </li>
                <?php endif; ?>

                <!-- Stock y Productos: Admin y Proveedor -->
                <?php if (in_array(ROL_NOMBRE_ADMIN, $rolesNombres, true) || in_array(ROL_NOMBRE_PROVEEDOR, $rolesNombres, true)): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= RUTA_URL ?>stock/listar">Stock</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= RUTA_URL ?>productos/listar">Productos</a>
                </li>
                <?php endif; ?>
                <!-- Proveedores ahora se administran desde Usuarios (rol Proveedor) -->
                <?php if (in_array(ROL_NOMBRE_REPARTIDOR, $rolesNombres, true) || in_array(ROL_NOMBRE_ADMIN, $rolesNombres, true)): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= RUTA_URL ?>seguimiento/listar">Seguimiento</a>
                </li>
                <?php endif; ?>

            </ul>

            <ul class="navbar-nav mb-2 mb-lg-0">
                <!-- <li class="nav-item">
                    <a class="nav-link" href="<?= RUTA_URL ?>dashboard">Inicio</a>
                </li> -->

                <!-- <li class="nav-item">
                                <a class="nav-link" href="index.php?enlace=registrar">Registrarse</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../acceder.php">Acceder</a>
                            </li> -->


                <?php
                // Mostrar dropdown de usuario si la sesión está iniciada
                $userName = $_SESSION['nombre'] ?? null;
                if ($userName) {
                    echo '
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarUserDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i> ' . htmlspecialchars($userName) . '
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarUserDropdown">
                            <li><a class="dropdown-item" href="' . RUTA_URL . 'usuarios/perfil"><i class="bi bi-person-gear"></i> Editar Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="' . RUTA_URL . 'salir"><i class="bi bi-box-arrow-right"></i> Salir</a></li>
                        </ul>
                    </li>';
                }
                ?>
                 <li class="nav-item">
                    <a class="nav-link" href="<?= RUTA_URL ?>/api/doc/">Documentación API´S</a>
                </li>
               

            </ul>

        </div>
    </div>
</nav>

<div class="container mt-5 caja">