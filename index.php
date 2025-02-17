<?php 


$ruta = isset($_GET['enlace']) ? explode("/", $_GET['enlace']) : ['inicio'];

if ($ruta[0] === "api") {
    // Incluir el archivo API correspondiente sin cargar el template
    $archivoAPI = "api/" . (isset($ruta[1]) ? $ruta[1] : "index") . "/" . (isset($ruta[2]) ? $ruta[2] : "index") . ".php";
    
    if (file_exists($archivoAPI)) {
        include $archivoAPI;
    } else {
        // Manejar error si el archivo API no existe
        header("HTTP/1.0 404 Not Found");
        echo json_encode(["error" => "Endpoint not found"]);
    }
    exit; // Detener la ejecución
}




//config

require "config/config.php";


//helper

 require_once "helpers/helpers.php";

//modelos
require_once "modelo/enlaces.php";




require_once "modelo/usuario.php";
require_once "modelo/cliente.php";
require_once "modelo/pedido.php";

//controladores
require_once "controlador/enlaces.php";
require_once "controlador/usuario.php";
require_once "controlador/cliente.php";
require_once "controlador/pedido.php";




require_once "controlador/template.php";

$plantilla = new templateController();
$plantilla->template();


