<?php
/**
 * PushNotificationService
 *
 * Servicio para gestionar suscripciones Web Push y enviar notificaciones push
 * del navegador usando la librería minishlink/web-push.
 *
 * Requiere:
 *   - composer require "minishlink/web-push:^9.0"
 *   - Constantes VAPID_PUBLIC_KEY y VAPID_PRIVATE_KEY en config/config.php
 *
 * Generar VAPID keys (ejecutar UNA sola vez en terminal):
 *   php -r "
 *     require 'vendor/autoload.php';
 *     \$keys = \Minishlink\WebPush\VAPID::createVapidKeys();
 *     echo 'PUBLIC:  ' . \$keys['publicKey']  . PHP_EOL;
 *     echo 'PRIVATE: ' . \$keys['privateKey'] . PHP_EOL;
 *   "
 * Luego pegar los valores en config/config.php como:
 *   define('VAPID_PUBLIC_KEY',  'BA...');
 *   define('VAPID_PRIVATE_KEY', 'xxx...');
 *   define('VAPID_SUBJECT',     'mailto:tu@dominio.com');
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../modelo/conexion.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class PushNotificationService
{
    // ─────────────────────────────────────────────────────────────────────────
    // Gestión de suscripciones
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Guarda o actualiza una suscripción push para un usuario.
     *
     * @param int    $userId           ID del usuario autenticado
     * @param array  $subscriptionData { endpoint, keys: { p256dh, auth } }
     * @param string $contexto         Contexto opcional (logistica, admin, etc.)
     * @return bool
     */
    public static function saveSubscription(int $userId, array $subscriptionData, string $contexto = null): bool
    {
        try {
            $db       = (new Conexion())->conectar();
            $endpoint = $subscriptionData['endpoint'] ?? '';
            $p256dh   = $subscriptionData['keys']['p256dh'] ?? '';
            $auth     = $subscriptionData['keys']['auth']   ?? '';
            $ua       = $_SERVER['HTTP_USER_AGENT'] ?? null;

            if (empty($endpoint) || empty($p256dh) || empty($auth)) {
                error_log('[PushService] saveSubscription: datos incompletos para user ' . $userId);
                return false;
            }

            // Upsert: si el endpoint ya existe para este usuario, actualizarlo
            $sql = "INSERT INTO push_subscriptions
                        (id_usuario, endpoint, p256dh, auth, user_agent, contexto, activo)
                    VALUES
                        (:uid, :endpoint, :p256dh, :auth, :ua, :ctx, 1)
                    ON DUPLICATE KEY UPDATE
                        p256dh     = VALUES(p256dh),
                        auth       = VALUES(auth),
                        user_agent = VALUES(user_agent),
                        contexto   = VALUES(contexto),
                        activo     = 1,
                        updated_at = CURRENT_TIMESTAMP";

            // Agregar unique key en endpoint+id_usuario si no existe
            // (el ON DUPLICATE KEY funciona con el índice único)
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':uid'      => $userId,
                ':endpoint' => $endpoint,
                ':p256dh'   => $p256dh,
                ':auth'     => $auth,
                ':ua'       => $ua,
                ':ctx'      => $contexto,
            ]);

            return true;

        } catch (Exception $e) {
            error_log('[PushService] saveSubscription error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Desactiva una suscripción push (marcar inactiva, no eliminar).
     *
     * @param int    $userId
     * @param string $endpoint
     * @return bool
     */
    public static function disableSubscription(int $userId, string $endpoint): bool
    {
        try {
            $db   = (new Conexion())->conectar();
            $stmt = $db->prepare(
                "UPDATE push_subscriptions SET activo = 0, updated_at = CURRENT_TIMESTAMP
                 WHERE id_usuario = :uid AND endpoint = :endpoint"
            );
            $stmt->execute([':uid' => $userId, ':endpoint' => $endpoint]);
            return true;
        } catch (Exception $e) {
            error_log('[PushService] disableSubscription error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Elimina suscripciones expiradas/inválidas (llamado automáticamente tras 410/404 del browser).
     *
     * @param string $endpoint
     */
    public static function deleteByEndpoint(string $endpoint): void
    {
        try {
            $db   = (new Conexion())->conectar();
            $stmt = $db->prepare("DELETE FROM push_subscriptions WHERE endpoint = :endpoint");
            $stmt->execute([':endpoint' => $endpoint]);
        } catch (Exception $e) {
            error_log('[PushService] deleteByEndpoint error: ' . $e->getMessage());
        }
    }

    /**
     * Obtiene las suscripciones activas de un usuario.
     *
     * @param int $userId
     * @return array
     */
    public static function getActiveSubscriptions(int $userId): array
    {
        try {
            $db   = (new Conexion())->conectar();
            $stmt = $db->prepare(
                "SELECT endpoint, p256dh, auth FROM push_subscriptions
                 WHERE id_usuario = :uid AND activo = 1"
            );
            $stmt->execute([':uid' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('[PushService] getActiveSubscriptions error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Verifica si un usuario tiene al menos una suscripción push activa.
     *
     * @param int $userId
     * @return bool
     */
    public static function userHasActiveSub(int $userId): bool
    {
        try {
            $db   = (new Conexion())->conectar();
            $stmt = $db->prepare(
                "SELECT COUNT(*) FROM push_subscriptions WHERE id_usuario = :uid AND activo = 1"
            );
            $stmt->execute([':uid' => $userId]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Envío de notificaciones push
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Envía una notificación push a todas las suscripciones activas de un usuario.
     *
     * @param int    $userId
     * @param string $title   Título de la notificación
     * @param string $body    Cuerpo del mensaje
     * @param string $url     URL a abrir al hacer click (relativa a la raíz del proyecto)
     * @param array  $payload Datos extra opcionales
     * @return array          ['sent' => int, 'failed' => int]
     */
    public static function sendToUser(int $userId, string $title, string $body, string $url = '/', array $payload = []): array
    {
        $subs = self::getActiveSubscriptions($userId);
        if (empty($subs)) {
            return ['sent' => 0, 'failed' => 0];
        }

        return self::dispatch($subs, $title, $body, $url, $payload);
    }

    /**
     * Envía un push directamente desde un registro de notificación interna.
     * Útil para llamar justo después de crear la notificación en BD.
     *
     * @param int $notificationId  ID en tabla notificaciones_logistica
     * @return array               ['sent' => int, 'failed' => int]
     */
    public static function sendFromNotification(int $notificationId): array
    {
        try {
            $db   = (new Conexion())->conectar();
            $stmt = $db->prepare(
                "SELECT n.user_id, n.titulo, n.mensaje, n.pedido_id, n.tipo
                 FROM notificaciones_logistica n
                 WHERE n.id = :id LIMIT 1"
            );
            $stmt->execute([':id' => $notificationId]);
            $notif = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$notif) {
                return ['sent' => 0, 'failed' => 0];
            }

            $userId = (int)$notif['user_id'];
            $title  = $notif['titulo']  ?? 'PaqueteriaCZ';
            $body   = $notif['mensaje'] ?? '';
            $url    = defined('RUTA_URL') ? rtrim(RUTA_URL, '/') . '/logistica/dashboard' : '/';

            if (!empty($notif['pedido_id'])) {
                $url = (defined('RUTA_URL') ? rtrim(RUTA_URL, '/') : '') . '/logistica/ver/' . (int)$notif['pedido_id'];
            }

            return self::sendToUser($userId, $title, $body, $url, [
                'tipo'      => $notif['tipo']     ?? '',
                'pedido_id' => $notif['pedido_id'] ?? null,
            ]);

        } catch (Exception $e) {
            error_log('[PushService] sendFromNotification error: ' . $e->getMessage());
            return ['sent' => 0, 'failed' => 0];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Core: despachar push a un array de suscripciones
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param array  $subs    [['endpoint', 'p256dh', 'auth'], ...]
     * @param string $title
     * @param string $body
     * @param string $url
     * @param array  $payload
     * @return array ['sent', 'failed']
     */
    private static function dispatch(array $subs, string $title, string $body, string $url, array $payload = []): array
    {
        if (!defined('VAPID_PUBLIC_KEY') || !defined('VAPID_PRIVATE_KEY')) {
            error_log('[PushService] VAPID keys no configuradas. Agrega VAPID_PUBLIC_KEY y VAPID_PRIVATE_KEY en config/config.php');
            return ['sent' => 0, 'failed' => count($subs)];
        }

        $auth = [
            'VAPID' => [
                'subject'    => defined('VAPID_SUBJECT') ? VAPID_SUBJECT : 'mailto:admin@paqueteriacz.com',
                'publicKey'  => VAPID_PUBLIC_KEY,
                'privateKey' => VAPID_PRIVATE_KEY,
            ],
        ];

        $webPush = new WebPush($auth);
        $webPush->setReuseVAPIDHeaders(true);

        $pushPayload = json_encode([
            'title'   => $title,
            'body'    => $body,
            'url'     => $url,
            'icon'    => defined('RUTA_URL') ? rtrim(RUTA_URL, '/') . '/android-chrome-192x192.png' : '/android-chrome-192x192.png',
            'badge'   => defined('RUTA_URL') ? rtrim(RUTA_URL, '/') . '/favicon-32x32.png' : '/favicon-32x32.png',
            'tag'     => 'paqueteriacz-' . ($payload['tipo'] ?? 'notif'),
            'data'    => array_merge(['url' => $url], $payload),
        ]);

        foreach ($subs as $sub) {
            $subscription = Subscription::create([
                'endpoint'        => $sub['endpoint'],
                'contentEncoding' => 'aesgcm',
                'keys'            => [
                    'p256dh' => $sub['p256dh'],
                    'auth'   => $sub['auth'],
                ],
            ]);
            $webPush->queueNotification($subscription, $pushPayload);
        }

        $sent   = 0;
        $failed = 0;

        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getRequest()->getUri()->__toString();

            if ($report->isSuccess()) {
                $sent++;
            } else {
                $failed++;
                // Si el endpoint ya no existe (410 Gone / 404 Not Found) → limpiar BD
                $statusCode = $report->getResponse() ? $report->getResponse()->getStatusCode() : 0;
                if (in_array($statusCode, [404, 410])) {
                    self::deleteByEndpoint($endpoint);
                    error_log('[PushService] Endpoint expirado eliminado: ' . substr($endpoint, 0, 60) . '...');
                } else {
                    error_log('[PushService] Push fallido (' . $statusCode . '): ' . $report->getReason());
                }
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }
}
