<?php
/**
 * scripts/reset_and_seed_test_users.php
 *
 * Script de mantenimiento para resetear la base de datos de Logística Operativa
 * y dejar únicamente listo el entorno de prueba para:
 *   - Cliente ID 42: Test Integración (testcliente@rutaexlatam.com)
 *   - Proveedor ID 43: Test Proveedor (testpro@rutaexlatam.com)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../modelo/conexion.php';

try {
    $db = (new Conexion())->conectar();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=========================================================================\n";
    echo "  RESET COMPLETO LOGÍSTICA OPERATIVA — ENFOCADO EN USUARIOS ID 42 Y 43\n";
    echo "=========================================================================\n\n";

    $db->exec("SET FOREIGN_KEY_CHECKS = 0");

    // 1. Limpieza de tablas transaccionales de logística
    $tablasTransaccionales = [
        'logistica_ubicacion_historial',
        'logistica_recepciones',
        'logistica_escaneos',
        'logistica_colecta_pedidos',
        'logistica_colectas',
        'logistica_ruta_pedidos',
        'logistica_liquidaciones',
        'logistica_rutas',
        'logistica_devolucion_pedidos',
        'logistica_devoluciones',
        'logistica_custodias'
    ];

    foreach ($tablasTransaccionales as $tabla) {
        $db->exec("TRUNCATE TABLE `{$tabla}`");
        $db->exec("ALTER TABLE `{$tabla}` AUTO_INCREMENT = 1");
        echo "✔ Tabla `{$tabla}` limpiada y AUTO_INCREMENT reiniciado a 1.\n";
    }

    $db->exec("SET FOREIGN_KEY_CHECKS = 1");

    // 2. Preparar los 5 paquetes de prueba en la tabla pedidos para (Cliente: 42, Proveedor: 43)
    $pedidosIds = [20127, 20128, 20129, 20130, 20131];
    $stmtUpd = $db->prepare("
        UPDATE pedidos 
           SET id_cliente = 42,
               id_proveedor = 43,
               id_estado = 11
         WHERE id = ?
    ");

    foreach ($pedidosIds as $pid) {
        $stmtUpd->execute([$pid]);
    }

    echo "\n✔ 5 pedidos de prueba (IDs #20127 a #20131) reseteados a estado 11 (Pendiente Recolección) para Cliente 42 + Proveedor 43.\n";

    echo "\n=========================================================================\n";
    echo "  RESET Y PREPARACIÓN COMPLETADOS CON ÉXITO\n";
    echo "  - Entorno totalmente limpio.\n";
    echo "  - Cliente de prueba: Test Integracion (ID 42)\n";
    echo "  - Proveedor de prueba: Test Proovedor (ID 43)\n";
    echo "  - 5 paquetes listos en estado 'Pendiente Recolección' (#20127 - #20131)\n";
    echo "=========================================================================\n";

} catch (Throwable $e) {
    echo "❌ Error durante el reseteo: " . $e->getMessage() . "\n";
    exit(1);
}
