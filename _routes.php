<?php
require 'config/config.php'; require 'modelo/conexion.php';
$db=(new Conexion())->conectar();
// Buscar rutas de stock por enlace
$rows = $db->query("SELECT * FROM enlaces WHERE enlace LIKE '%stock%' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
