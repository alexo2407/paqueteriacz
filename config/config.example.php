<?php

// Example config file for Appcz
// Copy this file to config.php and edit values for your environment.

// Determinar dinámicamente la ruta base del proyecto
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath = str_replace('\\', '/', dirname($scriptName));
if ($basePath !== '/') {
    $basePath = rtrim($basePath, '/') . '/';
}
// Ajuste para scripts en subcarpetas
if (str_contains($basePath, '/api/')) $basePath = explode('/api/', $basePath)[0] . '/';
if (str_contains($basePath, '/controlador/')) $basePath = explode('/controlador/', $basePath)[0] . '/';
if (str_contains($basePath, '/cli/')) $basePath = explode('/cli/', $basePath)[0] . '/';

$puerto = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443 ? ':' . $_SERVER['SERVER_PORT'] : '';
define('RUTA_URL', $protocolo . $servidor . $puerto . $basePath);

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
