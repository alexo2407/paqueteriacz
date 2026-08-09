<?php

declare(strict_types=1);

/**
 * TestDatabaseConnection
 *
 * Abre una conexión PDO exclusiva hacia paquetes_apppack_test.
 *
 * Reglas:
 * - Siempre llama assertTestDatabase() antes de conectar.
 * - Lanza RuntimeException si la base no termina en '_test'.
 * - No usa la conexión global de conexion.php.
 * - Compatible con rollback en pruebas de integración.
 */
class TestDatabaseConnection
{
    private static ?PDO $instance = null;

    /**
     * Devuelve una conexión PDO hacia la base de pruebas.
     * Reutiliza la instancia si ya existe (singleton por proceso).
     *
     * @throws RuntimeException si la base no es segura
     */
    public static function get(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::crear();
        }
        return self::$instance;
    }

    /**
     * Crea siempre una conexión nueva (útil en setUp() cuando se necesita
     * una instancia fresca para cada test con su propia transacción).
     *
     * @throws RuntimeException si la base no es segura
     */
    public static function nueva(): PDO
    {
        return self::get();
    }

    private static function crear(): PDO
    {
        // Seguridad: verifica que DB_SCHEMA sea la base de pruebas
        assertTestDatabase();

        $host   = defined('DB_HOST')     ? DB_HOST     : 'localhost';
        $schema = defined('DB_SCHEMA')   ? DB_SCHEMA   : '';
        $user   = defined('DB_USER')     ? DB_USER     : 'root';
        $pass   = defined('DB_PASSWORD') ? DB_PASSWORD : '';

        $dsn = "mysql:host={$host};dbname={$schema};charset=utf8mb4";

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return $pdo;
    }

    /** Libera la instancia compartida (útil para tearDownAfterClass). */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
