<?php
/**
 * Script de Mantenimiento de Logs - PaqueteriaCZ
 * 
 * Este script purga registros antiguos de auditoría e historial de accesos
 * para mantener el rendimiento de la base de datos.
 * 
 * Uso: php utils/mantenimiento_logs.php [dias_auditoria] [dias_accesos]
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../modelo/conexion.php';

// Configuración por defecto
$diasAuditoria = isset($argv[1]) ? (int)$argv[1] : 180; // 6 meses
$diasAccesos   = isset($argv[2]) ? (int)$argv[2] : 90;  // 3 meses

echo "--- Iniciando mantenimiento de logs (" . date('Y-m-d H:i:s') . ") ---\n";
echo "Retención Auditoría: $diasAuditoria días\n";
echo "Retención Accesos:   $diasAccesos días\n";

try {
    $db = (new Conexion())->conectar();

    // 1. Purgar Auditoría de Cambios
    $stmtAud = $db->prepare("DELETE FROM auditoria_cambios WHERE created_at < DATE_SUB(NOW(), INTERVAL :dias DAY)");
    $stmtAud->bindValue(':dias', $diasAuditoria, PDO::PARAM_INT);
    $stmtAud->execute();
    $countAud = $stmtAud->rowCount();
    echo "Auditoría: se eliminaron $countAud registros antiguos.\n";

    // 2. Purgar Historial de Accesos
    $stmtAcc = $db->prepare("DELETE FROM historial_accesos WHERE created_at < DATE_SUB(NOW(), INTERVAL :dias DAY)");
    $stmtAcc->bindValue(':dias', $diasAccesos, PDO::PARAM_INT);
    $stmtAcc->execute();
    $countAcc = $stmtAcc->rowCount();
    echo "Accesos: se eliminaron $countAcc registros antiguos.\n";

    // 3. Optimizar tablas para liberar espacio en disco
    echo "Optimizando tablas...\n";
    $db->query("OPTIMIZE TABLE auditoria_cambios, historial_accesos");
    echo "¡Mantenimiento completado con éxito!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log("Fallo en mantenimiento_logs: " . $e->getMessage());
}

echo "--- Fin del proceso ---\n";
