<?php
require_once __DIR__ . '/conexion.php';

/**
 * Modelo para gestión de webhooks de clientes.
 * 
 * Permite notificar cambios de estado de pedidos a endpoints externos
 * configurados por cliente (ej: LogisPro).
 * 
 * Flujo: Login (JWT) → POST UpdateOrder → Log resultado
 */
class WebhookModel
{
    /**
     * Obtener configuración de webhook para un cliente.
     *
     * @param int $idCliente ID del cliente (id_cliente en pedidos)
     * @return array|null Config del webhook o null si no tiene
     */
    public static function obtenerConfigPorCliente(int $idCliente): ?array
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('SELECT * FROM webhooks_clientes WHERE id_cliente = :id AND activo = 1 LIMIT 1');
            $stmt->execute([':id' => $idCliente]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (Exception $e) {
            error_log('[Webhook] Error obteniendo config: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Autenticarse en el API del cliente para obtener JWT token.
     *
     * @param array $config Registro de webhooks_clientes
     * @return string|null JWT token o null si falla
     */
    public static function login(array $config): ?string
    {
        $payload = json_encode([
            'userName' => $config['auth_user'],
            'password' => $config['auth_password']
        ]);

        $ch = curl_init($config['url_login']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("[Webhook] cURL login error: $curlError");
            return null;
        }

        $data = json_decode($response, true);

        // LogisPro devuelve: { "HasError": false, "Data": { "JwtToken": "..." } }
        if ($httpCode === 200 && isset($data['Data']['JwtToken'])) {
            return $data['Data']['JwtToken'];
        }

        error_log("[Webhook] Login fallido HTTP $httpCode: " . substr($response, 0, 500));
        return null;
    }

    /**
     * Enviar cambio de estado al API del cliente.
     *
     * @param string $jwt Token de autenticación
     * @param array  $config Configuración del webhook
     * @param string $numeroOrden Número de orden del pedido
     * @param string $estadoNombre Nombre del nuevo estado
     * @return array ['success' => bool, 'http_code' => int, 'response' => string]
     */
    public static function enviarCambioEstado(string $jwt, array $config, string $numeroOrden, string $estadoNombre): array
    {
        $payload = json_encode([
            'customersId' => (int)$config['customers_id'],
            'orderNumber' => $numeroOrden,
            'state'       => $estadoNombre,
            'auditUser'   => $config['auth_user']
        ]);

        $ch = curl_init($config['url_webhook']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $jwt
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return [
                'success'   => false,
                'http_code' => 0,
                'response'  => "cURL error: $curlError"
            ];
        }

        return [
            'success'   => ($httpCode >= 200 && $httpCode < 300),
            'http_code' => $httpCode,
            'response'  => $response
        ];
    }

    /**
     * Disparar webhook buscando los datos del pedido por ID.
     * Útil para integrar desde controladores que solo manejan ID de pedido.
     * 
     * @param int $idPedido
     * @param int $idEstadoNuevo
     */
    public static function dispararPorPedidoId(int $idPedido, int $idEstadoNuevo): void
    {
        try {
            $db = (new Conexion())->conectar();
            
            // 1. Obtener nombre del nuevo estado
            $st = $db->prepare('SELECT nombre_estado FROM estados_pedidos WHERE id = :id LIMIT 1');
            $st->execute([':id' => $idEstadoNuevo]);
            $nombreEstado = $st->fetchColumn() ?: "Estado $idEstadoNuevo";

            // 2. Obtener datos del pedido (numero_orden e id_cliente)
            $st = $db->prepare('SELECT numero_orden, id_cliente FROM pedidos WHERE id = :id LIMIT 1');
            $st->execute([':id' => $idPedido]);
            $pedido = $st->fetch(PDO::FETCH_ASSOC);

            if ($pedido && $pedido['id_cliente']) {
                self::dispararSiAplica(
                    $idPedido,
                    (string)$pedido['numero_orden'],
                    $nombreEstado,
                    (int)$pedido['id_cliente']
                );
            }
        } catch (Exception $e) {
            error_log("[Webhook] Error en dispararPorPedidoId ($idPedido): " . $e->getMessage());
        }
    }

    /**
     * Disparar webhook si el cliente tiene uno configurado.
     * 
     * Este es el método principal que se llama desde actualizarPedido().
     * NUNCA lanza excepciones — cualquier error se loguea silenciosamente.
     *
     * @param int    $idPedido       ID interno del pedido
     * @param string $numeroOrden    Número de orden visible
     * @param string $estadoNombre   Nombre del estado nuevo
     * @param int    $idCliente      ID del cliente dueño del pedido
     */
    public static function dispararSiAplica(int $idPedido, string $numeroOrden, string $estadoNombre, int $idCliente): void
    {
        try {
            // 1. Buscar config de webhook para este cliente
            $config = self::obtenerConfigPorCliente($idCliente);
            if (!$config) return; // No tiene webhook configurado — no hacer nada

            // 2. Login para obtener JWT
            $jwt = self::login($config);
            if (!$jwt) {
                self::registrarLog($config['id'], $idPedido, $numeroOrden, $estadoNombre, null, null, 'error', 'Login fallido - no se obtuvo JWT');
                return;
            }

            // 3. Enviar cambio de estado
            $resultado = self::enviarCambioEstado($jwt, $config, $numeroOrden, $estadoNombre);

            // 4. Registrar log
            $requestBody = json_encode([
                'customersId' => (int)$config['customers_id'],
                'orderNumber' => $numeroOrden,
                'state'       => $estadoNombre,
                'auditUser'   => $config['auth_user']
            ]);

            self::registrarLog(
                $config['id'],
                $idPedido,
                $numeroOrden,
                $estadoNombre,
                $requestBody,
                $resultado['http_code'],
                $resultado['success'] ? 'ok' : 'error',
                $resultado['success'] ? null : substr($resultado['response'], 0, 500),
                $resultado['response']
            );

            if ($resultado['success']) {
                error_log("[Webhook] OK pedido #$numeroOrden → {$config['nombre']} (HTTP {$resultado['http_code']})");
            } else {
                error_log("[Webhook] ERROR pedido #$numeroOrden → {$config['nombre']} (HTTP {$resultado['http_code']}): " . substr($resultado['response'], 0, 200));
            }

        } catch (Exception $e) {
            error_log('[Webhook] Excepción en dispararSiAplica: ' . $e->getMessage());
        }
    }

    /**
     * Registrar un intento de webhook en webhooks_log.
     */
    public static function registrarLog(
        int $idWebhookCliente,
        int $idPedido,
        string $numeroOrden,
        string $estadoEnviado,
        ?string $requestBody,
        ?int $responseCode,
        string $status,
        ?string $errorMessage = null,
        ?string $responseBody = null
    ): void {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("
                INSERT INTO webhooks_log 
                    (id_webhook_cliente, id_pedido, numero_orden, estado_enviado, request_body, response_code, response_body, status, intentos, error_message, enviado_at)
                VALUES 
                    (:id_wc, :id_ped, :num_ord, :estado, :req_body, :resp_code, :resp_body, :status, 1, :error_msg, NOW())
            ");
            $stmt->execute([
                ':id_wc'     => $idWebhookCliente,
                ':id_ped'    => $idPedido,
                ':num_ord'   => $numeroOrden,
                ':estado'    => $estadoEnviado,
                ':req_body'  => $requestBody,
                ':resp_code' => $responseCode,
                ':resp_body' => $responseBody ? substr($responseBody, 0, 5000) : null,
                ':status'    => $status,
                ':error_msg' => $errorMessage
            ]);
        } catch (Exception $e) {
            error_log('[Webhook] Error registrando log: ' . $e->getMessage());
        }
    }

    /**
     * Obtener todos los logs de webhook (para UI de administración).
     */
    public static function obtenerLogs(int $limite = 100): array
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("
                SELECT l.*, c.nombre AS cliente_nombre 
                FROM webhooks_log l
                JOIN webhooks_clientes c ON c.id = l.id_webhook_cliente
                ORDER BY l.created_at DESC
                LIMIT :limite
            ");
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Obtener logs pendientes/fallidos para reintento.
     */
    public static function obtenerPendientes(): array
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->query("
                SELECT l.*, c.* 
                FROM webhooks_log l
                JOIN webhooks_clientes c ON c.id = l.id_webhook_cliente
                WHERE l.status = 'error' AND l.intentos < 5 AND c.activo = 1
                ORDER BY l.created_at ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    // ====================================================================
    //  CRUD — Configuración de webhooks
    // ====================================================================

    /**
     * Obtener todas las configuraciones de webhook.
     */
    public static function listarConfigs(): array
    {
        try {
            $db = (new Conexion())->conectar();
            return $db->query("
                SELECT wc.*, u.nombre AS cliente_nombre,
                       (SELECT COUNT(*) FROM webhooks_log wl WHERE wl.id_webhook_cliente = wc.id) AS total_logs,
                       (SELECT COUNT(*) FROM webhooks_log wl WHERE wl.id_webhook_cliente = wc.id AND wl.status = 'ok') AS total_ok,
                       (SELECT COUNT(*) FROM webhooks_log wl WHERE wl.id_webhook_cliente = wc.id AND wl.status = 'error') AS total_error
                FROM webhooks_clientes wc
                LEFT JOIN usuarios u ON u.id = wc.id_cliente
                ORDER BY wc.nombre
            ")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Obtener una configuración por su ID.
     */
    public static function obtenerPorId(int $id): ?array
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare('SELECT * FROM webhooks_clientes WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $id]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            return $r ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Crear una nueva configuración de webhook.
     */
    public static function crear(array $data): int
    {
        $db = (new Conexion())->conectar();
        $stmt = $db->prepare("
            INSERT INTO webhooks_clientes (id_cliente, nombre, url_login, url_webhook, auth_user, auth_password, customers_id, activo)
            VALUES (:id_cliente, :nombre, :url_login, :url_webhook, :auth_user, :auth_password, :customers_id, :activo)
        ");
        $stmt->execute([
            ':id_cliente'    => (int)$data['id_cliente'],
            ':nombre'        => trim($data['nombre']),
            ':url_login'     => trim($data['url_login']),
            ':url_webhook'   => trim($data['url_webhook']),
            ':auth_user'     => trim($data['auth_user']),
            ':auth_password' => $data['auth_password'],
            ':customers_id'  => !empty($data['customers_id']) ? (int)$data['customers_id'] : null,
            ':activo'        => isset($data['activo']) ? 1 : 0,
        ]);
        return (int)$db->lastInsertId();
    }

    /**
     * Actualizar una configuración existente.
     */
    public static function actualizar(int $id, array $data): bool
    {
        $db = (new Conexion())->conectar();
        $stmt = $db->prepare("
            UPDATE webhooks_clientes SET
                id_cliente = :id_cliente,
                nombre = :nombre,
                url_login = :url_login,
                url_webhook = :url_webhook,
                auth_user = :auth_user,
                auth_password = :auth_password,
                customers_id = :customers_id,
                activo = :activo
            WHERE id = :id
        ");
        return $stmt->execute([
            ':id'            => $id,
            ':id_cliente'    => (int)$data['id_cliente'],
            ':nombre'        => trim($data['nombre']),
            ':url_login'     => trim($data['url_login']),
            ':url_webhook'   => trim($data['url_webhook']),
            ':auth_user'     => trim($data['auth_user']),
            ':auth_password' => $data['auth_password'],
            ':customers_id'  => !empty($data['customers_id']) ? (int)$data['customers_id'] : null,
            ':activo'        => isset($data['activo']) ? 1 : 0,
        ]);
    }

    /**
     * Eliminar una configuración y sus logs.
     */
    public static function eliminar(int $id): bool
    {
        $db = (new Conexion())->conectar();
        $db->beginTransaction();
        try {
            $db->prepare('DELETE FROM webhooks_log WHERE id_webhook_cliente = :id')->execute([':id' => $id]);
            $db->prepare('DELETE FROM webhooks_clientes WHERE id = :id')->execute([':id' => $id]);
            $db->commit();
            return true;
        } catch (Exception $e) {
            $db->rollBack();
            return false;
        }
    }

    /**
     * Alternar estado activo/inactivo.
     */
    public static function toggleActivo(int $id): bool
    {
        $db = (new Conexion())->conectar();
        $stmt = $db->prepare('UPDATE webhooks_clientes SET activo = NOT activo WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }
}
