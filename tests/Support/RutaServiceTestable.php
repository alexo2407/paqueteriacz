<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/services/logistica_operativa/LogisticaOperativaException.php';
require_once dirname(__DIR__, 2) . '/services/logistica_operativa/RutaService.php';
require_once dirname(__DIR__, 2) . '/modelo/logistica_operativa/RutaModel.php';

/**
 * RutaServiceTestable
 *
 * Subclase de RutaService para entornos de prueba:
 * - Omite la verificación de feature flags.
 * - Soporta Savepoints de MariaDB para transacciones anidadas en PHPUnit.
 */
class RutaServiceTestable extends RutaService
{
    private PDO $pdo;
    private RutaModel $rutaModelTest;
    private int $savepointSeq = 0;

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->pdo = $db;
        $this->rutaModelTest = new RutaModel($db);
    }

    private function begin(): string
    {
        if ($this->pdo->inTransaction()) {
            $sp = 'sp_ruta_testable_' . (++$this->savepointSeq);
            $this->pdo->exec("SAVEPOINT {$sp}");
            return $sp;
        }
        $this->pdo->beginTransaction();
        return '';
    }

    private function commit(string $savepoint): void
    {
        if ($savepoint !== '') {
            $this->pdo->exec("RELEASE SAVEPOINT {$savepoint}");
        } else {
            $this->pdo->commit();
        }
    }

    private function rollback(string $savepoint): void
    {
        if ($savepoint !== '') {
            $this->pdo->exec("ROLLBACK TO SAVEPOINT {$savepoint}");
        } elseif ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function crearRuta(array $datos): array
    {
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

        $sp = $this->begin();
        try {
            $codigo = sprintf('RUT-%s-%d', date('Ymd', strtotime($fecha)), mt_rand(100, 999));

            $stmtMonto = $this->pdo->prepare("SELECT id, precio_total_local FROM pedidos WHERE id = ?");
            $totalCod = 0.0;
            $pedidosConMonto = [];

            foreach ($pedidosIds as $idPed) {
                $stmtMonto->execute([$idPed]);
                $ped = $stmtMonto->fetch(PDO::FETCH_ASSOC);
                $cod = (float)($ped['precio_total_local'] ?? 0.0);
                $totalCod += $cod;
                $pedidosConMonto[] = ['id' => $idPed, 'monto_cod' => $cod];
            }

            $idRuta = $this->rutaModelTest->insertar(
                $codigo,
                $nombre,
                $fecha,
                $idRepartidor,
                $idCreadaPor,
                count($pedidosIds),
                $totalCod
            );

            foreach ($pedidosConMonto as $idx => $p) {
                $this->rutaModelTest->insertarPedido(
                    $idRuta,
                    (int)$p['id'],
                    $idx + 1,
                    (float)$p['monto_cod']
                );
            }

            $this->commit($sp);

            return [
                'id_ruta'          => $idRuta,
                'codigo'           => $codigo,
                'cantidad_pedidos' => count($pedidosIds),
                'total_cod'        => $totalCod
            ];

        } catch (Throwable $e) {
            $this->rollback($sp);
            if ($e instanceof LogisticaOperativaException) {
                throw $e;
            }
            throw new LogisticaOperativaException('Error al crear la ruta: ' . $e->getMessage(), 0, $e);
        }
    }

    public function sellarRuta(int $idRuta, int $idSelladaPor): void
    {
        $ruta = $this->rutaModelTest->obtenerPorId($idRuta);
        if (!$ruta) {
            throw new LogisticaOperativaException("Ruta #{$idRuta} no encontrada.");
        }
        if ($ruta['estado'] === 'SELLADA') {
            throw new LogisticaOperativaException("La ruta #{$idRuta} ya está sellada.");
        }

        $this->rutaModelTest->sellar($idRuta, $idSelladaPor);
    }
}
