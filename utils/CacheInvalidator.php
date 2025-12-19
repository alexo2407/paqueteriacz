<?php
/**
 * Cache Invalidation Helper
 * 
 * Provides convenient methods to invalidate cache when catalog data changes.
 * Include this file in controllers that modify catalog data.
 * 
 * Usage:
 * require_once __DIR__ . '/../utils/CacheInvalidator.php';
 * CacheInvalidator::invalidateProductos();
 */

require_once __DIR__ . '/Cache.php';

class CacheInvalidator
{
    /**
     * Invalidate estados cache
     */
    public static function invalidateEstados(): void
    {
        Cache::delete('estados_pedidos');
    }

    /**
     * Invalidate vendedores/repartidores cache
     */
    public static function invalidateVendedores(): void
    {
        Cache::delete('vendedores');
        Cache::delete('repartidores');
    }

    /**
     * Invalidate productos cache
     */
    public static function invalidateProductos(): void
    {
        Cache::delete('productos');
    }

    /**
     * Invalidate proveedores cache
     */
    public static function invalidateProveedores(): void
    {
        Cache::delete('proveedores');
    }

    /**
     * Invalidate monedas cache
     */
    public static function invalidateMonedas(): void
    {
        Cache::delete('monedas');
    }

    /**
     * Invalidate all catalog caches
     */
    public static function invalidateAll(): void
    {
        self::invalidateEstados();
        self::invalidateVendedores();
        self::invalidateProductos();
        self::invalidateProveedores();
        self::invalidateMonedas();
    }
}
