<?php
require_once __DIR__ . '/../controlador/proveedor.php';

$provCtrl = new ProveedorController();
$proveedores = $provCtrl->listarProveedores();

if (!empty($proveedores)) {
    echo "Proveedores encontrados:\n";
    print_r($proveedores);
} else {
    echo "No se encontraron proveedores o hubo un error.\n";
}