<?php 

//config

require "config/config.php";

// Obtener la URL solicitada
$url = isset($_GET['url']) ? $_GET['url'] : 'inicio';

//helper

 require_once "helpers/helpers.php";

//modelos
require_once "modelo/enlaces.php";

$enlace = EnlacesModel::enlacesModel($url);


// Verifica si es una solicitud de API
if (strpos($enlace['archivo'], 'api/') === 0) {
    // Incluir el archivo API correspondiente
    if (file_exists($enlace['archivo'])) {
        include $enlace['archivo'];
    } else {
        // Si no existe el archivo API, devolver error 404
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Endpoint no encontrado"]);
    }
    exit; // Detener flujo de vistas
}


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


