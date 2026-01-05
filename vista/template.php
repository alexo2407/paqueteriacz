<!doctype html>
<html lang="es">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <?php
    $paginaActual = isset($_GET['enlace']) ? explode("/", $_GET['enlace'])[0] : "inicio";

    cargarRecursos($paginaActual); 
    
    ?>
    <title>Aplicacion App</title>
</head>

<body>



    <?php

    // En el controlador principal

    $mostarEnlaces = new EnlacesController();
    $mostarEnlaces->enlacesController();
    //  var_dump($mostarEnlaces);

    ?>


    
</body>

</html>