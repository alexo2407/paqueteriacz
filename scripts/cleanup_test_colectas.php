<?php
/**
 * scripts/cleanup_test_colectas.php
 *
 * Script independiente para la eliminación controlada de datos de prueba
 * en las tablas de colectas previo a la migración 026.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../modelo/conexion.php';

try {
    $db = (new Conexion())->conectar();
    $db->beginTransaction();

    echo "=== LIMPIEZA CONTROLADA DE DATOS DE PRUEBA DE COLECTAS ===\n";

    // 1. Eliminar escaneos de colecta
    $stmt1 = $db->exec("DELETE FROM `logistica_escaneos` WHERE `id_colecta` IS NOT NULL");
    echo " - Filas eliminadas en 'logistica_escaneos': {$stmt1}\n";

    // 2. Eliminar relación colecta-pedidos
    $stmt2 = $db->exec("DELETE FROM `logistica_colecta_pedidos`");
    echo " - Filas eliminadas en 'logistica_colecta_pedidos': {$stmt2}\n";

    // 3. Eliminar cabeceras de colectas
    $stmt3 = $db->exec("DELETE FROM `logistica_colectas`");
    echo " - Filas eliminadas en 'logistica_colectas': {$stmt3}\n";

    $db->commit();
    echo "✅ Limpieza de datos de prueba completada exitosamente.\n";
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo "❌ Error al limpiar datos de prueba: " . $e->getMessage() . "\n";
    exit(1);
}
