<?php 

// Verificar si las variables del servidor están definidas
$protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$servidor = $_SERVER['SERVER_NAME'] ?? 'localhost';

$puerto = isset($_SERVER['SERVER_PORT']) ? ':' . $_SERVER['SERVER_PORT'] : '';
define('RUTA_URL', $protocolo . $servidor . $puerto . '/paqueteriacz/');
// define('RUTA_FRONT', $protocolo . $servidor .'/');


// Claves y configuración adicional
define("API_MAP",'AIzaSyBTjuPpkTWWePCndprG532i9GhEdBRr_a0');

//clave sel API

define('JWT_SECRET_KEY', 'AIzaSyBTjuPpkTWWePCndprG532i9GhEdBRr_a0');


//agregamos las variables de parametros de conexion a la BD

// Toggle para habilitar migración automática de contraseñas en texto plano.
// Por seguridad, dejar en false en producción. Activar temporalmente en dev si no puedes iniciar sesión.
define('ALLOW_PLAINTEXT_MIGRATION', true);

define('DB_HOST','localhost');
define('DB_SCHEMA','sistema_multinacional');
define('DB_USER','root');
define('DB_PASSWORD','');

// nombres de roles comunes en la tabla roles
define('ROL_NOMBRE_ADMIN', 'Administrador');
define('ROL_NOMBRE_VENDEDOR', 'Vendedor');
define('ROL_NOMBRE_SUPERVISOR', 'Supervisor');
define('ROL_NOMBRE_PROVEEDOR', 'Proveedor');
define('ROL_NOMBRE_REPARTIDOR', 'Repartidor');

// Debug toggle: activar solo en entornos de desarrollo. Cuando está en true,
// algunos controladores escribirán logs sanitizados para facilitar la depuración.
// IMPORTANTE: dejar en false en producción.
// Atención: habilita logs de depuración solo en entornos de desarrollo.
// Por seguridad, dejar en false en producción.
define('DEBUG', false);