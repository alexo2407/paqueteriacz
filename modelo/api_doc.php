<?php
/**
 * ApiDocModel.php
 * Modelo para el historial de documentos de API generados.
 * Permite guardar y recuperar documentos generados por el wizard.
 */

require_once __DIR__ . '/conexion.php';

class ApiDocModel
{
    /**
     * Guarda un documento generado en el historial.
     *
     * @param array $data Datos del documento:
     *   - titulo: string
     *   - empresa_cliente: string
     *   - url_base: string
     *   - secciones: array (lista de secciones incluidas)
     *   - config_json: array (configuración completa del wizard)
     *   - html_generado: string (HTML del documento)
     *   - id_usuario: int|null
     * @return int ID del registro creado
     */
    public static function guardar(array $data): int
    {
        $db = (new Conexion())->conectar();
        $stmt = $db->prepare("
            INSERT INTO api_doc_historial
                (titulo, empresa_cliente, url_base, secciones, config_json, html_generado, id_usuario)
            VALUES
                (:titulo, :empresa_cliente, :url_base, :secciones, :config_json, :html_generado, :id_usuario)
        ");
        $stmt->execute([
            ':titulo'          => $data['titulo'],
            ':empresa_cliente' => $data['empresa_cliente'],
            ':url_base'        => $data['url_base'],
            ':secciones'       => json_encode($data['secciones'] ?? []),
            ':config_json'     => json_encode($data['config_json'] ?? []),
            ':html_generado'   => $data['html_generado'],
            ':id_usuario'      => $data['id_usuario'] ?? null,
        ]);
        return (int)$db->lastInsertId();
    }

    /**
     * Obtiene el listado de documentos del historial.
     *
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function listar(int $limit = 50, int $offset = 0): array
    {
        $db   = (new Conexion())->conectar();
        $stmt = $db->prepare("
            SELECT id, titulo, empresa_cliente, url_base, secciones, id_usuario, created_at
            FROM api_doc_historial
            ORDER BY created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene un documento específico por su ID.
     *
     * @param int $id
     * @return array|null
     */
    public static function obtenerPorId(int $id): ?array
    {
        $db   = (new Conexion())->conectar();
        $stmt = $db->prepare("
            SELECT * FROM api_doc_historial WHERE id = :id LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        // Decodificar JSON
        $row['secciones']   = json_decode($row['secciones']   ?? '[]', true) ?? [];
        $row['config_json'] = json_decode($row['config_json'] ?? '{}', true) ?? [];
        return $row;
    }

    /**
     * Elimina un documento del historial por su ID.
     *
     * @param int $id
     * @return bool
     */
    public static function eliminar(int $id): bool
    {
        $db   = (new Conexion())->conectar();
        $stmt = $db->prepare("DELETE FROM api_doc_historial WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Cuenta el total de documentos en el historial.
     *
     * @return int
     */
    public static function contar(): int
    {
        $db   = (new Conexion())->conectar();
        $stmt = $db->query("SELECT COUNT(*) FROM api_doc_historial");
        return (int)$stmt->fetchColumn();
    }
}
