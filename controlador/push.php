<?php
/**
 * PushController
 *
 * Endpoints:
 *   POST /push/subscribe    → guarda suscripción push
 *   POST /push/unsubscribe  → desactiva suscripción push
 *   GET  /push/vapid-key    → devuelve la clave pública VAPID para el frontend
 */

require_once __DIR__ . '/../services/PushNotificationService.php';
require_once __DIR__ . '/../utils/session.php';

class PushController
{
    /**
     * POST /push/subscribe
     * Body JSON: { endpoint, keys: { p256dh, auth }, contexto? }
     *
     * Response JSON:
     *   { success: true }
     *   { success: false, error: "..." }
     */
    public function subscribe(): void
    {
        start_secure_session();
        header('Content-Type: application/json');

        $userId = $_SESSION['idUsuario'] ?? $_SESSION['user_id'] ?? 0;
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'No autenticado']);
            exit;
        }

        $body = file_get_contents('php://input');
        $data = json_decode($body, true);

        if (!$data || empty($data['endpoint']) || empty($data['keys']['p256dh']) || empty($data['keys']['auth'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Datos de suscripción inválidos o incompletos']);
            exit;
        }

        $contexto = $data['contexto'] ?? 'logistica';
        $ok       = PushNotificationService::saveSubscription((int)$userId, $data, $contexto);

        echo json_encode(['success' => $ok]);
        exit;
    }

    /**
     * POST /push/unsubscribe
     * Body JSON: { endpoint }
     *
     * Response JSON:
     *   { success: true }
     */
    public function unsubscribe(): void
    {
        start_secure_session();
        header('Content-Type: application/json');

        $userId = $_SESSION['idUsuario'] ?? $_SESSION['user_id'] ?? 0;
        if (!$userId) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'No autenticado']);
            exit;
        }

        $body     = file_get_contents('php://input');
        $data     = json_decode($body, true);
        $endpoint = $data['endpoint'] ?? '';

        if (empty($endpoint)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Endpoint requerido']);
            exit;
        }

        $ok = PushNotificationService::disableSubscription((int)$userId, $endpoint);
        echo json_encode(['success' => $ok]);
        exit;
    }

    /**
     * GET /push/vapid-key
     * Devuelve la clave pública VAPID en formato base64url para el frontend.
     *
     * Response JSON:
     *   { publicKey: "BA..." }
     */
    public function vapidKey(): void
    {
        header('Content-Type: application/json');

        if (!defined('VAPID_PUBLIC_KEY')) {
            http_response_code(503);
            echo json_encode(['error' => 'VAPID no configurado']);
            exit;
        }

        echo json_encode(['publicKey' => VAPID_PUBLIC_KEY]);
        exit;
    }
}
