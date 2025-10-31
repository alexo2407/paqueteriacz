<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="<?=RUTA_URL?>">Paqueteria CruzValle</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <?php $rolNombre = $_SESSION['rol_nombre'] ?? null; ?>
                <?php if ($rolNombre === ROL_NOMBRE_ADMIN): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Administración
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>usuarios/listar">Usuarios</a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_URL ?>stock/listar">Stock</a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>



                <?php if ($rolNombre === ROL_NOMBRE_ADMIN): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?= RUTA_URL ?>pedidos/listar">pedidos</a>
                </li>
                <?php endif; ?> <!-- 
                <li class="nav-item">
                    <a class="nav-link" href="<?= RUTA_URL ?>productos/listars">productos</a>
                </li>-->
                <!-- Proveedores ahora se administran desde Usuarios (rol Proveedor) -->
                <?php if ($rolNombre === ROL_NOMBRE_REPARTIDOR): ?>
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
                // Mostrar nombre de usuario si la sesión está iniciada
                $userName = $_SESSION['nombre'] ?? null;
                if ($userName) {
                    echo "<li class=\"nav-item\"><p class=\"text-white mt-2\">Bienvenido <i class=\"bi bi-person-circle\"> " . htmlspecialchars($userName) . "</i></p></li>";
                }
                ?>
                 <li class="nav-item">
                    <a class="nav-link" href="<?= RUTA_URL ?>/api/doc/">Documentación API´S</a>
                </li>
               
                <li class="nav-item">
                    <a class="nav-link" href="<?= RUTA_URL ?>salir">Salir</a>
                </li>

            </ul>

        </div>
    </div>
</nav>

<div class="container mt-5 caja">