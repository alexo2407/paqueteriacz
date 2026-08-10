<?php

declare(strict_types=1);

// Este archivo define ColectaServiceTestable en el namespace global.
// Se carga antes del namespace Tests\LogisticaOperativa para que
// 'extends ColectaService' resuelva la clase global correctamente.
//
// ColectaServiceTestable sobreescribe los métodos públicos de ColectaService
// para omitir la verificación de feature flags.
//
// Gestión de transacciones anidadas:
//   Las pruebas de integración abren una transacción en setUp() para hacer
//   rollback en tearDown(). Si el servicio también llama beginTransaction()
//   sobre la misma conexión PDO, MariaDB/PDO lanza "already active transaction".
//   Solución: usar SAVEPOINT cuando ya hay una transacción activa.

require_once dirname(__DIR__, 2) . '/services/logistica_operativa/LogisticaOperativaException.php';
require_once dirname(__DIR__, 2) . '/services/logistica_operativa/ColectaService.php';

class ColectaServiceTestable extends ColectaService
{
    private \ColectaModel $colModel;
    private \EscaneoModel $escModel;
    private \PDO $pdo;
    private int $savepointSeq = 0;

    public function __construct(\PDO $db)
    {
        parent::__construct($db);
        $this->pdo      = $db;
        $this->colModel = new \ColectaModel($db);
        $this->escModel = new \EscaneoModel($db);
    }

    // ── Gestión de transacciones anidadas ─────────────────────────────────

    private function begin(): string
    {
        if ($this->pdo->inTransaction()) {
            $sp = 'sp_testable_' . (++$this->savepointSeq);
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

    // ── Métodos de dominio (sin verificación de flags) ────────────────────

    public function abrirColecta(int $idCliente, int $idProveedor, string $fecha, string $turno, int $idOperador): array
    {
        $this->validarFechaLocal($fecha);
        $this->validarTurnoLocal($turno);

        if (!$this->existeUsuarioLocal($idCliente)) {
            throw new \LogisticaOperativaException("Cliente no encontrado: ID {$idCliente}.");
        }
        if (!$this->tieneRolUsuarioLocal($idCliente, 4)) {
            throw new \LogisticaOperativaException("El usuario ID {$idCliente} no posee el Rol Cliente (ID 4).");
        }
        if (!$this->existeUsuarioLocal($idProveedor)) {
            throw new \LogisticaOperativaException("Proveedor no encontrado: ID {$idProveedor}.");
        }
        if (!$this->tieneRolUsuarioLocal($idProveedor, 5)) {
            throw new \LogisticaOperativaException("El usuario ID {$idProveedor} no posee el Rol Proveedor (ID 5).");
        }
        if ($idCliente === $idProveedor) {
            throw new \LogisticaOperativaException("El cliente y el proveedor no pueden ser el mismo usuario.");
        }
        if (!$this->existeUsuarioLocal($idOperador)) {
            throw new \LogisticaOperativaException("Operador no encontrado: ID {$idOperador}.");
        }

        $sp = $this->begin();
        try {
            $existente = $this->colModel->buscarPorClienteProveedorFechaTurno($idCliente, $idProveedor, $fecha, $turno);
            if ($existente !== null) {
                throw new \LogisticaOperativaException(
                    "Ya existe una colecta para el cliente {$idCliente} y proveedor {$idProveedor} en {$fecha} turno {$turno}."
                );
            }

            $pedidosIds       = $this->colModel->obtenerPedidosElegibles($idCliente, $idProveedor);
            $cantidadEsperada = count($pedidosIds);
            $idColecta        = $this->colModel->insertar($idCliente, $idProveedor, $fecha, $turno, $cantidadEsperada, $idOperador);

            foreach ($pedidosIds as $idPedido) {
                $this->colModel->insertarPedidoEsperado($idColecta, (int) $idPedido);
            }

            $this->commit($sp);

            return [
                'id_colecta'        => $idColecta,
                'cantidad_esperada' => $cantidadEsperada,
                'pedidos_ids'       => array_map('intval', $pedidosIds),
            ];
        } catch (\Throwable $e) {
            $this->rollback($sp);
            if ($e instanceof \LogisticaOperativaException) { throw $e; }
            throw new \LogisticaOperativaException('Error al abrir colecta: ' . $e->getMessage(), 0, $e);
        }
    }

    public function registrarEscaneo(array $datos): array
    {
        $this->validarUuidLocal($datos['uuid'] ?? '');
        $this->validarQrHashLocal($datos['qr_hash'] ?? '');

        $idColecta   = (int) ($datos['id_colecta']  ?? 0);
        $idPedido    = (int) ($datos['id_pedido']    ?? 0);
        $idOperador  = (int) ($datos['id_operador']  ?? 0);
        $tipoEvento  = $datos['tipo_evento']          ?? '';
        $escaneadoAt = $datos['escaneado_at']         ?? date('Y-m-d H:i:s');

        if (!$this->existeUsuarioLocal($idOperador)) {
            throw new \LogisticaOperativaException("Operador no encontrado: ID {$idOperador}.");
        }
        if (!$this->existePedidoLocal($idPedido)) {
            throw new \LogisticaOperativaException("Pedido no encontrado: ID {$idPedido}.");
        }

        // Idempotencia por UUID (lectura fuera de transacción anidada)
        $existing = $this->escModel->buscarPorUuid($datos['uuid']);
        if ($existing !== null) {
            $reg = $this->colModel->obtenerPedidoEnColecta($idColecta, $idPedido);
            return [
                'idempotente'     => true,
                'id_escaneo'      => (int) $existing['id'],
                'resultado_pedido' => $reg['resultado'] ?? 'DESCONOCIDO',
            ];
        }

        // Idempotencia por evento (lectura fuera de transacción anidada)
        if ($this->escModel->existeEvento($idColecta, $idPedido, $tipoEvento)) {
            $reg = $this->colModel->obtenerPedidoEnColecta($idColecta, $idPedido);
            return [
                'idempotente'     => true,
                'id_escaneo'      => 0,
                'resultado_pedido' => $reg['resultado'] ?? 'DESCONOCIDO',
            ];
        }

        $sp = $this->begin();
        try {
            $colecta = $this->colModel->obtenerPorId($idColecta);
            if ($colecta === null) {
                throw new \LogisticaOperativaException("Colecta no encontrada: ID {$idColecta}.");
            }
            if ($colecta['estado'] !== 'ABIERTA') {
                throw new \LogisticaOperativaException("Colecta {$idColecta} no está ABIERTA.");
            }

            $idEscaneo = $this->escModel->insertar([
                'uuid'          => $datos['uuid'],
                'id_colecta'    => $idColecta,
                'id_pedido'     => $idPedido,
                'tipo_evento'   => $tipoEvento,
                'qr_hash'       => $datos['qr_hash'],
                'id_operador'   => $idOperador,
                'dispositivo'   => $datos['dispositivo']   ?? null,
                'escaneado_at'  => $escaneadoAt,
                'metadata_json' => $datos['metadata_json'] ?? null,
            ]);

            $reg = $this->colModel->obtenerPedidoEnColecta($idColecta, $idPedido);

            if ($reg !== null && $reg['resultado'] === 'ESPERADO') {
                $this->colModel->actualizarResultadoPedido($idColecta, $idPedido, 'RECIBIDO', $escaneadoAt);
                $resultadoPedido = 'RECIBIDO';
            } elseif ($reg === null) {
                $this->colModel->insertarPedidoExtra($idColecta, $idPedido, $escaneadoAt);
                $resultadoPedido = 'EXTRA';
            } else {
                $resultadoPedido = $reg['resultado'];
            }

            $this->colModel->recalcularContadores($idColecta);
            $this->commit($sp);

            return [
                'idempotente'     => false,
                'id_escaneo'      => $idEscaneo,
                'resultado_pedido' => $resultadoPedido,
            ];
        } catch (\Throwable $e) {
            $this->rollback($sp);
            if ($e instanceof \LogisticaOperativaException) { throw $e; }
            throw new \LogisticaOperativaException('Error al registrar escaneo: ' . $e->getMessage(), 0, $e);
        }
    }

    public function cerrarYConciliar(int $idColecta, int $idOperador): array
    {
        if (!$this->existeUsuarioLocal($idOperador)) {
            throw new \LogisticaOperativaException("Operador no encontrado: ID {$idOperador}.");
        }

        $sp = $this->begin();
        try {
            $colecta = $this->colModel->obtenerPorId($idColecta, forUpdate: true);
            if ($colecta === null) {
                throw new \LogisticaOperativaException("Colecta no encontrada: ID {$idColecta}.");
            }
            if ($colecta['estado'] !== 'ABIERTA') {
                throw new \LogisticaOperativaException(
                    "No se puede cerrar la colecta {$idColecta}: estado '{$colecta['estado']}'."
                );
            }

            $this->colModel->cerrar($idColecta, $idOperador);
            $this->commit($sp);

            return $this->obtenerResumen($idColecta);
        } catch (\Throwable $e) {
            $this->rollback($sp);
            if ($e instanceof \LogisticaOperativaException) { throw $e; }
            throw new \LogisticaOperativaException('Error al cerrar colecta: ' . $e->getMessage(), 0, $e);
        }
    }

    public function obtenerResumen(int $idColecta): array
    {
        $resumen = $this->colModel->obtenerResumen($idColecta);
        if (empty($resumen)) {
            throw new \LogisticaOperativaException("Colecta no encontrada: ID {$idColecta}.");
        }
        return $resumen;
    }

    // ── Validaciones locales ───────────────────────────────────────────────

    private function validarFechaLocal(string $f): void
    {
        $d = \DateTime::createFromFormat('Y-m-d', $f);
        if ($d === false || $d->format('Y-m-d') !== $f) {
            throw new \LogisticaOperativaException("Fecha inválida: '{$f}'. Use formato Y-m-d.");
        }
    }

    private function validarTurnoLocal(string $t): void
    {
        if (!in_array($t, ['MANANA', 'TARDE'], true)) {
            throw new \LogisticaOperativaException("Turno inválido: '{$t}'. Use MANANA o TARDE.");
        }
    }

    private function validarUuidLocal(string $uuid): void
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
            throw new \LogisticaOperativaException("UUID inválido: '{$uuid}'.");
        }
    }

    private function validarQrHashLocal(string $hash): void
    {
        if (!preg_match('/^[0-9a-f]{64}$/i', $hash)) {
            throw new \LogisticaOperativaException('qr_hash debe ser SHA-256 de 64 caracteres.');
        }
    }

    private function existeUsuarioLocal(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM usuarios WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() !== false;
    }

    private function tieneRolUsuarioLocal(int $idUsuario, int $idRol): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM usuarios_roles WHERE id_usuario = :uid AND id_rol = :rid LIMIT 1'
        );
        $stmt->execute([':uid' => $idUsuario, ':rid' => $idRol]);
        return $stmt->fetch() !== false;
    }

    private function existePedidoLocal(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM pedidos WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() !== false;
    }
}
