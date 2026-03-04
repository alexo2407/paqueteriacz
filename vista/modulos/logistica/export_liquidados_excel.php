<?php
/**
 * Exportar pedidos LIQUIDADOS a Excel con los filtros del tab Liquidados.
 * Ruta: /logistica/export_liquidados_excel
 *
 * Este archivo se incluye desde EnlacesController cuando la ruta es
 * logistica/export_liquidados_excel. Llama al método exportarLiquidadosExcel()
 * del LogisticaController y genera la descarga del .xlsx.
 *
 * Parámetros GET aceptados:
 *   liq_desde  — fecha inicio liquidación (Y-m-d)
 *   liq_hasta  — fecha fin liquidación (Y-m-d)
 *   liq_search — búsqueda libre
 *   id_cliente — filtro por cliente (opcional, solo para proveedores)
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
$ctrl->exportarLiquidadosExcel();
