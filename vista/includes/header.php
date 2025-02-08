<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="<?=RUTA_FRONT?>">Curso PHP</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Administración
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_BACK ?>articulos">Artículos</a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= RUTA_BACK ?>comentarios">Comentarios</a>
                        </li>
                    </ul>
                </li>



                <li class="nav-item">
                    <a class="nav-link" href="<?= RUTA_BACK ?>usuarios">Usuarios</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= RUTA_BACK ?>dashboard">dashboard</a>
                </li>

            </ul>

            <ul class="navbar-nav mb-2 mb-lg-0">
                <!-- <li class="nav-item">
                    <a class="nav-link" href="<?= RUTA_BACK ?>dashboard">Inicio</a>
                </li> -->

                <!-- <li class="nav-item">
                                <a class="nav-link" href="index.php?enlace=registrar">Registrarse</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../acceder.php">Acceder</a>
                            </li> -->

                <?php
                
                if(isset($_SESSION['nombre']))
                {
                echo ' <li class="nav-item">
                    <p class="text-white mt-2">Bienvenido <i class="bi bi-person-circle"> '.$_SESSION['nombre'].'</i></p>
                </li>';
                }
                
                ?>
               
                <li class="nav-item">
                    <a class="nav-link" href="<?= RUTA_BACK ?>salir">Salir</a>
                </li>

            </ul>

        </div>
    </div>
</nav>

<div class="container mt-5 caja">