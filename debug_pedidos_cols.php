<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/modelo/conexion.php';
try {
    $db = (new Conexion())->conectar();
    $stmt = $db->query("DESCRIBE pedidos");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo implode("\n", $cols);
} catch (Exception $e) {
    echo $e->getMessage();
}
