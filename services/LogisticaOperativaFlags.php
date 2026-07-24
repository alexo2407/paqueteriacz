<?php

declare(strict_types=1);

/**
 * LogisticaOperativaFlags — Consulta de feature flags del módulo Logística Operativa.
 *
 * Reglas de dependencia:
 *   enabled()           ← LOGISTICA_OPERATIVA_ENABLED
 *   shadowMode()        ← enabled() AND LOGISTICA_OPERATIVA_SHADOW_MODE
 *   canUpdateStates()   ← enabled() AND NOT shadowMode() AND LOGISTICA_OPERATIVA_UPDATE_STATES
 *   inventoryEnabled()  ← enabled() AND NOT shadowMode() AND LOGISTICA_OPERATIVA_INVENTORY_ENABLED
 *   routesEnabled()     ← enabled() AND LOGISTICA_OPERATIVA_ROUTES_ENABLED
 *   settlementEnabled() ← enabled() AND NOT shadowMode() AND LOGISTICA_OPERATIVA_SETTLEMENT_ENABLED
 *
 * Sin conexión a base de datos. Ningún flag modifica datos por sí solo.
 * @see docs/specs/logistica-operativa/v1/08-feature-flags.md
 */
final class LogisticaOperativaFlags
{
    /**
     * Interpreta una constante PHP como booleano de forma segura.
     *
     * Necesario porque phpunit.xml inyecta <const value="false"/> como string,
     * y (bool)"false" === true en PHP. filter_var resuelve esto correctamente.
     */
    private static function resolveFlag(string $name, bool $default): bool
    {
        if (!defined($name)) {
            return $default;
        }

        $value = constant($name);

        if (is_bool($value)) {
            return $value;
        }

        $parsed = filter_var((string) $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $parsed ?? $default;
    }

    /** Habilita el módulo completo. Cuando es false los demás flags son inoperantes. */
    public static function enabled(): bool
    {
        return self::resolveFlag('LOGISTICA_OPERATIVA_ENABLED', false);
    }

    /** Modo sombra: registra sin modificar estados ni inventario. Requiere enabled(). */
    public static function shadowMode(): bool
    {
        return self::enabled()
            && self::resolveFlag('LOGISTICA_OPERATIVA_SHADOW_MODE', true);
    }

    /** Permite cambiar estados de pedidos. Requiere enabled() y shadow desactivado. */
    public static function canUpdateStates(): bool
    {
        return self::enabled()
            && !self::shadowMode()
            && self::resolveFlag('LOGISTICA_OPERATIVA_UPDATE_STATES', false);
    }

    /** Permite movimientos de inventario (Kardex). Requiere enabled() y shadow desactivado. */
    public static function inventoryEnabled(): bool
    {
        return self::enabled()
            && !self::shadowMode()
            && self::resolveFlag('LOGISTICA_OPERATIVA_INVENTORY_ENABLED', false);
    }

    /** Habilita creación de rutas. Requiere enabled() (puede operar en shadow). */
    public static function routesEnabled(): bool
    {
        return self::enabled()
            && self::resolveFlag('LOGISTICA_OPERATIVA_ROUTES_ENABLED', false);
    }

    /** Habilita liquidación de rutas. Requiere enabled() y shadow desactivado. */
    public static function settlementEnabled(): bool
    {
        return self::enabled()
            && !self::shadowMode()
            && self::resolveFlag('LOGISTICA_OPERATIVA_SETTLEMENT_ENABLED', false);
    }

    /**
     * Arreglo de diagnóstico con el estado de todos los flags.
     * No expone información sensible. No imprime ni registra automáticamente.
     *
     * @return array{enabled:bool,shadow_mode:bool,can_update_states:bool,inventory_enabled:bool,routes_enabled:bool,settlement_enabled:bool}
     */
    public static function configuration(): array
    {
        return [
            'enabled'            => self::enabled(),
            'shadow_mode'        => self::shadowMode(),
            'can_update_states'  => self::canUpdateStates(),
            'inventory_enabled'  => self::inventoryEnabled(),
            'routes_enabled'     => self::routesEnabled(),
            'settlement_enabled' => self::settlementEnabled(),
        ];
    }
}
