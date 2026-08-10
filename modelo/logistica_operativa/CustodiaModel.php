<?php

declare(strict_types=1);

/**
 * CustodiaModel
 *
 * Persistencia y consultas para la tabla `logistica_custodias` (Fase 8 - Custodia Departamental y Traspasos).
 */
class CustodiaModel
{
    public function __construct(private PDO $db) {}

    public function listar(array $filtros = []): array
    {
        $sql = "
            SELECT 
                c.*, 
                p.numero_orden, 
                p.destinatario, 
                b.nombre as bodega_origen_nombre, 
                d.nombre as departamento_destino_nombre,
                u.nombre as responsable_nombre
            FROM logistica_custodias c
            LEFT JOIN pedidos p ON p.id = c.id_pedido
            LEFT JOIN logistica_bodegas b ON b.id = c.id_bodega_origen
            LEFT JOIN departamentos d ON d.id = c.id_departamento_destino
            LEFT JOIN usuarios u ON u.id = c.id_responsable
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filtros['estado'])) {
            $sql .= " AND c.estado = :estado";
            $params[':estado'] = $filtros['estado'];
        }

        if (!empty($filtros['id_departamento'])) {
            $sql .= " AND c.id_departamento_destino = :id_depto";
            $params[':id_depto'] = $filtros['id_departamento'];
        }

        $sql .= " ORDER BY c.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crear(
        int $idPedido,
        ?int $idBodegaOrigen,
        ?int $idDeptoDestino,
        int $idResponsable,
        ?string $observaciones
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO logistica_custodias (
                id_pedido, id_bodega_origen, id_departamento_destino, 
                id_responsable, estado, observaciones, created_at, updated_at
            ) VALUES (
                :id_pedido, :id_bodega_origen, :id_departamento_destino,
                :id_responsable, 'EN_TRANSITO', :observaciones, NOW(), NOW()
            )
        ");
        $stmt->execute([
            ':id_pedido'               => $idPedido,
            ':id_bodega_origen'        => $idBodegaOrigen,
            ':id_departamento_destino' => $idDeptoDestino,
            ':id_responsable'          => $idResponsable,
            ':observaciones'           => $observaciones
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function actualizarEstado(int $idCustodia, string $nuevoEstado, ?string $observaciones = null): bool
    {
        $sql = "UPDATE logistica_custodias SET estado = :estado, updated_at = NOW()";
        $params = [':estado' => $nuevoEstado, ':id' => $idCustodia];

        if ($nuevoEstado === 'RECIBIDO_CUSTODIA') {
            $sql .= ", recibido_at = NOW()";
        }

        if ($observaciones !== null) {
            $sql .= ", observaciones = CONCAT(IFNULL(observaciones, ''), '\n', :obs)";
            $params[':obs'] = $observaciones;
        }

        $sql .= " WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
}
