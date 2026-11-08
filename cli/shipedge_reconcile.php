<?php
/**
 * Script CLI de conciliación periódica de órdenes ShipEdge OMS v41
 *
 * Uso:
 *   php cli/shipedge_reconcile.php [--provider=ID] [--limit=50]
 *
 * Propósito:
 *   Consultar periódicamente el estado de las órdenes activas enviadas a ShipEdge
 *   (vía GET /orders/{order}?identify_by=order_number) para detectar cambios de estado,
 *   generación de guía o asignación de tracking_number cuando falle o no esté activo un webhook.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse desde CLI.');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../modelo/conexion.php';
require_once __DIR__ . '/../services/ShipEdgeWebhookAndReconcileService.php';

$options = getopt('', ['provider::', 'limit::']);
$idProvider = isset($options['provider']) ? (int)$options['provider'] : null;
$limit      = isset($options['limit']) ? max(1, min(200, (int)$options['limit'])) : 50;

echo '[' . date('Y-m-d H:i:s') . "] Iniciando conciliación periódica ShipEdge OMS v41...\n";

try {
    $db = (new Conexion())->conectar();
    $summary = ShipEdgeWebhookAndReconcileService::conciliarOrdenesPendientes($db, $idProvider, $limit);

    echo sprintf(
        "[%s] Conciliación completada: proveedores=%d, órdenes consultadas=%d, órdenes actualizadas=%d, errores=%d\n",
        date('Y-m-d H:i:s'),
        $summary['providers_checked'],
        $summary['orders_checked'],
        $summary['orders_updated'],
        count($summary['errors'])
    );

    if (!empty($summary['errors'])) {
        foreach ($summary['errors'] as $err) {
            fwrite(STDERR, "  [ERROR] {$err}\n");
        }
    }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] Error fatal en conciliación ShipEdge: ' . $e->getMessage() . "\n");
    exit(1);
}

