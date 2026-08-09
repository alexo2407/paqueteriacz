<?php

declare(strict_types=1);

/**
 * RutaModel
 *
 * Responsabilidad: persistencia y consultas sobre logistica_rutas
 * y logistica_ruta_pedidos.
 */
class RutaModel
{
    public function __construct(private PDO $db) {}

    /**
     * Inserta una nueva ruta y devuelve su ID.
     */
    public function insertar(
        string $codigo,
        string $nombre,
        string $fecha,
        int    $idRepartidor,
        int    $idCreadaPor,
        int    $cantidadPedidos,
        float  $totalCod
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO logistica_rutas
               (codigo, nombre, fecha, id_repartidor, estado, cantidad_pedidos, total_cod, id_creada_por, created_at, updated_at)
             VALUES
               (:codigo, :nombre, :fecha, :id_repartidor, 'ASIGNADA', :cantidad_pedidos, :total_cod, :id_creada_por, NOW(), NOW())
        ");
        $stmt->execute([
            ':codigo'           => $codigo,
            ':nombre'           => $nombre,
            ':fecha'            => $fecha,
            ':id_repartidor'    => $idRepartidor,
            ':cantidad_pedidos' => $cantidadPedidos,
            ':total_cod'        => $totalCod,
            ':id_creada_por'    => $idCreadaPor,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Asigna un pedido a una ruta.
     */
    public function insertarPedido(int $idRuta, int $idPedido, int $ordenVisita, float $montoCod): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO logistica_ruta_pedidos
               (id_ruta, id_pedido, orden_visita, monto_cod, estado_entrega, created_at, updated_at)
             VALUES
               (:id_ruta, :id_pedido, :orden_visita, :monto_cod, 'PENDIENTE', NOW(), NOW())
        ");
        $stmt->execute([
            ':id_ruta'      => $idRuta,
            ':id_pedido'    => $idPedido,
            ':orden_visita' => $ordenVisita,
            ':monto_cod'    => $montoCod,
        ]);
    }

    /**
     * Obtiene una ruta por ID.
     */
    public function obtenerPorId(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT r.*,
                   ur.nombre AS repartidor_nombre,
                   uc.nombre AS creada_por_nombre,
                   us.nombre AS sellada_por_nombre
              FROM logistica_rutas r
         LEFT JOIN usuarios ur ON ur.id = r.id_repartidor
         LEFT JOIN usuarios uc ON uc.id = r.id_creada_por
         LEFT JOIN usuarios us ON us.id = r.id_sellada_por
             WHERE r.id = :id
             LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Devuelve los pedidos de una ruta.
     */
    public function obtenerPedidosDeRuta(int $idRuta): array
    {
        $stmt = $this->db->prepare("
            SELECT
                rp.id,
                rp.id_pedido,
                rp.orden_visita,
                rp.monto_cod,
                rp.estado_entrega,
                p.numero_orden,
                p.destinatario,
                p.telefono,
                p.direccion,
                p.departmentName,
                p.municipalitiesName,
                u.codigo AS ubicacion_codigo
              FROM logistica_ruta_pedidos rp
         LEFT JOIN pedidos p ON p.id = rp.id_pedido
         LEFT JOIN logistica_ubicacion_historial uh ON (uh.id_pedido = p.id AND uh.activo = 1)
         LEFT JOIN logistica_ubicaciones u ON u.id = uh.id_ubicacion
             WHERE rp.id_ruta = :id_ruta
          ORDER BY rp.orden_visita ASC
        ");
        $stmt->execute([':id_ruta' => $idRuta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Sella la ruta (no permite más cambios de asignación).
     */
    public function sellar(int $idRuta, int $idSelladaPor): void
    {
        $stmt = $this->db->prepare("
            UPDATE logistica_rutas
               SET estado         = 'SELLADA',
                   id_sellada_por = :id_sellada_por,
                   sellada_at     = NOW(),
                   updated_at     = NOW()
             WHERE id = :id
        ");
        $stmt->execute([':id_sellada_por' => $idSelladaPor, ':id' => $idRuta]);
    }

    /**
     * Lista rutas con filtros opcionales.
     */
    public function listarConFiltros(array $filtros = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filtros['fecha'])) {
            $where[] = 'r.fecha = :fecha';
            $params[':fecha'] = $filtros['fecha'];
        }
        if (!empty($filtros['estado'])) {
            $where[] = 'r.estado = :estado';
            $params[':estado'] = $filtros['estado'];
        }
        if (!empty($filtros['id_repartidor'])) {
            $where[] = 'r.id_repartidor = :id_repartidor';
            $params[':id_repartidor'] = (int)$filtros['id_repartidor'];
        }
        if (!empty($filtros['repartidor'])) {
            $where[] = 'ur.nombre LIKE :repartidor';
            $params[':repartidor'] = '%' . $filtros['repartidor'] . '%';
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $stmt = $this->db->prepare("
            SELECT
                r.id,
                r.codigo,
                r.nombre,
                r.fecha,
                r.estado,
                r.cantidad_pedidos,
                r.total_cod,
                r.sellada_at,
                r.created_at,
                ur.nombre AS repartidor_nombre,
                uc.nombre AS creada_por_nombre
              FROM logistica_rutas r
         LEFT JOIN usuarios ur ON ur.id = r.id_repartidor
         LEFT JOIN usuarios uc ON uc.id = r.id_creada_por
              {$whereSql}
          ORDER BY r.created_at DESC
             LIMIT 100
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene paquetes en bodega listos para ser asignados a una ruta.
     */
    public function obtenerPaquetesUbicadosElegibles(): array
    {
        $stmt = $this->db->query("
            SELECT
                p.id AS id_pedido,
                p.numero_orden,
                p.destinatario,
                p.departmentName,
                p.municipalitiesName,
                p.precio_total_local AS monto_cod,
                u.codigo AS ubicacion_codigo
              FROM logistica_recepciones r
              JOIN pedidos p ON p.id = r.id_pedido
         LEFT JOIN logistica_ubicacion_historial uh ON (uh.id_pedido = p.id AND uh.activo = 1)
         LEFT JOIN logistica_ubicaciones u ON u.id = uh.id_ubicacion
             WHERE r.estado = 'UBICADO'
               AND p.id_estado NOT IN (3, 7, 17)
               AND NOT EXISTS (
                   SELECT 1 FROM logistica_ruta_pedidos rp
                   JOIN logistica_rutas lr ON lr.id = rp.id_ruta
                  WHERE rp.id_pedido = p.id
                    AND lr.estado IN ('ASIGNADA', 'SELLADA', 'EN_CURSO')
               )
          ORDER BY p.id DESC
             LIMIT 100
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
