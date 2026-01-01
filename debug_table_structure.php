<?php
require_once 'modelo/conexion.php';

$db = (new Conexion())->conectar();
$stmt = $db->query('DESCRIBE crm_leads');

echo "Estructura de la tabla crm_leads:\n\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
