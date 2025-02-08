<?php 

//verifico el protocolo de solicitud
$protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
define('RUTA_BACK', $protocolo . $_SERVER['SERVER_NAME'] . '/backend/');
define('RUTA_FRONT', $protocolo . $_SERVER['SERVER_NAME'] .'/');



//agregamos las variables de parametros de conexion a la BD

define('DB_HOST','localhost');
define('DB_SCHEMA','gestorweb');
define('DB_USER','root');
define('DB_PASSWORD','');