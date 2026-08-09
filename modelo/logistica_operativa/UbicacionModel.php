<?php

declare(strict_types=1);

/**
 * UbicacionModel
 *
 * Responsabilidad: persistencia y consultas sobre logistica_ubicaciones.
 *
 * Sin lógica HTTP. Sin conexión global. Sin echo/exit.
 * Recibe PDO por constructor.
 */
class UbicacionModel
{
    public function __construct(private PDO $db) {}

    /**
     * Obtiene una ubicación por ID.
     * Con forUpdate=true emite SELECT ... FOR UPDATE dentro de una transacción activa.
     */
    public function obtenerPorId(int $id, bool $forUpdate = false): ?array
    {
        $lock = $forUpdate ? ' FOR UPDATE' : '';
        $stmt = $this->db->prepare(
            "SELECT * FROM logistica_ubicaciones WHERE id = :id LIMIT 1{$lock}"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Obtiene una ubicación activa por ID que pertenezca a la bodega indicada.
     * Devuelve null si no existe, no es activa o no pertenece a la bodega.
     */
    public function obtenerActivaEnBodega(int $id, int $idBodega): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM logistica_ubicaciones
              WHERE id       = :id
                AND id_bodega = :id_bodega
                AND activa   = 1
              LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':id_bodega' => $idBodega]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Devuelve todas las ubicaciones activas de una bodega, ordenadas por código.
     * Solo se llama cuando id_bodega > 0.
     *
     * @return array<int,array{id:int,codigo:string,zona:string|null,pasillo:string|null,estante:string|null,cajon:string|null,nivel:string|null,tipo:string,nomenclatura:string}>
     */
    public function listarActivasPorBodega(int $idBodega): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, codigo, zona, pasillo, estante, cajon, nivel, tipo,
                    TRIM(CONCAT_WS("/",
                        IF(zona    IS NOT NULL AND zona    <>\'\', CONCAT(\'ZONA-\',    zona),    NULL),
                        IF(estante IS NOT NULL AND estante <>\'\', CONCAT(\'ESTANTE-\', estante), NULL),
                        IF(cajon   IS NOT NULL AND cajon   <>\'\', CONCAT(\'CAJ\xC3\xB3N-\',  cajon),   NULL)
                    )) AS nomenclatura
               FROM logistica_ubicaciones
              WHERE id_bodega = :id_bodega
                AND activa   = 1
           ORDER BY codigo ASC'
        );
        $stmt->execute([':id_bodega' => $idBodega]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
