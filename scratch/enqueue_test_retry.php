<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../modelo/conexion.php';
require_once __DIR__ . '/../services/LogisticsQueueService.php';

$db = (new Conexion())->conectar();
$stmt = $db->query("SELECT id, id_provider FROM forwarding_rules WHERE activo = 1 LIMIT 1");
$rule = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$rule) {
    echo "No active rules found.\n";
    exit;
}
$ruleId = $rule['id'];

$stmt = $db->query("SELECT id FROM pedidos LIMIT 1");
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$pedido) {
    echo "No pedidos found.\n";
    exit;
}
$pedidoId = $pedido['id'];

$jobRes = LogisticsQueueService::queue('forwarding_retry', $pedidoId, [
    'id_rule' => $ruleId,
    'id_provider' => $rule['id_provider'],
    'error_message' => 'Initial test failure'
]);
echo "Enqueued job #" . $jobRes['id'] . "\n";
