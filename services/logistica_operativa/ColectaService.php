<?php

declare(strict_types=1);

require_once __DIR__ . '/../../services/logistica_operativa/LogisticaOperativaException.php';
require_once __DIR__ . '/../../services/LogisticaOperativaFlags.php';
require_once __DIR__ . '/../../modelo/logistica_operativa/ColectaModel.php';
require_once __DIR__ . '/../../modelo/logistica_operativa/EscaneoModel.php';

/**
 * ColectaService
 *
 * Lógica de dominio para apertura, escaneo y cierre de colectas.
 *
 * Modo sombra: el servicio no modifica pedidos.id_estado, stock ni inventario.
 * Todas las operaciones destructivas ocurren dentro de transacciones.
 *
 * Recibe PDO por constructor para facilitar pruebas de integración con rollback.
 */
class ColectaService
{
    private ColectaModel $colectaModel;
    private EscaneoModel $escaneoModel;

    public function __construct(private PDO $db)
    {
        $this->colectaModel = new ColectaModel($db);
        $this->escaneoModel = new EscaneoModel($db);
    }

    // ── Validación del módulo ─────────────────────────────────────────────

    /**
     * Verifica que el módulo esté habilitado y en modo sombra.
     * @throws LogisticaOperativaException
     */
    private function verificarFlags(): void
    {
        if (!LogisticaOperativaFlags::enabled()) {
            throw new LogisticaOperativaException(
                'El módulo Logística Operativa no está habilitado.'
            );
        }
        // En Fase 2, el módulo opera exclusivamente en modo sombra.
        // canUpdateStates() y inventoryEnabled() deben ser false.
    }

    // ── Validaciones de entrada ───────────────────────────────────────────

    private function validarTurno(string $turno): void
    {
        if (!in_array($turno, ['MANANA', 'TARDE'], true)) {
            throw new LogisticaOperativaException(
                "Turno inválido: '{$turno}'. Use MANANA o TARDE."
            );
        }
    }

    private function validarFecha(string $fecha): void
    {
        $d = \DateTime::createFromFormat('Y-m-d', $fecha);
        if ($d === false || $d->format('Y-m-d') !== $fecha) {
            throw new LogisticaOperativaException(
                "Fecha inválida: '{$fecha}'. Use formato Y-m-d."
            );
        }
    }

    private function validarUuid(string $uuid): void
    {
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        if (!preg_match($pattern, $uuid)) {
            throw new LogisticaOperativaException(
                "UUID inválido: '{$uuid}'."
            );
        }
    }

    private function validarQrHash(string $hash): void
    {
        if (!preg_match('/^[0-9a-f]{64}$/i', $hash)) {
            throw new LogisticaOperativaException(
                'qr_hash debe ser un SHA-256 hexadecimal de 64 caracteres.'
            );
        }
    }

    private function existeUsuario(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM usuarios WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() !== false;
    }

    private function tieneRolUsuario(int $idUsuario, int $idRol): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM usuarios_roles WHERE id_usuario = :uid AND id_rol = :rid LIMIT 1'
        );
        $stmt->execute([':uid' => $idUsuario, ':rid' => $idRol]);
        return $stmt->fetch() !== false;
    }

    private function resolverIdPedido(int $codigo, int $idColecta = 0): ?int
    {
        // 1. Si hay colecta activa, priorizar los pedidos esperados en esta colecta por numero_orden o id
        if ($idColecta > 0) {
            $stmtCol = $this->db->prepare('
                SELECT p.id
                  FROM logistica_colecta_pedidos cp
                  JOIN pedidos p ON p.id = cp.id_pedido
                 WHERE cp.id_colecta = :id_colecta
                   AND (p.numero_orden = :c1 OR p.id = :c2)
                 LIMIT 1
            ');
            $stmtCol->execute([':id_colecta' => $idColecta, ':c1' => $codigo, ':c2' => $codigo]);
            $rowCol = $stmtCol->fetch();
            if ($rowCol !== false) {
                return (int)$rowCol['id'];
            }
        }

        // 2. Buscar globalmente por numero_orden
        $stmtNum = $this->db->prepare('SELECT id FROM pedidos WHERE numero_orden = :codigo LIMIT 1');
        $stmtNum->execute([':codigo' => $codigo]);
        $rowNum = $stmtNum->fetch();
        if ($rowNum !== false) {
            return (int)$rowNum['id'];
        }

        // 3. Buscar por ID primario
        $stmtId = $this->db->prepare('SELECT id FROM pedidos WHERE id = :codigo LIMIT 1');
        $stmtId->execute([':codigo' => $codigo]);
        $rowId = $stmtId->fetch();
        return $rowId !== false ? (int)$rowId['id'] : null;
    }

    private function existePedido(int $id): bool
    {
        return $this->resolverIdPedido($id) !== null;
    }

    // ── Apertura ──────────────────────────────────────────────────────────

    /**
     * Abre una nueva colecta para un cliente y proveedor en una fecha y turno.
     *
     * - Valida flags, turno, fecha, roles de cliente (Rol 4) y proveedor (Rol 5).
     * - Detecta duplicado (mismo cliente + proveedor + fecha + turno).
     * - Obtiene la fotografía de pedidos elegibles (estado 11).
     * - Crea la colecta e inserta los pedidos esperados.
     *
     * @return array{id_colecta: int, cantidad_esperada: int, pedidos_ids: int[]}
     * @throws LogisticaOperativaException
     */
    public function abrirColecta(
        int    $idCliente,
        int    $idProveedor,
        string $fecha,
        string $turno,
        int    $idOperador
    ): array {
        $this->verificarFlags();
        $this->validarFecha($fecha);
        $this->validarTurno($turno);

        if (!$this->existeUsuario($idCliente)) {
            throw new LogisticaOperativaException("Cliente no encontrado: ID {$idCliente}.");
        }
        if (!$this->tieneRolUsuario($idCliente, 4)) {
            throw new LogisticaOperativaException("El usuario ID {$idCliente} no posee el Rol Cliente (ID 4).");
        }

        if (!$this->existeUsuario($idProveedor)) {
            throw new LogisticaOperativaException("Proveedor no encontrado: ID {$idProveedor}.");
        }
        if (!$this->tieneRolUsuario($idProveedor, 5)) {
            throw new LogisticaOperativaException("El usuario ID {$idProveedor} no posee el Rol Proveedor (ID 5).");
        }

        if ($idCliente === $idProveedor) {
            throw new LogisticaOperativaException("El cliente y el proveedor no pueden ser el mismo usuario.");
        }

        if (!$this->existeUsuario($idOperador)) {
            throw new LogisticaOperativaException("Operador no encontrado: ID {$idOperador}.");
        }

        $this->db->beginTransaction();
        try {
            // Detectar duplicado por (cliente, proveedor, fecha, turno)
            $existente = $this->colectaModel->buscarPorClienteProveedorFechaTurno($idCliente, $idProveedor, $fecha, $turno);
            if ($existente !== null) {
                throw new LogisticaOperativaException(
                    "Ya existe una colecta para el cliente {$idCliente} y proveedor {$idProveedor} en {$fecha} turno {$turno}."
                );
            }

            // Fotografía de pedidos elegibles para esta tupla (cliente, proveedor)
            $pedidosIds = $this->colectaModel->obtenerPedidosElegibles($idCliente, $idProveedor);
            $cantidadEsperada = count($pedidosIds);

            // Crear colecta
            $idColecta = $this->colectaModel->insertar(
                $idCliente, $idProveedor, $fecha, $turno, $cantidadEsperada, $idOperador
            );

            // Insertar pedidos esperados
            foreach ($pedidosIds as $idPedido) {
                $this->colectaModel->insertarPedidoEsperado($idColecta, (int) $idPedido);
            }

            $this->db->commit();

            return [
                'id_colecta'       => $idColecta,
                'cantidad_esperada' => $cantidadEsperada,
                'pedidos_ids'      => array_map('intval', $pedidosIds),
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            if ($e instanceof LogisticaOperativaException) {
                throw $e;
            }
            throw new LogisticaOperativaException(
                'Error al abrir colecta: ' . $e->getMessage(), 0, $e
            );
        }
    }

    // ── Escaneo ───────────────────────────────────────────────────────────

    /**
     * Registra un escaneo físico de forma idempotente.
     *
     * Si el UUID ya existe, devuelve el resultado existente sin duplicar.
     * No modifica pedidos.id_estado, stock ni inventario.
     *
     * @param array{
     *   uuid: string,
     *   id_colecta: int,
     *   id_pedido: int,
     *   tipo_evento: string,
     *   qr_hash: string,
     *   id_operador: int,
     *   dispositivo?: string|null,
     *   escaneado_at: string,
     *   metadata_json?: string|null,
     * } $datos
     *
     * @return array{idempotente: bool, id_escaneo: int, resultado_pedido: string}
     * @throws LogisticaOperativaException
     */
    public function registrarEscaneo(array $datos): array
    {
        $this->verificarFlags();

        // Validaciones de entrada
        $this->validarUuid($datos['uuid'] ?? '');
        $this->validarQrHash($datos['qr_hash'] ?? '');

        $idColecta  = (int) ($datos['id_colecta'] ?? 0);
        $idPedidoRaw = (int) ($datos['id_pedido']  ?? 0);
        $idOperador = (int) ($datos['id_operador'] ?? 0);
        $tipoEvento = $datos['tipo_evento'] ?? '';
        $escaneadoAt = $datos['escaneado_at'] ?? date('Y-m-d H:i:s');

        if (!$this->existeUsuario($idOperador)) {
            throw new LogisticaOperativaException("Operador no encontrado: ID {$idOperador}.");
        }
        
        $idPedido = $this->resolverIdPedido($idPedidoRaw, $idColecta);
        if ($idPedido === null) {
            throw new LogisticaOperativaException("Pedido no encontrado: ID/Orden {$idPedidoRaw}.");
        }
        $datos['id_pedido'] = $idPedido;

        $this->db->beginTransaction();
        try {
            // Idempotencia por UUID
            $escaneoExistente = $this->escaneoModel->buscarPorUuid($datos['uuid']);
            if ($escaneoExistente !== null) {
                $this->db->rollBack();
                $registroColecta = $this->colectaModel->obtenerPedidoEnColecta($idColecta, $idPedido);
                return [
                    'idempotente'     => true,
                    'id_escaneo'      => (int) $escaneoExistente['id'],
                    'resultado_pedido' => $registroColecta['resultado'] ?? 'DESCONOCIDO',
                ];
            }

            // Verificar colecta activa
            $colecta = $this->colectaModel->obtenerPorId($idColecta);
            if ($colecta === null) {
                throw new LogisticaOperativaException("Colecta no encontrada: ID {$idColecta}.");
            }
            if ($colecta['estado'] !== 'ABIERTA') {
                throw new LogisticaOperativaException(
                    "La colecta {$idColecta} no está ABIERTA (estado: {$colecta['estado']})."
                );
            }

            // Idempotencia por evento (colecta + pedido + tipo)
            if ($this->escaneoModel->existeEvento($idColecta, $idPedido, $tipoEvento)) {
                $registroColecta = $this->colectaModel->obtenerPedidoEnColecta($idColecta, $idPedido);
                $idEscaneo = (int) $this->db->query(
                    "SELECT id FROM logistica_escaneos
                      WHERE id_colecta=? AND id_pedido=? AND tipo_evento=? LIMIT 1"
                )->execute([$idColecta, $idPedido, $tipoEvento]);
                $this->db->rollBack();
                return [
                    'idempotente'     => true,
                    'id_escaneo'      => 0,
                    'resultado_pedido' => $registroColecta['resultado'] ?? 'DESCONOCIDO',
                ];
            }

            // Insertar escaneo
            $idEscaneo = $this->escaneoModel->insertar([
                'uuid'          => $datos['uuid'],
                'id_colecta'    => $idColecta,
                'id_pedido'     => $idPedido,
                'tipo_evento'   => $tipoEvento,
                'qr_hash'       => $datos['qr_hash'],
                'id_operador'   => $idOperador,
                'dispositivo'   => $datos['dispositivo'] ?? null,
                'escaneado_at'  => $escaneadoAt,
                'metadata_json' => $datos['metadata_json'] ?? null,
            ]);

            // Determinar resultado para el pedido en la colecta
            $registroColecta = $this->colectaModel->obtenerPedidoEnColecta($idColecta, $idPedido);

            if ($registroColecta !== null && $registroColecta['resultado'] === 'ESPERADO') {
                $this->colectaModel->actualizarResultadoPedido(
                    $idColecta, $idPedido, 'RECIBIDO', $escaneadoAt
                );
                $resultadoPedido = 'RECIBIDO';
            } elseif ($registroColecta === null) {
                // Pedido no estaba en los esperados → EXTRA
                $this->colectaModel->insertarPedidoExtra($idColecta, $idPedido, $escaneadoAt);
                $resultadoPedido = 'EXTRA';
            } else {
                // Ya tenía resultado distinto (RECIBIDO/EXTRA/FALTANTE) → no cambia
                $resultadoPedido = $registroColecta['resultado'];
            }

            // Recalcular contadores desde registros reales
            $this->colectaModel->recalcularContadores($idColecta);

            $this->db->commit();

            return [
                'idempotente'     => false,
                'id_escaneo'      => $idEscaneo,
                'resultado_pedido' => $resultadoPedido,
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($e instanceof LogisticaOperativaException) {
                throw $e;
            }
            throw new LogisticaOperativaException(
                'Error al registrar escaneo: ' . $e->getMessage(), 0, $e
            );
        }
    }

    // ── Cierre y conciliación ─────────────────────────────────────────────

    /**
     * Cierra y concilia la colecta.
     *
     * - Bloquea la fila con FOR UPDATE.
     * - Marca pedidos ESPERADO como FALTANTE.
     * - Recalcula contadores.
     * - Cambia estado a CONCILIADA.
     * - No modifica pedidos.id_estado, stock ni inventario.
     *
     * @return array{estado: string, conteos: array}
     * @throws LogisticaOperativaException
     */
    public function cerrarYConciliar(int $idColecta, int $idOperador): array
    {
        $this->verificarFlags();

        if (!$this->existeUsuario($idOperador)) {
            throw new LogisticaOperativaException("Operador no encontrado: ID {$idOperador}.");
        }

        $this->db->beginTransaction();
        try {
            $colecta = $this->colectaModel->obtenerPorId($idColecta, forUpdate: true);

            if ($colecta === null) {
                throw new LogisticaOperativaException("Colecta no encontrada: ID {$idColecta}.");
            }
            if ($colecta['estado'] !== 'ABIERTA') {
                throw new LogisticaOperativaException(
                    "No se puede cerrar la colecta {$idColecta}: estado actual '{$colecta['estado']}'."
                );
            }

            $this->colectaModel->cerrar($idColecta, $idOperador);

            $this->db->commit();

            return $this->obtenerResumen($idColecta);
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($e instanceof LogisticaOperativaException) {
                throw $e;
            }
            throw new LogisticaOperativaException(
                'Error al cerrar colecta: ' . $e->getMessage(), 0, $e
            );
        }
    }

    // ── Eliminar Extra ───────────────────────────────────────────────────

    /**
     * Elimina un paquete registrado como EXTRA en una colecta ABIERTA.
     */
    public function eliminarExtra(int $idColecta, int $idPedido, int $idOperador): array
    {
        $this->verificarFlags();

        if (!$this->existeUsuario($idOperador)) {
            throw new LogisticaOperativaException("Operador no encontrado: ID {$idOperador}.");
        }

        $colecta = $this->colectaModel->obtenerPorId($idColecta);
        if ($colecta === null) {
            throw new LogisticaOperativaException("Colecta no encontrada: ID {$idColecta}.");
        }
        if ($colecta['estado'] !== 'ABIERTA') {
            throw new LogisticaOperativaException("No se pueden eliminar extras de una colecta {$colecta['estado']}.");
        }

        $eliminado = $this->colectaModel->eliminarPedidoExtra($idColecta, $idPedido);
        if (!$eliminado) {
            throw new LogisticaOperativaException("El pedido #{$idPedido} no está registrado como EXTRA en esta colecta.");
        }

        // Eliminar escaneos de logistica_escaneos para este pedido en esta colecta
        $stmtEsc = $this->db->prepare("DELETE FROM logistica_escaneos WHERE id_colecta = :idc AND id_pedido = :idp");
        $stmtEsc->execute([':idc' => $idColecta, ':idp' => $idPedido]);

        return $this->obtenerResumen($idColecta);
    }

    // ── Resumen ───────────────────────────────────────────────────────────

    /**
     * Devuelve el resumen completo de una colecta.
     *
     * @return array{colecta: array, conteos: array}
     * @throws LogisticaOperativaException
     */
    public function obtenerResumen(int $idColecta): array
    {
        $resumen = $this->colectaModel->obtenerResumen($idColecta);
        if (empty($resumen)) {
            throw new LogisticaOperativaException("Colecta no encontrada: ID {$idColecta}.");
        }
        return $resumen;
    }
}
