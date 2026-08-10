<?php

declare(strict_types=1);

require_once __DIR__ . '/../../modelo/conexion.php';
require_once __DIR__ . '/../../modelo/logistica_operativa/CampoModel.php';
require_once __DIR__ . '/../../modelo/logistica_operativa/RutaModel.php';
require_once __DIR__ . '/../../services/PedidoService.php';

/**
 * CampoService
 *
 * Servicio para procesar firmas, evidencias y cambios de estado en tiempo real desde la vista del repartidor.
 */
class CampoService
{
    private CampoModel $campoModel;
    private RutaModel $rutaModel;

    public function __construct(?PDO $db = null)
    {
        $db = $db ?? (new Conexion())->conectar();
        $this->campoModel = new CampoModel($db);
        $this->rutaModel = new RutaModel($db);
    }

    public function obtenerHojaDeRuta(int $idRuta): array
    {
        $ruta = $this->rutaModel->obtenerPorId($idRuta);
        if (!$ruta) {
            throw new Exception("Ruta no encontrada.");
        }

        $pedidos = $this->rutaModel->obtenerPedidosDeRuta($idRuta);
        return [
            'ruta' => $ruta,
            'pedidos' => $pedidos
        ];
    }

    public function completarEntrega(
        int $idRuta,
        int $idPedido,
        ?string $firmaBase64,
        ?string $evidenciaFotoUrl,
        ?float $lat,
        ?float $lng,
        ?string $notas
    ): bool {
        // Actualizar en logistica_ruta_pedidos
        $res = $this->campoModel->registrarEntrega($idRuta, $idPedido, $firmaBase64, $evidenciaFotoUrl, $lat, $lng, $notas);

        // Actualizar estado general del pedido a 3 (Entregado)
        if ($res) {
            try {
                PedidoService::cambiarEstado($idPedido, 3, 1, "Entregado por repartidor en campo");
            } catch (Exception $e) {
                error_log("Error actualizando estado general pedido $idPedido: " . $e->getMessage());
            }
        }
        return $res;
    }

    public function reportarIncidencia(
        int $idRuta,
        int $idPedido,
        string $tipoIncidencia,
        ?string $notas,
        ?float $lat,
        ?float $lng
    ): bool {
        $res = $this->campoModel->registrarIncidencia($idRuta, $idPedido, $tipoIncidencia, $notas, $lat, $lng);

        // Actualizar estado general a 16 (Incidencia)
        if ($res) {
            try {
                PedidoService::cambiarEstado($idPedido, 16, 1, "Incidencia en campo: $tipoIncidencia");
            } catch (Exception $e) {
                error_log("Error actualizando estado incidencia pedido $idPedido: " . $e->getMessage());
            }
        }
        return $res;
    }
}
