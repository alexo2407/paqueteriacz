<?php

declare(strict_types=1);

require_once __DIR__ . '/LogisticaOperativaException.php';
require_once __DIR__ . '/../LogisticaOperativaFlags.php';
require_once __DIR__ . '/../../modelo/logistica_operativa/RutaModel.php';

/**
 * RutaService
 *
 * Lógica de negocio para la gestión de rutas y manifiestos de despacho.
 */
class RutaService
{
    private RutaModel $rutaModel;

    public function __construct(private PDO $db)
    {
        $this->rutaModel = new RutaModel($db);
    }

    private function verificarFlags(): void
    {
        if (!LogisticaOperativaFlags::enabled()) {
            throw new LogisticaOperativaException('El módulo Logística Operativa no está habilitado.');
        }
    }

    /**
     * Crea una ruta con la lista de pedidos seleccionados.
     *
     * @param array{
     *    nombre: string,
     *    fecha: string,
     *    id_repartidor: int,
     *    id_creada_por: int,
     *    pedidos: int[]
     * } $datos
     */
    public function crearRuta(array $datos): array
    {
        $this->verificarFlags();

        $nombre        = trim($datos['nombre'] ?? '');
        $fecha         = trim($datos['fecha'] ?? date('Y-m-d'));
        $idRepartidor  = (int)($datos['id_repartidor'] ?? 0);
        $idCreadaPor   = (int)($datos['id_creada_por'] ?? 0);
        $pedidosIds    = $datos['pedidos'] ?? [];

        if (empty($nombre)) {
            throw new LogisticaOperativaException('El nombre de la ruta es obligatorio.');
        }
        if ($idRepartidor <= 0) {
            throw new LogisticaOperativaException('Debe seleccionar un repartidor válido.');
        }
        if (empty($pedidosIds) || !is_array($pedidosIds)) {
            throw new LogisticaOperativaException('Debe incluir al menos un paquete en la ruta.');
        }

        $this->db->beginTransaction();
        try {
            // Generar código de ruta único: RUT-MGA-YYYYMMDD-COUNT
            $codigo = sprintf('RUT-%s-%d', date('Ymd', strtotime($fecha)), mt_rand(100, 999));

            // Calcular monto COD total
            $stmtMonto = $this->db->prepare("SELECT id, precio_total_local FROM pedidos WHERE id = ?");
            $totalCod = 0.0;
            $pedidosConMonto = [];

            foreach ($pedidosIds as $idPed) {
                $stmtMonto->execute([$idPed]);
                $ped = $stmtMonto->fetch(PDO::FETCH_ASSOC);
                $cod = (float)($ped['precio_total_local'] ?? 0.0);
                $totalCod += $cod;
                $pedidosConMonto[] = ['id' => $idPed, 'monto_cod' => $cod];
            }

            $idRuta = $this->rutaModel->insertar(
                $codigo,
                $nombre,
                $fecha,
                $idRepartidor,
                $idCreadaPor,
                count($pedidosIds),
                $totalCod
            );

            // Asignar pedidos en orden
            foreach ($pedidosConMonto as $idx => $p) {
                $this->rutaModel->insertarPedido(
                    $idRuta,
                    (int)$p['id'],
                    $idx + 1,
                    (float)$p['monto_cod']
                );
            }

            $this->db->commit();

            return [
                'id_ruta'          => $idRuta,
                'codigo'           => $codigo,
                'cantidad_pedidos' => count($pedidosIds),
                'total_cod'        => $totalCod
            ];

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($e instanceof LogisticaOperativaException) {
                throw $e;
            }
            throw new LogisticaOperativaException('Error al crear la ruta: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Sella una ruta impidiendo modificaciones futuras de pedidos.
     */
    public function sellarRuta(int $idRuta, int $idSelladaPor): void
    {
        $this->verificarFlags();

        $ruta = $this->rutaModel->obtenerPorId($idRuta);
        if (!$ruta) {
            throw new LogisticaOperativaException("Ruta #{$idRuta} no encontrada.");
        }
        if ($ruta['estado'] === 'SELLADA') {
            throw new LogisticaOperativaException("La ruta #{$idRuta} ya está sellada.");
        }

        $this->rutaModel->sellar($idRuta, $idSelladaPor);
    }
}
