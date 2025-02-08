<?php 
//config
require "config/config.php";


//helper

require_once "../vista/helper/helper.php";

//modelos
require_once "modelo/enlaces.php";
require_once "modelo/usuario.php";
require_once "modelo/articulo.php";

//controladores
require_once "controlador/enlaces.php";
require_once "controlador/usuario.php";
require_once "controlador/articulo.php";




require_once "controlador/template.php";
$plantilla = new backendTemplateController();
$plantilla->template();
