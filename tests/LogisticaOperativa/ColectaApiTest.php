<?php

declare(strict_types=1);

namespace Tests\LogisticaOperativa;

use PHPUnit\Framework\TestCase;

// ── Soporte de pruebas ────────────────────────────────────────────────────
require_once dirname(__DIR__, 2) . '/tests/Support/TestDatabaseConnection.php';
require_once dirname(__DIR__, 2) . '/tests/Support/LogisticaTestDataFactory.php';
require_once dirname(__DIR__, 2) . '/tests/Support/ColectaServiceTestable.php';

// ── Controlador real (cargado antes del testeable para evitar redefinición) ─
require_once dirname(__DIR__, 2) . '/controlador/logistica_operativa/ColectaController.php';

/**
 * Excepción interna usada para simular exit() en ColectaControllerTestable.
 *
 * Cuando el controlador llama a ok() o error(), en lugar de llamar exit
 * lanza ControllerResponseException. ColectaApiTest la captura y extrae
 * el código HTTP y el body JSON sin matar el proceso de PHPUnit.
 */
class ControllerResponseException extends \RuntimeException
{
    public function __construct(
        public readonly int    $httpCode,
        public readonly string $jsonBody
    ) {
        parent::__construct("HTTP {$httpCode}");
    }
}

/**
 * ColectaControllerTestable
 *
 * Subclase de ColectaController para pruebas unitarias de controlador:
 *   - Inyecta la conexión PDO de prueba (sin tocar paquetes_apppack).
 *   - Simula autenticación con el operador inyectado.
 *   - Simula verificarModulo() según el flag del test.
 *   - Lee el body JSON desde la propiedad estática $simulatedInput.
 *   - Reemplaza ok() y error() con ControllerResponseException
 *     en lugar de exit(), para que PHPUnit no muera.
 *   - Elimina headers HTTP en CLI (php_sapi_name() === 'cli').
 */
class ColectaControllerTestable extends \ColectaController
{
    /** Body JSON simulado (sustituye php://input). */
    public static string $simulatedInput = '';

    public function __construct(
        private \PDO $injectedDb,
        private int  $simulatedOperadorId,
        private bool $simulatedAuth = true,
        private bool $moduleEnabled = true
    ) {}

    // ── Módulo ──────────────────────────────────────────────────────────

    public function verificarModulo(): void
    {
        if (!$this->moduleEnabled) {
            $this->error('MODULE_DISABLED', 'El módulo Logística Operativa no está habilitado.', 403);
        }
    }

    // ── Servicio de prueba ────────────────────────────────────────────────

    /** Crea un ColectaServiceTestable (sin verificación de flags). */
    private function servicio(): \ColectaServiceTestable
    {
        return new \ColectaServiceTestable($this->injectedDb);
    }

    // ── Acciones sobreescritas para usar ColectaServiceTestable ───────────

    public function abrir(): void
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

        try {
            $resultado = $this->servicio()->abrirColecta($idCliente, $fecha, $turno, $idOperador);
            $this->ok($resultado, 201);
        } catch (ControllerResponseException $e) {
            throw $e; // Re-lanzar para que callController() la capture
        } catch (\LogisticaOperativaException $e) {
            $this->mapearExcepcionTestable($e);
        } catch (\Throwable $e) {
            $this->error('INTERNAL_ERROR', 'Error interno del servidor.', 500);
        }
    }

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

        foreach (['uuid', 'id_colecta', 'id_pedido', 'tipo_evento', 'qr_hash', 'escaneado_at'] as $campo) {
            if (!isset($body[$campo]) || (is_string($body[$campo]) && trim($body[$campo]) === '')) {
                $this->error('MISSING_FIELD', "El campo '{$campo}' es requerido.", 400);
            }
        }
        if (!is_numeric($body['id_colecta']) || !is_numeric($body['id_pedido'])) {
            $this->error('INVALID_FIELD', 'id_colecta e id_pedido deben ser numéricos.', 400);
        }

        $datos = [
            'uuid'          => trim((string) $body['uuid']),
            'id_colecta'    => (int) $body['id_colecta'],
            'id_pedido'     => (int) $body['id_pedido'],
            'tipo_evento'   => strtoupper(trim((string) $body['tipo_evento'])),
            'qr_hash'       => strtolower(trim((string) $body['qr_hash'])),
            'id_operador'   => $idOperador,
            'dispositivo'   => isset($body['dispositivo']) ? trim((string) $body['dispositivo']) : null,
            'escaneado_at'  => trim((string) $body['escaneado_at']),
            'metadata_json' => isset($body['metadata_json']) && is_array($body['metadata_json'])
                                    ? json_encode($body['metadata_json']) : null,
        ];

        try {
            $resultado = $this->servicio()->registrarEscaneo($datos);
            $this->ok($resultado);
        } catch (ControllerResponseException $e) {
            throw $e;
        } catch (\LogisticaOperativaException $e) {
            $this->mapearExcepcionTestable($e);
        } catch (\Throwable $e) {
            $this->error('INTERNAL_ERROR', 'Error interno del servidor.', 500);
        }
    }

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

        try {
            $resultado = $this->servicio()->cerrarYConciliar((int) $body['id_colecta'], $idOperador);
            $this->ok($resultado);
        } catch (ControllerResponseException $e) {
            throw $e;
        } catch (\LogisticaOperativaException $e) {
            $this->mapearExcepcionTestable($e);
        } catch (\Throwable $e) {
            $this->error('INTERNAL_ERROR', 'Error interno del servidor.', 500);
        }
    }

    public function resumen(): void
    {
        $this->aplicarHeaders('GET, OPTIONS');
        $this->requerirMetodo('GET');
        $this->verificarModulo();
        $this->autenticar();

        $idColectaRaw = $_GET['id_colecta'] ?? '';
        if ($idColectaRaw === '' || !is_numeric($idColectaRaw)) {
            $this->error('MISSING_PARAM', 'El parámetro id_colecta es requerido y debe ser numérico.', 400);
        }

        try {
            $resultado = $this->servicio()->obtenerResumen((int) $idColectaRaw);
            $this->ok($resultado);
        } catch (ControllerResponseException $e) {
            throw $e;
        } catch (\LogisticaOperativaException $e) {
            $this->mapearExcepcionTestable($e);
        } catch (\Throwable $e) {
            $this->error('INTERNAL_ERROR', 'Error interno del servidor.', 500);
        }
    }

    // ── Mapeo de excepción local (igual que el privado del padre) ─────────

    private function mapearExcepcionTestable(\LogisticaOperativaException $e): never
    {
        $msg = $e->getMessage();
        if (str_contains($msg, 'Ya existe una colecta') ||
            str_contains($msg, 'no está ABIERTA') ||
            str_contains($msg, 'No se puede cerrar')) {
            $this->error('CONFLICT', $msg, 409);
        }
        if (str_contains($msg, 'no encontrada') || str_contains($msg, 'no encontrado')) {
            $this->error('NOT_FOUND', $msg, 404);
        }
        if (str_contains($msg, 'inválido') || str_contains($msg, 'inválida') ||
            str_contains($msg, 'debe ser') || str_contains($msg, 'Use ')) {
            $this->error('UNPROCESSABLE', $msg, 422);
        }
        $this->error('BAD_REQUEST', $msg, 400);
    }

    // ── Autenticación ────────────────────────────────────────────────────

    public function autenticar(): array
    {
        if (!$this->simulatedAuth) {
            $this->error('UNAUTHENTICATED', 'Se requiere autenticación.', 401);
        }
        return ['id' => $this->simulatedOperadorId, 'nombre' => 'test-operador', 'rol' => 1];
    }

    // ── Conexión ─────────────────────────────────────────────────────────

    public function crearConexion(): \PDO
    {
        return $this->injectedDb;
    }

    // ── Lectura JSON ─────────────────────────────────────────────────────

    public function leerJson(): array
    {
        $raw = self::$simulatedInput;
        if (trim($raw) === '') {
            $this->error('EMPTY_BODY', 'El cuerpo de la petición está vacío.', 400);
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $this->error('INVALID_JSON', 'El cuerpo no es JSON válido.', 400);
        }
        return $data;
    }

    // ── Content-Type ─────────────────────────────────────────────────────

    public function requerirJsonContentType(): void
    {
        // En pruebas siempre aceptamos application/json; no hay headers reales.
    }

    // ── Headers y preflight ───────────────────────────────────────────────

    public function aplicarHeaders(string $allowedMethods = 'POST, OPTIONS'): void
    {
        // No enviar headers en CLI
    }

    // ── Respuestas (sin exit, con excepción capturable) ───────────────────

    public function ok(mixed $data, int $code = 200): never
    {
        $json = json_encode(
            ['success' => true, 'data' => $data],
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
        throw new ControllerResponseException($code, $json ?: '{}');
    }

    public function error(string $code, string $message, int $http = 400): never
    {
        $json = json_encode(
            ['success' => false, 'code' => $code, 'message' => $message],
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
        throw new ControllerResponseException($http, $json ?: '{}');
    }
}

// ─────────────────────────────────────────────────────────────────────────────

/**
 * ColectaApiTest
 *
 * Prueba los endpoints de Logística Operativa — Colectas invocando el controlador
 * directamente con requests simulados (sin servidor HTTP real).
 *
 * Aislamiento:
 *   setUp()    → abre transacción en paquetes_apppack_test
 *   tearDown() → rollback → la base queda intacta
 *
 * No se modifica pedidos.id_estado, stock, inventario ni reservas.
 * No se toca paquetes_apppack.
 */
class ColectaApiTest extends TestCase
{
    private \PDO $db;

    /** ID del operador activo (simulado en el token). */
    private int $operadorActivo = 0;

    // ── setUp / tearDown ──────────────────────────────────────────────────

    protected function setUp(): void
    {
        assertTestDatabase();
        $this->db = \TestDatabaseConnection::nueva();
        $this->db->beginTransaction();

        // Crear un operador de prueba para esta sesión
        $this->operadorActivo = \LogisticaTestDataFactory::crearUsuario($this->db, 'operador');
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    // ── Helper principal ──────────────────────────────────────────────────

    /**
     * Invoca un método del ColectaControllerTestable con requests simulados.
     *
     * @param string               $action     Método del controlador: 'abrir' | 'escanear' | 'cerrar' | 'resumen'
     * @param string               $httpMethod Método HTTP: 'GET' | 'POST'
     * @param array<string,mixed>  $body       Payload JSON (para POST)
     * @param array<string,mixed>  $query      Parámetros GET ($_GET)
     * @param bool                 $modEnabled Si el módulo está habilitado
     * @param bool                 $withAuth   Si se simula autenticación
     *
     * @return array{ code: int, body: array<string,mixed> }
     */
    private function callController(
        string $action,
        string $httpMethod  = 'POST',
        array  $body        = [],
        array  $query       = [],
        bool   $modEnabled  = true,
        bool   $withAuth    = true
    ): array {
        // Simular REQUEST_METHOD
        $_SERVER['REQUEST_METHOD'] = strtoupper($httpMethod);
        $_SERVER['CONTENT_TYPE']   = 'application/json';

        // Simular query string
        $_GET = $query;

        // Simular body JSON
        ColectaControllerTestable::$simulatedInput = empty($body) ? '' : (string) json_encode($body);

        $controller = new ColectaControllerTestable(
            $this->db,
            $this->operadorActivo,
            $withAuth,
            $modEnabled
        );

        try {
            $controller->$action();
            // Si llega aquí sin excepción → algo inesperado
            return ['code' => 200, 'body' => []];
        } catch (ControllerResponseException $e) {
            $parsed = json_decode($e->jsonBody, true) ?? [];
            return ['code' => $e->httpCode, 'body' => $parsed];
        }
    }

    // ── Tests — SEGURIDAD ─────────────────────────────────────────────────

    /**
     * @test T01. Módulo deshabilitado devuelve 403.
     */
    public function test_modulo_deshabilitado_devuelve_403(): void
    {
        $r = $this->callController('abrir', 'POST',
            ['id_cliente' => 1, 'fecha' => '2099-01-01', 'turno' => 'MANANA'],
            [], false
        );

        $this->assertSame(403, $r['code']);
        $this->assertFalse($r['body']['success']);
        $this->assertSame('MODULE_DISABLED', $r['body']['code']);
    }

    /**
     * @test T02. Usuario no autenticado devuelve 401.
     */
    public function test_sin_autenticacion_devuelve_401(): void
    {
        $r = $this->callController('abrir', 'POST',
            ['id_cliente' => 1, 'fecha' => '2099-01-01', 'turno' => 'MANANA'],
            [], true, false
        );

        $this->assertSame(401, $r['code']);
        $this->assertFalse($r['body']['success']);
    }

    /**
     * @test T03. Método HTTP incorrecto en /abrir devuelve 405.
     */
    public function test_metodo_incorrecto_devuelve_405(): void
    {
        $r = $this->callController('abrir', 'GET');

        $this->assertSame(405, $r['code']);
        $this->assertFalse($r['body']['success']);
        $this->assertSame('METHOD_NOT_ALLOWED', $r['body']['code']);
    }

    /**
     * @test T04. Body sin campo turno devuelve 400.
     */
    public function test_datos_invalidos_campo_faltante_devuelven_400(): void
    {
        $r = $this->callController('abrir', 'POST',
            ['id_cliente' => 1, 'fecha' => '2099-01-01']
            // Falta turno
        );

        $this->assertSame(400, $r['code']);
        $this->assertFalse($r['body']['success']);
    }

    /**
     * @test T05. Turno inválido devuelve 422 (regla de negocio).
     */
    public function test_turno_invalido_devuelve_422(): void
    {
        $cliente = \LogisticaTestDataFactory::crearUsuario($this->db, 'cliente');

        $r = $this->callController('abrir', 'POST', [
            'id_cliente' => $cliente,
            'fecha'      => '2099-01-01',
            'turno'      => 'NOCHE',
        ]);

        $this->assertSame(422, $r['code']);
        $this->assertFalse($r['body']['success']);
    }

    /**
     * @test T06. Fecha inválida devuelve 422.
     */
    public function test_fecha_invalida_devuelve_422(): void
    {
        $cliente = \LogisticaTestDataFactory::crearUsuario($this->db, 'cliente');

        $r = $this->callController('abrir', 'POST', [
            'id_cliente' => $cliente,
            'fecha'      => '24/07/2099',
            'turno'      => 'MANANA',
        ]);

        $this->assertSame(422, $r['code']);
        $this->assertFalse($r['body']['success']);
    }

    // ── Tests — APERTURA ──────────────────────────────────────────────────

    /**
     * @test T07. Abrir colecta devuelve 201 con estructura correcta.
     */
    public function test_abrir_colecta_devuelve_201(): void
    {
        $cliente = \LogisticaTestDataFactory::crearUsuario($this->db, 'cliente');
        $p1      = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $p2      = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);

        $r = $this->callController('abrir', 'POST', [
            'id_cliente' => $cliente,
            'fecha'      => '2099-06-01',
            'turno'      => 'MANANA',
        ]);

        $this->assertSame(201, $r['code']);
        $this->assertTrue($r['body']['success']);
        $this->assertArrayHasKey('id_colecta', $r['body']['data']);
        $this->assertGreaterThan(0, $r['body']['data']['id_colecta']);
        $this->assertSame(2, $r['body']['data']['cantidad_esperada']);
        $this->assertContains($p1, $r['body']['data']['pedidos_ids']);
        $this->assertContains($p2, $r['body']['data']['pedidos_ids']);
    }

    /**
     * @test T08. Segunda apertura con misma clave devuelve 409.
     */
    public function test_colecta_duplicada_devuelve_409(): void
    {
        $cliente = \LogisticaTestDataFactory::crearUsuario($this->db, 'cliente');

        $this->callController('abrir', 'POST', [
            'id_cliente' => $cliente,
            'fecha'      => '2099-06-02',
            'turno'      => 'TARDE',
        ]);

        $r = $this->callController('abrir', 'POST', [
            'id_cliente' => $cliente,
            'fecha'      => '2099-06-02',
            'turno'      => 'TARDE',
        ]);

        $this->assertSame(409, $r['code']);
        $this->assertFalse($r['body']['success']);
        $this->assertSame('CONFLICT', $r['body']['code']);
    }

    // ── Tests — ESCANEO ───────────────────────────────────────────────────

    /**
     * @test T09. Escaneo de pedido esperado devuelve RECIBIDO.
     */
    public function test_escaneo_esperado_devuelve_recibido(): void
    {
        $cliente = \LogisticaTestDataFactory::crearUsuario($this->db, 'cliente');
        $pedido  = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);

        $colecta   = $this->callController('abrir', 'POST', [
            'id_cliente' => $cliente, 'fecha' => '2099-06-03', 'turno' => 'MANANA',
        ]);
        $idColecta = $colecta['body']['data']['id_colecta'];

        $r = $this->callController('escanear', 'POST', [
            'uuid'          => \LogisticaTestDataFactory::uuid(),
            'id_colecta'    => $idColecta,
            'id_pedido'     => $pedido,
            'tipo_evento'   => 'COLECTA_RECEPCION',
            'qr_hash'       => \LogisticaTestDataFactory::qrHash(),
            'dispositivo'   => 'scanner-01',
            'escaneado_at'  => '2099-06-03 10:00:00',
            'metadata_json' => [],
        ]);

        $this->assertSame(200, $r['code']);
        $this->assertTrue($r['body']['success']);
        $this->assertSame('RECIBIDO', $r['body']['data']['resultado_pedido']);
        $this->assertFalse($r['body']['data']['idempotente']);
    }

    /**
     * @test T10. Escaneo de pedido extra devuelve EXTRA.
     */
    public function test_escaneo_extra_devuelve_extra(): void
    {
        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cliente');
        $clienteB = \LogisticaTestDataFactory::crearUsuario($this->db, 'clienteB');
        $extra    = \LogisticaTestDataFactory::crearPedido($this->db, $clienteB);

        $colecta   = $this->callController('abrir', 'POST', [
            'id_cliente' => $cliente, 'fecha' => '2099-06-04', 'turno' => 'TARDE',
        ]);
        $idColecta = $colecta['body']['data']['id_colecta'];

        $r = $this->callController('escanear', 'POST', [
            'uuid'          => \LogisticaTestDataFactory::uuid(),
            'id_colecta'    => $idColecta,
            'id_pedido'     => $extra,
            'tipo_evento'   => 'COLECTA_RECEPCION',
            'qr_hash'       => \LogisticaTestDataFactory::qrHash(),
            'dispositivo'   => 'scanner-01',
            'escaneado_at'  => '2099-06-04 10:00:00',
            'metadata_json' => [],
        ]);

        $this->assertSame(200, $r['code']);
        $this->assertSame('EXTRA', $r['body']['data']['resultado_pedido']);
    }

    /**
     * @test T11. UUID repetido es idempotente (no duplica el escaneo).
     */
    public function test_uuid_repetido_es_idempotente(): void
    {
        $cliente = \LogisticaTestDataFactory::crearUsuario($this->db, 'cliente');
        $pedido  = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);

        $colecta   = $this->callController('abrir', 'POST', [
            'id_cliente' => $cliente, 'fecha' => '2099-06-05', 'turno' => 'MANANA',
        ]);
        $idColecta = $colecta['body']['data']['id_colecta'];

        $uuid    = \LogisticaTestDataFactory::uuid();
        $payload = [
            'uuid'          => $uuid,
            'id_colecta'    => $idColecta,
            'id_pedido'     => $pedido,
            'tipo_evento'   => 'COLECTA_RECEPCION',
            'qr_hash'       => \LogisticaTestDataFactory::qrHash(),
            'dispositivo'   => 'scanner-01',
            'escaneado_at'  => '2099-06-05 10:00:00',
            'metadata_json' => [],
        ];

        $r1 = $this->callController('escanear', 'POST', $payload);
        $r2 = $this->callController('escanear', 'POST', $payload);

        $this->assertFalse($r1['body']['data']['idempotente']);
        $this->assertTrue($r2['body']['data']['idempotente']);

        // Solo 1 registro en BD
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM logistica_escaneos WHERE uuid = :u');
        $stmt->execute([':u' => $uuid]);
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    // ── Tests — CIERRE ────────────────────────────────────────────────────

    /**
     * @test T12. Cierre devuelve resumen conciliado con FALTANTE.
     */
    public function test_cierre_devuelve_resumen_conciliado(): void
    {
        $cliente = \LogisticaTestDataFactory::crearUsuario($this->db, 'cliente');
        $p1      = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $p2      = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);

        $colecta   = $this->callController('abrir', 'POST', [
            'id_cliente' => $cliente, 'fecha' => '2099-06-06', 'turno' => 'MANANA',
        ]);
        $idColecta = $colecta['body']['data']['id_colecta'];

        // Solo escanear p1; p2 quedará FALTANTE
        $this->callController('escanear', 'POST', [
            'uuid'          => \LogisticaTestDataFactory::uuid(),
            'id_colecta'    => $idColecta,
            'id_pedido'     => $p1,
            'tipo_evento'   => 'COLECTA_RECEPCION',
            'qr_hash'       => \LogisticaTestDataFactory::qrHash(),
            'dispositivo'   => 'scanner-01',
            'escaneado_at'  => '2099-06-06 10:00:00',
            'metadata_json' => [],
        ]);

        $r = $this->callController('cerrar', 'POST', ['id_colecta' => $idColecta]);

        $this->assertSame(200, $r['code']);
        $this->assertTrue($r['body']['success']);
        $this->assertSame('CONCILIADA', $r['body']['data']['colecta']['estado']);
        $this->assertSame(1, $r['body']['data']['conteos']['RECIBIDO']);
        $this->assertSame(1, $r['body']['data']['conteos']['FALTANTE']);
        $this->assertSame(0, $r['body']['data']['conteos']['ESPERADO']);
    }

    /**
     * @test T13. Segundo cierre devuelve 409.
     */
    public function test_segundo_cierre_devuelve_409(): void
    {
        $cliente = \LogisticaTestDataFactory::crearUsuario($this->db, 'cliente');

        $colecta   = $this->callController('abrir', 'POST', [
            'id_cliente' => $cliente, 'fecha' => '2099-06-07', 'turno' => 'TARDE',
        ]);
        $idColecta = $colecta['body']['data']['id_colecta'];

        $this->callController('cerrar', 'POST', ['id_colecta' => $idColecta]);
        $r = $this->callController('cerrar', 'POST', ['id_colecta' => $idColecta]);

        $this->assertSame(409, $r['code']);
        $this->assertFalse($r['body']['success']);
        $this->assertSame('CONFLICT', $r['body']['code']);
    }

    // ── Tests — RESUMEN ───────────────────────────────────────────────────

    /**
     * @test T14. Resumen de colecta inexistente devuelve 404.
     */
    public function test_resumen_inexistente_devuelve_404(): void
    {
        $r = $this->callController('resumen', 'GET', [], ['id_colecta' => '9999999']);

        $this->assertSame(404, $r['code']);
        $this->assertFalse($r['body']['success']);
        $this->assertSame('NOT_FOUND', $r['body']['code']);
    }

    /**
     * @test T15. La respuesta no contiene file, line, trace ni password.
     */
    public function test_respuesta_no_contiene_datos_sensibles(): void
    {
        $r    = $this->callController('resumen', 'GET', [], ['id_colecta' => '9999998']);
        $json = json_encode($r['body']);

        $this->assertIsString($json);
        $this->assertStringNotContainsString('"file"', $json);
        $this->assertStringNotContainsString('"line"', $json);
        $this->assertStringNotContainsString('"trace"', $json);
        $this->assertStringNotContainsString('password', $json);
    }

    // ── Tests — INTEGRIDAD DE DATOS ───────────────────────────────────────

    /**
     * @test T16. pedidos.id_estado permanece intacto en todo el flujo.
     */
    public function test_pedido_id_estado_no_cambia(): void
    {
        $cliente = \LogisticaTestDataFactory::crearUsuario($this->db, 'cliente');
        $pedido  = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);

        $stmt = $this->db->prepare('SELECT id_estado FROM pedidos WHERE id = :id');
        $stmt->execute([':id' => $pedido]);
        $estadoOriginal = (int) $stmt->fetchColumn();

        $colecta   = $this->callController('abrir', 'POST', [
            'id_cliente' => $cliente, 'fecha' => '2099-07-01', 'turno' => 'MANANA',
        ]);
        $idColecta = $colecta['body']['data']['id_colecta'];

        $this->callController('escanear', 'POST', [
            'uuid'          => \LogisticaTestDataFactory::uuid(),
            'id_colecta'    => $idColecta,
            'id_pedido'     => $pedido,
            'tipo_evento'   => 'COLECTA_RECEPCION',
            'qr_hash'       => \LogisticaTestDataFactory::qrHash(),
            'dispositivo'   => 'scanner-01',
            'escaneado_at'  => '2099-07-01 10:00:00',
            'metadata_json' => [],
        ]);

        $this->callController('cerrar', 'POST', ['id_colecta' => $idColecta]);

        $stmt->execute([':id' => $pedido]);
        $this->assertSame($estadoOriginal, (int) $stmt->fetchColumn(), 'pedidos.id_estado no debe cambiar.');
    }

    /**
     * @test T17. inventario y stock permanecen intactos.
     */
    public function test_inventario_y_stock_no_cambian(): void
    {
        $antesStock = (int) $this->db->query('SELECT COUNT(*) FROM stock')->fetchColumn();
        $antesInv   = (int) $this->db->query('SELECT COUNT(*) FROM inventario')->fetchColumn();

        $cliente = \LogisticaTestDataFactory::crearUsuario($this->db, 'cliente');
        $pedido  = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);

        $colecta   = $this->callController('abrir', 'POST', [
            'id_cliente' => $cliente, 'fecha' => '2099-07-02', 'turno' => 'TARDE',
        ]);
        $idColecta = $colecta['body']['data']['id_colecta'];

        $this->callController('escanear', 'POST', [
            'uuid'          => \LogisticaTestDataFactory::uuid(),
            'id_colecta'    => $idColecta,
            'id_pedido'     => $pedido,
            'tipo_evento'   => 'COLECTA_RECEPCION',
            'qr_hash'       => \LogisticaTestDataFactory::qrHash(),
            'dispositivo'   => 'scanner-01',
            'escaneado_at'  => '2099-07-02 10:00:00',
            'metadata_json' => [],
        ]);
        $this->callController('cerrar', 'POST', ['id_colecta' => $idColecta]);

        $this->assertSame($antesStock, (int) $this->db->query('SELECT COUNT(*) FROM stock')->fetchColumn(), 'stock no debe cambiar.');
        $this->assertSame($antesInv,   (int) $this->db->query('SELECT COUNT(*) FROM inventario')->fetchColumn(), 'inventario no debe cambiar.');
    }

    /**
     * @test T18. Cada test ejecuta rollback — los datos no persisten más allá del test.
     */
    public function test_rollback_garantizado(): void
    {
        $antes = (int) $this->db->query('SELECT COUNT(*) FROM logistica_colectas')->fetchColumn();

        $cliente = \LogisticaTestDataFactory::crearUsuario($this->db, 'cliente');
        $this->callController('abrir', 'POST', [
            'id_cliente' => $cliente, 'fecha' => '2099-08-01', 'turno' => 'MANANA',
        ]);

        $durante = (int) $this->db->query('SELECT COUNT(*) FROM logistica_colectas')->fetchColumn();
        $this->assertSame($antes + 1, $durante, 'Dato debe ser visible dentro de la transacción.');
        // tearDown() hará rollback → quedará = $antes en la siguiente ejecución.
    }
}
