<?php
/**
 * Descargar plantilla informativa Excel .xlsx (vacía o con pedidos filtrados).
 * Ruta: /logistica/plantilla_informativa_excel
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['registrado'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Acceso denegado. Por favor inicia sesión.');
}

require_once __DIR__ . '/../../../utils/permissions.php';
require_once __DIR__ . '/../../../controlador/logistica.php';

$ctrl = new LogisticaController();
$ctrl->exportarPlantillaInformativaExcel();
