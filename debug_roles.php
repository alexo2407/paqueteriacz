<?php
// Script temporal para verificar estructura de tabla roles
require_once 'modelo/conexion.php';

$db = (new Conexion())->conectar();

// Ver estructura de tabla roles
echo "=== Estructura de tabla 'roles' ===\n";
$stmt = $db->query("DESCRIBE roles");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($columns);

echo "\n\n=== Datos en tabla 'roles' ===\n";
$stmt = $db->query("SELECT * FROM roles");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($roles);

echo "\n\n=== Roles del usuario 5 ===\n";
$stmt = $db->query("
    SELECT ur.*, r.* 
    FROM usuarios_roles ur
    INNER JOIN roles r ON r.id = ur.id_rol
    WHERE ur.id_usuario = 5
");
$userRoles = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($userRoles);
