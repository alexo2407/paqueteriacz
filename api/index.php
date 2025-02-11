<?php

header('Content-Type: application/json');

// Cargar configuración y utilidades
require_once __DIR__ .'../config/config.php';
require_once __DIR__ .'utils/responder.php';

// Procesar rutas
if ($_SERVER["REQUEST_METHOD"] == "POST" && $_GET['url'] == 'pedidos/crear') {
    require_once __DIR__ .'pedidos/crear.php';
} elseif ($_SERVER["REQUEST_METHOD"] == "GET" && $_GET['url'] == 'pedidos/listar') {
   require_once __DIR__ .'pedidos/listar.php';
} elseif ($_SERVER["REQUEST_METHOD"] == "GET" && preg_match('/pedidos\/ver\/(\d+)/', $_GET['url'], $matches)) {
    $_GET['id'] = $matches[1];
    require_once __DIR__ .'pedidos/ver.php';
} elseif ($_SERVER["REQUEST_METHOD"] == "PUT" && preg_match('/pedidos\/actualizar\/(\d+)/', $_GET['url'], $matches)) {
    $_GET['id'] = $matches[1];
    require_once __DIR__ .'pedidos/actualizar.php';
} elseif ($_SERVER["REQUEST_METHOD"] == "DELETE" && preg_match('/pedidos\/desactivar\/(\d+)/', $_GET['url'], $matches)) {
    $_GET['id'] = $matches[1];
   require_once __DIR__ .'pedidos/desactivar.php';
} else {
    responder(false, "Ruta no encontrada", null, 404);
}

