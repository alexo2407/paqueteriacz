<?php

declare(strict_types=1);

/**
 * CampoModel
 *
 * Manejo de operaciones de entrega e incidencias realizadas en campo por el repartidor.
 */
class CampoModel
{
    public function __construct(private PDO $db) {}

    public function registrarEntrega(
        int $idRuta,
        int $idPedido,
        ?string $firmaCliente,
        ?string $evidenciaFotoUrl,
        ?float $latitud,
        ?float $longitud,
        ?string $notasCampo
    ): bool {
        $stmt = $this->db->prepare("
            UPDATE logistica_ruta_pedidos
            SET estado_entrega = 'ENTREGADO',
                firma_cliente = :firma,
                evidencia_foto_url = :foto,
                latitud = :lat,
                longitud = :lng,
                notas_campo = :notas,
                entregado_at = NOW(),
                updated_at = NOW()
            WHERE id_ruta = :id_ruta AND id_pedido = :id_pedido
        ");
        return $stmt->execute([
            ':firma'     => $firmaCliente,
            ':foto'      => $evidenciaFotoUrl,
            ':lat'       => $latitud,
            ':lng'       => $longitud,
            ':notas'     => $notasCampo,
            ':id_ruta'   => $idRuta,
            ':id_pedido' => $idPedido
        ]);
    }

    public function registrarIncidencia(
        int $idRuta,
        int $idPedido,
        string $tipoIncidencia,
        ?string $notasCampo,
        ?float $latitud,
        ?float $longitud
    ): bool {
        $stmt = $this->db->prepare("
            UPDATE logistica_ruta_pedidos
            SET estado_entrega = 'INCIDENCIA',
                notas_campo = :notas,
                latitud = :lat,
                longitud = :lng,
                updated_at = NOW()
            WHERE id_ruta = :id_ruta AND id_pedido = :id_pedido
        ");
        return $stmt->execute([
            ':notas'     => "[$tipoIncidencia] " . ($notasCampo ?? ''),
            ':lat'       => $latitud,
            ':lng'       => $longitud,
            ':id_ruta'   => $idRuta,
            ':id_pedido' => $idPedido
        ]);
    }

    public function obtenerRutaActivaRepartidor(int $idRepartidor): ?array
    {
        $stmt = $this->db->prepare("
            SELECT r.*, count(rp.id) as total_paquetes
            FROM logistica_rutas r
            LEFT JOIN logistica_ruta_pedidos rp ON rp.id_ruta = r.id
            WHERE r.id_repartidor = :id_repartidor AND r.fecha = CURDATE()
            GROUP BY r.id
            ORDER BY r.id DESC
            LIMIT 1
        ");
        $stmt->execute([':id_repartidor' => $idRepartidor]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
