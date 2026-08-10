<?php

declare(strict_types=1);

/**
 * DevolucionModel
 *
 * Persistencia y consultas para manifiestos de devolución (Logística Inversa - Fase 9).
 */
class DevolucionModel
{
    public function __construct(private PDO $db) {}

    public function listar(array $filtros = []): array
    {
        $sql = "
            SELECT 
                d.*, 
                c.nombre as cliente_nombre, 
                p.nombre as proveedor_nombre,
                u.nombre as operador_nombre
            FROM logistica_devoluciones d
            LEFT JOIN usuarios c ON c.id = d.id_cliente
            LEFT JOIN usuarios p ON p.id = d.id_proveedor
            LEFT JOIN usuarios u ON u.id = d.id_operador
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filtros['id_cliente'])) {
            $sql .= " AND d.id_cliente = :id_cliente";
            $params[':id_cliente'] = $filtros['id_cliente'];
        }

        if (!empty($filtros['estado'])) {
            $sql .= " AND d.estado = :estado";
            $params[':estado'] = $filtros['estado'];
        }

        $sql .= " ORDER BY d.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT 
                d.*, 
                c.nombre as cliente_nombre, 
                p.nombre as proveedor_nombre,
                u.nombre as operador_nombre
            FROM logistica_devoluciones d
            LEFT JOIN usuarios c ON c.id = d.id_cliente
            LEFT JOIN usuarios p ON p.id = d.id_proveedor
            LEFT JOIN usuarios u ON u.id = d.id_operador
            WHERE d.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function obtenerPedidos(int $idDevolucion): array
    {
        $stmt = $this->db->prepare("
            SELECT dp.*, p.numero_orden, p.destinatario, p.precio_total_local
            FROM logistica_devolucion_pedidos dp
            JOIN pedidos p ON p.id = dp.id_pedido
            WHERE dp.id_devolucion = :id_dev
            ORDER BY dp.id ASC
        ");
        $stmt->execute([':id_dev' => $idDevolucion]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crearManifiesto(
        string $codigoManifiesto,
        int $idCliente,
        ?int $idProveedor,
        int $idOperador,
        string $fechaDevolucion,
        ?string $observaciones
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO logistica_devoluciones (
                codigo_manifiesto, id_cliente, id_proveedor, id_operador,
                total_paquetes, estado, fecha_devolucion, observaciones, created_at, updated_at
            ) VALUES (
                :codigo, :id_cliente, :id_proveedor, :id_operador,
                0, 'BORRADOR', :fecha, :observaciones, NOW(), NOW()
            )
        ");
        $stmt->execute([
            ':codigo'        => $codigoManifiesto,
            ':id_cliente'    => $idCliente,
            ':id_proveedor'  => $idProveedor,
            ':id_operador'   => $idOperador,
            ':fecha'         => $fechaDevolucion,
            ':observaciones' => $observaciones
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function agregarPedido(int $idDevolucion, int $idPedido, ?string $obs = null): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO logistica_devolucion_pedidos (id_devolucion, id_pedido, observaciones, created_at)
            VALUES (:id_dev, :id_ped, :obs, NOW())
        ");
        $stmt->execute([':id_dev' => $idDevolucion, ':id_ped' => $idPedido, ':obs' => $obs]);

        // Actualizar total_paquetes en la cabecera
        $stmt2 = $this->db->prepare("
            UPDATE logistica_devoluciones
            SET total_paquetes = (SELECT COUNT(*) FROM logistica_devolucion_pedidos WHERE id_devolucion = :id_dev)
            WHERE id = :id_dev
        ");
        $stmt2->execute([':id_dev' => $idDevolucion]);
    }

    public function finalizarDevolucion(int $idDevolucion, ?string $firmaCliente = null): bool
    {
        $stmt = $this->db->prepare("
            UPDATE logistica_devoluciones
            SET estado = 'ENTREGADO_CLIENTE',
                firma_cliente = :firma,
                updated_at = NOW()
            WHERE id = :id
        ");
        return $stmt->execute([':firma' => $firmaCliente, ':id' => $idDevolucion]);
    }
}
