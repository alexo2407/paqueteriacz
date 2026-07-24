<?php

declare(strict_types=1);

namespace Tests\LogisticaOperativa;

use PHPUnit\Framework\TestCase;

// Carga LogisticaOperativaFlags (clase global sin namespace, sin conexión a BD).
require_once dirname(__DIR__, 2) . '/services/LogisticaOperativaFlags.php';

/**
 * Pruebas unitarias para LogisticaOperativaFlags.
 *
 * Contexto: phpunit.xml inyecta las constantes antes del bootstrap con valores:
 *   LOGISTICA_OPERATIVA_ENABLED           = "false"
 *   LOGISTICA_OPERATIVA_SHADOW_MODE       = "true"   (pero inoperante sin enabled)
 *   LOGISTICA_OPERATIVA_UPDATE_STATES     = "false"
 *   LOGISTICA_OPERATIVA_INVENTORY_ENABLED = "false"
 *   LOGISTICA_OPERATIVA_ROUTES_ENABLED    = "false"
 *   LOGISTICA_OPERATIVA_SETTLEMENT_ENABLED= "false"
 *
 * Las constantes PHP no se pueden redefinir en el mismo proceso, por lo que
 * todas las pruebas operan sobre el estado seguro por defecto (módulo desactivado).
 * Sin conexión a base de datos. Sin dependencia de variables de entorno del SO.
 */
class LogisticaOperativaFlagsTest extends TestCase
{
    // ── Sección 1: Módulo desactivado por defecto ─────────────────────────────

    /** @test */
    public function test_module_is_disabled_by_default(): void
    {
        $this->assertFalse(\LogisticaOperativaFlags::enabled());
    }

    /** @test El shadow_mode requiere enabled(); con módulo off debe ser false. */
    public function test_shadow_mode_false_when_module_disabled(): void
    {
        $this->assertFalse(\LogisticaOperativaFlags::shadowMode());
    }

    /** @test */
    public function test_cannot_update_states_when_module_disabled(): void
    {
        $this->assertFalse(\LogisticaOperativaFlags::canUpdateStates());
    }

    /** @test */
    public function test_inventory_disabled_when_module_disabled(): void
    {
        $this->assertFalse(\LogisticaOperativaFlags::inventoryEnabled());
    }

    /** @test */
    public function test_routes_disabled_when_module_disabled(): void
    {
        $this->assertFalse(\LogisticaOperativaFlags::routesEnabled());
    }

    /** @test */
    public function test_settlement_disabled_when_module_disabled(): void
    {
        $this->assertFalse(\LogisticaOperativaFlags::settlementEnabled());
    }

    // ── Sección 2: Invariantes de dependencia (shadow bloquea destructivos) ───

    /**
     * @test
     * Invariante: si canUpdateStates() fuera true, shadowMode() debe ser false.
     * En el estado por defecto ambos son false, lo que satisface la invariante.
     */
    public function test_shadow_mode_blocks_state_updates(): void
    {
        $this->assertFalse(
            \LogisticaOperativaFlags::canUpdateStates() && \LogisticaOperativaFlags::shadowMode(),
            'canUpdateStates() y shadowMode() no pueden ser true simultáneamente.'
        );
    }

    /**
     * @test
     * Invariante: inventoryEnabled() y shadowMode() no pueden ser true simultáneamente.
     */
    public function test_shadow_mode_blocks_inventory(): void
    {
        $this->assertFalse(
            \LogisticaOperativaFlags::inventoryEnabled() && \LogisticaOperativaFlags::shadowMode(),
            'inventoryEnabled() y shadowMode() no pueden ser true simultáneamente.'
        );
    }

    /**
     * @test
     * Invariante: settlementEnabled() y shadowMode() no pueden ser true simultáneamente.
     */
    public function test_shadow_mode_blocks_settlement(): void
    {
        $this->assertFalse(
            \LogisticaOperativaFlags::settlementEnabled() && \LogisticaOperativaFlags::shadowMode(),
            'settlementEnabled() y shadowMode() no pueden ser true simultáneamente.'
        );
    }

    // ── Sección 3: configuration() — estructura y tipos ──────────────────────

    /** @test configuration() devuelve exactamente las seis claves esperadas. */
    public function test_configuration_returns_all_keys(): void
    {
        $config = \LogisticaOperativaFlags::configuration();

        $expectedKeys = [
            'enabled', 'shadow_mode', 'can_update_states',
            'inventory_enabled', 'routes_enabled', 'settlement_enabled',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $config);
        }

        $this->assertCount(count($expectedKeys), $config);
    }

    /** @test Todos los valores de configuration() deben ser booleanos. */
    public function test_configuration_values_are_booleans(): void
    {
        foreach (\LogisticaOperativaFlags::configuration() as $key => $value) {
            $this->assertIsBool($value, "'{$key}' debe ser bool.");
        }
    }

    /** @test Con módulo desactivado, configuration() devuelve todos false. */
    public function test_configuration_all_false_when_disabled(): void
    {
        foreach (\LogisticaOperativaFlags::configuration() as $key => $value) {
            $this->assertFalse($value, "'{$key}' debe ser false con módulo desactivado.");
        }
    }

    /** @test configuration() es coherente con los métodos individuales. */
    public function test_configuration_matches_individual_methods(): void
    {
        $c = \LogisticaOperativaFlags::configuration();

        $this->assertSame(\LogisticaOperativaFlags::enabled(),           $c['enabled']);
        $this->assertSame(\LogisticaOperativaFlags::shadowMode(),        $c['shadow_mode']);
        $this->assertSame(\LogisticaOperativaFlags::canUpdateStates(),   $c['can_update_states']);
        $this->assertSame(\LogisticaOperativaFlags::inventoryEnabled(),  $c['inventory_enabled']);
        $this->assertSame(\LogisticaOperativaFlags::routesEnabled(),     $c['routes_enabled']);
        $this->assertSame(\LogisticaOperativaFlags::settlementEnabled(), $c['settlement_enabled']);
    }
}
