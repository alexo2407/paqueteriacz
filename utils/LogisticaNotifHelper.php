<?php
/**
 * LogisticaNotifHelper
 *
 * Helper estático para disparar notificaciones logísticas desde cualquier
 * punto de la aplicación (API, controladores, workers).
 *
 * Uso:
 *   LogisticaNotifHelper::notificarPedido($idPedido, 'creado');
 *   LogisticaNotifHelper::notificarPedido($idPedido, 'actualizado');
 *   LogisticaNotifHelper::notificarPedido($idPedido, 'estado_cambiado', 'En Ruta');
 */
class LogisticaNotifHelper
{
    // Acciones soportadas
    const ACCION_CREADO         = 'creado';
    const ACCION_ACTUALIZADO    = 'actualizado';
    const ACCION_ESTADO_CAMBIADO = 'estado_cambiado';

    /**
     * Dispara notificaciones logísticas a todos los destinatarios del pedido.
     *
     * @param int    $idPedido  ID del pedido en la tabla `pedidos`
     * @param string $accion    Una de las constantes ACCION_*
     * @param string $estadoNuevo  Nombre del nuevo estado (solo para estado_cambiado)
     * @return void  — falla silenciosamente con error_log
     */
    public static function notificarPedido(int $idPedido, string $accion, string $estadoNuevo = ''): void
    {
        try {
            require_once __DIR__ . '/../modelo/logistica_notification.php';
            require_once __DIR__ . '/../modelo/conexion.php';

            $db = (new Conexion())->conectar();

            // ── 1. Obtener datos del pedido ────────────────────────────────
            $stmt = $db->prepare("
                SELECT p.id, p.numero_orden, p.destinatario, p.id_cliente, p.id_proveedor,
                       ep.nombre_estado
                FROM pedidos p
                LEFT JOIN estados_pedidos ep ON ep.id = p.id_estado
                WHERE p.id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $idPedido]);
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pedido) {
                error_log("[LogisticaNotifHelper] Pedido #$idPedido no encontrado.");
                return;
            }

            $numeroOrden       = $pedido['numero_orden'];
            $destinatarioPedido = $pedido['destinatario'];
            $estadoActual      = $estadoNuevo ?: ($pedido['nombre_estado'] ?? 'N\/D');
            $idCliente         = (int)($pedido['id_cliente']   ?? 0);
            $idProveedor       = (int)($pedido['id_proveedor'] ?? 0);

            // ── 2. Construir título y mensaje según acción ─────────────────
            switch ($accion) {
                case self::ACCION_CREADO:
                    $tipo    = 'pedido_creado';
                    $titulo  = "Nuevo pedido #$numeroOrden creado";
                    $mensaje = "Se creó el pedido #$numeroOrden para «$destinatarioPedido» vía API.";
                    break;
                case self::ACCION_ACTUALIZADO:
                    $tipo    = 'pedido_actualizado';
                    $titulo  = "Pedido #$numeroOrden actualizado";
                    $mensaje = "Se actualizaron los datos del pedido #$numeroOrden para «$destinatarioPedido».";
                    break;
                case self::ACCION_ESTADO_CAMBIADO:
                    $tipo    = 'pedido_estado';
                    $titulo  = "Estado de pedido #$numeroOrden: $estadoActual";
                    $mensaje = "El pedido #$numeroOrden cambió de estado a «$estadoActual».";
                    break;
                default:
                    $tipo    = 'pedido_evento';
                    $titulo  = "Evento en pedido #$numeroOrden";
                    $mensaje = "Ocurrió un evento ($accion) en el pedido #$numeroOrden.";
            }

            $payload = [
                'accion'       => $accion,
                'numero_orden' => $numeroOrden,
                'destinatario' => $destinatarioPedido,
                'estado'       => $estadoActual,
            ];

            // ── 3. Obtener IDs de todos los admin ──────────────────────────
            $adminStmt = $db->prepare("
                SELECT DISTINCT ur.id_usuario
                FROM usuarios_roles ur
                INNER JOIN roles r ON r.id = ur.id_rol
                WHERE r.nombre_rol = 'Admin'
            ");
            $adminStmt->execute();
            $adminIds = $adminStmt->fetchAll(PDO::FETCH_COLUMN);

            // ── 4. Construir lista de destinatarios únicos ─────────────────
            $destinatarios = [];

            if ($idCliente > 0) {
                $destinatarios[$idCliente] = 'cliente';
            }
            if ($idProveedor > 0) {
                $destinatarios[$idProveedor] = 'proveedor';
            }
            foreach ($adminIds as $adminId) {
                $adminId = (int)$adminId;
                if (!isset($destinatarios[$adminId])) {
                    $destinatarios[$adminId] = 'admin';
                }
            }

            // ── 5. Insertar notificación para cada destinatario ────────────
            foreach ($destinatarios as $userId => $contexto) {
                LogisticaNotificationModel::agregar(
                    $userId,
                    $tipo,
                    $titulo,
                    $mensaje,
                    $idPedido,
                    array_merge($payload, ['contexto' => $contexto])
                );
            }

        } catch (Throwable $e) {
            error_log("[LogisticaNotifHelper] Error: " . $e->getMessage());
        }
    }

    /**
     * Envía UNA SOLA notificación resumen para un lote de pedidos creados.
     *
     * @param array $pedidosCreados  Array de ['id' => int, 'numero_orden' => int]
     * @param int   $idCliente       ID del cliente (común en el lote, puede ser 0)
     * @param int   $idProveedor     ID del proveedor (común en el lote, puede ser 0)
     */
    public static function notificarLote(array $pedidosCreados, int $idCliente = 0, int $idProveedor = 0): void
    {
        try {
            if (empty($pedidosCreados)) return;

            require_once __DIR__ . '/../modelo/logistica_notification.php';
            require_once __DIR__ . '/../modelo/conexion.php';

            $total    = count($pedidosCreados);
            $numeros  = array_column($pedidosCreados, 'numero_orden');
            $primeros = array_slice($numeros, 0, 5);
            $resto    = $total - count($primeros);

            $listaStr = '#' . implode(', #', $primeros);
            if ($resto > 0) {
                $listaStr .= " (+$resto más)";
            }

            $tipo    = 'lote_creado';
            $titulo  = "$total pedido" . ($total > 1 ? 's' : '') . " creado" . ($total > 1 ? 's' : '') . " vía API";
            $mensaje = "Se crearon $total pedidos en lote: $listaStr.";
            $payload = [
                'accion'         => 'lote_creado',
                'total'          => $total,
                'numeros_orden'  => $numeros,
            ];

            // ID del primer pedido como referencia en la notificación
            $idPedidoRef = $pedidosCreados[0]['id'] ?? null;

            $db = (new Conexion())->conectar();

            // Obtener admins
            $adminStmt = $db->prepare("
                SELECT DISTINCT ur.id_usuario
                FROM usuarios_roles ur
                INNER JOIN roles r ON r.id = ur.id_rol
                WHERE r.nombre_rol = 'Admin'
            ");
            $adminStmt->execute();
            $adminIds = $adminStmt->fetchAll(PDO::FETCH_COLUMN);

            // Construir destinatarios únicos
            $destinatarios = [];
            if ($idCliente > 0)   $destinatarios[$idCliente]  = 'cliente';
            if ($idProveedor > 0) $destinatarios[$idProveedor] = 'proveedor';
            foreach ($adminIds as $adminId) {
                $adminId = (int)$adminId;
                if (!isset($destinatarios[$adminId])) {
                    $destinatarios[$adminId] = 'admin';
                }
            }

            foreach ($destinatarios as $userId => $contexto) {
                LogisticaNotificationModel::agregar(
                    $userId,
                    $tipo,
                    $titulo,
                    $mensaje,
                    $idPedidoRef,
                    array_merge($payload, ['contexto' => $contexto])
                );
            }

        } catch (Throwable $e) {
            error_log("[LogisticaNotifHelper] Error lote: " . $e->getMessage());
        }
    }
}
