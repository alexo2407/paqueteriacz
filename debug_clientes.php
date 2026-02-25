<?php
require_once 'modelo/conexion.php';
require_once 'modelo/usuario.php';
$clientes = UsuarioModel::listarClientes();
echo "Clientes encontrados: " . count($clientes) . "\n";
foreach ($clientes as $c) {
    echo "  ID=" . $c['id'] . " Nombre=" . $c['nombre'] . "\n";
}

// También verifica los roles en la BD
$db = (new Conexion())->conectar();
$roles = $db->query("SELECT id, nombre_rol FROM roles ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
echo "\nRoles en BD:\n";
foreach ($roles as $r) {
    echo "  ID=" . $r['id'] . " nombre_rol=" . $r['nombre_rol'] . "\n";
}
?>
