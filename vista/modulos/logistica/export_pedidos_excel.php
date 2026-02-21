<?php
/**
 * Exportar pedidos a Excel con filtros actuales.
 * Ruta: /logistica/export_pedidos_excel
 *
 * Este archivo se incluye desde EnlacesController cuando la ruta es
 * logistica/export_pedidos_excel. Llama al método exportarExcel()
 * del LogisticaController y genera la descarga del .xlsx.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar sesión
if (empty($_SESSION['registrado'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Acceso denegado. Por favor inicia sesión.');
}

require_once __DIR__ . '/../../../utils/permissions.php';
require_once __DIR__ . '/../../../controlador/logistica.php';

$ctrl = new LogisticaController();
$ctrl->exportarExcel();
