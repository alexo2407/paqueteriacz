<?php
/**
 * Simple File-Based Cache with TTL Support
 * 
 * Provides a lightweight caching mechanism using PHP serialized files.
 * Ideal for caching catalog data that changes infrequently.
 * 
 * Features:
 * - TTL (Time-To-Live) support
 * - Automatic cleanup of expired entries
 * - Namespace support to avoid key collisions
 * - Fallback to callback if cache miss
 * 
 * @example
 * $estados = Cache::remember('estados_pedidos', 3600, function() {
 *     return PedidosModel::obtenerEstados();
 * });
 */

class Cache
{
    /**
     * Cache directory path
     */
    private static $cacheDir = __DIR__ . '/../cache/';

    /**
     * Default TTL in seconds (1 hour)
     */
    private static $defaultTTL = 3600;

    /**
     * Get cached value or execute callback and cache result
     * 
     * @param string $key Cache key
     * @param int $ttl Time to live in seconds
     * @param callable $callback Function to execute on cache miss
     * @return mixed Cached or fresh value
     */
    public static function remember(string $key, int $ttl, callable $callback)
    {
        // Try to get from cache
        $cached = self::get($key);
        
        if ($cached !== null) {
            return $cached;
        }

        // Cache miss - execute callback
        $value = $callback();
        
        // Store in cache
        self::set($key, $value, $ttl);
        
        return $value;
    }

    /**
     * Get value from cache
     * 
     * @param string $key Cache key
     * @return mixed|null Cached value or null if not found/expired
     */
    public static function get(string $key)
    {
        self::ensureCacheDir();
        
        $file = self::getFilePath($key);
        
        if (!file_exists($file)) {
            return null;
        }

        try {
            $contents = file_get_contents($file);
            $data = unserialize($contents);
            
            // Validate data structure
            if (!is_array($data) || !isset($data['expires_at']) || !isset($data['value'])) {
                unlink($file);
                return null;
            }
            
            // Check if expired
            if ($data['expires_at'] < time()) {
                unlink($file);
                return null;
            }
            
            return $data['value'];
        } catch (Exception $e) {
            // If unserialize fails, delete corrupted cache file
            if (file_exists($file)) {
                unlink($file);
            }
            return null;
        }
    }

    /**
     * Store value in cache
     * 
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds
     * @return bool Success status
     */
    public static function set(string $key, $value, int $ttl = null): bool
    {
        self::ensureCacheDir();
        
        if ($ttl === null) {
            $ttl = self::$defaultTTL;
        }

        $file = self::getFilePath($key);
        
        $data = [
            'value' => $value,
            'expires_at' => time() + $ttl,
            'created_at' => time()
        ];

        try {
            $result = file_put_contents($file, serialize($data), LOCK_EX);
            return $result !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Delete specific cache entry
     * 
     * @param string $key Cache key
     * @return bool Success status
     */
    public static function delete(string $key): bool
    {
        $file = self::getFilePath($key);
        
        if (file_exists($file)) {
            return unlink($file);
        }
        
        return true;
    }

    /**
     * Clear all cache entries
     * 
     * @return int Number of files deleted
     */
    public static function clear(): int
    {
        self::ensureCacheDir();
        
        $files = glob(self::$cacheDir . '*.cache');
        $count = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                $count++;
            }
        }
        
        return $count;
    }

    /**
     * Clean up expired cache entries
     * 
     * @return int Number of expired files deleted
     */
    public static function cleanup(): int
    {
        self::ensureCacheDir();
        
        $files = glob(self::$cacheDir . '*.cache');
        $count = 0;
        
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            
            try {
                $contents = file_get_contents($file);
                $data = unserialize($contents);
                
                if (is_array($data) && isset($data['expires_at']) && $data['expires_at'] < time()) {
                    unlink($file);
                    $count++;
                }
            } catch (Exception $e) {
                // Delete corrupted files
                unlink($file);
                $count++;
            }
        }
        
        return $count;
    }

    /**
     * Check if cache entry exists and is valid
     * 
     * @param string $key Cache key
     * @return bool
     */
    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    /**
     * Get cache file path for a key
     * 
     * @param string $key Cache key
     * @return string File path
     */
    private static function getFilePath(string $key): string
    {
        // Sanitize key to prevent directory traversal
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return self::$cacheDir . $safeKey . '.cache';
    }

    /**
     * Ensure cache directory exists
     * 
     * @return void
     */
    private static function ensureCacheDir(): void
    {
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
    }

    /**
     * Get cache statistics
     * 
     * @return array Statistics about cache usage
     */
    public static function stats(): array
    {
        self::ensureCacheDir();
        
        $files = glob(self::$cacheDir . '*.cache');
        $totalSize = 0;
        $validCount = 0;
        $expiredCount = 0;
        
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            
            $totalSize += filesize($file);
            
            try {
                $contents = file_get_contents($file);
                $data = unserialize($contents);
                
                if (is_array($data) && isset($data['expires_at'])) {
                    if ($data['expires_at'] >= time()) {
                        $validCount++;
                    } else {
                        $expiredCount++;
                    }
                }
            } catch (Exception $e) {
                $expiredCount++;
            }
        }
        
        return [
            'total_files' => count($files),
            'valid_entries' => $validCount,
            'expired_entries' => $expiredCount,
            'total_size_bytes' => $totalSize,
            'total_size_kb' => round($totalSize / 1024, 2)
        ];
    }
}
