<?php

// Example config file for paqueteriacz
// Copy this file to config.php and edit values for your environment.

// Base URL detection (do not include trailing slash)
$protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$servidor = $_SERVER['SERVER_NAME'] ?? 'localhost';
$puerto = isset($_SERVER['SERVER_PORT']) ? ':' . $_SERVER['SERVER_PORT'] : '';
define('RUTA_URL', $protocolo . $servidor . $puerto . '/paqueteriacz/');

// API keys / secrets (replace with your real secret in local config.php)
define('JWT_SECRET_KEY', 'CHANGE_ME_REPLACE_WITH_SECURE_RANDOM');

define('API_MAP','CHANGE_ME_GOOGLE_MAPS_API_KEY');

// Migration toggle - set to false in production
define('ALLOW_PLAINTEXT_MIGRATION', false);

// Database connection
define('DB_HOST','localhost');
define('DB_SCHEMA','sistema_multinacional');
define('DB_USER','root');
define('DB_PASSWORD','');

// Roles names
define('ROL_NOMBRE_ADMIN', 'Administrador');
define('ROL_NOMBRE_VENDEDOR', 'Vendedor');
define('ROL_NOMBRE_SUPERVISOR', 'Supervisor');
define('ROL_NOMBRE_PROVEEDOR', 'Proveedor');
define('ROL_NOMBRE_REPARTIDOR', 'Repartidor');

// Debug toggle (false in production)
define('DEBUG', false);
