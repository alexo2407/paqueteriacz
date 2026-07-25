<?php

declare(strict_types=1);

/**
 * UbicacionHistorialModel
 *
 * Responsabilidad: persistencia y consultas sobre logistica_ubicacion_historial.
 *
 * Sin lógica HTTP. Sin conexión global. Sin echo/exit.
 * Recibe PDO por constructor.
 */
class UbicacionHistorialModel
{
    public function __construct(private PDO $db) {}

    // ── Búsquedas ─────────────────────────────────────────────────────────

    /**
     * Devuelve la fila activa (activo=1) del historial para un pedido.
     * Con forUpdate=true bloquea la fila en la transacción actual.
     *
     * @return array|null
     */
    public function obtenerActivoPorPedido(int $idPedido, bool $forUpdate = false): ?array
    {
        $lock = $forUpdate ? ' FOR UPDATE' : '';
        $stmt = $this->db->prepare(
            "SELECT h.*,
                    b.codigo  AS bodega_codigo,
                    b.nombre  AS bodega_nombre,
                    u.codigo  AS ubicacion_codigo,
                    u.zona, u.pasillo, u.estante, u.cajon, u.nivel,
                    u.tipo    AS tipo_ubicacion
               FROM logistica_ubicacion_historial h
               JOIN logistica_bodegas    b ON b.id = h.id_bodega
               JOIN logistica_ubicaciones u ON u.id = h.id_ubicacion
              WHERE h.id_pedido = :id_pedido
                AND h.activo   = 1
              LIMIT 1{$lock}"
        );
        $stmt->execute([':id_pedido' => $idPedido]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Cuenta cuántas filas activas (activo=1) tiene un pedido.
     * Se usa para verificar consistencia al final de cada operación.
     */
    public function contarActivosPorPedido(int $idPedido): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM logistica_ubicacion_historial
              WHERE id_pedido = :id_pedido AND activo = 1'
        );
        $stmt->execute([':id_pedido' => $idPedido]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Devuelve todo el historial de movimientos de un pedido en orden cronológico.
     */
    public function obtenerHistorialPorPedido(int $idPedido): array
    {
        $stmt = $this->db->prepare(
            'SELECT h.*,
                    b.codigo  AS bodega_codigo,
                    b.nombre  AS bodega_nombre,
                    u.codigo  AS ubicacion_codigo,
                    u.zona, u.pasillo, u.estante, u.cajon, u.nivel,
                    u.tipo    AS tipo_ubicacion,
                    usr.nombre AS operador_nombre
               FROM logistica_ubicacion_historial h
               JOIN logistica_bodegas     b   ON b.id   = h.id_bodega
               JOIN logistica_ubicaciones u   ON u.id   = h.id_ubicacion
               JOIN usuarios              usr ON usr.id = h.id_operador
              WHERE h.id_pedido = :id_pedido
              ORDER BY h.ubicado_at ASC, h.id ASC'
        );
        $stmt->execute([':id_pedido' => $idPedido]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Escrituras ────────────────────────────────────────────────────────

    /**
     * Inserta un nuevo movimiento y devuelve su ID.
     *
     * @param array{
     *   id_pedido: int,
     *   id_recepcion: int|null,
     *   id_bodega: int,
     *   id_ubicacion: int,
     *   id_operador: int,
     *   tipo_movimiento: string,
     *   motivo: string|null,
     *   activo: int,
     *   ubicado_at: string,
     * } $datos
     */
    public function insertar(array $datos): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO logistica_ubicacion_historial
               (id_pedido, id_recepcion, id_bodega, id_ubicacion, id_operador,
                tipo_movimiento, motivo, activo, ubicado_at, created_at)
             VALUES
               (:id_pedido, :id_recepcion, :id_bodega, :id_ubicacion, :id_operador,
                :tipo_movimiento, :motivo, :activo, :ubicado_at, NOW())'
        );
        $stmt->execute([
            ':id_pedido'      => $datos['id_pedido'],
            ':id_recepcion'   => $datos['id_recepcion'] ?? null,
            ':id_bodega'      => $datos['id_bodega'],
            ':id_ubicacion'   => $datos['id_ubicacion'],
            ':id_operador'    => $datos['id_operador'],
            ':tipo_movimiento' => $datos['tipo_movimiento'],
            ':motivo'         => $datos['motivo'] ?? null,
            ':activo'         => $datos['activo'],
            ':ubicado_at'     => $datos['ubicado_at'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Desactiva la fila activa de un pedido:
     * establece activo=0 y registra retirado_at.
     *
     * Solo desactiva filas que estén activo=1.
     */
    public function desactivarActivoPorPedido(int $idPedido, ?string $motivo = null): int
    {
        $stmt = $this->db->prepare(
            'UPDATE logistica_ubicacion_historial
                SET activo      = 0,
                    retirado_at = NOW()
              WHERE id_pedido   = :id_pedido
                AND activo      = 1'
        );
        $stmt->execute([':id_pedido' => $idPedido]);
        return $stmt->rowCount();
    }
}
