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
}
