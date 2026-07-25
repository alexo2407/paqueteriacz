<?php

declare(strict_types=1);

require_once __DIR__ . '/../../services/logistica_operativa/LogisticaOperativaException.php';
require_once __DIR__ . '/../../services/LogisticaOperativaFlags.php';
require_once __DIR__ . '/../../modelo/logistica_operativa/BodegaModel.php';
require_once __DIR__ . '/../../modelo/logistica_operativa/UbicacionModel.php';
require_once __DIR__ . '/../../modelo/logistica_operativa/RecepcionModel.php';
require_once __DIR__ . '/../../modelo/logistica_operativa/UbicacionHistorialModel.php';

/**
 * BodegaUbicacionService
 *
 * Lógica de dominio para recepción física, ubicación, reubicación,
 * retiro y consulta de historial de paquetes en bodega.
 *
 * Modo sombra: el servicio no modifica pedidos.id_estado, stock ni inventario.
 * Todas las operaciones destructivas ocurren dentro de transacciones.
 * Usa SELECT FOR UPDATE para garantizar consistencia concurrente.
 *
 * Recibe PDO por constructor para facilitar pruebas de integración con rollback.
 *
 * Requiere:
 *   LOGISTICA_OPERATIVA_ENABLED=true
 *   LOGISTICA_OPERATIVA_SHADOW_MODE=true
 */
class BodegaUbicacionService
{
    private BodegaModel           $bodegaModel;
    private UbicacionModel        $ubicacionModel;
    private RecepcionModel        $recepcionModel;
    private UbicacionHistorialModel $historialModel;

    public function __construct(private PDO $db)
    {
        $this->bodegaModel    = new BodegaModel($db);
        $this->ubicacionModel = new UbicacionModel($db);
        $this->recepcionModel = new RecepcionModel($db);
        $this->historialModel = new UbicacionHistorialModel($db);
    }

    // ── Verificación del módulo ───────────────────────────────────────────

    /**
     * Verifica que el módulo esté habilitado y en modo sombra.
     * @throws LogisticaOperativaException
     */
    protected function verificarFlags(): void
    {
        if (!LogisticaOperativaFlags::enabled()) {
            throw new LogisticaOperativaException(
                'El módulo Logística Operativa no está habilitado.',
                'MODULE_DISABLED'
            );
        }
        if (!LogisticaOperativaFlags::shadowMode()) {
            throw new LogisticaOperativaException(
                'El módulo Logística Operativa debe estar en modo sombra.',
                'SHADOW_MODE_REQUIRED'
            );
        }
    }

    // ── Validaciones internas ─────────────────────────────────────────────

    protected function validarUuid(string $uuid): void
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
            throw new LogisticaOperativaException(
                "UUID inválido: '{$uuid}'.",
                'UUID_INVALIDO'
            );
        }
    }

    protected function validarFechaDatetime(string $fecha): void
    {
        $d = \DateTime::createFromFormat('Y-m-d H:i:s', $fecha);
        if ($d === false || $d->format('Y-m-d H:i:s') !== $fecha) {
            throw new LogisticaOperativaException(
                "Fecha inválida: '{$fecha}'. Use formato Y-m-d H:i:s.",
                'FECHA_INVALIDA'
            );
        }
    }

    protected function existeUsuario(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM usuarios WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() !== false;
    }

    protected function existePedido(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM pedidos WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() !== false;
    }

    protected function existeEscaneo(int $id, int $idPedido): bool
    {
        // Verificamos que el escaneo exista y pertenezca al pedido indicado
        $stmt = $this->db->prepare(
            'SELECT id FROM logistica_escaneos WHERE id = :id AND id_pedido = :id_pedido LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':id_pedido' => $idPedido]);
        return $stmt->fetch() !== false;
    }

    // ── Gestión de transacciones anidadas ─────────────────────────────────

    /**
     * Inicia una transacción o crea un SAVEPOINT si ya existe una transacción activa.
     * Devuelve el nombre del savepoint (vacío si se abrió una nueva transacción).
     */
    protected function begin(): string
    {
        if ($this->db->inTransaction()) {
            $sp = 'sp_buservice_' . uniqid('', true);
            $this->db->exec("SAVEPOINT `{$sp}`");
            return $sp;
        }
        $this->db->beginTransaction();
        return '';
    }

    /**
     * Confirma el trabajo: RELEASE SAVEPOINT o COMMIT según corresponda.
     */
    protected function commit(string $savepoint): void
    {
        if ($savepoint !== '') {
            $this->db->exec("RELEASE SAVEPOINT `{$savepoint}`");
        } else {
            $this->db->commit();
        }
    }

    /**
     * Revierte el trabajo: ROLLBACK TO SAVEPOINT o ROLLBACK según corresponda.
     */
    protected function rollback(string $savepoint): void
    {
        if ($savepoint !== '') {
            $this->db->exec("ROLLBACK TO SAVEPOINT `{$savepoint}`");
        } elseif ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    // ── Contratos públicos ────────────────────────────────────────────────

    /**
     * Registra la recepción física de un paquete en bodega.
     *
     * - UUID repetido: idempotente (devuelve la recepción existente).
     * - Sin ubicación: estado RECIBIDO, sin historial.
     * - Con ubicación: estado UBICADO, crea historial tipo INGRESO.
     * - No modifica pedidos.id_estado, stock ni inventario.
     *
     * @param array{
     *   uuid: string,
     *   id_pedido: int,
     *   id_bodega: int,
     *   id_ubicacion: int|null,
     *   id_escaneo: int|null,
     *   tipo_recepcion: string,
     *   id_operador: int,
     *   recibido_at: string,
     *   observacion: string|null,
     * } $datos
     *
     * @return array{idempotente: bool, id_recepcion: int, estado: string}
     * @throws LogisticaOperativaException
     */
    public function registrarRecepcion(array $datos): array
    {
        $this->verificarFlags();

        // ── Validaciones de entrada ───────────────────────────────────────
        $uuid        = $datos['uuid']         ?? '';
        $idPedido    = (int) ($datos['id_pedido']   ?? 0);
        $idBodega    = (int) ($datos['id_bodega']   ?? 0);
        $idUbicacion = isset($datos['id_ubicacion']) ? (int) $datos['id_ubicacion'] : null;
        $idEscaneo   = isset($datos['id_escaneo'])   ? (int) $datos['id_escaneo']   : null;
        $tipo        = $datos['tipo_recepcion'] ?? '';
        $idOperador  = (int) ($datos['id_operador']  ?? 0);
        $recibidoAt  = $datos['recibido_at']   ?? '';
        $observacion = $datos['observacion']   ?? null;

        $this->validarUuid($uuid);
        $this->validarFechaDatetime($recibidoAt);

        $tiposPermitidos = ['COLECTA', 'RETORNO_RUTA', 'INCIDENCIA', 'DEVOLUCION'];
        if (!in_array($tipo, $tiposPermitidos, true)) {
            throw new LogisticaOperativaException(
                "Tipo de recepción inválido: '{$tipo}'.",
                'TIPO_RECEPCION_INVALIDO'
            );
        }

        if (!$this->existeUsuario($idOperador)) {
            throw new LogisticaOperativaException(
                "Operador no encontrado: ID {$idOperador}.",
                'OPERADOR_NO_ENCONTRADO'
            );
        }
        if (!$this->existePedido($idPedido)) {
            throw new LogisticaOperativaException(
                "Pedido no encontrado: ID {$idPedido}.",
                'PEDIDO_NO_ENCONTRADO'
            );
        }

        // ── Idempotencia por UUID (lectura previa a la transacción) ───────
        $existente = $this->recepcionModel->buscarPorUuid($uuid);
        if ($existente !== null) {
            return [
                'idempotente'   => true,
                'id_recepcion'  => (int) $existente['id'],
                'estado'        => $existente['estado'],
            ];
        }

        $sp = $this->begin();
        try {
            // Verificar bodega activa
            $bodega = $this->bodegaModel->obtenerActivaPorId($idBodega);
            if ($bodega === null) {
                $hayBodega = $this->bodegaModel->obtenerPorId($idBodega);
                if ($hayBodega === null) {
                    throw new LogisticaOperativaException(
                        "Bodega no encontrada: ID {$idBodega}.",
                        'BODEGA_NO_ENCONTRADA'
                    );
                }
                throw new LogisticaOperativaException(
                    "Bodega inactiva: ID {$idBodega}.",
                    'BODEGA_INACTIVA'
                );
            }

            // Verificar ubicación si se proporcionó
            if ($idUbicacion !== null) {
                $ubicacion = $this->ubicacionModel->obtenerActivaEnBodega($idUbicacion, $idBodega);
                if ($ubicacion === null) {
                    $uExiste = $this->ubicacionModel->obtenerPorId($idUbicacion);
                    if ($uExiste === null) {
                        throw new LogisticaOperativaException(
                            "Ubicación no encontrada: ID {$idUbicacion}.",
                            'UBICACION_NO_ENCONTRADA'
                        );
                    }
                    if ((int) $uExiste['activa'] === 0) {
                        throw new LogisticaOperativaException(
                            "Ubicación inactiva: ID {$idUbicacion}.",
                            'UBICACION_INACTIVA'
                        );
                    }
                    throw new LogisticaOperativaException(
                        "La ubicación {$idUbicacion} no pertenece a la bodega {$idBodega}.",
                        'UBICACION_NO_PERTENECE_BODEGA'
                    );
                }
            }

            // Verificar escaneo si se proporcionó
            if ($idEscaneo !== null) {
                if (!$this->existeEscaneo($idEscaneo, $idPedido)) {
                    throw new LogisticaOperativaException(
                        "Escaneo {$idEscaneo} no encontrado o no corresponde al pedido {$idPedido}.",
                        0
                    );
                }
            }

            // Verificar que no exista recepción activa para el mismo pedido
            $recepcionActiva = $this->recepcionModel->obtenerActivaPorPedido($idPedido, forUpdate: true);
            if ($recepcionActiva !== null) {
                throw new LogisticaOperativaException(
                    "El pedido {$idPedido} ya tiene una recepción activa (ID {$recepcionActiva['id']}).",
                    'RECEPCION_ACTIVA_EXISTENTE'
                );
            }

            // Determinar estado inicial
            $estado = ($idUbicacion !== null) ? 'UBICADO' : 'RECIBIDO';

            // Insertar recepción
            $idRecepcion = $this->recepcionModel->insertar([
                'uuid'           => $uuid,
                'id_pedido'      => $idPedido,
                'id_bodega'      => $idBodega,
                'id_ubicacion'   => $idUbicacion,
                'id_escaneo'     => $idEscaneo,
                'tipo_recepcion' => $tipo,
                'estado'         => $estado,
                'id_operador'    => $idOperador,
                'recibido_at'    => $recibidoAt,
                'observacion'    => $observacion,
            ]);

            // Si se proporcionó ubicación, crear historial INGRESO
            if ($idUbicacion !== null) {
                $this->historialModel->insertar([
                    'id_pedido'       => $idPedido,
                    'id_recepcion'    => $idRecepcion,
                    'id_bodega'       => $idBodega,
                    'id_ubicacion'    => $idUbicacion,
                    'id_operador'     => $idOperador,
                    'tipo_movimiento' => 'INGRESO',
                    'motivo'          => $observacion,
                    'activo'          => 1,
                    'ubicado_at'      => $recibidoAt,
                ]);
            }

            $this->commit($sp);

            return [
                'idempotente'  => false,
                'id_recepcion' => $idRecepcion,
                'estado'       => $estado,
            ];
        } catch (\Throwable $e) {
            $this->rollback($sp);
            if ($e instanceof LogisticaOperativaException) {
                throw $e;
            }
            throw new LogisticaOperativaException(
                'Error al registrar recepción.',
                0,
                $e
            );
        }
    }

    /**
     * Asigna una ubicación a una recepción en estado RECIBIDO.
     *
     * - La recepción debe existir, pertenecer al pedido y estar RECIBIDO.
     * - La ubicación debe estar activa y pertenecer a la misma bodega.
     * - El pedido no debe tener otra ubicación activa.
     * - Inserta historial tipo INGRESO.
     *
     * @return array{id_recepcion: int, id_ubicacion: int, estado: string}
     * @throws LogisticaOperativaException
     */
    public function ubicarPaquete(
        int $idPedido,
        int $idRecepcion,
        int $idUbicacion,
        int $idOperador,
        ?string $motivo = null
    ): array {
        $this->verificarFlags();

        if (!$this->existeUsuario($idOperador)) {
            throw new LogisticaOperativaException(
                "Operador no encontrado: ID {$idOperador}.",
                'OPERADOR_NO_ENCONTRADO'
            );
        }

        $sp = $this->begin();
        try {
            // Bloquear la recepción
            $recepcion = $this->recepcionModel->obtenerPorId($idRecepcion, forUpdate: true);
            if ($recepcion === null) {
                throw new LogisticaOperativaException(
                    "Recepción no encontrada: ID {$idRecepcion}.",
                    'RECEPCION_NO_ENCONTRADA'
                );
            }
            if ((int) $recepcion['id_pedido'] !== $idPedido) {
                throw new LogisticaOperativaException(
                    "La recepción {$idRecepcion} no corresponde al pedido {$idPedido}.",
                    'RECEPCION_NO_CORRESPONDE_PEDIDO'
                );
            }
            if ($recepcion['estado'] !== 'RECIBIDO') {
                throw new LogisticaOperativaException(
                    "La recepción {$idRecepcion} no está en estado RECIBIDO (estado actual: {$recepcion['estado']}).",
                    'PAQUETE_YA_UBICADO'
                );
            }

            // Verificar ubicación activa en la misma bodega
            $idBodega  = (int) $recepcion['id_bodega'];
            $ubicacion = $this->ubicacionModel->obtenerActivaEnBodega($idUbicacion, $idBodega);
            if ($ubicacion === null) {
                $uExiste = $this->ubicacionModel->obtenerPorId($idUbicacion);
                if ($uExiste === null) {
                    throw new LogisticaOperativaException(
                        "Ubicación no encontrada: ID {$idUbicacion}.",
                        'UBICACION_NO_ENCONTRADA'
                    );
                }
                if ((int) $uExiste['activa'] === 0) {
                    throw new LogisticaOperativaException(
                        "Ubicación inactiva: ID {$idUbicacion}.",
                        'UBICACION_INACTIVA'
                    );
                }
                throw new LogisticaOperativaException(
                    "La ubicación {$idUbicacion} no pertenece a la bodega {$idBodega}.",
                    'UBICACION_NO_PERTENECE_BODEGA'
                );
            }

            // Verificar que el pedido no tenga ubicación activa en el historial
            $historialActivo = $this->historialModel->obtenerActivoPorPedido($idPedido, forUpdate: true);
            if ($historialActivo !== null) {
                throw new LogisticaOperativaException(
                    "El pedido {$idPedido} ya tiene una ubicación activa (historial ID {$historialActivo['id']}).",
                    'PAQUETE_YA_UBICADO'
                );
            }

            // Actualizar recepción a UBICADO
            $this->recepcionModel->actualizarEstado($idRecepcion, 'UBICADO', $idUbicacion);

            // Insertar historial INGRESO
            $this->historialModel->insertar([
                'id_pedido'       => $idPedido,
                'id_recepcion'    => $idRecepcion,
                'id_bodega'       => $idBodega,
                'id_ubicacion'    => $idUbicacion,
                'id_operador'     => $idOperador,
                'tipo_movimiento' => 'INGRESO',
                'motivo'          => $motivo,
                'activo'          => 1,
                'ubicado_at'      => date('Y-m-d H:i:s'),
            ]);

            $this->commit($sp);

            return [
                'id_recepcion' => $idRecepcion,
                'id_ubicacion' => $idUbicacion,
                'estado'       => 'UBICADO',
            ];
        } catch (\Throwable $e) {
            $this->rollback($sp);
            if ($e instanceof LogisticaOperativaException) {
                throw $e;
            }
            throw new LogisticaOperativaException(
                'Error al ubicar paquete.',
                0,
                $e
            );
        }
    }

    /**
     * Reubica un paquete a una nueva ubicación dentro de la misma bodega.
     *
     * - El pedido debe tener una ubicación activa.
     * - La ubicación destino debe estar activa y pertenecer a la misma bodega.
     * - Si la ubicación destino es igual a la actual, devuelve el estado sin cambios.
     * - Traslados entre bodegas están reservados para la Fase 4.
     * - Garantiza exactamente una ubicación activa al finalizar.
     *
     * @return array{id_recepcion: int, id_ubicacion_anterior: int, id_ubicacion_nueva: int, movimiento: string}
     * @throws LogisticaOperativaException
     */
    public function reubicarPaquete(
        int $idPedido,
        int $idUbicacionDestino,
        int $idOperador,
        ?string $motivo = null
    ): array {
        $this->verificarFlags();

        if (!$this->existeUsuario($idOperador)) {
            throw new LogisticaOperativaException(
                "Operador no encontrado: ID {$idOperador}.",
                'OPERADOR_NO_ENCONTRADO'
            );
        }

        $sp = $this->begin();
        try {
            // Bloquear el historial activo del pedido
            $historialActivo = $this->historialModel->obtenerActivoPorPedido($idPedido, forUpdate: true);
            if ($historialActivo === null) {
                throw new LogisticaOperativaException(
                    "El pedido {$idPedido} no tiene una ubicación activa.",
                    'PAQUETE_SIN_UBICACION'
                );
            }

            $idUbicacionActual = (int) $historialActivo['id_ubicacion'];
            $idBodega          = (int) $historialActivo['id_bodega'];

            // Misma ubicación: idempotente
            if ($idUbicacionActual === $idUbicacionDestino) {
                $this->rollback($sp);
                return [
                    'id_recepcion'          => (int) ($historialActivo['id_recepcion'] ?? 0),
                    'id_ubicacion_anterior' => $idUbicacionActual,
                    'id_ubicacion_nueva'    => $idUbicacionDestino,
                    'movimiento'            => 'SIN_CAMBIO',
                ];
            }

            // Verificar ubicación destino
            $ubicacionDestino = $this->ubicacionModel->obtenerActivaEnBodega($idUbicacionDestino, $idBodega);
            if ($ubicacionDestino === null) {
                $uExiste = $this->ubicacionModel->obtenerPorId($idUbicacionDestino);
                if ($uExiste === null) {
                    throw new LogisticaOperativaException(
                        "Ubicación no encontrada: ID {$idUbicacionDestino}.",
                        'UBICACION_NO_ENCONTRADA'
                    );
                }
                if ((int) $uExiste['activa'] === 0) {
                    throw new LogisticaOperativaException(
                        "Ubicación inactiva: ID {$idUbicacionDestino}.",
                        'UBICACION_INACTIVA'
                    );
                }
                // Pertenece a otra bodega
                throw new LogisticaOperativaException(
                    "La ubicación {$idUbicacionDestino} pertenece a otra bodega. Los traslados entre bodegas no están permitidos en esta fase.",
                    'TRASLADO_ENTRE_BODEGAS_NO_PERMITIDO'
                );
            }

            // Obtener recepción activa para actualizar id_ubicacion
            $recepcionActiva = $this->recepcionModel->obtenerActivaPorPedido($idPedido, forUpdate: true);

            // Desactivar historial actual
            $this->historialModel->desactivarActivoPorPedido($idPedido);

            // Insertar nuevo historial REUBICACION
            $this->historialModel->insertar([
                'id_pedido'       => $idPedido,
                'id_recepcion'    => $recepcionActiva !== null ? (int) $recepcionActiva['id'] : null,
                'id_bodega'       => $idBodega,
                'id_ubicacion'    => $idUbicacionDestino,
                'id_operador'     => $idOperador,
                'tipo_movimiento' => 'REUBICACION',
                'motivo'          => $motivo,
                'activo'          => 1,
                'ubicado_at'      => date('Y-m-d H:i:s'),
            ]);

            // Actualizar id_ubicacion en la recepción activa
            if ($recepcionActiva !== null) {
                $this->recepcionModel->actualizarEstado(
                    (int) $recepcionActiva['id'],
                    'UBICADO',
                    $idUbicacionDestino
                );
            }

            // Verificación de consistencia: debe quedar exactamente 1 activo
            $activos = $this->historialModel->contarActivosPorPedido($idPedido);
            if ($activos !== 1) {
                throw new LogisticaOperativaException(
                    "Inconsistencia: el pedido {$idPedido} quedó con {$activos} ubicaciones activas.",
                    0
                );
            }

            $this->commit($sp);

            return [
                'id_recepcion'          => $recepcionActiva !== null ? (int) $recepcionActiva['id'] : 0,
                'id_ubicacion_anterior' => $idUbicacionActual,
                'id_ubicacion_nueva'    => $idUbicacionDestino,
                'movimiento'            => 'REUBICACION',
            ];
        } catch (\Throwable $e) {
            $this->rollback($sp);
            if ($e instanceof LogisticaOperativaException) {
                throw $e;
            }
            throw new LogisticaOperativaException(
                'Error al reubicar paquete.',
                0,
                $e
            );
        }
    }

    /**
     * Retira un paquete de su ubicación actual.
     *
     * - Idempotente: si el paquete ya fue retirado, devuelve el último estado conocido.
     * - Desactiva el historial activo y actualiza la recepción a RETIRADO.
     * - No elimina registros históricos.
     * - No cambia pedidos.id_estado.
     *
     * @return array{id_recepcion: int, estado: string, idempotente: bool}
     * @throws LogisticaOperativaException
     */
    public function retirarPaquete(
        int $idPedido,
        int $idOperador,
        ?string $motivo = null
    ): array {
        $this->verificarFlags();

        if (!$this->existeUsuario($idOperador)) {
            throw new LogisticaOperativaException(
                "Operador no encontrado: ID {$idOperador}.",
                'OPERADOR_NO_ENCONTRADO'
            );
        }

        $sp = $this->begin();
        try {
            // Verificar historial activo
            $historialActivo = $this->historialModel->obtenerActivoPorPedido($idPedido, forUpdate: true);

            if ($historialActivo === null) {
                // Sin historial activo: puede ser retiro repetido o paquete sin ubicación
                // Buscamos la recepción para informar el último estado
                $recepcionActiva = $this->recepcionModel->obtenerActivaPorPedido($idPedido, forUpdate: true);
                if ($recepcionActiva !== null && $recepcionActiva['estado'] === 'RECIBIDO') {
                    // El pedido está RECIBIDO pero sin ubicación → no hay nada que retirar
                    throw new LogisticaOperativaException(
                        "El pedido {$idPedido} no tiene una ubicación activa.",
                        'PAQUETE_SIN_UBICACION'
                    );
                }
                // Retiro repetido: ya fue retirado, devolver estado conocido
                // Buscamos la última recepción aunque esté RETIRADA
                $stmt = $this->db->prepare(
                    "SELECT * FROM logistica_recepciones
                      WHERE id_pedido = :id_pedido
                        AND estado    = 'RETIRADO'
                      ORDER BY updated_at DESC
                      LIMIT 1"
                );
                $stmt->execute([':id_pedido' => $idPedido]);
                $ultimaRecepcion = $stmt->fetch(PDO::FETCH_ASSOC);

                $this->rollback($sp);
                return [
                    'id_recepcion' => $ultimaRecepcion !== false ? (int) $ultimaRecepcion['id'] : 0,
                    'estado'       => 'RETIRADO',
                    'idempotente'  => true,
                ];
            }

            // Bloquear recepción activa
            $recepcionActiva = $this->recepcionModel->obtenerActivaPorPedido($idPedido, forUpdate: true);
            if ($recepcionActiva === null) {
                throw new LogisticaOperativaException(
                    "No se encontró recepción activa para el pedido {$idPedido}.",
                    'RECEPCION_NO_ENCONTRADA'
                );
            }

            // Desactivar historial
            $this->historialModel->desactivarActivoPorPedido($idPedido);

            // Actualizar recepción a RETIRADO
            $this->recepcionModel->actualizarEstado(
                (int) $recepcionActiva['id'],
                'RETIRADO',
                null
            );

            $this->commit($sp);

            return [
                'id_recepcion' => (int) $recepcionActiva['id'],
                'estado'       => 'RETIRADO',
                'idempotente'  => false,
            ];
        } catch (\Throwable $e) {
            $this->rollback($sp);
            if ($e instanceof LogisticaOperativaException) {
                throw $e;
            }
            throw new LogisticaOperativaException(
                'Error al retirar paquete.',
                0,
                $e
            );
        }
    }

    /**
     * Devuelve la ubicación actual activa del paquete.
     *
     * La fuente de verdad es logistica_ubicacion_historial.activo=1,
     * no solo logistica_recepciones.id_ubicacion.
     *
     * @return array|null  null si el paquete no tiene ubicación activa.
     */
    public function obtenerUbicacionActual(int $idPedido): ?array
    {
        $this->verificarFlags();

        $historial = $this->historialModel->obtenerActivoPorPedido($idPedido);
        if ($historial === null) {
            return null;
        }

        return [
            'id_pedido'       => $idPedido,
            'id_recepcion'    => $historial['id_recepcion'] !== null ? (int) $historial['id_recepcion'] : null,
            'id_bodega'       => (int) $historial['id_bodega'],
            'bodega_codigo'   => $historial['bodega_codigo'],
            'bodega_nombre'   => $historial['bodega_nombre'],
            'id_ubicacion'    => (int) $historial['id_ubicacion'],
            'ubicacion_codigo' => $historial['ubicacion_codigo'],
            'zona'            => $historial['zona'],
            'pasillo'         => $historial['pasillo'],
            'estante'         => $historial['estante'],
            'cajon'           => $historial['cajon'],
            'nivel'           => $historial['nivel'],
            'tipo_ubicacion'  => $historial['tipo_ubicacion'],
            'ubicado_at'      => $historial['ubicado_at'],
        ];
    }

    /**
     * Devuelve todos los movimientos físicos del pedido en orden cronológico.
     *
     * Incluye: INGRESO, REUBICACION, RETIRO (retirado_at), con bodega,
     * ubicación, operador, motivo, fechas e indicador activo.
     *
     * @return array<int,array>
     */
    public function obtenerHistorial(int $idPedido): array
    {
        $this->verificarFlags();
        return $this->historialModel->obtenerHistorialPorPedido($idPedido);
    }
}
