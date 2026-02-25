<?php

/**
 * PedidoService
 *
 * Servicio central para cambios de estado de pedidos con movimientos de stock.
 * Debe llamarse DENTRO de una transacción activa ($db->beginTransaction() ya hecho).
 *
 * Estados que mueven stock:
 *   1  – En bodega     → RESERVA (sin tocar stock físico)
 *   2  – En ruta       → SALIDA física + libera reserva
 *   3  – Entregado     → Sin movimiento
 *   7  – Devuelto      → ENTRADA por devolución
 */

include_once __DIR__ . '/../modelo/conexion.php';
include_once __DIR__ . '/../modelo/stock.php';
include_once __DIR__ . '/../modelo/inventario.php';

class PedidoService
{
    // IDs de estado que disparan movimientos
    const ESTADO_EN_BODEGA  = 1;
    const ESTADO_EN_RUTA    = 2;
    const ESTADO_ENTREGADO  = 3;
    const ESTADO_DEVUELTO   = 7;

    /**
     * Aplica movimientos de stock según el nuevo estado del pedido.
     * Debe llamarse dentro de la transacción del método que cambia estado.
     *
     * @param int  $idPedido     ID del pedido
     * @param int  $nuevoEstado  ID del nuevo estado (1,2,3,7,...)
     * @param int  $actorUserId  ID del usuario que realiza el cambio
     * @param PDO  $db           Conexión PDO activa (con transacción en curso)
     * @return void
     * @throws Exception si ocurre un error irrecuperable
     */
    public static function aplicarStockPorEstado(int $idPedido, int $nuevoEstado, int $actorUserId, PDO $db): void
    {
        switch ($nuevoEstado) {
            case self::ESTADO_EN_BODEGA:
                self::aplicarReserva($idPedido, $actorUserId, $db);
                break;

            case self::ESTADO_EN_RUTA:
                self::aplicarSalidaFisica($idPedido, $actorUserId, $db);
                break;

            case self::ESTADO_ENTREGADO:
                // Sin movimiento de stock – la salida ya se hizo en estado 2
                break;

            case self::ESTADO_DEVUELTO:
                self::aplicarDevolucion($idPedido, $actorUserId, $db);
                break;

            // Cualquier otro estado: sin movimiento de stock
        }
    }

    // =========================================================================
    // ESTADO 1 – RESERVA
    // =========================================================================

    /**
     * Reserva stock por cada producto del pedido.
     * Idempotente: si la reserva ya existe para ese pedido+producto, no la duplica.
     * Actualiza inventario.cantidad_reservada (el trigger no lo hace).
     */
    private static function aplicarReserva(int $idPedido, int $actorUserId, PDO $db): void
    {
        $productos = self::obtenerProductosPedido($idPedido, $db);

        foreach ($productos as $p) {
            $idProducto = (int)$p['id_producto'];
            $cantidad   = max(0, (int)$p['cantidad'] - (int)($p['cantidad_devuelta'] ?? 0));

            if ($cantidad <= 0) continue;

            // Idempotencia: INSERT IGNORE si ya existe la reserva
            $stmt = $db->prepare('
                INSERT IGNORE INTO pedido_reservas_stock
                    (id_pedido, id_producto, cantidad, liberada)
                VALUES
                    (:id_pedido, :id_producto, :cantidad, 0)
            ');
            $stmt->execute([
                ':id_pedido'   => $idPedido,
                ':id_producto' => $idProducto,
                ':cantidad'    => $cantidad,
            ]);

            // Solo si se insertó (no era duplicado) actualizamos inventario
            if ($stmt->rowCount() > 0) {
                $stmt2 = $db->prepare('
                    UPDATE inventario
                    SET cantidad_reservada = cantidad_reservada + :cantidad,
                        updated_at         = NOW()
                    WHERE id_producto = :id_producto AND ubicacion = :ubicacion
                ');
                $stmt2->execute([
                    ':cantidad'    => $cantidad,
                    ':id_producto' => $idProducto,
                    ':ubicacion'   => 'Principal',
                ]);
            }
        }
    }

    // =========================================================================
    // ESTADO 2 – SALIDA FÍSICA
    // =========================================================================

    /**
     * Registra la salida física del stock al pasar a "En ruta".
     * Idempotente: si ya existe una salida para este pedido, no la duplica.
     * Libera las reservas existentes.
     */
    private static function aplicarSalidaFisica(int $idPedido, int $actorUserId, PDO $db): void
    {
        // Verificar si ya existe una salida para este pedido
        $stmt = $db->prepare('
            SELECT COUNT(*) FROM stock
            WHERE referencia_tipo = :tipo
              AND referencia_id   = :id
              AND tipo_movimiento = :movimiento
        ');
        $stmt->execute([
            ':tipo'       => 'pedido',
            ':id'         => $idPedido,
            ':movimiento' => 'salida',
        ]);

        if ((int)$stmt->fetchColumn() > 0) {
            return; // Ya se registró la salida, no duplicar
        }

        $productos = self::obtenerProductosPedido($idPedido, $db);

        foreach ($productos as $p) {
            $idProducto = (int)$p['id_producto'];
            $cantidad   = max(0, (int)$p['cantidad'] - (int)($p['cantidad_devuelta'] ?? 0));

            if ($cantidad <= 0) continue;

            // INSERT en stock con cantidad negativa (el trigger actualiza inventario.cantidad_disponible)
            $stmt = $db->prepare('
                INSERT INTO stock
                    (id_producto, id_usuario, cantidad, tipo_movimiento,
                     referencia_tipo, referencia_id, motivo,
                     ubicacion_origen, ubicacion_destino)
                VALUES
                    (:id_producto, :id_usuario, :cantidad, :tipo,
                     :ref_tipo, :ref_id, :motivo,
                     :origen, :destino)
            ');
            $stmt->execute([
                ':id_producto' => $idProducto,
                ':id_usuario'  => $actorUserId,
                ':cantidad'    => -$cantidad,
                ':tipo'        => 'salida',
                ':ref_tipo'    => 'pedido',
                ':ref_id'      => $idPedido,
                ':motivo'      => 'Salida por cambio a En ruta o proceso',
                ':origen'      => 'Bodega',
                ':destino'     => 'Principal',
            ]);

            // Liberar reserva si existía
            $reserva = self::obtenerReserva($idPedido, $idProducto, $db);
            if ($reserva && !(int)$reserva['liberada']) {
                // Reducir cantidad_reservada en inventario
                $stmt2 = $db->prepare('
                    UPDATE inventario
                    SET cantidad_reservada = GREATEST(0, cantidad_reservada - :cantidad),
                        updated_at         = NOW()
                    WHERE id_producto = :id_producto AND ubicacion = :ubicacion
                ');
                $stmt2->execute([
                    ':cantidad'    => (int)$reserva['cantidad'],
                    ':id_producto' => $idProducto,
                    ':ubicacion'   => 'Principal',
                ]);

                // Marcar reserva como liberada
                $stmt3 = $db->prepare('
                    UPDATE pedido_reservas_stock
                    SET liberada = 1, updated_at = NOW()
                    WHERE id_pedido = :id_pedido AND id_producto = :id_producto
                ');
                $stmt3->execute([
                    ':id_pedido'   => $idPedido,
                    ':id_producto' => $idProducto,
                ]);
            }
        }
    }

    // =========================================================================
    // ESTADO 7 – DEVOLUCIÓN
    // =========================================================================

    /**
     * Registra entrada de stock por devolución.
     * Solo para productos con cantidad_devuelta > 0.
     * Idempotente: verifica si ya existe una entrada de devolución para ese pedido+producto.
     */
    private static function aplicarDevolucion(int $idPedido, int $actorUserId, PDO $db): void
    {
        $productos = self::obtenerProductosPedido($idPedido, $db);

        foreach ($productos as $p) {
            $idProducto      = (int)$p['id_producto'];
            $cantDevuelta    = (int)($p['cantidad_devuelta'] ?? 0);

            if ($cantDevuelta <= 0) continue;

            // Idempotencia: verificar si ya existe devolución para este pedido+producto
            $stmt = $db->prepare('
                SELECT COUNT(*) FROM stock
                WHERE referencia_tipo = :tipo
                  AND referencia_id   = :id
                  AND id_producto     = :id_producto
                  AND tipo_movimiento = :movimiento
            ');
            $stmt->execute([
                ':tipo'        => 'pedido',
                ':id'          => $idPedido,
                ':id_producto' => $idProducto,
                ':movimiento'  => 'devolucion',
            ]);

            if ((int)$stmt->fetchColumn() > 0) {
                continue; // Ya se registró esta devolución
            }

            // Registrar entrada (positiva) — el trigger actualizará inventario.cantidad_disponible
            $stmt2 = $db->prepare('
                INSERT INTO stock
                    (id_producto, id_usuario, cantidad, tipo_movimiento,
                     referencia_tipo, referencia_id, motivo,
                     ubicacion_origen, ubicacion_destino)
                VALUES
                    (:id_producto, :id_usuario, :cantidad, :tipo,
                     :ref_tipo, :ref_id, :motivo,
                     :origen, :destino)
            ');
            $stmt2->execute([
                ':id_producto' => $idProducto,
                ':id_usuario'  => $actorUserId,
                ':cantidad'    => $cantDevuelta,
                ':tipo'        => 'devolucion',
                ':ref_tipo'    => 'pedido',
                ':ref_id'      => $idPedido,
                ':motivo'      => 'Devolución de pedido #' . $idPedido,
                ':origen'      => 'Ruta',
                ':destino'     => 'Bodega',
            ]);
        }
    }

    // =========================================================================
    // Helpers internos
    // =========================================================================

    /**
     * Obtener productos de un pedido con sus cantidades.
     */
    private static function obtenerProductosPedido(int $idPedido, PDO $db): array
    {
        $stmt = $db->prepare('
            SELECT id_producto, cantidad, COALESCE(cantidad_devuelta, 0) AS cantidad_devuelta
            FROM pedidos_productos
            WHERE id_pedido = :id_pedido
        ');
        $stmt->execute([':id_pedido' => $idPedido]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener fila de reserva para un pedido+producto.
     */
    private static function obtenerReserva(int $idPedido, int $idProducto, PDO $db): ?array
    {
        $stmt = $db->prepare('
            SELECT * FROM pedido_reservas_stock
            WHERE id_pedido = :id_pedido AND id_producto = :id_producto
            LIMIT 1
        ');
        $stmt->execute([
            ':id_pedido'   => $idPedido,
            ':id_producto' => $idProducto,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
