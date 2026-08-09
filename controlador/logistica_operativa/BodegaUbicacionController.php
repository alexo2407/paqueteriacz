<?php

declare(strict_types=1);

/**
 * BodegaUbicacionController
 *
 * Controlador delgado para los endpoints de Logística Operativa — Recepción y Ubicación Física.
 *
 * Responsabilidades:
 *   1. Orquestar autenticación y autorización.
 *   2. Validar método HTTP y Content-Type.
 *   3. Parsear y validar el JSON de entrada.
 *   4. Delegar a BodegaUbicacionService la lógica de dominio.
 *   5. Emitir respuesta JSON uniforme.
 *
 * Restricciones de seguridad:
 *   - Nunca acepta id_operador desde el cliente.
 *   - Nunca expone file, line, trace, SQL, DSN ni contraseñas en errores.
 *   - Registra errores internos solo con error_log().
 *   - Bloquea si LOGISTICA_OPERATIVA_ENABLED=false.
 *   - Bloquea si LOGISTICA_OPERATIVA_SHADOW_MODE=false.
 *   - Mantiene shadowMode=true (no modifica pedidos.id_estado, stock, inventario).
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../api/utils/autenticacion.php';
require_once __DIR__ . '/../../services/LogisticaOperativaFlags.php';
require_once __DIR__ . '/../../services/logistica_operativa/LogisticaOperativaException.php';
require_once __DIR__ . '/../../services/logistica_operativa/BodegaUbicacionService.php';
require_once __DIR__ . '/../../utils/logistica_permissions.php';

class BodegaUbicacionController
{
    // ── Respuesta uniforme ────────────────────────────────────────────────

    /**
     * Emite una respuesta JSON de éxito y detiene la ejecución.
     *
     * @param mixed $data  Payload de la respuesta.
     * @param int   $code  HTTP status code (200 ó 201).
     */
    public function ok(mixed $data, int $code = 200): never
    {
        http_response_code($code);
        echo json_encode(
            ['success' => true, 'data' => $data],
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
        exit;
    }

    /**
     * Emite una respuesta JSON de error y detiene la ejecución.
     * Nunca incluye file, line, trace, SQL ni información sensible.
     *
     * @param string $code    Código de error legible por máquina.
     * @param string $message Mensaje seguro para el cliente.
     * @param int    $http    HTTP status code.
     */
    public function error(string $code, string $message, int $http = 400): never
    {
        http_response_code($http);
        echo json_encode(
            ['success' => false, 'code' => $code, 'message' => $message],
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
        exit;
    }

    // ── Seguridad de transporte ───────────────────────────────────────────

    /**
     * Aplica headers CORS/JSON comunes y responde al preflight OPTIONS.
     */
    public function aplicarHeaders(string $allowedMethods = 'POST, OPTIONS'): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: ' . $allowedMethods);
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }

    /**
     * Rechaza el request si el método HTTP no coincide.
     */
    public function requerirMetodo(string $expected): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== strtoupper($expected)) {
            $this->error('METHOD_NOT_ALLOWED', 'Método HTTP no permitido.', 405);
        }
    }

    /**
     * Rechaza el request si Content-Type no es application/json.
     */
    public function requerirJsonContentType(): void
    {
        $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if (stripos($ct, 'application/json') === false) {
            $this->error('INVALID_CONTENT_TYPE', 'Content-Type debe ser application/json.', 400);
        }
    }

    // ── Autenticación y autorización ──────────────────────────────────────

    /**
     * Valida el token JWT y devuelve los datos del usuario autenticado.
     * Comprueba que el usuario esté activo y que el token sea válido.
     *
     * @return array{id:int, nombre:string, rol:int}
     */
    public function autenticar(): array
    {
        $token = AuthMiddleware::obtenerTokenDeHeaders();

        if ($token === null || $token === '') {
            // Fallback a sesión PHP activa para llamadas AJAX internas de la interfaz web
            require_once __DIR__ . '/../../utils/session.php';
            start_secure_session();

            $userId = $_SESSION['user_id'] ?? $_SESSION['idUsuario'] ?? null;
            if (!empty($userId)) {
                $rolId = (int)($_SESSION['rol'] ?? (is_array($_SESSION['roles'] ?? null) && !empty($_SESSION['roles']) ? $_SESSION['roles'][0] : 1));
                return [
                    'id'     => (int) $userId,
                    'nombre' => $_SESSION['nombre'] ?? '',
                    'rol'    => $rolId,
                ];
            }

            $this->error('UNAUTHENTICATED', 'Se requiere autenticación.', 401);
        }

        $auth      = new AuthMiddleware();
        $resultado = $auth->validarToken($token);

        if (!$resultado['success']) {
            $this->error('INVALID_TOKEN', 'Token inválido o expirado.', 401);
        }

        $usuario = $resultado['data'];

        // Verificar que el usuario esté activo (campo 'activo' en el JWT
        // o ausencia del mismo tratada como activo por compatibilidad).
        if (isset($usuario['activo']) && !(bool) $usuario['activo']) {
            $this->error('USER_INACTIVE', 'El usuario no está activo.', 401);
        }

        return $usuario;
    }

    /**
     * Verifica que el usuario autenticado tenga el permiso formal
     * 'logistica_operativa_bodega' (tabla permisos, migración 022).
     *
     * Fase 4 implementada: consulta real a roles_permisos JOIN permisos.
     * Deny by default: si la consulta BD falla o el rol no existe → 403.
     * No expone SQL, DSN ni traza en la respuesta JSON.
     *
     * @see utils/logistica_permissions.php
     * @param array{id:int, nombre:string, rol:int} $usuario  Datos del JWT.
     */
    public function verificarAutorizacion(array $usuario): void
    {
        // api_require_permission() responde 403 JSON y sale si falla.
        // Nunca devuelve false — o pasa o termina la ejecución.
        api_require_permission('logistica_operativa_bodega', $usuario);
    }

    /**
     * Verifica que el módulo Logística Operativa esté habilitado y en shadow mode.
     */
    public function verificarModulo(): void
    {
        if (!LogisticaOperativaFlags::enabled()) {
            $this->error('MODULE_DISABLED', 'El módulo Logística Operativa no está habilitado.', 403);
        }
        if (!LogisticaOperativaFlags::shadowMode()) {
            $this->error('SHADOW_MODE_REQUIRED', 'El modo sombra debe estar activo.', 403);
        }
    }

    // ── Conexión a la base de datos ───────────────────────────────────────

    /**
     * Crea y devuelve una conexión PDO a la base de datos activa.
     * Usa las constantes DB_* definidas en config.php.
     */
    public function crearConexion(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            DB_HOST,
            DB_SCHEMA
        );

        return new PDO($dsn, DB_USER, DB_PASSWORD, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    /**
     * Crea el servicio BodegaUbicacionService con la conexión indicada.
     * Permite sobreescritura en pruebas.
     */
    protected function crearServicio(PDO $db): BodegaUbicacionService
    {
        return new BodegaUbicacionService($db);
    }

    // ── JSON input ────────────────────────────────────────────────────────

    /**
     * Lee y decodifica el body JSON del request.
     * Emite 400 si el JSON es inválido o el body está vacío.
     *
     * @return array<string, mixed>
     */
    public function leerJson(): array
    {
        $raw = file_get_contents('php://input');

        if ($raw === false || trim($raw) === '') {
            $this->error('EMPTY_BODY', 'El cuerpo de la petición está vacío.', 400);
        }

        $data = json_decode($raw, true);

        if (!is_array($data)) {
            $this->error('INVALID_JSON', 'El cuerpo no es JSON válido.', 400);
        }

        return $data;
    }

    // ── Acciones de dominio ───────────────────────────────────────────────

    /**
     * POST /api/logistica-operativa/bodega/recepciones/registrar
     *
     * Registra la recepción física de un paquete en bodega.
     * El id_operador se obtiene del usuario autenticado, nunca del JSON.
     *
     * Respuesta 201: nueva recepción.
     * Respuesta 200: UUID idempotente, ya existía.
     */
    public function registrar(): void
    {
        $this->aplicarHeaders('POST, OPTIONS');
        $this->requerirMetodo('POST');
        $this->verificarModulo();
        $this->requerirJsonContentType();

        $usuario    = $this->autenticar();
        $this->verificarAutorizacion($usuario);
        $idOperador = (int) ($usuario['id'] ?? 0);

        if ($idOperador <= 0) {
            $this->error('INVALID_OPERATOR', 'No se pudo obtener el operador del token.', 401);
        }

        $body = $this->leerJson();

        // Validar campos requeridos
        foreach (['uuid', 'id_pedido', 'id_bodega', 'tipo_recepcion', 'recibido_at'] as $campo) {
            if (!isset($body[$campo]) || (is_string($body[$campo]) && trim($body[$campo]) === '')) {
                $this->error('MISSING_FIELD', "El campo '{$campo}' es requerido.", 400);
            }
        }
        if (!is_numeric($body['id_pedido']) || !is_numeric($body['id_bodega'])) {
            $this->error('INVALID_FIELD', 'id_pedido e id_bodega deben ser numéricos.', 400);
        }

        // Construir datos para el servicio; id_operador viene del token, nunca del cliente
        $datos = [
            'uuid'           => trim((string) $body['uuid']),
            'id_pedido'      => (int) $body['id_pedido'],
            'id_bodega'      => (int) $body['id_bodega'],
            'id_ubicacion'   => isset($body['id_ubicacion']) && is_numeric($body['id_ubicacion'])
                                    ? (int) $body['id_ubicacion'] : null,
            'id_escaneo'     => isset($body['id_escaneo']) && is_numeric($body['id_escaneo'])
                                    ? (int) $body['id_escaneo'] : null,
            'tipo_recepcion' => strtoupper(trim((string) $body['tipo_recepcion'])),
            'id_operador'    => $idOperador,                     // ← del token
            'recibido_at'    => trim((string) $body['recibido_at']),
            'observacion'    => isset($body['observacion']) ? trim((string) $body['observacion']) : null,
        ];

        try {
            $db        = $this->crearConexion();
            $servicio  = $this->crearServicio($db);
            $resultado = $servicio->registrarRecepcion($datos);

            // 200 si idempotente, 201 si se creó nueva
            $httpCode = $resultado['idempotente'] ? 200 : 201;
            $this->ok($resultado, $httpCode);
        } catch (LogisticaOperativaException $e) {
            error_log('[BodegaUbicacionController::registrar] ' . $e->getMessage());
            $this->mapearExcepcion($e);
        } catch (Throwable $e) {
            error_log('[BodegaUbicacionController::registrar] Error interno: ' . $e->getMessage());
            $this->error('INTERNAL_ERROR', 'Error interno del servidor.', 500);
        }
    }

    /**
     * POST /api/logistica-operativa/bodega/ubicaciones/asignar
     *
     * Asigna una ubicación a una recepción en estado RECIBIDO.
     * El id_operador se obtiene del token.
     */
    public function asignar(): void
    {
        $this->aplicarHeaders('POST, OPTIONS');
        $this->requerirMetodo('POST');
        $this->verificarModulo();
        $this->requerirJsonContentType();

        $usuario    = $this->autenticar();
        $this->verificarAutorizacion($usuario);
        $idOperador = (int) ($usuario['id'] ?? 0);

        if ($idOperador <= 0) {
            $this->error('INVALID_OPERATOR', 'No se pudo obtener el operador del token.', 401);
        }

        $body = $this->leerJson();

        foreach (['id_pedido', 'id_recepcion', 'id_ubicacion'] as $campo) {
            if (!isset($body[$campo]) || !is_numeric($body[$campo])) {
                $this->error('MISSING_FIELD', "El campo '{$campo}' es requerido y debe ser numérico.", 400);
            }
        }

        $idPedido    = (int) $body['id_pedido'];
        $idRecepcion = (int) $body['id_recepcion'];
        $idUbicacion = (int) $body['id_ubicacion'];
        $motivo      = isset($body['motivo']) ? trim((string) $body['motivo']) : null;

        try {
            $db        = $this->crearConexion();
            $servicio  = $this->crearServicio($db);
            $resultado = $servicio->ubicarPaquete($idPedido, $idRecepcion, $idUbicacion, $idOperador, $motivo);
            $this->ok($resultado);
        } catch (LogisticaOperativaException $e) {
            error_log('[BodegaUbicacionController::asignar] ' . $e->getMessage());
            $this->mapearExcepcion($e);
        } catch (Throwable $e) {
            error_log('[BodegaUbicacionController::asignar] Error interno: ' . $e->getMessage());
            $this->error('INTERNAL_ERROR', 'Error interno del servidor.', 500);
        }
    }

    /**
     * GET /api/logistica-operativa/bodega/ubicaciones/actual?id_pedido=N
     *
     * Devuelve la ubicación física actual del paquete.
     * Devuelve 404 si no tiene ubicación activa.
     */
    public function actual(): void
    {
        $this->aplicarHeaders('GET, OPTIONS');
        $this->requerirMetodo('GET');
        $this->verificarModulo();
        $usuario = $this->autenticar();
        $this->verificarAutorizacion($usuario);

        $idPedidoRaw = $_GET['id_pedido'] ?? '';

        if ($idPedidoRaw === '' || !is_numeric($idPedidoRaw)) {
            $this->error('MISSING_PARAM', 'El parámetro id_pedido es requerido y debe ser numérico.', 400);
        }

        $idPedido = (int) $idPedidoRaw;

        try {
            $db        = $this->crearConexion();
            $servicio  = $this->crearServicio($db);
            $resultado = $servicio->obtenerUbicacionActual($idPedido);

            if ($resultado === null) {
                $this->error('UBICACION_ACTUAL_NO_ENCONTRADA', 'El pedido no tiene una ubicación activa.', 404);
            }

            $this->ok($resultado);
        } catch (LogisticaOperativaException $e) {
            error_log('[BodegaUbicacionController::actual] ' . $e->getMessage());
            $this->mapearExcepcion($e);
        } catch (Throwable $e) {
            error_log('[BodegaUbicacionController::actual] Error interno: ' . $e->getMessage());
            $this->error('INTERNAL_ERROR', 'Error interno del servidor.', 500);
        }
    }

    /**
     * POST /api/logistica-operativa/bodega/ubicaciones/reubicar
     *
     * Reubica el paquete a una nueva ubicación dentro de la misma bodega.
     * El id_operador se obtiene del token.
     */
    public function reubicar(): void
    {
        $this->aplicarHeaders('POST, OPTIONS');
        $this->requerirMetodo('POST');
        $this->verificarModulo();
        $this->requerirJsonContentType();

        $usuario    = $this->autenticar();
        $this->verificarAutorizacion($usuario);
        $idOperador = (int) ($usuario['id'] ?? 0);

        if ($idOperador <= 0) {
            $this->error('INVALID_OPERATOR', 'No se pudo obtener el operador del token.', 401);
        }

        $body = $this->leerJson();

        foreach (['id_pedido', 'id_ubicacion_destino'] as $campo) {
            if (!isset($body[$campo]) || !is_numeric($body[$campo])) {
                $this->error('MISSING_FIELD', "El campo '{$campo}' es requerido y debe ser numérico.", 400);
            }
        }

        $idPedido          = (int) $body['id_pedido'];
        $idUbicacionDestino = (int) $body['id_ubicacion_destino'];
        $motivo            = isset($body['motivo']) ? trim((string) $body['motivo']) : null;

        try {
            $db        = $this->crearConexion();
            $servicio  = $this->crearServicio($db);
            $resultado = $servicio->reubicarPaquete($idPedido, $idUbicacionDestino, $idOperador, $motivo);
            $this->ok($resultado);
        } catch (LogisticaOperativaException $e) {
            error_log('[BodegaUbicacionController::reubicar] ' . $e->getMessage());
            $this->mapearExcepcion($e);
        } catch (Throwable $e) {
            error_log('[BodegaUbicacionController::reubicar] Error interno: ' . $e->getMessage());
            $this->error('INTERNAL_ERROR', 'Error interno del servidor.', 500);
        }
    }

    /**
     * POST /api/logistica-operativa/bodega/ubicaciones/retirar
     *
     * Retira el paquete de su ubicación actual.
     * El id_operador se obtiene del token.
     * Operación idempotente.
     */
    public function retirar(): void
    {
        $this->aplicarHeaders('POST, OPTIONS');
        $this->requerirMetodo('POST');
        $this->verificarModulo();
        $this->requerirJsonContentType();

        $usuario    = $this->autenticar();
        $this->verificarAutorizacion($usuario);
        $idOperador = (int) ($usuario['id'] ?? 0);

        if ($idOperador <= 0) {
            $this->error('INVALID_OPERATOR', 'No se pudo obtener el operador del token.', 401);
        }

        $body = $this->leerJson();

        if (!isset($body['id_pedido']) || !is_numeric($body['id_pedido'])) {
            $this->error('MISSING_FIELD', "El campo 'id_pedido' es requerido y debe ser numérico.", 400);
        }

        $idPedido = (int) $body['id_pedido'];
        $motivo   = isset($body['motivo']) ? trim((string) $body['motivo']) : null;

        try {
            $db        = $this->crearConexion();
            $servicio  = $this->crearServicio($db);
            $resultado = $servicio->retirarPaquete($idPedido, $idOperador, $motivo);
            $this->ok($resultado);
        } catch (LogisticaOperativaException $e) {
            error_log('[BodegaUbicacionController::retirar] ' . $e->getMessage());
            $this->mapearExcepcion($e);
        } catch (Throwable $e) {
            error_log('[BodegaUbicacionController::retirar] Error interno: ' . $e->getMessage());
            $this->error('INTERNAL_ERROR', 'Error interno del servidor.', 500);
        }
    }

    /**
     * GET /api/logistica-operativa/bodega/ubicaciones/historial?id_pedido=N
     *
     * Devuelve el historial completo de movimientos del paquete en orden cronológico.
     */
    public function historial(): void
    {
        $this->aplicarHeaders('GET, OPTIONS');
        $this->requerirMetodo('GET');
        $this->verificarModulo();
        $usuario = $this->autenticar();
        $this->verificarAutorizacion($usuario);

        $idPedidoRaw = $_GET['id_pedido'] ?? '';

        if ($idPedidoRaw === '' || !is_numeric($idPedidoRaw)) {
            $this->error('MISSING_PARAM', 'El parámetro id_pedido es requerido y debe ser numérico.', 400);
        }

        $idPedido = (int) $idPedidoRaw;

        try {
            $db        = $this->crearConexion();
            $servicio  = $this->crearServicio($db);
            $resultado = $servicio->obtenerHistorial($idPedido);
            $this->ok($resultado);
        } catch (LogisticaOperativaException $e) {
            error_log('[BodegaUbicacionController::historial] ' . $e->getMessage());
            $this->mapearExcepcion($e);
        } catch (Throwable $e) {
            error_log('[BodegaUbicacionController::historial] Error interno: ' . $e->getMessage());
            $this->error('INTERNAL_ERROR', 'Error interno del servidor.', 500);
        }
    }

    // ── Mapeo de excepciones de dominio ───────────────────────────────────

    /**
     * Convierte una LogisticaOperativaException en respuesta HTTP.
     *
     * El mapeo se basa EXCLUSIVAMENTE en el código de dominio estable
     * (getDomainCode()), nunca en el contenido del mensaje.
     * Esto garantiza que cambiar el idioma o el texto del mensaje
     * no altera el código HTTP devuelto.
     *
     * Códigos HTTP devueltos:
     *   404 → recursos no encontrados
     *   409 → conflictos de estado
     *   422 → reglas de negocio violadas
     *   400 → datos de entrada inválidos
     *   500 → código de dominio desconocido (never deberia llegar) o vacío
     */
    protected function mapearExcepcion(LogisticaOperativaException $e): never
    {
        $domainCode = $e->getDomainCode();
        $msg        = $this->mensajeSeguro($e->getMessage());

        $http = match ($domainCode) {
            // ── 404 — No encontrado ────────────────────────────────────────
            'PEDIDO_NO_ENCONTRADO',
            'BODEGA_NO_ENCONTRADA',
            'UBICACION_NO_ENCONTRADA',
            'RECEPCION_NO_ENCONTRADA',
            'UBICACION_ACTUAL_NO_ENCONTRADA'
                => 404,

            // ── 409 — Conflicto de estado ─────────────────────────────────
            'RECEPCION_ACTIVA_EXISTENTE',
            'PAQUETE_YA_UBICADO'
                => 409,

            // ── 422 — Regla de negocio ────────────────────────────────────
            'BODEGA_INACTIVA',
            'UBICACION_INACTIVA',
            'UBICACION_NO_PERTENECE_BODEGA',
            'RECEPCION_NO_CORRESPONDE_PEDIDO',
            'TRASLADO_ENTRE_BODEGAS_NO_PERMITIDO',
            'PAQUETE_SIN_UBICACION',
            'TIPO_RECEPCION_INVALIDO'
                => 422,

            // ── 400 — Datos de entrada inválidos ─────────────────────────
            'UUID_INVALIDO',
            'FECHA_INVALIDA'
                => 400,

            // ── 500 — Código desconocido o vacío ─────────────────────────
            // Un código desconocido indica un error de programación (excepción
            // lanzada sin domainCode). Tratarlo como error interno seguro.
            default => 500,
        };

        $errorCode = match ($http) {
            404  => 'NOT_FOUND',
            409  => 'CONFLICT',
            422  => 'UNPROCESSABLE',
            400  => 'BAD_REQUEST',
            default => 'INTERNAL_ERROR',
        };

        // Para 500 no devolvemos el mensaje del dominio al cliente
        $clientMsg = ($http === 500)
            ? 'Error interno del servidor.'
            : $msg;

        if ($http === 500) {
            // Registrar el código desconocido para facilitar depuración
            error_log("[BodegaUbicacionController] domainCode desconocido: '{$domainCode}' | {$e->getMessage()}");
        }

        $this->error($errorCode, $clientMsg, $http);
    }

    /**
     * Filtra el mensaje para eliminar posible información sensible.
     * Los mensajes de LogisticaOperativaException ya son seguros,
     * pero aplicamos una capa adicional de limpieza por defensa en profundidad.
     */
    private function mensajeSeguro(string $msg): string
    {
        // Eliminar posibles rutas de archivo (por si acaso)
        $msg = preg_replace('/\bin\s+[\/\\\\].+\.php(:\d+)?/i', '', $msg) ?? $msg;
        // Truncar mensajes excesivamente largos
        return mb_substr(trim($msg), 0, 300);
    }
}
