<?php

declare(strict_types=1);

require_once __DIR__ . '/../../modelo/conexion.php';
require_once __DIR__ . '/../../modelo/logistica_operativa/LiquidacionModel.php';
require_once __DIR__ . '/../../modelo/logistica_operativa/RutaModel.php';

/**
 * LiquidacionService
 *
 * Lógica de negocio para calcular el arqueo de rutas, verificar entregas vs devoluciones y registrar la liquidación.
 */
class LiquidacionService
{
    private LiquidacionModel $liquidacionModel;
    private RutaModel $rutaModel;

    public function __construct(?PDO $db = null)
    {
        $db = $db ?? (new Conexion())->conectar();
        $this->liquidacionModel = new LiquidacionModel($db);
        $this->rutaModel = new RutaModel($db);
    }

    public function obtenerLiquidaciones(array $filtros = []): array
    {
        return $this->liquidacionModel->listar($filtros);
    }

    public function obtenerDetalle(int $id): ?array
    {
        return $this->liquidacionModel->obtenerPorId($id);
    }

    public function liquidarRuta(
        int $idRuta,
        int $idOperador,
        float $codRecibido,
        ?string $observaciones = null
    ): int {
        $ruta = $this->rutaModel->obtenerPorId($idRuta);
        if (!$ruta) {
            throw new Exception("La ruta especificada no existe.");
        }

        $pedidosRuta = $this->rutaModel->obtenerPedidosDeRuta($idRuta);
        
        $codEsperado = 0.0;
        $entregados = 0;
        $devueltos = 0;
        $reprogramados = 0;

        foreach ($pedidosRuta as $p) {
            $estado = $p['estado_entrega'] ?? 'PENDIENTE';
            if ($estado === 'ENTREGADO') {
                $entregados++;
                $codEsperado += (float) ($p['monto_cod'] ?? 0);
            } elseif ($estado === 'DEVUELTO') {
                $devueltos++;
            } elseif ($estado === 'INCIDENCIA' || $estado === 'PENDIENTE') {
                $reprogramados++;
            }
        }

        $diferencia = round($codRecibido - $codEsperado, 2);
        $estadoLiq = ($diferencia == 0.0) ? 'LIQUIDADA' : 'CON_OBSERVACIONES';

        return $this->liquidacionModel->crear(
            $idRuta,
            $idOperador,
            $codEsperado,
            $codRecibido,
            $diferencia,
            $entregados,
            $devueltos,
            $reprogramados,
            $observaciones,
            $estadoLiq
        );
    }
}
