<?php
start_secure_session();
require_once __DIR__ . '/../../../utils/permissions.php';
if (!isAdmin()) { header('Location: ' . RUTA_URL . 'dashboard'); exit; }

require_once __DIR__ . '/../../../controlador/crm.php';
$ctrl = new CrmController();

$filters = [
    'estado' => $_GET['estado'] ?? '',
    'fecha_desde' => $_GET['fecha_desde'] ?? '',
    'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
    'busqueda' => $_GET['busqueda'] ?? ''
];

$ctrl->exportarLeads($filters);
