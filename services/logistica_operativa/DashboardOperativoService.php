<?php

declare(strict_types=1);

require_once __DIR__ . '/../../modelo/conexion.php';
require_once __DIR__ . '/../../modelo/logistica_operativa/DashboardOperativoModel.php';

/**
 * DashboardOperativoService
 *
 * Agrupa los datos y prepara métricas de KPI para la interfaz gráfica del Dashboard Operativo.
 */
class DashboardOperativoService
{
    private DashboardOperativoModel $model;

    public function __construct(?PDO $db = null)
    {
        $db = $db ?? (new Conexion())->conectar();
        $this->model = new DashboardOperativoModel($db);
    }

    public function obtenerResumenDashboard(): array
    {
        return $this->model->obtenerMétricasResumen();
    }
}
