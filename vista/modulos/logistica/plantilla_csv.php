<?php
/**
 * Descargar plantilla CSV pre-rellena con los pedidos filtrados.
 * Ruta: /logistica/plantilla_csv
 *
 * Este archivo se incluye desde EnlacesController cuando la ruta es
 * logistica/plantilla_csv. Llama al método exportarPlantillaCSV()
 * del LogisticaController y genera la descarga del .csv listo para
 * editar y subir al bulk-update.
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
$ctrl->exportarPlantillaCSV();
