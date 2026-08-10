<?php

declare(strict_types=1);

/**
 * ColectaController
 *
 * Controlador delgado para los endpoints de Logística Operativa — Colectas.
 *
 * Responsabilidades:
 *   1. Orquestar autenticación y autorización.
 *   2. Validar método HTTP y Content-Type.
 *   3. Parsear y validar el JSON de entrada.
 *   4. Delegar a ColectaService la lógica de dominio.
 *   5. Emitir respuesta JSON uniforme.
 *
 * Restricciones de seguridad:
 *   - Nunca acepta id_operador desde el cliente.
 *   - Nunca expone file, line, trace ni contraseñas en errores.
 *   - Registra errores internos solo con error_log().
 *   - Bloquea si LOGISTICA_OPERATIVA_ENABLED=false.
 *   - Mantiene shadowMode=true (no modifica pedidos.id_estado, stock, inventario).
 *
 * Fase 4 implementada: verificarAutorizacion() usa el permiso formal
 *   'logistica_operativa_colectas' (tabla permisos, migración 022).
 * @see utils/logistica_permissions.php
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../utils/permissions.php';
require_once __DIR__ . '/../../api/utils/autenticacion.php';
require_once __DIR__ . '/../../services/LogisticaOperativaFlags.php';
require_once __DIR__ . '/../../services/logistica_operativa/LogisticaOperativaException.php';
require_once __DIR__ . '/../../services/logistica_operativa/ColectaService.php';
require_once __DIR__ . '/../../utils/logistica_permissions.php';

class ColectaController
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
     * Nunca incluye file, line, trace ni información sensible.
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

    // ── Seguridad de transporte ────────────────────────────────────────────

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
     *
     * @param string $expected Método esperado (GET, POST, …).
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

        return $resultado['data'];
    }

    /**
     * Verifica que el módulo Logística Operativa esté habilitado.
     */
    public function verificarModulo(): void
    {
        if (!LogisticaOperativaFlags::enabled()) {
            $this->error('MODULE_DISABLED', 'El módulo Logística Operativa no está habilitado.', 403);
        }
    }

    /**
     * Verifica que el usuario autenticado tenga el permiso formal
     * 'logistica_operativa_colectas' (tabla permisos, migración 022).
     *
     * Fase 4 implementada: consulta real a roles_permisos JOIN permisos.
     * Deny by default: si la consulta BD falla o el rol no existe → 403 JSON.
     * No expone SQL, DSN ni traza en la respuesta.
     *
     * @see utils/logistica_permissions.php
     * @param array{id:int, nombre:string, rol:int} $usuario  Datos del JWT.
     */
    public function verificarAutorizacion(array $usuario): void
    {
        // api_require_permission() responde 403 JSON y sale si falla.
        // Nunca devuelve false — o pasa o termina la ejecución.
        api_require_permission('logistica_operativa_colectas', $usuario);
    }

    // ── Conexión a la base de datos ───────────────────────────────────────

    /**
     * Crea y devuelve una conexión PDO a la base de datos activa.
     * Usa las constantes DB_* definidas en config.php.
     *
     * @throws RuntimeException si la conexión falla.
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

    /**
     * Verifica que el usuario autenticado (si no es Admin) sea el propietario (id_proveedor) de la colecta.
     * id_abierta_por NO otorga ningún permiso de acceso.
     */
    public function verificarPropiedadColecta(PDO $db, int $idColecta, array $usuario): array
    {
        $model = new ColectaModel($db);
        $colecta = $model->obtenerPorId($idColecta);
        if ($colecta === null) {
            $this->error('NOT_FOUND', 'Colecta no encontrada.', 404);
        }

        $currentUserId = (int)($usuario['id'] ?? 0);
        $isAdmin       = isSuperAdmin();
        $isProveedorDueno = ($currentUserId > 0 && (int)$colecta['id_proveedor'] === $currentUserId);

        if (!$isAdmin && !$isProveedorDueno) {
            $this->error('FORBIDDEN', 'Acceso denegado: Esta colecta pertenece a otro Proveedor.', 403);
        }

        return $colecta;
    }

    // ── Acciones de dominio ───────────────────────────────────────────────

    /**
     * POST /api/logistica-operativa/colectas/abrir
     *
     * Abre una nueva colecta para un cliente y proveedor en una fecha y turno.
     * El id_operador se obtiene del usuario autenticado.
     *
     * Respuesta 201: { success: true, data: { id_colecta, cantidad_esperada, pedidos_ids } }
     */
    public function abrir(): void
    {
        $this->aplicarHeaders('POST, OPTIONS');
        $this->requerirMetodo('POST');
        $this->verificarModulo();
        $this->requerirJsonContentType();

        $usuario = $this->autenticar();
        $idOperador = (int) ($usuario['id'] ?? 0);

        if ($idOperador <= 0) {
            $this->error('INVALID_OPERATOR', 'No se pudo obtener el operador del token.', 401);
        }

        $body = $this->leerJson();

        // Validar campos requeridos
        if (empty($body['id_cliente']) || !is_numeric($body['id_cliente'])) {
            $this->error('MISSING_FIELD', 'El campo id_cliente es requerido y debe ser numérico.', 400);
        }
        if (empty($body['fecha']) || !is_string($body['fecha'])) {
            $this->error('MISSING_FIELD', 'El campo fecha es requerido.', 400);
        }
        if (empty($body['turno']) || !is_string($body['turno'])) {
            $this->error('MISSING_FIELD', 'El campo turno es requerido.', 400);
        }

        $idCliente = (int) $body['id_cliente'];
        $fecha     = trim($body['fecha']);
        $turno     = strtoupper(trim($body['turno']));

        // id_proveedor viene en el payload si lo selecciona un Admin o el modal, o defaultea al operador
        $isAdmin = isSuperAdmin();
        if (!empty($body['id_proveedor']) && is_numeric($body['id_proveedor'])) {
            $idProveedor = (int) $body['id_proveedor'];
        } else {
            $idProveedor = $idOperador;
        }

        try {
            $db       = $this->crearConexion();
            $servicio = new ColectaService($db);
            $resultado = $servicio->abrirColecta($idCliente, $idProveedor, $fecha, $turno, $idOperador);
            $this->ok($resultado, 201);
        } catch (LogisticaOperativaException $e) {
            error_log('[ColectaController::abrir] ' . $e->getMessage());
            $this->mapearExcepcion($e);
        } catch (Throwable $e) {
            error_log('[ColectaController::abrir] Error interno: ' . $e->getMessage());
            $this->error('INTERNAL_ERROR', 'Error interno del servidor.', 500);
        }
    }

    /**
     * POST /api/logistica-operativa/colectas/escanear
     *
     * Registra un escaneo físico de forma idempotente.
     * El id_operador se obtiene del usuario autenticado.
     */
    public function escanear(): void
    {
        $this->aplicarHeaders('POST, OPTIONS');
        $this->requerirMetodo('POST');
        $this->verificarModulo();
        $this->requerirJsonContentType();

        $usuario    = $this->autenticar();
        $idOperador = (int) ($usuario['id'] ?? 0);

        if ($idOperador <= 0) {
            $this->error('INVALID_OPERATOR', 'No se pudo obtener el operador del token.', 401);
        }

        $body = $this->leerJson();

        // Validar campos requeridos
        foreach (['uuid', 'id_colecta', 'id_pedido', 'tipo_evento', 'qr_hash', 'escaneado_at'] as $campo) {
            if (!isset($body[$campo]) || (is_string($body[$campo]) && trim($body[$campo]) === '')) {
                $this->error('MISSING_FIELD', "El campo '{$campo}' es requerido.", 400);
            }
        }

        if (!is_numeric($body['id_colecta']) || !is_numeric($body['id_pedido'])) {
            $this->error('INVALID_FIELD', 'id_colecta e id_pedido deben ser numéricos.', 400);
        }

        $idColecta = (int)$body['id_colecta'];

        try {
            $db = $this->crearConexion();
            $this->verificarPropiedadColecta($db, $idColecta, $usuario);

            $datos = [
                'uuid'          => trim((string) $body['uuid']),
                'id_colecta'    => $idColecta,
                'id_pedido'     => (int) $body['id_pedido'],
                'tipo_evento'   => strtoupper(trim((string) $body['tipo_evento'])),
                'qr_hash'       => strtolower(trim((string) $body['qr_hash'])),
                'id_operador'   => $idOperador,
                'dispositivo'   => isset($body['dispositivo']) ? trim((string) $body['dispositivo']) : null,
                'escaneado_at'  => trim((string) $body['escaneado_at']),
                'metadata_json' => isset($body['metadata_json']) && is_array($body['metadata_json'])
                                        ? json_encode($body['metadata_json'])
                                        : null,
            ];

            $servicio = new ColectaService($db);
            $resultado = $servicio->registrarEscaneo($datos);
            $this->ok($resultado);
        } catch (LogisticaOperativaException $e) {
            error_log('[ColectaController::escanear] ' . $e->getMessage());
            $this->mapearExcepcion($e);
        } catch (Throwable $e) {
            error_log('[ColectaController::escanear] Error interno: ' . $e->getMessage());
            $this->error('INTERNAL_ERROR', 'Error interno del servidor.', 500);
        }
    }

    /**
     * POST /api/logistica-operativa/colectas/cerrar
     *
     * Cierra y concilia la colecta.
     * El id_operador se obtiene del usuario autenticado.
     */
    public function cerrar(): void
    {
        $this->aplicarHeaders('POST, OPTIONS');
        $this->requerirMetodo('POST');
        $this->verificarModulo();
        $this->requerirJsonContentType();

        $usuario    = $this->autenticar();
        $idOperador = (int) ($usuario['id'] ?? 0);

        if ($idOperador <= 0) {
            $this->error('INVALID_OPERATOR', 'No se pudo obtener el operador del token.', 401);
        }

        $body = $this->leerJson();

        if (empty($body['id_colecta']) || !is_numeric($body['id_colecta'])) {
            $this->error('MISSING_FIELD', 'El campo id_colecta es requerido y debe ser numérico.', 400);
        }

        $idColecta = (int) $body['id_colecta'];

        try {
            $db       = $this->crearConexion();
            $this->verificarPropiedadColecta($db, $idColecta, $usuario);

            $servicio = new ColectaService($db);
            $resultado = $servicio->cerrarYConciliar($idColecta, $idOperador);
            $this->ok($resultado);
        } catch (LogisticaOperativaException $e) {
            error_log('[ColectaController::cerrar] ' . $e->getMessage());
            $this->mapearExcepcion($e);
        } catch (Throwable $e) {
            error_log('[ColectaController::cerrar] Error interno: ' . $e->getMessage());
            $this->error('INTERNAL_ERROR', 'Error interno del servidor.', 500);
        }
    }

    /**
     * POST /api/logistica-operativa/colectas/eliminar-extra
     *
     * Elimina un paquete EXTRA de la colecta.
     */
    public function eliminarExtra(): void
    {
        $this->aplicarHeaders('POST, OPTIONS');
        $this->requerirMetodo('POST');
        $this->verificarModulo();
        $this->requerirJsonContentType();

        $usuario    = $this->autenticar();
        $idOperador = (int) ($usuario['id'] ?? 0);

        if ($idOperador <= 0) {
            $this->error('INVALID_OPERATOR', 'No se pudo obtener el operador del token.', 401);
        }

        $body = $this->leerJson();

        if (empty($body['id_colecta']) || empty($body['id_pedido'])) {
            $this->error('MISSING_FIELD', 'Los campos id_colecta e id_pedido son requeridos.', 400);
        }

        $idColecta = (int) $body['id_colecta'];
        $idPedido  = (int) $body['id_pedido'];

        try {
            $db       = $this->crearConexion();
            $this->verificarPropiedadColecta($db, $idColecta, $usuario);

            $servicio = new ColectaService($db);
            $resultado = $servicio->eliminarExtra($idColecta, $idPedido, $idOperador);
            $this->ok($resultado);
        } catch (LogisticaOperativaException $e) {
            error_log('[ColectaController::eliminarExtra] ' . $e->getMessage());
            $this->mapearExcepcion($e);
        } catch (Throwable $e) {
            error_log('[ColectaController::eliminarExtra] Error interno: ' . $e->getMessage());
            $this->error('INTERNAL_ERROR', 'Error interno del servidor.', 500);
        }
    }

    /**
     * GET /api/logistica-operativa/colectas/resumen?id_colecta=N
     *
     * Devuelve el resumen completo de una colecta.
     */
    public function resumen(): void
    {
        $this->aplicarHeaders('GET, OPTIONS');
        $this->requerirMetodo('GET');
        $this->verificarModulo();
        $usuario = $this->autenticar();

        $idColectaRaw = $_GET['id_colecta'] ?? '';

        if ($idColectaRaw === '' || !is_numeric($idColectaRaw)) {
            $this->error('MISSING_PARAM', 'El parámetro id_colecta es requerido y debe ser numérico.', 400);
        }

        $idColecta = (int) $idColectaRaw;

        try {
            $db       = $this->crearConexion();
            $this->verificarPropiedadColecta($db, $idColecta, $usuario);

            $servicio = new ColectaService($db);
            $resultado = $servicio->obtenerResumen($idColecta);
            $this->ok($resultado);
        } catch (LogisticaOperativaException $e) {
            error_log('[ColectaController::resumen] ' . $e->getMessage());
            $this->mapearExcepcion($e);
        } catch (Throwable $e) {
            error_log('[ColectaController::resumen] Error interno: ' . $e->getMessage());
            $this->error('INTERNAL_ERROR', 'Error interno del servidor.', 500);
        }
    }

    // ── Mapeo de excepciones de dominio ───────────────────────────────────

    /**
     * Convierte una LogisticaOperativaException en una respuesta HTTP apropiada.
     * El mensaje de la excepción se usa como mensaje de error (ya es seguro).
     * Nunca expone stack trace, rutas de archivo ni variables internas.
     */
    private function mapearExcepcion(LogisticaOperativaException $e): never
    {
        $msg = $e->getMessage();

        // Detectar duplicado / colecta cerrada → 409
        if (
            str_contains($msg, 'Ya existe una colecta') ||
            str_contains($msg, 'no está ABIERTA') ||
            str_contains($msg, 'No se puede cerrar')
        ) {
            $this->error('CONFLICT', $msg, 409);
        }

        // No encontrado → 404
        if (
            str_contains($msg, 'no encontrada') ||
            str_contains($msg, 'no encontrado')
        ) {
            $this->error('NOT_FOUND', $msg, 404);
        }

        // Regla de negocio / validación → 422
        if (
            str_contains($msg, 'inválido') ||
            str_contains($msg, 'inválida') ||
            str_contains($msg, 'debe ser') ||
            str_contains($msg, 'Use ')
        ) {
            $this->error('UNPROCESSABLE', $msg, 422);
        }

        // Default → 400
        $this->error('BAD_REQUEST', $msg, 400);
    }
}
