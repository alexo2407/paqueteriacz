<?php
/**
 * LogisticaNotificationModel
 * 
 * Modelo para gestionar notificaciones internas del módulo de Logística.
 * Tabla: notificaciones_logistica
 */

require_once __DIR__ . '/conexion.php';

class LogisticaNotificationModel {

    /**
     * Tipos válidos de notificación logística y su configuración visual.
     */
    public static function getTipoConfig(string $tipo): array {
        $config = [
            'pedido_creado'   => ['icon' => 'bi-box-seam',            'color' => 'bg-soft-success',   'border' => '#198754', 'label' => 'Pedido Creado'],
            'estado_cambiado' => ['icon' => 'bi-arrow-repeat',        'color' => 'bg-soft-info',      'border' => '#0dcaf0', 'label' => 'Cambio de Estado'],
            'asignado'        => ['icon' => 'bi-person-check-fill',   'color' => 'bg-soft-primary',   'border' => '#0d6efd', 'label' => 'Asignado'],
            'devuelto'        => ['icon' => 'bi-box-arrow-left',      'color' => 'bg-soft-danger',    'border' => '#dc3545', 'label' => 'Devuelto'],
            'reprogramado'    => ['icon' => 'bi-calendar-event-fill', 'color' => 'bg-soft-warning',   'border' => '#ffc107', 'label' => 'Reprogramado'],
            'comentario'      => ['icon' => 'bi-chat-dots-fill',      'color' => 'bg-soft-secondary', 'border' => '#6c757d', 'label' => 'Comentario'],
            'incidencia'      => ['icon' => 'bi-exclamation-triangle-fill', 'color' => 'bg-soft-orange', 'border' => '#fd7e14', 'label' => 'Incidencia'],
        ];
        return $config[$tipo] ?? ['icon' => 'bi-bell-fill', 'color' => 'bg-soft-primary', 'border' => '#0d6efd', 'label' => ucfirst($tipo)];
    }

    /**
     * Crea una nueva notificación logística.
     *
     * @param int    $userId   Usuario destinatario
     * @param string $tipo     Tipo de evento
     * @param string $titulo   Título corto
     * @param string $mensaje  Descripción larga (opcional)
     * @param int    $pedidoId ID del pedido relacionado (opcional)
     * @param array  $payload  Datos extra en JSON
     * @return bool
     */
    public static function agregar(int $userId, string $tipo, string $titulo, string $mensaje = '', ?int $pedidoId = null, array $payload = []): bool {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("
                INSERT INTO notificaciones_logistica
                    (user_id, tipo, titulo, mensaje, pedido_id, payload, is_read, created_at)
                VALUES
                    (:user_id, :tipo, :titulo, :mensaje, :pedido_id, :payload, 0, NOW())
            ");
            $ok = $stmt->execute([
                ':user_id'   => $userId,
                ':tipo'      => $tipo,
                ':titulo'    => $titulo,
                ':mensaje'   => $mensaje,
                ':pedido_id' => $pedidoId,
                ':payload'   => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            // ── Disparo automático de Web Push ───────────────────────────────
            // Solo si el usuario tiene suscripciones activas.
            // Falla silenciosamente para no bloquear el flujo principal.
            if ($ok) {
                try {
                    $pushFile = __DIR__ . '/../services/PushNotificationService.php';
                    if (file_exists($pushFile)) {
                        require_once $pushFile;
                        if (PushNotificationService::userHasActiveSub($userId)) {
                            $url = defined('RUTA_URL') ? rtrim(RUTA_URL, '/') . '/logistica/dashboard' : '/';
                            if ($pedidoId) {
                                $url = (defined('RUTA_URL') ? rtrim(RUTA_URL, '/') : '') . '/logistica/ver/' . $pedidoId;
                            }
                            PushNotificationService::sendToUser(
                                $userId, $titulo, $mensaje ?: '', $url,
                                ['tipo' => $tipo, 'pedido_id' => $pedidoId]
                            );
                        }
                    }
                } catch (Exception $pushEx) {
                    error_log('[LogisticaNotification] Push dispatch error: ' . $pushEx->getMessage());
                }
            }

            return $ok;

        } catch (Exception $e) {
            error_log("[LogisticaNotification] Error al crear: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene notificaciones de un usuario con filtros opcionales.
     *
     * @param int    $userId
     * @param int    $limit
     * @param int    $offset
     * @param bool   $onlyUnread
     * @param string $search
     * @return array
     */
    public static function obtenerPorUsuario(int $userId, int $limit = 200, int $offset = 0, bool $onlyUnread = false, string $search = ''): array {
        try {
            $db = (new Conexion())->conectar();
            $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

            $sql = "
                SELECT n.*,
                       p.numero_orden,
                       p.destinatario,
                       p.id_estado
                FROM notificaciones_logistica n
                LEFT JOIN pedidos p ON p.id = n.pedido_id
                WHERE n.user_id = :user_id
            ";
            $params = [':user_id' => $userId];

            if ($onlyUnread) {
                $sql .= " AND n.is_read = 0";
            }

            if (!empty($search)) {
                $sql .= " AND (
                    n.titulo COLLATE utf8mb4_unicode_ci LIKE :search
                    OR n.mensaje COLLATE utf8mb4_unicode_ci LIKE :search
                    OR p.numero_orden LIKE :search
                    OR p.destinatario COLLATE utf8mb4_unicode_ci LIKE :search
                )";
                $params[':search'] = "%" . $search . "%";
            }

            $sql .= " ORDER BY n.created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("[LogisticaNotification] Error al obtener: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Cuenta el total de notificaciones de un usuario (para paginación).
     */
    public static function contarTotalPorUsuario(int $userId, bool $onlyUnread = false, string $search = ''): int {
        try {
            $db = (new Conexion())->conectar();
            $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

            $sql = "
                SELECT COUNT(*)
                FROM notificaciones_logistica n
                LEFT JOIN pedidos p ON p.id = n.pedido_id
                WHERE n.user_id = :user_id
            ";
            $params = [':user_id' => $userId];

            if ($onlyUnread) {
                $sql .= " AND n.is_read = 0";
            }

            if (!empty($search)) {
                $sql .= " AND (
                    n.titulo COLLATE utf8mb4_unicode_ci LIKE :search
                    OR n.mensaje COLLATE utf8mb4_unicode_ci LIKE :search
                    OR p.numero_orden LIKE :search
                    OR p.destinatario COLLATE utf8mb4_unicode_ci LIKE :search
                )";
                $params[':search'] = "%" . $search . "%";
            }

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();

        } catch (Exception $e) {
            error_log("[LogisticaNotification] Error al contar: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Cuenta notificaciones no leídas de un usuario.
     */
    public static function contarNoLeidas(int $userId): int {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM notificaciones_logistica
                WHERE user_id = :user_id AND is_read = 0
            ");
            $stmt->execute([':user_id' => $userId]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("[LogisticaNotification] Error al contar no leídas: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtiene notificaciones pendientes (no leídas) para "Por Atender".
     */
    public static function obtenerPendientes(int $userId): array {
        return self::obtenerPorUsuario($userId, 100, 0, true, '');
    }

    /**
     * Marca una notificación como leída (verifica propiedad del usuario).
     */
    public static function marcarLeida(int $id, int $userId): bool {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("
                UPDATE notificaciones_logistica
                SET is_read = 1
                WHERE id = :id AND user_id = :user_id
            ");
            $stmt->execute([':id' => $id, ':user_id' => $userId]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("[LogisticaNotification] Error al marcar leída: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Marca todas las notificaciones de un usuario como leídas.
     */
    public static function marcarTodasLeidas(int $userId): bool {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("
                UPDATE notificaciones_logistica
                SET is_read = 1
                WHERE user_id = :user_id AND is_read = 0
            ");
            $stmt->execute([':user_id' => $userId]);
            return true;
        } catch (Exception $e) {
            error_log("[LogisticaNotification] Error al marcar todas leídas: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Limpia notificaciones antiguas ya leídas.
     *
     * @param int $days Días de antigüedad
     * @return int Registros eliminados
     */
    public static function limpiarAntiguas(int $days = 60): int {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("
                DELETE FROM notificaciones_logistica
                WHERE is_read = 1
                AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
            ");
            $stmt->execute([':days' => $days]);
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("[LogisticaNotification] Error al limpiar antiguas: " . $e->getMessage());
            return 0;
        }
    }
}
