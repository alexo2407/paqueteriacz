<?php
/**
 * scripts/reset_logistica_operativa.php
 *
 * Script para el reseteo seguro de datos operacionales y transaccionales
 * del módulo de Logística Operativa.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../modelo/conexion.php';

try {
    $db = (new Conexion())->conectar();
    
    echo "=== RESETEO DE DATOS OPERACIONALES DE LOGÍSTICA OPERATIVA ===\n\n";

    // Deshabilitar verificación de llaves foráneas para permitir TRUNCATE/DELETE masivo
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");

    $tablasTransaccionales = [
        'logistica_escaneos',
        'logistica_colecta_pedidos',
        'logistica_colectas',
        'logistica_ruta_pedidos',
        'logistica_liquidaciones',
        'logistica_rutas',
        'logistica_devolucion_pedidos',
        'logistica_devoluciones',
        'logistica_custodias',
        'logistica_ubicacion_historial',
        'logistica_recepciones',
    ];

    foreach ($tablasTransaccionales as $tabla) {
        // Verificar si la tabla existe en la base de datos
        $quotedTabla = $db->quote($tabla);
        $checkStmt = $db->query("SHOW TABLES LIKE {$quotedTabla}");
        if ($checkStmt && $checkStmt->fetch()) {
            $deletedRows = $db->exec("DELETE FROM `{$tabla}`");
            // Resetear AUTO_INCREMENT
            $db->exec("ALTER TABLE `{$tabla}` AUTO_INCREMENT = 1");
            echo " [✓] Tabla '{$tabla}': {$deletedRows} registro(s) eliminado(s).\n";
        } else {
            echo " [-] Tabla '{$tabla}': no existe en la base de datos actual.\n";
        }
    }

    // Rehabilitar verificación de llaves foráneas
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "\n✅ Reseteo de datos operacionales completado exitosamente.\n";
    echo "📌 Los catálogos 'logistica_bodegas' y 'logistica_ubicaciones' se mantienen intactos.\n";

} catch (Exception $e) {
    if (isset($db)) {
        $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    }
    echo "❌ Error durante el reseteo de datos: " . $e->getMessage() . "\n";
    exit(1);
}
