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
 * 9. Registra DB_SCHEMA para que pruebas puedan validarlo antes de conectar.
 * 10. Expone una función de seguridad: assertTestDatabase().
 * 11. Rechaza nombres: paqueteriacz, paquetes_apppack, production, prod.
 * 12. Permite pruebas unitarias que no necesiten conexión (sin lanzar excepción).
 *
 * IMPORTANTE: Este bootstrap NO bloquea la ejecución por la BD productiva
 * porque las pruebas de regresión de Fase 0 son 100% unitarias (sin conexión).
 * La protección se activa cuando una prueba intenta conectarse vía
 * assertTestDatabase() o cuando DB_SCHEMA_TEST no está definida y se necesita BD.
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
// config.php define constantes como DB_SCHEMA, DB_HOST, etc.
// Solo lo incluimos para que estén disponibles si algún test lo necesita.
// NO abrimos conexión aquí.
//
$configFile = __DIR__ . '/../config/config.php';
if (file_exists($configFile)) {
    ob_start();
    @include_once $configFile;
    ob_end_clean();
}

// ── 5. Constantes de base de datos prohibidas ─────────────────────────────
//
// Lista negra de bases de datos que nunca deben usarse en pruebas.
// Cualquier test que llame a assertTestDatabase() será bloqueado
// si DB_SCHEMA coincide con alguno de estos valores.
//
define('DB_SCHEMAS_PROHIBIDOS', [
    'paqueteriacz',
    'paquetes_apppack',
    'production',
    'prod',
]);

/**
 * Protección de seguridad para pruebas que SÍ necesitan base de datos.
 *
 * Llama a esta función al inicio de cualquier prueba que vaya a abrir
 * una conexión PDO. Lanzará una excepción si la base configurada es
 * una base de datos productiva o no termina en '_test'.
 *
 * Las pruebas unitarias (como PedidoServiceStateTest) NO deben llamar
 * a esta función porque no necesitan base de datos.
 *
 * Ejemplo de uso en un TestCase:
 *   protected function setUp(): void {
 *       assertTestDatabase();
 *   }
 *
 * @throws RuntimeException si la base no es segura para pruebas
 */
function assertTestDatabase(): void
{
    $dbSchema = defined('DB_SCHEMA') ? DB_SCHEMA : '';

    foreach (DB_SCHEMAS_PROHIBIDOS as $prohibido) {
        if (strtolower($dbSchema) === strtolower($prohibido)) {
            throw new RuntimeException(
                "Las pruebas fueron detenidas porque la base configurada " .
                "({$dbSchema}) no parece ser una base de pruebas. " .
                "La base de datos de pruebas debe terminar en '_test'. " .
                "Configura DB_SCHEMA con un nombre como 'paquetes_apppack_test'."
            );
        }
    }

    if ($dbSchema !== '' && !str_ends_with(strtolower($dbSchema), '_test')) {
        throw new RuntimeException(
            "Las pruebas fueron detenidas porque la base configurada " .
            "({$dbSchema}) no parece ser una base de pruebas. " .
            "El nombre de la base de datos debe terminar en '_test' " .
            "para poder ejecutar pruebas de integración de forma segura."
        );
    }

    if ($dbSchema === '') {
        throw new RuntimeException(
            "Las pruebas fueron detenidas porque DB_SCHEMA no está definida. " .
            "Configura una base de datos de pruebas terminada en '_test'."
        );
    }
}

// ── 6. Bootstrap completado ───────────────────────────────────────────────
// Las pruebas unitarias (sin BD) pueden ejecutarse normalmente.
// Las pruebas de integración deben llamar a assertTestDatabase() en setUp().
