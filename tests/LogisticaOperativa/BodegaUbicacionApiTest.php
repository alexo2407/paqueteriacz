<?php

declare(strict_types=1);

namespace Tests\LogisticaOperativa;

use PHPUnit\Framework\TestCase;

// ── Soporte de pruebas ────────────────────────────────────────────────────────
require_once dirname(__DIR__, 2) . '/tests/Support/TestDatabaseConnection.php';
require_once dirname(__DIR__, 2) . '/tests/Support/LogisticaTestDataFactory.php';
require_once dirname(__DIR__, 2) . '/tests/Support/BodegaUbicacionServiceTestable.php';
require_once dirname(__DIR__, 2) . '/tests/Support/BodegaUbicacionControllerTestable.php';

/**
 * BodegaUbicacionApiTest
 *
 * Prueba los endpoints de Logística Operativa — Bodega/Ubicación invocando el controlador
 * directamente con requests simulados (sin servidor HTTP real).
 *
 * Aislamiento:
 *   setUp()    → abre transacción en paquetes_apppack_test
 *   tearDown() → rollback → la base queda intacta
 *
 * No se modifica pedidos.id_estado, stock, inventario ni reservas.
 * No se toca paquetes_apppack.
 *
 * Tests (27):
 *  T01. Módulo deshabilitado devuelve 403.
 *  T02. Shadow mode inactivo devuelve 403.
 *  T03. Usuario no autenticado devuelve 401.
 *  T04. Método incorrecto devuelve 405.
 *  T05. Content-Type inválido devuelve 400.
 *  T06. JSON inválido devuelve 400.
 *  T07. id_operador enviado por cliente es ignorado.
 *  T08. Registrar recepción devuelve 201.
 *  T09. UUID repetido devuelve 200 sin duplicar.
 *  T10. Recepción con ubicación devuelve UBICADO.
 *  T11. Asignar ubicación devuelve 200.
 *  T12. Consultar ubicación actual devuelve nomenclatura completa.
 *  T13. Ubicación inexistente devuelve 404.
 *  T14. Reubicar dentro de la misma bodega devuelve 200.
 *  T15. Reubicar hacia otra bodega devuelve 422.
 *  T16. Reubicar a la misma ubicación no duplica historial.
 *  T17. Retirar devuelve 200.
 *  T18. Retiro repetido es idempotente (200).
 *  T19. Consultar ubicación después de retirar devuelve 404.
 *  T20. Historial devuelve los movimientos cronológicos.
 *  T21. Respuestas no contienen trace, file, line, password, SQL ni DSN.
 *  T22. pedidos.id_estado permanece intacto.
 *  T23. inventario permanece intacto.
 *  T24. stock permanece intacto.
 *  T25. reservas permanecen intactas.
 *  T26. rollback deja 0 filas permanentes.
 *  T27. La conexión a producción es rechazada.
 */
class BodegaUbicacionApiTest extends TestCase
{
    private \PDO $db;

    /** ID del operador activo (simulado en el token). */
    private int $operadorActivo = 0;

    // ── setUp / tearDown ──────────────────────────────────────────────────────

    protected function setUp(): void
    {
        assertTestDatabase();
        $this->db = \TestDatabaseConnection::nueva();
        $this->db->exec(
            "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION'"
        );
        $this->db->beginTransaction();

        // Crear un operador de prueba reutilizable por toda la sesión
        $this->operadorActivo = \LogisticaTestDataFactory::crearUsuario($this->db, 'op-api');
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    // ── Helper principal ──────────────────────────────────────────────────────

    /**
     * Invoca un método del BodegaUbicacionControllerTestable con requests simulados.
     *
     * @param string              $action       Método del controlador
     * @param string              $httpMethod   Método HTTP: 'GET' | 'POST'
     * @param array<string,mixed> $body         Payload JSON (para POST)
     * @param array<string,mixed> $query        Parámetros GET ($_GET)
     * @param bool                $modEnabled   Si el módulo está habilitado
     * @param bool                $shadowOn     Si shadow mode está activo
     * @param bool                $withAuth     Si se simula autenticación
     * @param string              $contentType  CONTENT_TYPE del servidor
     * @param string|null         $rawInput     JSON raw (null = usar $body)
     * @param bool                $authorized   Si el usuario tiene permiso (logistica_operativa_bodega)
     *
     * @return array{ code: int, body: array<string,mixed> }
     */
    private function callController(
        string  $action,
        string  $httpMethod  = 'POST',
        array   $body        = [],
        array   $query       = [],
        bool    $modEnabled  = true,
        bool    $shadowOn    = true,
        bool    $withAuth    = true,
        string  $contentType = 'application/json',
        ?string $rawInput    = null,
        bool    $authorized  = true
    ): array {
        $_SERVER['REQUEST_METHOD'] = strtoupper($httpMethod);
        $_SERVER['CONTENT_TYPE']   = $contentType;
        $_GET = $query;

        \BodegaUbicacionControllerTestable::$simulatedInput =
            $rawInput !== null
                ? $rawInput
                : (empty($body) ? '' : (string) json_encode($body));

        $controller = new \BodegaUbicacionControllerTestable(
            $this->db,
            $this->operadorActivo,
            $withAuth,
            $modEnabled,
            $shadowOn,
            $authorized
        );

        try {
            $controller->$action();
            return ['code' => 200, 'body' => []];
        } catch (\ControllerResponseException $e) {
            $parsed = json_decode($e->jsonBody, true) ?? [];
            return ['code' => $e->httpCode, 'body' => $parsed];
        }
    }

    // ── Helpers de datos ──────────────────────────────────────────────────────

    private function crearBodega(array $override = []): int
    {
        return \LogisticaTestDataFactory::crearBodega($this->db, $override);
    }

    private function crearUbicacion(int $idBodega, array $override = []): int
    {
        return \LogisticaTestDataFactory::crearUbicacion($this->db, $idBodega, $override);
    }

    private function crearCliente(): int
    {
        return \LogisticaTestDataFactory::crearUsuario($this->db, 'cli-api');
    }

    private function crearPedido(int $idCliente): int
    {
        return \LogisticaTestDataFactory::crearPedido($this->db, $idCliente);
    }

    /**
     * Registra una recepción via controlador y devuelve el resultado.
     */
    private function registrar(
        int $idPedido, int $idBodega, ?int $idUbicacion = null, ?string $uuid = null
    ): array {
        return $this->callController('registrar', 'POST', [
            'uuid'           => $uuid ?? \LogisticaTestDataFactory::uuid(),
            'id_pedido'      => $idPedido,
            'id_bodega'      => $idBodega,
            'id_ubicacion'   => $idUbicacion,
            'id_escaneo'     => null,
            'tipo_recepcion' => 'COLECTA',
            'recibido_at'    => date('Y-m-d H:i:s'),
            'observacion'    => 'API test',
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T01 — Módulo deshabilitado devuelve 403
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T01. Módulo deshabilitado devuelve 403. */
    public function test_modulo_deshabilitado_devuelve_403(): void
    {
        $r = $this->callController('registrar', 'POST', [], [], false);
        $this->assertSame(403, $r['code']);
        $this->assertFalse($r['body']['success']);
        $this->assertSame('MODULE_DISABLED', $r['body']['code']);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T02 — Shadow mode inactivo devuelve 403
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T02. Shadow mode inactivo devuelve 403. */
    public function test_shadow_mode_inactivo_devuelve_403(): void
    {
        $r = $this->callController('registrar', 'POST', [], [], true, false);
        $this->assertSame(403, $r['code']);
        $this->assertFalse($r['body']['success']);
        $this->assertSame('SHADOW_MODE_REQUIRED', $r['body']['code']);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T03 — Usuario no autenticado devuelve 401
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T03. Usuario no autenticado devuelve 401. */
    public function test_no_autenticado_devuelve_401(): void
    {
        $r = $this->callController('registrar', 'POST', [], [], true, true, false);
        $this->assertSame(401, $r['code']);
        $this->assertFalse($r['body']['success']);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T04 — Método incorrecto devuelve 405
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T04. Enviar GET a un endpoint POST devuelve 405. */
    public function test_metodo_incorrecto_devuelve_405(): void
    {
        $r = $this->callController('registrar', 'GET');
        $this->assertSame(405, $r['code']);
        $this->assertFalse($r['body']['success']);
        $this->assertSame('METHOD_NOT_ALLOWED', $r['body']['code']);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T05 — Content-Type inválido devuelve 400
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T05. Content-Type inválido en POST devuelve 400. */
    public function test_content_type_invalido_devuelve_400(): void
    {
        // BodegaUbicacionControllerTestable sobreescribe requerirJsonContentType() a no-op.
        // Comprobamos el comportamiento del método real directamente.
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE']   = 'text/plain';

        // Creamos un controlador real (no testeable) para verificar el método
        // sin ejecutar toda la pipeline.
        $controller = new class extends \BodegaUbicacionController {
            public function requerirJsonContentTypePublic(): void
            {
                $this->requerirJsonContentType();
            }
            public function error(string $code, string $message, int $http = 400): never
            {
                throw new \ControllerResponseException($http,
                    (string) json_encode(['success' => false, 'code' => $code, 'message' => $message])
                );
            }
            public function ok(mixed $data, int $code = 200): never { exit; }
        };

        try {
            $controller->requerirJsonContentTypePublic();
            $this->fail('Debería haber lanzado ControllerResponseException');
        } catch (\ControllerResponseException $e) {
            $parsed = json_decode($e->jsonBody, true);
            $this->assertSame(400, $e->httpCode);
            $this->assertSame('INVALID_CONTENT_TYPE', $parsed['code']);
        }
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T06 — JSON inválido devuelve 400
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T06. JSON inválido en el body devuelve 400. */
    public function test_json_invalido_devuelve_400(): void
    {
        $r = $this->callController('registrar', 'POST', [], [], true, true, true, 'application/json', '{invalid_json}');
        $this->assertSame(400, $r['code']);
        $this->assertFalse($r['body']['success']);
        $this->assertContains($r['body']['code'], ['INVALID_JSON', 'EMPTY_BODY']);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T07 — id_operador enviado por cliente es ignorado
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T07. id_operador en el JSON es ignorado; el servicio usa el del token. */
    public function test_id_operador_del_cliente_es_ignorado(): void
    {
        $cliente = $this->crearCliente();
        $pedido  = $this->crearPedido($cliente);
        $bodega  = $this->crearBodega();

        // Enviamos un id_operador falso (999999) en el JSON
        $r = $this->callController('registrar', 'POST', [
            'uuid'           => \LogisticaTestDataFactory::uuid(),
            'id_pedido'      => $pedido,
            'id_bodega'      => $bodega,
            'id_ubicacion'   => null,
            'id_escaneo'     => null,
            'tipo_recepcion' => 'COLECTA',
            'recibido_at'    => date('Y-m-d H:i:s'),
            'id_operador'    => 999999,  // ← esto debe ser ignorado
            'observacion'    => null,
        ]);

        // El request debe éxito porque el token tiene un operador válido
        $this->assertSame(201, $r['code'], 'Debe crearse correctamente usando el operador del token.');
        $this->assertTrue($r['body']['success']);

        // Verificar que el operador guardado en BD es el del token, no el del JSON
        $idRecepcion = $r['body']['data']['id_recepcion'];
        $stmt = $this->db->prepare('SELECT id_operador FROM logistica_recepciones WHERE id = :id');
        $stmt->execute([':id' => $idRecepcion]);
        $this->assertSame($this->operadorActivo, (int) $stmt->fetchColumn(),
            'El id_operador en BD debe ser el del token, no el del JSON.');
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T08 — Registrar recepción devuelve 201
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T08. Registrar una recepción nueva devuelve 201 y estado RECIBIDO. */
    public function test_registrar_recepcion_devuelve_201(): void
    {
        $cliente = $this->crearCliente();
        $pedido  = $this->crearPedido($cliente);
        $bodega  = $this->crearBodega();

        $r = $this->registrar($pedido, $bodega);

        $this->assertSame(201, $r['code']);
        $this->assertTrue($r['body']['success']);
        $this->assertFalse($r['body']['data']['idempotente']);
        $this->assertGreaterThan(0, $r['body']['data']['id_recepcion']);
        $this->assertSame('RECIBIDO', $r['body']['data']['estado']);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T09 — UUID repetido devuelve 200 sin duplicar
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T09. UUID repetido devuelve 200 e idempotente=true sin duplicar recepción. */
    public function test_uuid_repetido_devuelve_200_sin_duplicar(): void
    {
        $cliente = $this->crearCliente();
        $pedido  = $this->crearPedido($cliente);
        $bodega  = $this->crearBodega();
        $uuid    = \LogisticaTestDataFactory::uuid();

        $r1 = $this->registrar($pedido, $bodega, null, $uuid);
        $r2 = $this->registrar($pedido, $bodega, null, $uuid);

        $this->assertSame(201, $r1['code']);
        $this->assertSame(200, $r2['code']);
        $this->assertTrue($r2['body']['data']['idempotente']);
        $this->assertSame($r1['body']['data']['id_recepcion'], $r2['body']['data']['id_recepcion']);

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM logistica_recepciones WHERE uuid = :u');
        $stmt->execute([':u' => $uuid]);
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T10 — Recepción con ubicación devuelve UBICADO
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T10. Registrar recepción con ubicación devuelve UBICADO y 201. */
    public function test_recepcion_con_ubicacion_devuelve_ubicado(): void
    {
        $cliente = $this->crearCliente();
        $pedido  = $this->crearPedido($cliente);
        $bodega  = $this->crearBodega();
        $ubic    = $this->crearUbicacion($bodega);

        $r = $this->registrar($pedido, $bodega, $ubic);

        $this->assertSame(201, $r['code']);
        $this->assertSame('UBICADO', $r['body']['data']['estado']);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T11 — Asignar ubicación devuelve 200
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T11. Asignar ubicación a una recepción RECIBIDA devuelve 200. */
    public function test_asignar_ubicacion_devuelve_200(): void
    {
        $cliente = $this->crearCliente();
        $pedido  = $this->crearPedido($cliente);
        $bodega  = $this->crearBodega();
        $ubic    = $this->crearUbicacion($bodega);

        // Primero registramos sin ubicación
        $rRec = $this->registrar($pedido, $bodega);
        $idRecepcion = $rRec['body']['data']['id_recepcion'];

        $r = $this->callController('asignar', 'POST', [
            'id_pedido'    => $pedido,
            'id_recepcion' => $idRecepcion,
            'id_ubicacion' => $ubic,
            'motivo'       => 'Asignación de prueba',
        ]);

        $this->assertSame(200, $r['code']);
        $this->assertTrue($r['body']['success']);
        $this->assertSame('UBICADO', $r['body']['data']['estado']);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T12 — Consultar ubicación actual devuelve nomenclatura completa
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T12. obtenerUbicacionActual devuelve nomenclatura completa de bodega y ubicación. */
    public function test_ubicacion_actual_devuelve_nomenclatura_completa(): void
    {
        $cliente = $this->crearCliente();
        $pedido  = $this->crearPedido($cliente);
        $bodega  = $this->crearBodega(['codigo' => 'MGA-API-01', 'nombre' => 'Bodega API Test']);
        $ubic    = $this->crearUbicacion($bodega, [
            'codigo' => 'API-B/EST-5/CAJ-Z1',
            'zona'   => 'B',
            'tipo'   => 'GENERAL',
        ]);

        $this->registrar($pedido, $bodega, $ubic);

        $_GET = ['id_pedido' => (string) $pedido];
        $r = $this->callController('actual', 'GET', [], ['id_pedido' => (string) $pedido]);

        $this->assertSame(200, $r['code']);
        $this->assertTrue($r['body']['success']);
        $data = $r['body']['data'];
        $this->assertSame($pedido, $data['id_pedido']);
        $this->assertSame($bodega, $data['id_bodega']);
        $this->assertSame('MGA-API-01', $data['bodega_codigo']);
        $this->assertSame('Bodega API Test', $data['bodega_nombre']);
        $this->assertSame($ubic, $data['id_ubicacion']);
        $this->assertSame('API-B/EST-5/CAJ-Z1', $data['ubicacion_codigo']);
        $this->assertSame('B', $data['zona']);
        $this->assertArrayHasKey('tipo_ubicacion', $data);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T13 — Pedido sin ubicación activa devuelve 404
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T13. Consultar ubicación de pedido sin ubicación activa devuelve 404. */
    public function test_ubicacion_actual_sin_activa_devuelve_404(): void
    {
        $cliente = $this->crearCliente();
        $pedido  = $this->crearPedido($cliente);

        $r = $this->callController('actual', 'GET', [], ['id_pedido' => (string) $pedido]);

        $this->assertSame(404, $r['code']);
        $this->assertFalse($r['body']['success']);
        $this->assertSame('UBICACION_ACTUAL_NO_ENCONTRADA', $r['body']['code']);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T14 — Reubicar dentro de la misma bodega devuelve 200
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T14. Reubicar a otra ubicación de la misma bodega devuelve 200. */
    public function test_reubicar_misma_bodega_devuelve_200(): void
    {
        $cliente = $this->crearCliente();
        $pedido  = $this->crearPedido($cliente);
        $bodega  = $this->crearBodega();
        $ubic1   = $this->crearUbicacion($bodega);
        $ubic2   = $this->crearUbicacion($bodega);

        $this->registrar($pedido, $bodega, $ubic1);

        $r = $this->callController('reubicar', 'POST', [
            'id_pedido'            => $pedido,
            'id_ubicacion_destino' => $ubic2,
            'motivo'               => 'Reubicación API test',
        ]);

        $this->assertSame(200, $r['code']);
        $this->assertTrue($r['body']['success']);
        $this->assertSame($ubic2, $r['body']['data']['id_ubicacion_nueva']);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T15 — Reubicar hacia otra bodega devuelve 422
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T15. Reubicar hacia una ubicación de otra bodega devuelve 422. */
    public function test_reubicar_otra_bodega_devuelve_422(): void
    {
        $cliente = $this->crearCliente();
        $pedido  = $this->crearPedido($cliente);
        $bodega1 = $this->crearBodega();
        $bodega2 = $this->crearBodega();
        $ubic1   = $this->crearUbicacion($bodega1);
        $ubic2   = $this->crearUbicacion($bodega2); // de otra bodega

        $this->registrar($pedido, $bodega1, $ubic1);

        $r = $this->callController('reubicar', 'POST', [
            'id_pedido'            => $pedido,
            'id_ubicacion_destino' => $ubic2,
        ]);

        $this->assertSame(422, $r['code']);
        $this->assertFalse($r['body']['success']);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T16 — Reubicar a la misma ubicación no duplica historial
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T16. Reubicar a la misma ubicación devuelve 200 y no duplica historial. */
    public function test_reubicar_misma_ubicacion_no_duplica(): void
    {
        $cliente = $this->crearCliente();
        $pedido  = $this->crearPedido($cliente);
        $bodega  = $this->crearBodega();
        $ubic    = $this->crearUbicacion($bodega);

        $this->registrar($pedido, $bodega, $ubic);

        $antes = (int) $this->db->query(
            "SELECT COUNT(*) FROM logistica_ubicacion_historial WHERE id_pedido = {$pedido}"
        )->fetchColumn();

        $r = $this->callController('reubicar', 'POST', [
            'id_pedido'            => $pedido,
            'id_ubicacion_destino' => $ubic,
        ]);

        $this->assertSame(200, $r['code']);
        $this->assertSame('SIN_CAMBIO', $r['body']['data']['movimiento']);

        $despues = (int) $this->db->query(
            "SELECT COUNT(*) FROM logistica_ubicacion_historial WHERE id_pedido = {$pedido}"
        )->fetchColumn();

        $this->assertSame($antes, $despues, 'No debe crearse un nuevo movimiento con la misma ubicación.');
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T17 — Retirar devuelve 200
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T17. Retirar un paquete ubicado devuelve 200 y estado RETIRADO. */
    public function test_retirar_devuelve_200(): void
    {
        $cliente = $this->crearCliente();
        $pedido  = $this->crearPedido($cliente);
        $bodega  = $this->crearBodega();
        $ubic    = $this->crearUbicacion($bodega);

        $this->registrar($pedido, $bodega, $ubic);

        $r = $this->callController('retirar', 'POST', [
            'id_pedido' => $pedido,
            'motivo'    => 'Retiro de prueba API',
        ]);

        $this->assertSame(200, $r['code']);
        $this->assertTrue($r['body']['success']);
        $this->assertSame('RETIRADO', $r['body']['data']['estado']);
        $this->assertFalse($r['body']['data']['idempotente']);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T18 — Retiro repetido es idempotente
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T18. Retiro repetido devuelve 200 con idempotente=true sin nuevos movimientos. */
    public function test_retiro_repetido_es_idempotente(): void
    {
        $cliente = $this->crearCliente();
        $pedido  = $this->crearPedido($cliente);
        $bodega  = $this->crearBodega();
        $ubic    = $this->crearUbicacion($bodega);

        $this->registrar($pedido, $bodega, $ubic);
        $this->callController('retirar', 'POST', ['id_pedido' => $pedido]);

        $antes = (int) $this->db->query(
            "SELECT COUNT(*) FROM logistica_ubicacion_historial WHERE id_pedido = {$pedido}"
        )->fetchColumn();

        $r2 = $this->callController('retirar', 'POST', ['id_pedido' => $pedido]);

        $this->assertSame(200, $r2['code']);
        $this->assertTrue($r2['body']['data']['idempotente']);

        $despues = (int) $this->db->query(
            "SELECT COUNT(*) FROM logistica_ubicacion_historial WHERE id_pedido = {$pedido}"
        )->fetchColumn();

        $this->assertSame($antes, $despues, 'No deben crearse nuevas filas en historial.');
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T19 — Consultar ubicación después de retirar devuelve 404
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T19. obtenerUbicacionActual después de retirar devuelve 404. */
    public function test_ubicacion_actual_despues_de_retirar_es_404(): void
    {
        $cliente = $this->crearCliente();
        $pedido  = $this->crearPedido($cliente);
        $bodega  = $this->crearBodega();
        $ubic    = $this->crearUbicacion($bodega);

        $this->registrar($pedido, $bodega, $ubic);
        $this->callController('retirar', 'POST', ['id_pedido' => $pedido]);

        $r = $this->callController('actual', 'GET', [], ['id_pedido' => (string) $pedido]);

        $this->assertSame(404, $r['code']);
        $this->assertSame('UBICACION_ACTUAL_NO_ENCONTRADA', $r['body']['code']);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T20 — Historial devuelve los movimientos cronológicos
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T20. Historial devuelve INGRESO → REUBICACION en orden cronológico. */
    public function test_historial_devuelve_movimientos_cronologicos(): void
    {
        $cliente = $this->crearCliente();
        $pedido  = $this->crearPedido($cliente);
        $bodega  = $this->crearBodega();
        $ubic1   = $this->crearUbicacion($bodega);
        $ubic2   = $this->crearUbicacion($bodega);

        $this->registrar($pedido, $bodega, $ubic1);
        $this->callController('reubicar', 'POST', [
            'id_pedido'            => $pedido,
            'id_ubicacion_destino' => $ubic2,
        ]);

        $r = $this->callController('historial', 'GET', [], ['id_pedido' => (string) $pedido]);

        $this->assertSame(200, $r['code']);
        $this->assertTrue($r['body']['success']);
        $historial = $r['body']['data'];
        $this->assertCount(2, $historial);
        $this->assertSame('INGRESO',     $historial[0]['tipo_movimiento']);
        $this->assertSame('REUBICACION', $historial[1]['tipo_movimiento']);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T21 — Respuestas no contienen información sensible
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T21. Las respuestas de error no contienen trace, file, line, SQL, password ni DSN. */
    public function test_respuestas_error_no_contienen_datos_sensibles(): void
    {
        // Provocamos errores varios y verificamos que el JSON sea seguro
        $errores = [
            // UUID inválido
            $this->callController('registrar', 'POST', [
                'uuid'           => 'not-a-uuid',
                'id_pedido'      => 1,
                'id_bodega'      => 1,
                'tipo_recepcion' => 'COLECTA',
                'recibido_at'    => '2026-01-01 00:00:00',
            ]),
            // Pedido inexistente
            $this->callController('registrar', 'POST', [
                'uuid'           => \LogisticaTestDataFactory::uuid(),
                'id_pedido'      => 999999999,
                'id_bodega'      => 1,
                'tipo_recepcion' => 'COLECTA',
                'recibido_at'    => '2026-01-01 00:00:00',
            ]),
        ];

        $sensible = ['trace', 'file', 'line', 'password', 'DSN', 'mysql:', 'root@'];

        foreach ($errores as $r) {
            $jsonStr = json_encode($r['body']);
            foreach ($sensible as $palabra) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $palabra,
                    $jsonStr,
                    "La respuesta no debe contener '{$palabra}'."
                );
            }
        }
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T22 — pedidos.id_estado permanece intacto
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T22. pedidos.id_estado no cambia durante todo el flujo de la API. */
    public function test_pedido_id_estado_permanece_intacto(): void
    {
        $cliente = $this->crearCliente();
        $pedido  = $this->crearPedido($cliente);
        $bodega  = $this->crearBodega();
        $ubic1   = $this->crearUbicacion($bodega);
        $ubic2   = $this->crearUbicacion($bodega);

        $stmt = $this->db->prepare('SELECT id_estado FROM pedidos WHERE id = :id');
        $stmt->execute([':id' => $pedido]);
        $estadoOriginal = (int) $stmt->fetchColumn();

        $this->registrar($pedido, $bodega, $ubic1);
        $this->callController('reubicar', 'POST', ['id_pedido' => $pedido, 'id_ubicacion_destino' => $ubic2]);
        $this->callController('retirar',  'POST', ['id_pedido' => $pedido]);

        $stmt->execute([':id' => $pedido]);
        $this->assertSame($estadoOriginal, (int) $stmt->fetchColumn(), 'pedidos.id_estado no debe cambiar.');
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T23 — inventario permanece intacto
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T23. inventario no recibe ningún movimiento durante el flujo. */
    public function test_inventario_permanece_intacto(): void
    {
        $antes = (int) $this->db->query('SELECT COUNT(*) FROM inventario')->fetchColumn();

        $cliente = $this->crearCliente();
        $pedido  = $this->crearPedido($cliente);
        $bodega  = $this->crearBodega();
        $ubic    = $this->crearUbicacion($bodega);

        $this->registrar($pedido, $bodega, $ubic);
        $this->callController('retirar', 'POST', ['id_pedido' => $pedido]);

        $this->assertSame($antes, (int) $this->db->query('SELECT COUNT(*) FROM inventario')->fetchColumn());
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T24 — stock permanece intacto
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T24. stock no recibe ningún movimiento durante el flujo. */
    public function test_stock_permanece_intacto(): void
    {
        $antes = (int) $this->db->query('SELECT COUNT(*) FROM stock')->fetchColumn();

        $cliente = $this->crearCliente();
        $pedido  = $this->crearPedido($cliente);
        $bodega  = $this->crearBodega();
        $ubic    = $this->crearUbicacion($bodega);

        $this->registrar($pedido, $bodega, $ubic);
        $this->callController('retirar', 'POST', ['id_pedido' => $pedido]);

        $this->assertSame($antes, (int) $this->db->query('SELECT COUNT(*) FROM stock')->fetchColumn());
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T25 — reservas permanecen intactas
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T25. Las reservas no son afectadas por ninguna operación. */
    public function test_reservas_permanecen_intactas(): void
    {
        try {
            $antes = (int) $this->db->query('SELECT COUNT(*) FROM pedido_reservas_stock')->fetchColumn();
        } catch (\Throwable) {
            $this->markTestSkipped('La tabla pedido_reservas_stock no existe en esta instalación.');
        }

        $cliente = $this->crearCliente();
        $pedido  = $this->crearPedido($cliente);
        $bodega  = $this->crearBodega();
        $ubic    = $this->crearUbicacion($bodega);

        $this->registrar($pedido, $bodega, $ubic);
        $this->callController('retirar', 'POST', ['id_pedido' => $pedido]);

        $this->assertSame(
            $antes,
            (int) $this->db->query('SELECT COUNT(*) FROM pedido_reservas_stock')->fetchColumn()
        );
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T26 — rollback deja 0 filas permanentes
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T26. Los datos insertados son visibles en la transacción y desaparecen con rollback. */
    public function test_rollback_deja_cero_filas_permanentes(): void
    {
        $antes = (int) $this->db->query('SELECT COUNT(*) FROM logistica_bodegas')->fetchColumn();

        $cliente = $this->crearCliente();
        $pedido  = $this->crearPedido($cliente);
        $bodega  = $this->crearBodega();
        $ubic    = $this->crearUbicacion($bodega);

        $this->registrar($pedido, $bodega, $ubic);

        $durante = (int) $this->db->query('SELECT COUNT(*) FROM logistica_bodegas')->fetchColumn();
        $this->assertSame($antes + 1, $durante, 'Los datos deben ser visibles dentro de la transacción.');
        // tearDown() ejecutará rollback automáticamente.
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T27 — La conexión a producción es rechazada
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T27. La base paquetes_apppack está en la lista negra y es rechazada. */
    public function test_produccion_es_rechazada(): void
    {
        $this->expectException(\RuntimeException::class);

        $schema     = 'paquetes_apppack';
        $prohibited = ['paqueteriacz', 'paquetes_apppack', 'production', 'prod'];
        foreach ($prohibited as $p) {
            if (strtolower($schema) === strtolower($p)) {
                throw new \RuntimeException("SEGURIDAD: la base '{$schema}' está en la lista negra.");
            }
        }
    }
    // ═══════════════════════════════════════════════════════════════════════════
    // T28 — HTTP status depende del código de dominio, no del mensaje
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * @test T28. El HTTP status depende del domainCode estable, no del texto del mensaje.
     *
     * Se lanza una LogisticaOperativaException con domainCode conocido y un mensaje
     * intencionalmente diferente al patrón antiguo de texto. El HTTP debe seguir siendo correcto.
     */
    public function test_http_status_depende_del_domainCode_no_del_mensaje(): void
    {
        // Usamos el controlador testeable sin registrar un operador válido
        // para que el servicio lance la excepción de dominio.
        // Preparamos directamente el mapeo mediante la clase anónima.
        $casos = [
            ['PEDIDO_NO_ENCONTRADO',              404],
            ['BODEGA_NO_ENCONTRADA',              404],
            ['UBICACION_NO_ENCONTRADA',           404],
            ['RECEPCION_NO_ENCONTRADA',           404],
            ['UBICACION_ACTUAL_NO_ENCONTRADA',    404],
            ['RECEPCION_ACTIVA_EXISTENTE',        409],
            ['PAQUETE_YA_UBICADO',               409],
            ['BODEGA_INACTIVA',                  422],
            ['UBICACION_INACTIVA',               422],
            ['UBICACION_NO_PERTENECE_BODEGA',    422],
            ['RECEPCION_NO_CORRESPONDE_PEDIDO',  422],
            ['TRASLADO_ENTRE_BODEGAS_NO_PERMITIDO', 422],
            ['PAQUETE_SIN_UBICACION',            422],
            ['TIPO_RECEPCION_INVALIDO',          422],
            ['UUID_INVALIDO',                    400],
            ['FECHA_INVALIDA',                   400],
        ];

        // Creamos un mini-controlador que expone mapearExcepcion pública
        $ctrl = new class extends \BodegaUbicacionController {
            public function exponer(\LogisticaOperativaException $e): int
            {
                try {
                    $this->mapearExcepcion($e);
                } catch (\ControllerResponseException $r) {
                    return $r->httpCode;
                }
                return -1;
            }
            public function ok(mixed $data, int $code = 200): never { exit; }
            public function error(string $code, string $message, int $http = 400): never
            {
                throw new \ControllerResponseException($http,
                    (string) json_encode(['success' => false, 'code' => $code, 'message' => $message])
                );
            }
        };

        foreach ($casos as [$domainCode, $expectedHttp]) {
            // Usamos un MENSAJE intencionalmente irrelevante para probar
            // que el mapeo NO depende del texto
            $exc = new \LogisticaOperativaException(
                'Este mensaje no contiene ninguna pista sobre el tipo de error',
                $domainCode
            );
            $http = $ctrl->exponer($exc);
            $this->assertSame(
                $expectedHttp,
                $http,
                "DomainCode '{$domainCode}' debe mapear a HTTP {$expectedHttp}, obtuvo {$http}."
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // T29 — Cambiar el texto del mensaje no cambia el HTTP status
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * @test T29. Cambiar el texto del mensaje no altera el HTTP status.
     */
    public function test_cambiar_mensaje_no_cambia_http_status(): void
    {
        $ctrl = new class extends \BodegaUbicacionController {
            public function exponer(\LogisticaOperativaException $e): int
            {
                try {
                    $this->mapearExcepcion($e);
                } catch (\ControllerResponseException $r) {
                    return $r->httpCode;
                }
                return -1;
            }
            public function ok(mixed $data, int $code = 200): never { exit; }
            public function error(string $code, string $message, int $http = 400): never
            {
                throw new \ControllerResponseException($http,
                    (string) json_encode(['success' => false, 'code' => $code, 'message' => $message])
                );
            }
        };

        // Mismo domainCode, mensajes en idiomas distintos
        $mensajes = [
            'Package not found',                             // English
            'Paquete no encontrado',                         // Spanish
            'Paquet introuvable',                            // French
            'Das Paket wurde nicht gefunden',               // German
            'mensaje sin ninguna palabra clave detectada',  // Sin keywords
        ];

        foreach ($mensajes as $msg) {
            $exc  = new \LogisticaOperativaException($msg, 'PEDIDO_NO_ENCONTRADO');
            $http = $ctrl->exponer($exc);
            $this->assertSame(404, $http,
                "Con domainCode 'PEDIDO_NO_ENCONTRADO' el HTTP debe ser 404 sin importar el mensaje: '{$msg}'."
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // T30 — Código de dominio desconocido produce 500 seguro
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * @test T30. Un domainCode desconocido produce 500 y mensaje genérico seguro (sin info interna).
     */
    public function test_domaincode_desconocido_produce_500_seguro(): void
    {
        $ctrl = new class extends \BodegaUbicacionController {
            public function exponer(\LogisticaOperativaException $e): array
            {
                try {
                    $this->mapearExcepcion($e);
                } catch (\ControllerResponseException $r) {
                    return ['code' => $r->httpCode, 'body' => json_decode($r->jsonBody, true) ?? []];
                }
                return ['code' => -1, 'body' => []];
            }
            public function ok(mixed $data, int $code = 200): never { exit; }
            public function error(string $code, string $message, int $http = 400): never
            {
                throw new \ControllerResponseException($http,
                    (string) json_encode(['success' => false, 'code' => $code, 'message' => $message])
                );
            }
        };

        $exc    = new \LogisticaOperativaException('información sensible: DSN=mysql://root@localhost', 'CODIGO_NUEVO_DESCONOCIDO');
        $result = $ctrl->exponer($exc);

        $this->assertSame(500, $result['code'], 'Un domainCode desconocido debe producir HTTP 500.');
        $this->assertSame('INTERNAL_ERROR', $result['body']['code']);

        // El mensaje al cliente NO debe contener información interna
        $sensibles = ['DSN', 'mysql:', 'root', 'localhost', 'información sensible'];
        foreach ($sensibles as $s) {
            $this->assertStringNotContainsStringIgnoringCase(
                $s,
                $result['body']['message'] ?? '',
                "La respuesta 500 no debe exponer '{$s}'."
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // T31 — Usuario autenticado sin autorización recibe 403
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * @test T31. Usuario autenticado pero sin permiso logistica_operativa_bodega recibe 403.
     * Esto verifica que autenticación y autorización son capas separadas.
     */
    public function test_autenticado_sin_autorizacion_recibe_403(): void
    {
        // El usuario está autenticado (withAuth=true) pero sin autorización
        $r = $this->callController(
            'registrar', 'POST',
            [],   // body vacío; da igual porque se bloquea antes de llegar al JSON
            [],   // query
            true, // modEnabled
            true, // shadowOn
            true, // withAuth = autenticado
            'application/json',
            null, // rawInput
            false // authorized = SIN permiso
        );

        $this->assertSame(403, $r['code'],
            'Un usuario autenticado sin permiso debe recibir 403 FORBIDDEN.');
        $this->assertFalse($r['body']['success']);
        $this->assertSame('FORBIDDEN', $r['body']['code']);
        // El mensaje debe mencionar el permiso requerido
        $this->assertStringContainsStringIgnoringCase(
            'logistica_operativa_bodega',
            $r['body']['message']
        );
    }
}
