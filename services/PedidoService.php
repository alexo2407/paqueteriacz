<?php

    /**
     * PedidoService
     *
     * Servicio central para cambios de estado de pedidos con movimientos de stock.
     * Debe llamarse DENTRO de una transacción activa ($db->beginTransaction() ya hecho).
     *
     * Reglas de stock por estado (Opción B – sin doble conteo):
     *   1  – En bodega          → RESERVA (sin tocar stock físico)
     *   2  – En ruta o proceso  → SALIDA física + libera reserva
     *   3  – Entregado          → Sin movimiento SI ya pasó por estado 2.
     *                             SALIDA AUTOMÁTICA + libera reserva si vino directo
     *                             desde estado 1 (flujo abreviado: En bodega → Entregado).
     *   7  – Devuelto           → Sin movimiento (solo acuse; el físico lo hace estado 15)
     *   9  – Rechazado          → Sin movimiento (producto no necesariamente regresa)
     *  15  – Devuelto a bodega  → ENTRADA por devolución (llegada física confirmada)
     */

    include_once __DIR__ . '/../modelo/conexion.php';
    include_once __DIR__ . '/../modelo/stock.php';
    include_once __DIR__ . '/../modelo/inventario.php';

    class PedidoService
    {
        // IDs de estado que disparan movimientos
        const ESTADO_EN_BODEGA       = 1;
        const ESTADO_EN_RUTA         = 2;
        const ESTADO_ENTREGADO       = 3;
        const ESTADO_CANCELADO       = 5;
        const ESTADO_DEVUELTO        = 7;
        const ESTADO_RECHAZADO       = 9;
        const ESTADO_DEVUELTO_BODEGA = 15; // Llegada física a bodega → suma stock

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
                    // Normalmente la salida física ya ocurrió en estado 2 (En ruta).
                    // CASO ESPECIAL: si el pedido saltó de "En bodega" (1) a "Entregado" (3)
                    // directamente (sin pasar por "En ruta"), la salida física nunca se registró.
                    // → Aplicamos la salida + liberamos la reserva automáticamente.
                    self::aplicarSalidaFisicaSiPendiente($idPedido, $actorUserId, $db);
                    break;

                case self::ESTADO_DEVUELTO:
                    // Solo acuse de que el cliente devuelve el pedido.
                    // El stock NO se mueve aquí para evitar doble conteo.
                    // La entrada física se registra cuando llega a bodega (estado 15).
                    break;

                case self::ESTADO_DEVUELTO_BODEGA:
                    // Confirma llegada física a bodega → suma stock.
                    self::aplicarDevolucion($idPedido, $actorUserId, $db);
                    break;

                case self::ESTADO_RECHAZADO:
                    // El destinatario rechaza pero el producto no necesariamente
                    // regresa a bodega en ese momento. Sin movimiento de stock.
                    // Usar "Devuelto a bodega" (15) cuando llegue físicamente.
                    break;

                case self::ESTADO_CANCELADO:
                    // Libera la reserva si existía (evita fuga de stock)
                    self::liberarReservaPedido($idPedido, $db);
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
         *
         * Opción B — balance neto:
         *   Bloquea solo si salidas > devoluciones (stock todavía fuera).
         *   Permite re-despacho cuando todas las salidas anteriores fueron devueltas.
         * Libera las reservas existentes.
         */
        private static function aplicarSalidaFisica(int $idPedido, int $actorUserId, PDO $db): void
        {
            // Balance neto: contar salidas y devoluciones para este pedido
            $stmtBal = $db->prepare('
                SELECT
                    SUM(CASE WHEN tipo_movimiento = :salida     THEN 1 ELSE 0 END) AS cnt_salidas,
                    SUM(CASE WHEN tipo_movimiento = :devolucion THEN 1 ELSE 0 END) AS cnt_dev
                FROM stock
                WHERE referencia_tipo = :tipo
                  AND referencia_id   = :id
            ');
            $stmtBal->execute([
                ':tipo'       => 'pedido',
                ':id'         => $idPedido,
                ':salida'     => 'salida',
                ':devolucion' => 'devolucion',
            ]);
            $bal        = $stmtBal->fetch(PDO::FETCH_ASSOC);
            $cntSalidas = (int)($bal['cnt_salidas'] ?? 0);
            $cntDev     = (int)($bal['cnt_dev']     ?? 0);

            if ($cntSalidas > $cntDev) {
                return; // Stock todavía fuera (más salidas que devoluciones), no duplicar
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
         *
         * Lógica:
         *  - Si cantidad_devuelta = 0 en pedidos_productos, se asume devolución total
         *    y se usa la cantidad completa del pedido.
         *  - Si el pedido tuvo salida física (estuvo "En ruta"): registra ENTRADA de devolución.
         *  - Si el pedido NUNCA salió (se devuelve desde "En bodega"): solo libera la reserva
         *    sin agregar stock que nunca salió, para no inflar el inventario.
         *
         * Idempotente: no duplica movimientos ya registrados.
         */
        private static function aplicarDevolucion(int $idPedido, int $actorUserId, PDO $db): void
        {
            // Verificar si hubo salida física para este pedido
            $stmtSalida = $db->prepare('
            SELECT COUNT(*) FROM stock
            WHERE referencia_tipo = :tipo
              AND referencia_id   = :id
              AND tipo_movimiento = :movimiento
        ');
            $stmtSalida->execute([
                ':tipo'       => 'pedido',
                ':id'         => $idPedido,
                ':movimiento' => 'salida',
            ]);
            $huboSalidaFisica = (int)$stmtSalida->fetchColumn() > 0;

            $productos = self::obtenerProductosPedido($idPedido, $db);

            foreach ($productos as $p) {
                $idProducto   = (int)$p['id_producto'];
                $cantDevuelta = (int)($p['cantidad_devuelta'] ?? 0);

                // Fix: si cantidad_devuelta = 0, asumir devolución total
                if ($cantDevuelta <= 0) {
                    $cantDevuelta = (int)$p['cantidad'];
                }

                if ($cantDevuelta <= 0) continue;

                if ($huboSalidaFisica) {
                    // ── Caso normal: pedido pasó por "En Ruta" ──────────────────
                    // El stock ya salió físicamente → registrar ENTRADA de devolución

                    // Opción B — balance por producto:
                    // Bloquear si devoluciones >= salidas para este producto
                    // (ya se devolvió todo lo que salió)
                    $stmtBal = $db->prepare('
                        SELECT
                            SUM(CASE WHEN tipo_movimiento = :salida     THEN 1 ELSE 0 END) AS cnt_salidas,
                            SUM(CASE WHEN tipo_movimiento = :devolucion THEN 1 ELSE 0 END) AS cnt_dev
                        FROM stock
                        WHERE referencia_tipo = :tipo
                          AND referencia_id   = :id
                          AND id_producto     = :id_producto
                    ');
                    $stmtBal->execute([
                        ':tipo'        => 'pedido',
                        ':id'          => $idPedido,
                        ':id_producto' => $idProducto,
                        ':salida'      => 'salida',
                        ':devolucion'  => 'devolucion',
                    ]);
                    $balProd    = $stmtBal->fetch(PDO::FETCH_ASSOC);
                    $cntSalidas = (int)($balProd['cnt_salidas'] ?? 0);
                    $cntDev     = (int)($balProd['cnt_dev']     ?? 0);
                    if ($cntDev >= $cntSalidas) continue; // Ya devuelto todo lo que salió

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
                        ':cantidad'    => $cantDevuelta,
                        ':tipo'        => 'devolucion',
                        ':ref_tipo'    => 'pedido',
                        ':ref_id'      => $idPedido,
                        ':motivo'      => 'Devolución de pedido #' . $idPedido,
                        ':origen'      => 'Ruta',
                        ':destino'     => 'Bodega',
                    ]);
                } else {
                    // ── Caso especial: pedido devuelto desde "En Bodega" ─────────
                    // El stock NUNCA salió físicamente → solo liberar la reserva
                    // (no agregar stock que nunca salió para no inflar inventario)
                    self::liberarReservaPedido($idPedido, $db);
                }
            }
        }

    // =========================================================================
    // ESTADO 3 – ENTREGADO (con salida física pendiente)
    // =========================================================================

        /**
         * Aplica la salida física SOLO si el pedido nunca pasó por "En ruta" (estado 2).
         *
         * Esto cubre el flujo abreviado: En bodega (1) → Entregado (3)
         * sin pasar por En ruta (2). En ese caso:
         *   - La reserva sigue activa y debe liberarse.
         *   - La salida física nunca se registró y debe registrarse ahora.
         *
         * Si el pedido ya tuvo salida física (pasó por estado 2), no hace nada
         * para evitar doble descuento de stock.
         */
        private static function aplicarSalidaFisicaSiPendiente(int $idPedido, int $actorUserId, PDO $db): void
        {
            // ¿Ya hubo salida física para este pedido?
            $stmtCheck = $db->prepare('
                SELECT COUNT(*) FROM stock
                WHERE referencia_tipo = :tipo
                  AND referencia_id   = :id
                  AND tipo_movimiento = :movimiento
            ');
            $stmtCheck->execute([
                ':tipo'       => 'pedido',
                ':id'         => $idPedido,
                ':movimiento' => 'salida',
            ]);
            $yaSalio = (int)$stmtCheck->fetchColumn() > 0;

            if ($yaSalio) {
                // Salida ya registrada (pasó por "En ruta") — nada que hacer
                return;
            }

            // No hubo salida → aplicar salida física + liberar reserva
            // (reutilizamos aplicarSalidaFisica que ya hace ambas cosas)
            $productos = self::obtenerProductosPedido($idPedido, $db);

            foreach ($productos as $p) {
                $idProducto = (int)$p['id_producto'];
                $cantidad   = max(0, (int)$p['cantidad'] - (int)($p['cantidad_devuelta'] ?? 0));

                if ($cantidad <= 0) continue;

                // Registrar salida física con motivo específico
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
                    ':motivo'      => 'Salida automática — pedido entregado sin pasar por En ruta',
                    ':origen'      => 'Bodega',
                    ':destino'     => 'Principal',
                ]);

                // Liberar reserva si existía
                $reserva = self::obtenerReserva($idPedido, $idProducto, $db);
                if ($reserva && !(int)$reserva['liberada']) {
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
    // Helpers internos
    // =========================================================================

        /**
         * Libera todas las reservas activas asociadas a un pedido.
         */
        private static function liberarReservaPedido(int $idPedido, PDO $db): void
        {
            $productos = self::obtenerProductosPedido($idPedido, $db);
            foreach ($productos as $p) {
                $idProducto = (int)$p['id_producto'];
                $reserva = self::obtenerReserva($idPedido, $idProducto, $db);
                if ($reserva && !(int)$reserva['liberada']) {
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
