<?php

declare(strict_types=1);

/**
 * LiquidacionModel
 *
 * Persistencia y consultas sobre la tabla `logistica_liquidaciones` y cálculo de arqueos.
 */
class LiquidacionModel
{
    public function __construct(private PDO $db) {}

    public function obtenerPorRutaId(int $idRuta): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM logistica_liquidaciones WHERE id_ruta = :id_ruta LIMIT 1");
        $stmt->execute([':id_ruta' => $idRuta]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function obtenerPorId(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT l.*, r.codigo as codigo_ruta, r.nombre as nombre_ruta, u.nombre as operador_nombre
            FROM logistica_liquidaciones l
            LEFT JOIN logistica_rutas r ON r.id = l.id_ruta
            LEFT JOIN usuarios u ON u.id = l.id_operador
            WHERE l.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listar(array $filtros = []): array
    {
        $sql = "
            SELECT 
                l.*, 
                r.codigo as codigo_ruta, 
                r.nombre as nombre_ruta, 
                rep.nombre as repartidor_nombre,
                u.nombre as operador_nombre
            FROM logistica_liquidaciones l
            LEFT JOIN logistica_rutas r ON r.id = l.id_ruta
            LEFT JOIN usuarios rep ON rep.id = r.id_repartidor
            LEFT JOIN usuarios u ON u.id = l.id_operador
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filtros['estado'])) {
            $sql .= " AND l.estado = :estado";
            $params[':estado'] = $filtros['estado'];
        }

        $sql .= " ORDER BY l.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crear(
        int $idRuta,
        int $idOperador,
        float $codEsperado,
        float $codRecibido,
        float $diferencia,
        int $entregados,
        int $devueltos,
        int $reprogramados,
        ?string $observaciones,
        string $estado
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO logistica_liquidaciones (
                id_ruta, id_operador, total_cod_esperado, total_cod_recibido, 
                diferencia, total_entregados, total_devueltos, total_reprogramados, 
                observaciones, estado, liquidado_at, created_at, updated_at
            ) VALUES (
                :id_ruta, :id_operador, :total_cod_esperado, :total_cod_recibido,
                :diferencia, :total_entregados, :total_devueltos, :total_reprogramados,
                :observaciones, :estado, NOW(), NOW(), NOW()
            )
        ");
        $stmt->execute([
            ':id_ruta'            => $idRuta,
            ':id_operador'        => $idOperador,
            ':total_cod_esperado' => $codEsperado,
            ':total_cod_recibido' => $codRecibido,
            ':diferencia'         => $diferencia,
            ':total_entregados'   => $entregados,
            ':total_devueltos'     => $devueltos,
            ':total_reprogramados'=> $reprogramados,
            ':observaciones'      => $observaciones,
            ':estado'             => $estado
        ]);

        return (int) $this->db->lastInsertId();
    }
}
