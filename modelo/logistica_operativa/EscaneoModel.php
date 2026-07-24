<?php

declare(strict_types=1);

/**
 * EscaneoModel
 *
 * Responsabilidad: persistencia y consultas sobre logistica_escaneos.
 *
 * Sin lógica HTTP. Sin conexión global. Sin echo/exit.
 * Recibe PDO por constructor.
 */
class EscaneoModel
{
    public function __construct(private PDO $db) {}

    /**
     * Busca un escaneo por UUID. Devuelve null si no existe.
     */
    public function buscarPorUuid(string $uuid): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM logistica_escaneos WHERE uuid = :uuid LIMIT 1'
        );
        $stmt->execute([':uuid' => $uuid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Verifica si ya existe un escaneo con la misma combinación
     * (id_colecta, id_pedido, tipo_evento). Devuelve true si es duplicado.
     */
    public function existeEvento(int $idColecta, int $idPedido, string $tipoEvento): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM logistica_escaneos
              WHERE id_colecta  = :id_colecta
                AND id_pedido   = :id_pedido
                AND tipo_evento = :tipo_evento
              LIMIT 1'
        );
        $stmt->execute([
            ':id_colecta'  => $idColecta,
            ':id_pedido'   => $idPedido,
            ':tipo_evento' => $tipoEvento,
        ]);
        return $stmt->fetch() !== false;
    }

    /**
     * Inserta un nuevo escaneo y devuelve su ID.
     *
     * @param array{
     *   uuid: string,
     *   id_colecta: int|null,
     *   id_pedido: int,
     *   tipo_evento: string,
     *   qr_hash: string,
     *   id_operador: int,
     *   dispositivo: string|null,
     *   escaneado_at: string,
     *   metadata_json: string|null,
     * } $datos
     */
    public function insertar(array $datos): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO logistica_escaneos
               (uuid, id_colecta, id_pedido, tipo_evento,
                qr_hash, id_operador, dispositivo,
                escaneado_at, recibido_at, metadata_json, created_at)
             VALUES
               (:uuid, :id_colecta, :id_pedido, :tipo_evento,
                :qr_hash, :id_operador, :dispositivo,
                :escaneado_at, NOW(), :metadata_json, NOW())'
        );
        $stmt->execute([
            ':uuid'          => $datos['uuid'],
            ':id_colecta'    => $datos['id_colecta'],
            ':id_pedido'     => $datos['id_pedido'],
            ':tipo_evento'   => $datos['tipo_evento'],
            ':qr_hash'       => $datos['qr_hash'],
            ':id_operador'   => $datos['id_operador'],
            ':dispositivo'   => $datos['dispositivo'] ?? null,
            ':escaneado_at'  => $datos['escaneado_at'],
            ':metadata_json' => $datos['metadata_json'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }
}
