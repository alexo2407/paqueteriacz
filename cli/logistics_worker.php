#!/usr/bin/env php
<?php
/**
 * Logistics Worker CLI
 * 
 * Worker para procesar la cola de trabajos de logística.
 * 
 * Modos de ejecución:
 *   php cli/logistics_worker.php --once    # Procesa una vez y termina (para cron)
 *   php cli/logistics_worker.php --loop    # Loop infinito con sleep 3s (para daemon)
 * 
 * Para producción se recomienda usar systemd o supervisor en modo --loop,
 * o configurar cron cada minuto en modo --once.
 */

// Cargar dependencias
require_once __DIR__ . '/../services/LogisticsQueueService.php';
require_once __DIR__ . '/processors/BaseProcessor.php';
require_once __DIR__ . '/processors/GenerarGuiaProcessor.php';
require_once __DIR__ . '/processors/ActualizarTrackingProcessor.php';
require_once __DIR__ . '/processors/ValidarDireccionProcessor.php';
require_once __DIR__ . '/processors/NotificarEstadoProcessor.php';

// Configuración
$pollInterval = 3; // Segundos entre polls
$mode = isset($argv[1]) ? $argv[1] : '--loop';

/**
 * Mapeo de tipos de trabajo a sus procesadores.
 */
$processors = [
    'generar_guia' => new GenerarGuiaProcessor(),
    'actualizar_tracking' => new ActualizarTrackingProcessor(),
    'validar_direccion' => new ValidarDireccionProcessor(),
    'notificar_estado' => new NotificarEstadoProcessor()
];

/**
 * Procesa una iteración del worker.
 */
function processIteration($processors) {
    $timestamp = date('Y-m-d H:i:s');
    
    try {
        // Obtener trabajos pendientes
        echo "[{$timestamp}] Checking for pending jobs...\n";
        $jobs = LogisticsQueueService::obtenerPendientes(100);
        
        if (empty($jobs)) {
            echo "[{$timestamp}] No pending jobs.\n";
            return;
        }
        
        $processed = 0;
        $failed = 0;
        $errors = [];
        
        foreach ($jobs as $job) {
            try {
                $jobType = $job['job_type'];
                $jobId = $job['id'];
                
                echo "[{$timestamp}] Processing job #{$jobId} (type: {$jobType}, pedido: {$job['pedido_id']})...\n";
                
                // Verificar que exista un procesador para este tipo de trabajo
                if (!isset($processors[$jobType])) {
                    $error = "No processor found for job type: {$jobType}";
                    error_log("[Logistics Worker] {$error}");
                    LogisticsQueueService::incrementarIntento($jobId, $error);
                    $failed++;
                    $errors[] = $error;
                    continue;
                }
                
                // Marcar como procesando
                LogisticsQueueService::marcarProcesando($jobId);
                
                // Procesar el trabajo
                $processor = $processors[$jobType];
                $result = $processor->process($job);
                
                if ($result['success']) {
                    // Marcar como completado
                    LogisticsQueueService::marcarCompletado($jobId);
                    $processed++;
                    echo "[{$timestamp}] ✓ Job #{$jobId} completed: {$result['message']}\n";
                } else {
                    // Incrementar contador de intentos
                    LogisticsQueueService::incrementarIntento($jobId, $result['message']);
                    $failed++;
                    $errors[] = "Job #{$jobId}: {$result['message']}";
                    echo "[{$timestamp}] ✗ Job #{$jobId} failed: {$result['message']}\n";
                }
                
            } catch (Exception $e) {
                $error = "Exception processing job #{$job['id']}: " . $e->getMessage();
                error_log("[Logistics Worker] {$error}");
                LogisticsQueueService::incrementarIntento($job['id'], $error);
                $failed++;
                $errors[] = $error;
                echo "[{$timestamp}] ✗ {$error}\n";
            }
        }
        
        // Resumen de la iteración
        echo "[{$timestamp}] Iteration complete: processed={$processed}, failed={$failed}\n";
        
        if (!empty($errors)) {
            foreach ($errors as $error) {
                error_log("[Logistics Worker] {$error}");
            }
        }
        
    } catch (Exception $e) {
        $error = "Worker error: " . $e->getMessage();
        error_log("[Logistics Worker] {$error}");
        echo "[{$timestamp}] ERROR: {$error}\n";
    }
}

// Ejecutar según modo
if ($mode === '--once') {
    echo "[" . date('Y-m-d H:i:s') . "] Logistics Worker: Single run mode\n";
    processIteration($processors);
    echo "[" . date('Y-m-d H:i:s') . "] Done.\n";
    exit(0);
}

if ($mode === '--loop') {
    echo "[" . date('Y-m-d H:i:s') . "] Logistics Worker: Starting in loop mode (interval: {$pollInterval}s)\n";
    echo "[" . date('Y-m-d H:i:s') . "] Press Ctrl+C to stop\n\n";
    
    // Signal handling para detención limpia
    if (function_exists('pcntl_signal')) {
        pcntl_signal(SIGTERM, function() {
            echo "\n[" . date('Y-m-d H:i:s') . "] Received SIGTERM, shutting down...\n";
            exit(0);
        });
        
        pcntl_signal(SIGINT, function() {
            echo "\n[" . date('Y-m-d H:i:s') . "] Received SIGINT, shutting down...\n";
            exit(0);
        });
    }
    
    while (true) {
        // Update heartbeat
        $heartbeatFile = __DIR__ . '/../logs/logistics_worker.heartbeat';
        if (!file_exists(dirname($heartbeatFile))) {
            mkdir(dirname($heartbeatFile), 0755, true);
        }
        touch($heartbeatFile);

        processIteration($processors);
        
        // Procesar señales si está disponible
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
        
        sleep($pollInterval);
    }
}

// Modo desconocido
echo "Usage: php logistics_worker.php [--once|--loop]\n";
echo "\n";
echo "Modes:\n";
echo "  --once    Process queues once and exit (for cron)\n";
echo "  --loop    Continuous processing with {$pollInterval}s interval (for daemon)\n";
echo "\n";
exit(1);
