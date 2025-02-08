<?php 

//verifico el protocolo de solicitud
$protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
define('RUTA_URL', $protocolo . $_SERVER['SERVER_NAME'] . '/');
// define('RUTA_FRONT', $protocolo . $_SERVER['SERVER_NAME'] .'/');



//agregamos las variables de parametros de conexion a la BD

define('DB_HOST','localhost');
define('DB_SCHEMA','');
define('DB_USER','');
define('DB_PASSWORD','');