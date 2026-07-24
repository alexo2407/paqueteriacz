<?php
/**
 * tests/bootstrap.php
 *
 * Bootstrap de pruebas para paqueteriacz.
 *
 * Reglas de seguridad:
 * 1. Activa E_ALL.
 * 2. Carga vendor/autoload.php.
 * 3. Define APP_ENV=testing.
 * 4. NO inicia sesión web.
 * 5. NO ejecuta controladores.
 * 6. NO procesa rutas.
 * 7. NO ejecuta migraciones.
 * 8. NO se conecta automáticamente a la base de datos.
 * 9. phpunit.xml inyecta DB_SCHEMA=paquetes_apppack_test como constante ANTES
 *    de que este bootstrap cargue config/config.php. Así config.php no puede
 *    sobreescribir la constante (define() ignora redefiniciones con @include_once).
 * 10. Expone assertTestDatabase() para pruebas de integración.
 * 11. Rechaza: paqueteriacz, paquetes_apppack, production, prod, cadena vacía.
 * 12. Acepta cualquier base cuyo nombre termine en '_test'.
 * 13. Permite pruebas unitarias sin conexión (sin lanzar excepción).
 */

declare(strict_types=1);

// ── 1. Activar reporte completo de errores ────────────────────────────────
error_reporting(E_ALL);
ini_set('display_errors', '1');

// ── 2. Definir entorno de pruebas ANTES de cualquier include ─────────────
if (!defined('APP_ENV')) {
    define('APP_ENV', 'testing');
}

// ── 3. Autoloader de Composer ─────────────────────────────────────────────
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    throw new RuntimeException(
        'vendor/autoload.php no encontrado. Ejecuta: composer install'
    );
}
require_once $autoload;

// ── 4. Cargar config.php de forma segura (sin conectar a BD) ─────────────
//
// phpunit.xml ya definió DB_SCHEMA='paquetes_apppack_test' como constante PHP
// antes de llegar aquí. Los define() de config.php para DB_* serán ignorados
// silenciosamente (@include_once suprime el E_NOTICE de redefinición).
// NO se abre ninguna conexión a la base de datos aquí.
//
$configFile = __DIR__ . '/../config/config.php';
if (file_exists($configFile)) {
    ob_start();
    @include_once $configFile;
    ob_end_clean();
}

// ── 5. Constantes de base de datos prohibidas ─────────────────────────────
define('DB_SCHEMAS_PROHIBIDOS', [
    'paqueteriacz',
    'paquetes_apppack',
    'production',
    'prod',
]);

/**
 * Protección de seguridad para pruebas que SÍ necesitan base de datos.
 *
 * Llama a esta función en setUp() de cualquier test que abra una conexión PDO.
 * Lanza RuntimeException si la base configurada es productiva o no termina en '_test'.
 *
 * Condiciones de rechazo:
 * - DB_SCHEMA vacía o no definida.
 * - DB_SCHEMA coincide con la lista negra (paquetes_apppack, production, prod, …).
 * - DB_SCHEMA no termina en '_test'.
 *
 * Las pruebas unitarias (PedidoServiceStateTest, LogisticaOperativaFlagsTest)
 * NO deben llamar a esta función porque no necesitan base de datos.
 *
 * @throws RuntimeException si la base no es segura para pruebas
 */
function assertTestDatabase(): void
{
    $dbSchema = defined('DB_SCHEMA') ? DB_SCHEMA : '';

    if ($dbSchema === '') {
        throw new RuntimeException(
            'SEGURIDAD: DB_SCHEMA no está definida. ' .
            'Configura phpunit.xml con <const name="DB_SCHEMA" value="paquetes_apppack_test"/>.'
        );
    }

    foreach (DB_SCHEMAS_PROHIBIDOS as $prohibido) {
        if (strtolower($dbSchema) === strtolower($prohibido)) {
            throw new RuntimeException(
                "SEGURIDAD: la base '{$dbSchema}' está en la lista negra y no puede " .
                'usarse en pruebas. Configura DB_SCHEMA=paquetes_apppack_test en phpunit.xml.'
            );
        }
    }

    if (!str_ends_with(strtolower($dbSchema), '_test')) {
        throw new RuntimeException(
            "SEGURIDAD: la base '{$dbSchema}' no termina en '_test'. " .
            'Las pruebas de integración solo pueden usar bases con sufijo _test. ' .
            'Configura DB_SCHEMA=paquetes_apppack_test en phpunit.xml.'
        );
    }
}

// ── 6. Bootstrap completado ───────────────────────────────────────────────
// Pruebas unitarias (sin BD): ejecutan normalmente sin llamar assertTestDatabase().
// Pruebas de integración (con BD): deben llamar assertTestDatabase() en setUp().
