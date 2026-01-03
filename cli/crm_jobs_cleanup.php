<?php
/**
 * Job Cleanup Script
 * 
 * Limpia jobs antiguos de la tabla crm_bulk_jobs
 * Ejecutar diariamente con cron/task scheduler
 */

require_once __DIR__ . '/../modelo/conexion.php';

echo "[" . date('Y-m-d H:i:s') . "] Iniciando limpieza de jobs antiguos...\n";

$db = (new Conexion())->conectar();

// 1. Eliminar jobs completados hace más de 7 días
$stmt = $db->prepare("
    DELETE FROM crm_bulk_jobs 
    WHERE status = 'completed' 
    AND completed_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$stmt->execute();
$completedDeleted = $stmt->rowCount();
echo "✓ Jobs completados eliminados: $completedDeleted\n";

// 2. Eliminar jobs fallidos hace más de 30 días
$stmt = $db->prepare("
    DELETE FROM crm_bulk_jobs 
    WHERE status = 'failed' 
    AND completed_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stmt->execute();
$failedDeleted = $stmt->rowCount();
echo "✓ Jobs fallidos eliminados: $failedDeleted\n";

// 3. Marcar como fallidos jobs que llevan más de 1 hora procesando
// (probablemente el worker se cayó)
$stmt = $db->prepare("
    UPDATE crm_bulk_jobs 
    SET status = 'failed',
        error_message = 'Job timeout - worker probablemente se detuvo',
        completed_at = NOW()
    WHERE status = 'processing' 
    AND started_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)
");
$stmt->execute();
$timeoutJobs = $stmt->rowCount();
echo "✓ Jobs con timeout marcados como fallidos: $timeoutJobs\n";

// 4. Alertar sobre jobs en cola por más de 1 hora
// (puede indicar que el worker no está corriendo)
$stmt = $db->query("
    SELECT COUNT(*) FROM crm_bulk_jobs 
    WHERE status = 'queued' 
    AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)
");
$stuckJobs = $stmt->fetchColumn();

if ($stuckJobs > 0) {
    echo "⚠️  ALERTA: $stuckJobs jobs en cola por más de 1 hora!\n";
    echo "   → Verificar que el worker esté corriendo.\n";
    
    // Aquí podrías enviar un email/notificación
    // sendAlert("Worker posiblemente detenido, $stuckJobs jobs atascados");
}

// 5. Estadísticas generales
$stmt = $db->query("
    SELECT 
        status,
        COUNT(*) as total,
        AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as avg_duration_seconds
    FROM crm_bulk_jobs
    WHERE status IN ('completed', 'failed')
    AND completed_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY status
");

echo "\n📊 Estadísticas últimas 24 horas:\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $avgDuration = round($row['avg_duration_seconds'], 2);
    echo "   {$row['status']}: {$row['total']} jobs (promedio {$avgDuration}s)\n";
}

// 6. Total de jobs en la tabla
$stmt = $db->query("SELECT COUNT(*) FROM crm_bulk_jobs");
$totalJobs = $stmt->fetchColumn();
echo "\n📦 Total de jobs en la tabla: $totalJobs\n";

echo "\n[" . date('Y-m-d H:i:s') . "] Limpieza completada.\n";
