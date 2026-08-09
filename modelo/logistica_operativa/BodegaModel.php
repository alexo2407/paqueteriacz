<?php

declare(strict_types=1);

/**
 * BodegaModel
 *
 * Responsabilidad: persistencia y consultas sobre logistica_bodegas.
 *
 * Sin lógica HTTP. Sin conexión global. Sin echo/exit.
 * Recibe PDO por constructor.
 */
class BodegaModel
{
    public function __construct(private PDO $db) {}

    /**
     * Obtiene una bodega por ID.
     * Con forUpdate=true emite SELECT ... FOR UPDATE dentro de transacción activa.
     */
    public function obtenerPorId(int $id, bool $forUpdate = false): ?array
    {
        $lock = $forUpdate ? ' FOR UPDATE' : '';
        $stmt = $this->db->prepare(
            "SELECT * FROM logistica_bodegas WHERE id = :id LIMIT 1{$lock}"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Verifica si una bodega existe y está activa.
     * Devuelve la fila o null.
     */
    public function obtenerActivaPorId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM logistica_bodegas
              WHERE id     = :id
                AND activa = 1
              LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Devuelve todas las bodegas activas ordenadas por nombre.
     * No incluye columnas que puedan no existir en la BD.
     *
     * @return array<int,array{id:int,nombre:string,codigo:string}>
     */
    public function listarActivas(): array
    {
        $stmt = $this->db->query(
            'SELECT id, nombre, codigo
               FROM logistica_bodegas
              WHERE activa = 1
           ORDER BY nombre ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
