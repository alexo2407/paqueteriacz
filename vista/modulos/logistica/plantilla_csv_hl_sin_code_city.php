<?php
/**
 * Descargar plantilla CSV con pedidos sin code_city para carga masiva HL Express.
 * Ruta: /logistica/plantilla_csv_hl_sin_code_city
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
$ctrl->exportarPlantillaHLSinCodeCity();
