<!doctype html>
<html lang="es">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />

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