<?php
require 'config/config.php'; require 'modelo/conexion.php';
$db=(new Conexion())->conectar();
print_r($db->query('DESCRIBE estados_pedidos')->fetchAll(PDO::FETCH_ASSOC));
