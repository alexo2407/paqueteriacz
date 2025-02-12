<?php 

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


