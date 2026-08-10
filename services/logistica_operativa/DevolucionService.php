<?php

declare(strict_types=1);

require_once __DIR__ . '/../../modelo/conexion.php';
require_once __DIR__ . '/../../modelo/logistica_operativa/DevolucionModel.php';
require_once __DIR__ . '/../../services/PedidoService.php';

/**
 * DevolucionService
 *
 * Lógica de negocio para manifiestos de devolución a comercios (Logística Inversa).
 */
class DevolucionService
{
    private DevolucionModel $devolucionModel;

    public function __construct(?PDO $db = null)
    {
        $db = $db ?? (new Conexion())->conectar();
        $this->devolucionModel = new DevolucionModel($db);
    }

    public function listarDevoluciones(array $filtros = []): array
    {
        return $this->devolucionModel->listar($filtros);
    }

    public function obtenerDetalle(int $id): ?array
    {
        $dev = $this->devolucionModel->obtenerPorId($id);
        if (!$dev) return null;

        $dev['pedidos'] = $this->devolucionModel->obtenerPedidos($id);
        return $dev;
    }

    public function crearManifiesto(
        int $idCliente,
        ?int $idProveedor,
        int $idOperador,
        string $fechaDevolucion,
        array $idsPedidos,
        ?string $observaciones = null
    ): int {
        $codigo = 'DEV-' . date('Ymd') . '-' . rand(1000, 9999);

        $idDev = $this->devolucionModel->crearManifiesto(
            $codigo,
            $idCliente,
            $idProveedor,
            $idOperador,
            $fechaDevolucion,
            $observaciones
        );

        foreach ($idsPedidos as $idPedido) {
            $this->devolucionModel->agregarPedido($idDev, (int) $idPedido);
        }

        return $idDev;
    }

    public function entregarACliente(int $idDevolucion, ?string $firmaBase64 = null): bool
    {
        $dev = $this->obtenerDetalle($idDevolucion);
        if (!$dev) {
            throw new Exception("Manifiesto de devolución no encontrado.");
        }

        $res = $this->devolucionModel->finalizarDevolucion($idDevolucion, $firmaBase64);

        if ($res && !empty($dev['pedidos'])) {
            foreach ($dev['pedidos'] as $p) {
                try {
                    // Cambiar estado a 7 (Devuelto) o 15 (Devolución entregada a cliente)
                    PedidoService::cambiarEstado((int) $p['id_pedido'], 7, (int) $dev['id_operador'], "Devolución entregada a cliente en manifiesto {$dev['codigo_manifiesto']}");
                } catch (Exception $e) {
                    error_log("Error actualizando pedido {$p['id_pedido']} en devolución: " . $e->getMessage());
                }
            }
        }

        return $res;
    }
}
