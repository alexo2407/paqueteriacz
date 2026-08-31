<?php
/**
 * reset_logistica_completo.php
 *
 * Resetea completamente las tablas de movimientos, recepciones, ubicaciones, colectas y rutas
 * de Logística Operativa para reiniciar todas las pruebas desde cero sin trazabilidad residual.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/modelo/conexion.php';

$db = (new Conexion())->conectar();

echo "Iniciando reseteo integral de Logística Operativa...\n";

// 1. Limpiar tablas transaccionales de logística
$tablasLimpiar = [
    'logistica_ubicacion_historial',
    'logistica_recepciones',
    'logistica_ruta_pedidos',
    'logistica_rutas',
    'logistica_colecta_pedidos',
    'logistica_colectas',
    'logistica_custodias',
    'logistica_devolucion_pedidos',
    'logistica_devoluciones',
    'logistica_escaneos',
    'logistica_liquidaciones'
];

$db->exec("SET FOREIGN_KEY_CHECKS = 0;");

foreach ($tablasLimpiar as $tabla) {
    try {
        $db->exec("TRUNCATE TABLE $tabla;");
        echo "✓ Tabla $tabla limpiada (TRUNCATE).\n";
    } catch (Throwable $e) {
        try {
            $db->exec("DELETE FROM $tabla;");
            echo "✓ Tabla $tabla limpiada (DELETE).\n";
        } catch (Throwable $e2) {
            echo "✗ Error en $tabla: " . $e2->getMessage() . "\n";
        }
    }
}

// 2. Re-crear Colecta de Prueba #1 limpia para hacer tests
// Cliente: Test Integracion (ID 42), Proveedor: Test Proovedor (ID 43)
$db->exec("
    INSERT INTO logistica_colectas 
       (id, id_cliente, id_proveedor, fecha, turno, estado, cantidad_esperada, cantidad_escaneada, cantidad_faltante, id_abierta_por, abierta_at, created_at, updated_at)
    VALUES 
       (1, 42, 43, CURDATE(), 'MAÑANA', 'ABIERTA', 5, 0, 0, 1, NOW(), NOW(), NOW())
    ON DUPLICATE KEY UPDATE 
       estado = 'ABIERTA', cantidad_esperada = 5, cantidad_escaneada = 0, cantidad_faltante = 0, updated_at = NOW();
");

// Asociar los 5 pedidos de Test Integración a la Colecta 1
$pedidosTest = [20127, 20128, 20129, 20130, 20131];
foreach ($pedidosTest as $idPed) {
    $db->exec("
        INSERT INTO logistica_colecta_pedidos (id_colecta, id_pedido, resultado, escaneado_at, created_at, updated_at)
        VALUES (1, $idPed, 'ESPERADO', NULL, NOW(), NOW())
        ON DUPLICATE KEY UPDATE resultado = 'ESPERADO', escaneado_at = NULL, updated_at = NOW();
    ");
    // Asegurar que el pedido esté en estado 11 (Pendiente recolección por mensajería)
    $db->exec("UPDATE pedidos SET id_estado = 11, id_cliente = 42, id_proveedor = 43 WHERE id = $idPed;");
}

// Recalcular contadores reales desde logistica_colecta_pedidos
require_once __DIR__ . '/modelo/logistica_operativa/ColectaModel.php';
$colectaModel = new ColectaModel($db);
$colectaModel->recalcularContadores(1);

$db->exec("SET FOREIGN_KEY_CHECKS = 1;");

echo "\n¡Reseteo completado con éxito! Colecta #1 en estado ABIERTA: 5 esperados, 0 escaneados, 0 faltantes.\n";
