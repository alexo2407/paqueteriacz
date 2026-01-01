<?php
// Verificar que los leads se crearon correctamente
require_once 'modelo/conexion.php';

$db = (new Conexion())->conectar();

echo "=== Leads creados en crm_leads ===\n";
$stmt = $db->query("SELECT * FROM crm_leads ORDER BY id DESC LIMIT 5");
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($leads);

echo "\n\n=== Estado de crm_inbox ===\n";
$stmt = $db->query("SELECT id, status, processed_at FROM crm_inbox ORDER BY id DESC LIMIT 5");
$inbox = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($inbox);
