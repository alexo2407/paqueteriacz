<?php
/**
 * config.php
 * Configuración global del sistema.
 * Sistema de Control Administrativo y Financiero
 * Iglesia Bautista Nueva Jerusalén
 *
 * Variables de entorno reconocidas (configurar en EasyPanel → Environment):
 *   APP_BASE_PATH  → ruta base de la app.
 *                    Producción (raíz):    "/"
 *                    Local XAMPP:          no definir  (usa "/iglesia/" por defecto)
 *   APP_DEBUG      → "true" activa modo debug. Omitir en producción.
 *   DB_HOST        → host MySQL. En EasyPanel usar el nombre del servicio, ej: "mysql"
 *   DB_SCHEMA      → nombre de la base de datos
 *   DB_USER        → usuario de la base de datos
 *   DB_PASSWORD    → contraseña de la base de datos (puede ser cadena vacía)
 */

// ── Detección de HTTPS compatible con proxy inverso ───────────────────────────
// EasyPanel termina TLS en su proxy (Nginx/Traefik). El contenedor PHP recibe
// las peticiones en HTTP puro, pero el proxy añade el encabezado:
//   X-Forwarded-Proto: https
// Sin leer ese encabezado, $protocolo sería siempre "http://" en producción.
$esHttps = (
    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
    (isset($_SERVER['HTTPS'])                  && $_SERVER['HTTPS'] !== 'off')
);
$protocolo = $esHttps ? 'https://' : 'http://';

// ── Host del servidor ─────────────────────────────────────────────────────────
// HTTP_HOST contiene el dominio correcto en ambos entornos (local y producción).
// strtok elimina el puerto si HTTP_HOST lo incluye, ej: "localhost:8080" → "localhost"
$servidor = strtok($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost', ':');

// ── Puerto: solo añadir si no es estándar Y no estamos detrás de proxy ────────
// Detrás del proxy de EasyPanel, SERVER_PORT refleja el puerto interno del
// contenedor (ej: 80), NO el puerto externo (443). No se debe incluir.
$detrasDeProxy = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) || isset($_SERVER['HTTP_X_FORWARDED_FOR']);
$puerto = '';
if (!$detrasDeProxy) {
    $puertoServidor = (int)($_SERVER['SERVER_PORT'] ?? 80);
    if (!in_array($puertoServidor, [80, 443], true)) {
        $puerto = ':' . $puertoServidor;
    }
}

// ── Ruta base de la aplicación ────────────────────────────────────────────────
// En EasyPanel configurar: APP_BASE_PATH=/
// En XAMPP local NO configurar nada → usará /iglesia/ como fallback.
//
// Normalización garantizada: siempre empieza y termina con "/"
//   APP_BASE_PATH=/         → /
//   APP_BASE_PATH=/iglesia/ → /iglesia/
//   Sin variable            → /iglesia/
$_envBase = getenv('APP_BASE_PATH');
if ($_envBase !== false && $_envBase !== '') {
    $rutaBase = '/' . trim($_envBase, '/');
    $rutaBase = ($rutaBase === '/') ? '/' : $rutaBase . '/';
} else {
    $rutaBase = '/iglesia/'; // Fallback local XAMPP
}

define('RUTA_URL', $protocolo . $servidor . $puerto . $rutaBase);

// ── Parámetros de conexión a la base de datos ─────────────────────────────────
// En EasyPanel, usar el nombre del servicio MySQL como DB_HOST (ej: "mysql" o "db").
// getenv('DB_PASSWORD') puede devolver "" (contraseña vacía válida) o false (no definida).
// Se usa !== false para distinguir ambos casos correctamente.
define('DB_HOST',     getenv('DB_HOST')   ?: 'localhost');
define('DB_SCHEMA',   getenv('DB_SCHEMA') ?: 'nueva_jersularen');
define('DB_USER',     getenv('DB_USER')   ?: 'root');
define('DB_PASSWORD', getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '');
define('DB_CHARSET',  'utf8mb4');

// ── Nombre del sistema ────────────────────────────────────────────
define('SISTEMA_NOMBRE',    'Nueva Jerusalén');
define('SISTEMA_SUBTITULO', 'Control Admin. y Financiero');
define('SISTEMA_VERSION',   '1.0.0');

// ── Roles del sistema ─────────────────────────────────────────────
// Estos valores deben coincidir con la columna `nombre_rol` en la tabla `roles`
define('ROL_NOMBRE_ADMIN',      'ADMINISTRADOR');  // debe coincidir con tabla `roles`
define('ROL_NOMBRE_TESORERO',   'TESORERO');
define('ROL_NOMBRE_SECRETARIO', 'CONSULTA');       // rol equivalente al secretario/consulta

// IDs de roles (según tabla `roles`)
define('ROL_ADMIN',      1);
define('ROL_TESORERO',   2);
define('ROL_SECRETARIO', 3); // id_rol=3 → CONSULTA

// ── Depuración ────────────────────────────────────────────────────
// Activar en EasyPanel con APP_DEBUG=true solo para diagnóstico temporal.
// ⚠ MANTENER AUSENTE O false EN PRODUCCIÓN
define('DEBUG', getenv('APP_DEBUG') === 'true');
