<?php

declare(strict_types=1);

require_once __DIR__ . '/../../modelo/conexion.php';
require_once __DIR__ . '/../../modelo/logistica_operativa/CustodiaModel.php';
require_once __DIR__ . '/../../services/PedidoService.php';

/**
 * CustodiaService
 *
 * Lógica de negocio para manejar la transferencia y custodia departamental de paquetes.
 */
class CustodiaService
{
    private CustodiaModel $custodiaModel;

    public function __construct(?PDO $db = null)
    {
        $db = $db ?? (new Conexion())->conectar();
        $this->custodiaModel = new CustodiaModel($db);
    }

    public function listarCustodias(array $filtros = []): array
    {
        return $this->custodiaModel->listar($filtros);
    }

    public function registrarTraspaso(
        int $idPedido,
        ?int $idBodegaOrigen,
        ?int $idDeptoDestino,
        int $idResponsable,
        ?string $observaciones = null
    ): int {
        $idCustodia = $this->custodiaModel->crear($idPedido, $idBodegaOrigen, $idDeptoDestino, $idResponsable, $observaciones);

        // Cambiar estado del pedido a 13 (Traslado a punto de distribución)
        try {
            PedidoService::cambiarEstado($idPedido, 13, $idResponsable, "Iniciado traslado a custodia departamental");
        } catch (Exception $e) {
            error_log("Error actualizando estado traslado custodia para pedido $idPedido: " . $e->getMessage());
        }

        return $idCustodia;
    }

    public function recibirEnCustodia(int $idCustodia, int $idUsuario, ?string $obs = null): bool
    {
        return $this->custodiaModel->actualizarEstado($idCustodia, 'RECIBIDO_CUSTODIA', $obs);
    }

    public function despacharLocal(int $idCustodia, int $idUsuario, ?string $obs = null): bool
    {
        return $this->custodiaModel->actualizarEstado($idCustodia, 'DESPACHADO_LOCAL', $obs);
    }
}
