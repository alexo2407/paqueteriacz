<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../modelo/conexion.php';
require_once __DIR__ . '/../controlador/crm.php';

try {
    $crm = new CRMController();
    
    echo "Stopping logistics_worker...\n";
    $res = $crm->controlWorker('logistics_worker', 'stop');
    print_r($res);
    
    echo "Waiting 5 seconds...\n";
    sleep(5);
    
    echo "Starting logistics_worker...\n";
    $res = $crm->controlWorker('logistics_worker', 'start');
    print_r($res);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
