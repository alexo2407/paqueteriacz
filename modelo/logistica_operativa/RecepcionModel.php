<?php

declare(strict_types=1);

/**
 * RecepcionModel
 *
 * Responsabilidad: persistencia y consultas sobre logistica_recepciones.
 *
 * Sin lógica HTTP. Sin conexión global. Sin echo/exit.
 * Recibe PDO por constructor.
 */
class RecepcionModel
{
    public function __construct(private PDO $db) {}

    // ── Búsquedas ─────────────────────────────────────────────────────────

    /**
     * Busca una recepción por UUID. Devuelve null si no existe.
     */
    public function buscarPorUuid(string $uuid): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM logistica_recepciones WHERE uuid = :uuid LIMIT 1'
        );
        $stmt->execute([':uuid' => $uuid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Obtiene una recepción por ID, con bloqueo FOR UPDATE opcional.
     */
    public function obtenerPorId(int $id, bool $forUpdate = false): ?array
    {
        $lock = $forUpdate ? ' FOR UPDATE' : '';
        $stmt = $this->db->prepare(
            "SELECT * FROM logistica_recepciones WHERE id = :id LIMIT 1{$lock}"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Devuelve la recepción activa (RECIBIDO o UBICADO) para un pedido, si existe.
     * Con forUpdate=true emite FOR UPDATE para bloquear la fila en transacción.
     */
    public function obtenerActivaPorPedido(int $idPedido, bool $forUpdate = false): ?array
    {
        $lock = $forUpdate ? ' FOR UPDATE' : '';
        $stmt = $this->db->prepare(
            "SELECT * FROM logistica_recepciones
              WHERE id_pedido = :id_pedido
                AND estado   IN ('RECIBIDO', 'UBICADO')
              LIMIT 1{$lock}"
        );
        $stmt->execute([':id_pedido' => $idPedido]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    // ── Escrituras ────────────────────────────────────────────────────────

    /**
     * Inserta una nueva recepción y devuelve su ID.
     *
     * @param array{
     *   uuid: string,
     *   id_pedido: int,
     *   id_bodega: int,
     *   id_ubicacion: int|null,
     *   id_escaneo: int|null,
     *   tipo_recepcion: string,
     *   estado: string,
     *   id_operador: int,
     *   recibido_at: string,
     *   observacion: string|null,
     * } $datos
     */
    public function insertar(array $datos): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO logistica_recepciones
               (uuid, id_pedido, id_bodega, id_ubicacion, id_escaneo,
                tipo_recepcion, estado, id_operador, recibido_at, observacion,
                created_at, updated_at)
             VALUES
               (:uuid, :id_pedido, :id_bodega, :id_ubicacion, :id_escaneo,
                :tipo_recepcion, :estado, :id_operador, :recibido_at, :observacion,
                NOW(), NOW())'
        );
        $stmt->execute([
            ':uuid'           => $datos['uuid'],
            ':id_pedido'      => $datos['id_pedido'],
            ':id_bodega'      => $datos['id_bodega'],
            ':id_ubicacion'   => $datos['id_ubicacion'],
            ':id_escaneo'     => $datos['id_escaneo'],
            ':tipo_recepcion' => $datos['tipo_recepcion'],
            ':estado'         => $datos['estado'],
            ':id_operador'    => $datos['id_operador'],
            ':recibido_at'    => $datos['recibido_at'],
            ':observacion'    => $datos['observacion'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Actualiza el estado de una recepción y opcionalmente su id_ubicacion.
     */
    public function actualizarEstado(int $id, string $estado, ?int $idUbicacion = null): void
    {
        $stmt = $this->db->prepare(
            'UPDATE logistica_recepciones
                SET estado       = :estado,
                    id_ubicacion = :id_ubicacion,
                    updated_at   = NOW()
              WHERE id = :id'
        );
        $stmt->execute([
            ':estado'       => $estado,
            ':id_ubicacion' => $idUbicacion,
            ':id'           => $id,
        ]);
    }
}
