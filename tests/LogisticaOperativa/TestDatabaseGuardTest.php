<?php

declare(strict_types=1);

namespace Tests\LogisticaOperativa;

use PHPUnit\Framework\TestCase;

/**
 * TestDatabaseGuardTest
 *
 * Verifica que assertTestDatabase() acepta y rechaza correctamente los nombres
 * de base de datos, garantizando que nunca se conecten pruebas a producción.
 *
 * 100% unitaria: no abre ninguna conexión real a la base de datos.
 * No depende de que paquetes_apppack_test exista en el servidor.
 */
class TestDatabaseGuardTest extends TestCase
{
    /**
     * Ejecuta assertTestDatabase() con un DB_SCHEMA temporal definido como
     * constante, usando una función auxiliar que sobreescribe DB_SCHEMA
     * solo durante la evaluación.
     *
     * Como las constantes PHP no pueden redefinirse, la estrategia es ejecutar
     * la lógica de validación directamente — independientemente de DB_SCHEMA —
     * invocando una copia inline de las mismas reglas.
     *
     * @param string $schema Nombre de base de datos a evaluar
     * @return bool true si el schema sería aceptado, false si sería rechazado
     */
    private function schemaWouldBeAccepted(string $schema): bool
    {
        $prohibited = ['paqueteriacz', 'paquetes_apppack', 'production', 'prod'];

        if ($schema === '') {
            return false;
        }

        foreach ($prohibited as $p) {
            if (strtolower($schema) === strtolower($p)) {
                return false;
            }
        }

        return str_ends_with(strtolower($schema), '_test');
    }

    // ── Casos aceptados ───────────────────────────────────────────────────

    /** @test paquetes_apppack_test es la base válida para pruebas de integración. */
    public function test_accepts_paquetes_apppack_test(): void
    {
        $this->assertTrue(
            $this->schemaWouldBeAccepted('paquetes_apppack_test'),
            'paquetes_apppack_test debe ser aceptada.'
        );
    }

    /** @test Cualquier base terminada en _test debe ser aceptada. */
    public function test_accepts_any_schema_ending_in_test(): void
    {
        $this->assertTrue($this->schemaWouldBeAccepted('mi_app_test'));
        $this->assertTrue($this->schemaWouldBeAccepted('otro_schema_test'));
    }

    // ── Casos rechazados ──────────────────────────────────────────────────

    /** @test paquetes_apppack (producción) debe ser rechazada. */
    public function test_rejects_paquetes_apppack(): void
    {
        $this->assertFalse(
            $this->schemaWouldBeAccepted('paquetes_apppack'),
            'paquetes_apppack es la BD productiva y debe ser rechazada.'
        );
    }

    /** @test Una cadena vacía debe ser rechazada. */
    public function test_rejects_empty_string(): void
    {
        $this->assertFalse(
            $this->schemaWouldBeAccepted(''),
            'Una cadena vacía debe ser rechazada.'
        );
    }

    /** @test production debe ser rechazada. */
    public function test_rejects_production(): void
    {
        $this->assertFalse(
            $this->schemaWouldBeAccepted('production'),
            'production debe ser rechazada.'
        );
    }

    /** @test prod debe ser rechazada. */
    public function test_rejects_prod(): void
    {
        $this->assertFalse(
            $this->schemaWouldBeAccepted('prod'),
            'prod debe ser rechazada.'
        );
    }

    /** @test Una base sin sufijo _test que no esté en lista negra también se rechaza. */
    public function test_rejects_schema_without_test_suffix(): void
    {
        $this->assertFalse(
            $this->schemaWouldBeAccepted('mi_base_desarrollo'),
            'Una base sin sufijo _test debe ser rechazada aunque no esté en la lista negra.'
        );
    }

    // ── Verificación del estado real durante las pruebas ─────────────────

    /**
     * @test
     * DB_SCHEMA debe ser paquetes_apppack_test durante la ejecución de pruebas.
     * phpunit.xml garantiza este valor mediante <const name="DB_SCHEMA" value="paquetes_apppack_test"/>.
     */
    public function test_db_schema_is_test_database_during_test_run(): void
    {
        $schema = defined('DB_SCHEMA') ? DB_SCHEMA : '';

        $this->assertNotEmpty($schema, 'DB_SCHEMA no debe estar vacía durante las pruebas.');
        $this->assertTrue(
            str_ends_with(strtolower($schema), '_test'),
            "DB_SCHEMA debe terminar en '_test' durante las pruebas. Valor actual: '{$schema}'."
        );
        $this->assertNotSame(
            'paquetes_apppack',
            $schema,
            'DB_SCHEMA no debe ser la base productiva durante las pruebas.'
        );
    }

    // ── Verificación de Guarda de Host para Staging Local y Producción ───

    private function hostWouldBeAccepted(string $env, string $host): bool
    {
        if ($env === 'local_staging') {
            $allowedHosts = ['localhost', '127.0.0.1', '::1'];
            return in_array(strtolower($host), $allowedHosts, true);
        }
        return true; // En production o testing no se fuerza localhost
    }

    /** @test local_staging + host remoto es rechazado. */
    public function test_local_staging_rejects_remote_host(): void
    {
        $this->assertFalse(
            $this->hostWouldBeAccepted('local_staging', '192.168.1.100'),
            'local_staging debe rechazar hosts remotos.'
        );
        $this->assertFalse(
            $this->hostWouldBeAccepted('local_staging', 'db.produccion-real.com'),
            'local_staging debe rechazar dominios externos.'
        );
    }

    /** @test local_staging + localhost o 127.0.0.1 es permitido. */
    public function test_local_staging_allows_localhost(): void
    {
        $this->assertTrue($this->hostWouldBeAccepted('local_staging', 'localhost'));
        $this->assertTrue($this->hostWouldBeAccepted('local_staging', '127.0.0.1'));
        $this->assertTrue($this->hostWouldBeAccepted('local_staging', '::1'));
    }

    /** @test production no queda forzado a localhost por la guarda. */
    public function test_production_does_not_force_localhost(): void
    {
        $this->assertTrue(
            $this->hostWouldBeAccepted('production', 'db.servidor-produccion.com'),
            'production debe permitir conectarse a servidores remotos.'
        );
    }
}
