#!/usr/bin/env php
<?php
/**
 * CRM Worker CLI
 * 
 * Worker para procesar la cola inbox del CRM Relay.
 * 
 * Modos de ejecución:
 *   php cli/crm_worker.php --once    # Procesa una vez y termina (para cron)
 *   php cli/crm_worker.php --loop    # Loop infinito con sleep 3s (para daemon)
 * 
 * Para producción se recomienda usar systemd o supervisor en modo --loop,
 * o configurar cron cada minuto en modo --once.
 */

// Cargar dependencias
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../services/crm_inbox_service.php';

// Configuración
$pollInterval = 3; // Segundos entre polls
$mode = isset($argv[1]) ? $argv[1] : '--loop';

/**
 * Procesa una iteración del worker.
 */
function processIteration() {
    $timestamp = date('Y-m-d H:i:s');
    
    try {
        // Procesar inbox
        echo "[{$timestamp}] Processing inbox...\n";
        $inboxStats = CrmInboxService::procesar(100);
        
        if ($inboxStats['processed'] > 0 || $inboxStats['failed'] > 0) {
            echo "[{$timestamp}] Inbox: processed={$inboxStats['processed']}, failed={$inboxStats['failed']}\n";
            
            if (!empty($inboxStats['errors'])) {
                foreach ($inboxStats['errors'] as $error) {
                    error_log("[CRM Worker][Inbox] $error");
                }
            }
        }
        
        if ($inboxStats['processed'] == 0 && $inboxStats['failed'] == 0) {
            echo "[{$timestamp}] No pending messages.\n";
        }
        
    } catch (Exception $e) {
        $error = "Worker error: " . $e->getMessage();
        error_log("[CRM Worker] $error");
        echo "[{$timestamp}] ERROR: $error\n";
    }
}

// Ejecutar según modo
if ($mode === '--once') {
    echo "[" . date('Y-m-d H:i:s') . "] CRM Worker: Single run mode\n";
    processIteration();
    echo "[" . date('Y-m-d H:i:s') . "] Done.\n";
    exit(0);
}

if ($mode === '--loop') {
    echo "[" . date('Y-m-d H:i:s') . "] CRM Worker: Starting in loop mode (interval: {$pollInterval}s)\n";
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
        $heartbeatFile = __DIR__ . '/../logs/crm_worker.heartbeat';
        if (!file_exists(dirname($heartbeatFile))) {
            mkdir(dirname($heartbeatFile), 0755, true);
        }
        touch($heartbeatFile);

        processIteration();
        
        // Procesar señales si está disponible
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
        
        sleep($pollInterval);
    }
}

// Modo desconocido
echo "Usage: php crm_worker.php [--once|--loop]\n";
echo "\n";
echo "Modes:\n";
echo "  --once    Process queues once and exit (for cron)\n";
echo "  --loop    Continuous processing with {$pollInterval}s interval (for daemon)\n";
echo "\n";
exit(1);
