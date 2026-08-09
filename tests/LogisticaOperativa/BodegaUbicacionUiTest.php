<?php

declare(strict_types=1);

namespace Tests\LogisticaOperativa;

use PHPUnit\Framework\TestCase;

// ── Soporte de pruebas ────────────────────────────────────────────────────────
require_once dirname(__DIR__, 2) . '/tests/Support/TestDatabaseConnection.php';
require_once dirname(__DIR__, 2) . '/tests/Support/LogisticaTestDataFactory.php';
require_once dirname(__DIR__, 2) . '/tests/Support/BodegaUbicacionServiceTestable.php';
require_once dirname(__DIR__, 2) . '/tests/Support/BodegaUbicacionControllerTestable.php';

// Modelos
require_once dirname(__DIR__, 2) . '/modelo/logistica_operativa/BodegaModel.php';
require_once dirname(__DIR__, 2) . '/modelo/logistica_operativa/UbicacionModel.php';
require_once dirname(__DIR__, 2) . '/modelo/logistica_operativa/RecepcionModel.php';
require_once dirname(__DIR__, 2) . '/services/logistica_operativa/BodegaUbicacionService.php';

/**
 * BodegaUbicacionUiTest
 *
 * Prueba los flujos de la interfaz de bodega invocando el controlador y
 * los modelos directamente (sin servidor HTTP real).
 *
 * Aislamiento:
 *   setUp()    → abre transacción en paquetes_apppack_test
 *   tearDown() → rollback → la base queda intacta
 *
 * Tests (30):
 *
 * ── BÚSQUEDA ──────────────────────────────────────────────────────────────
 *  U01. Buscar pedido por ID lo encuentra en BD.
 *  U02. Buscar pedido por número de orden lo encuentra en BD.
 *  U03. Buscar pedido inexistente devuelve falso.
 *  U04. Teléfono enmascarado no revela el número completo.
 *
 * ── CATÁLOGOS ─────────────────────────────────────────────────────────────
 *  U05. Catálogo de bodegas devuelve solo bodegas activas.
 *  U06. Catálogo de bodegas inactivas no aparece en la lista.
 *  U07. Catálogo de ubicaciones devuelve solo activas de la bodega.
 *  U08. Catálogo de ubicaciones de otra bodega no se mezcla.
 *  U09. Catálogo de ubicaciones con id_bodega 0 no lanza excepción.
 *
 * ── RECEPCIÓN ──────────────────────────────────────────────────────────────
 *  U10. Registrar recepción con bodega válida devuelve 201.
 *  U11. Registrar recepción con ubicación inicial devuelve estado UBICADO.
 *  U12. Registrar recepción con UUID repetido es idempotente (200).
 *  U13. Registrar recepción con bodega inexistente devuelve 404.
 *  U14. Registrar recepción con bodega inactiva devuelve 422.
 *  U15. id_operador del cuerpo es ignorado; siempre se usa el del token.
 *
 * ── ASIGNAR UBICACIÓN ─────────────────────────────────────────────────────
 *  U16. Asignar ubicación devuelve estado UBICADO.
 *  U17. Asignar ubicación de otra bodega devuelve 422.
 *  U18. Asignar sobre recepción ya UBICADA devuelve 409.
 *
 * ── UBICACIÓN ACTUAL ──────────────────────────────────────────────────────
 *  U19. Consultar ubicación actual devuelve id_bodega correcto.
 *  U20. Consultar ubicación de pedido sin recepción activa devuelve 404.
 *
 * ── REUBICAR ──────────────────────────────────────────────────────────────
 *  U21. Reubicar dentro de la misma bodega devuelve 200.
 *  U22. Reubicar a la misma ubicación no genera entrada duplicada.
 *  U23. Reubicar a otra bodega devuelve 422.
 *
 * ── RETIRAR ───────────────────────────────────────────────────────────────
 *  U24. Retirar devuelve 200 y estado RETIRADO.
 *  U25. Retirar dos veces es idempotente (200).
 *  U26. Consultar ubicación después de retirar devuelve 404.
 *
 * ── HISTORIAL ─────────────────────────────────────────────────────────────
 *  U27. Historial devuelve movimientos cronológicos.
 *  U28. Historial vacío devuelve array vacío.
 *
 * ── SEGURIDAD Y RESTRICCIONES ─────────────────────────────────────────────
 *  U29. Las respuestas no contienen trace, file, line, password, SQL ni DSN.
 *  U30. pedidos.id_estado permanece intacto después de todos los flujos.
 */
class BodegaUbicacionUiTest extends TestCase
{
    private \PDO $db;
    private int $operadorActivo = 0;

    // ── setUp / tearDown ──────────────────────────────────────────────────────

    protected function setUp(): void
    {
        assertTestDatabase();
        $this->db = \TestDatabaseConnection::nueva();
        $this->db->exec('START TRANSACTION');

        $this->operadorActivo = \LogisticaTestDataFactory::crearUsuario($this->db, 'opui');
        $this->assertGreaterThan(0, $this->operadorActivo);
    }

    protected function tearDown(): void
    {
        $this->db->exec('ROLLBACK');
    }

    // ── Helpers internos ──────────────────────────────────────────────────────

    /** Crea un controlador testeable autorizado, autenticado y con módulo activo. */
    private function ctrl(
        bool $auth = true,
        bool $modulo = true,
        bool $shadow = true,
        bool $autorizado = true
    ): \BodegaUbicacionControllerTestable {
        return new \BodegaUbicacionControllerTestable(
            $this->db,
            $this->operadorActivo,
            $auth,
            $modulo,
            $shadow,
            $autorizado
        );
    }

    /**
     * Simula una llamada POST al controlador y captura la respuesta JSON.
     * @param string $accion  Nombre del método del controlador (ej: 'registrar').
     * @param array  $payload JSON a enviar.
     * @param array  $options Opciones de entorno (auth, modulo, shadow, autorizado).
     * @return array{http:int, body:array}
     */
    private function post(string $accion, array $payload, array $options = []): array
    {
        $ctrl = $this->ctrl(
            $options['auth']       ?? true,
            $options['modulo']     ?? true,
            $options['shadow']     ?? true,
            $options['autorizado'] ?? true
        );

        \BodegaUbicacionControllerTestable::$simulatedInput = json_encode($payload);
        $_SERVER['REQUEST_METHOD']    = 'POST';
        $_SERVER['CONTENT_TYPE']      = 'application/json';
        $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';

        try {
            $ctrl->{$accion}();
        } catch (\ControllerResponseException $e) {
            return ['http' => $e->httpCode, 'body' => json_decode($e->jsonBody, true)];
        }
        $this->fail("El controlador debería haber lanzado ControllerResponseException en accion={$accion}");
    }

    /**
     * Simula una llamada GET al controlador con query params.
     * @param string $accion
     * @param array  $get     Parámetros GET.
     * @param array  $options
     * @return array{http:int, body:array}
     */
    private function get(string $accion, array $get = [], array $options = []): array
    {
        $ctrl = $this->ctrl(
            $options['auth']       ?? true,
            $options['modulo']     ?? true,
            $options['shadow']     ?? true,
            $options['autorizado'] ?? true
        );

        foreach ($get as $k => $v) {
            $_GET[$k] = $v;
        }
        $_SERVER['REQUEST_METHOD'] = 'GET';

        try {
            $ctrl->{$accion}();
        } catch (\ControllerResponseException $e) {
            foreach (array_keys($get) as $k) {
                unset($_GET[$k]);
            }
            return ['http' => $e->httpCode, 'body' => json_decode($e->jsonBody, true)];
        }
        foreach (array_keys($get) as $k) {
            unset($_GET[$k]);
        }
        $this->fail("El controlador debería haber lanzado ControllerResponseException en accion={$accion}");
    }

    /**
     * Crea datos de prueba: bodega, ubicación, pedido y registra una recepción.
     * Devuelve los IDs para encadenar otras operaciones.
     */
    private function crearRecepcion(): array
    {
        $idBodega    = \LogisticaTestDataFactory::crearBodega($this->db);
        $idUbicacion = \LogisticaTestDataFactory::crearUbicacion($this->db, $idBodega);
        $idCliente   = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli');
        $idPedido    = \LogisticaTestDataFactory::crearPedido($this->db, $idCliente);

        $resp = $this->post('registrar', [
            'uuid'           => \LogisticaTestDataFactory::uuid(),
            'id_pedido'      => $idPedido,
            'id_bodega'      => $idBodega,
            'id_ubicacion'   => null,
            'id_escaneo'     => null,
            'tipo_recepcion' => 'COLECTA',
            'recibido_at'    => date('Y-m-d H:i:s'),
            'observacion'    => 'Prueba UI',
        ]);
        $this->assertSame(201, $resp['http']);

        return [
            'id_bodega'    => $idBodega,
            'id_ubicacion' => $idUbicacion,
            'id_pedido'    => $idPedido,
            'id_recepcion' => $resp['body']['data']['id_recepcion'],
        ];
    }

    /** Asegura que el campo 'data' existe y lo devuelve. */
    private function data(array $resp): array
    {
        $this->assertArrayHasKey('data', $resp['body']);
        return $resp['body']['data'];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ── BÚSQUEDA ──────────────────────────────────────────────────────────────
    // ══════════════════════════════════════════════════════════════════════════

    /** U01. Buscar pedido por ID lo encuentra en BD. */
    public function testU01_buscarPedidoPorIdLoEncuentra(): void
    {
        $idCliente = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli-u01');
        $idPedido  = \LogisticaTestDataFactory::crearPedido($this->db, $idCliente);
        $this->assertGreaterThan(0, $idPedido);

        $stmt = $this->db->prepare('SELECT id FROM pedidos WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $idPedido]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotFalse($row);
        $this->assertSame($idPedido, (int) $row['id']);
    }

    /** U02. Buscar pedido por número de orden lo encuentra en BD. */
    public function testU02_buscarPedidoPorNumeroOrdenLoEncuentra(): void
    {
        $idCliente = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli-u02');
        $idPedido  = \LogisticaTestDataFactory::crearPedido($this->db, $idCliente);

        $stmt = $this->db->prepare('SELECT numero_orden FROM pedidos WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $idPedido]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotFalse($row);

        $stmt2 = $this->db->prepare('SELECT id FROM pedidos WHERE numero_orden = :num LIMIT 1');
        $stmt2->execute([':num' => $row['numero_orden']]);
        $found = $stmt2->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotFalse($found);
        $this->assertSame($idPedido, (int) $found['id']);
    }

    /** U03. Buscar pedido inexistente devuelve false. */
    public function testU03_buscarPedidoInexistenteDevuelveFalse(): void
    {
        $stmt = $this->db->query('SELECT MAX(id) AS max_id FROM pedidos');
        $max  = (int) ($stmt->fetch(\PDO::FETCH_ASSOC)['max_id'] ?? 0);

        $stmt = $this->db->prepare('SELECT id FROM pedidos WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $max + 9999]);
        $this->assertFalse($stmt->fetch(\PDO::FETCH_ASSOC));
    }

    /** U04. Teléfono enmascarado no revela el número completo. */
    public function testU04_telefonoEnmascaradoNoRevelaNumeroCompleto(): void
    {
        $idCliente = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli-u04');
        $idPedido  = \LogisticaTestDataFactory::crearPedido($this->db, $idCliente);

        $telefono = '5559876543';
        $this->db->exec("UPDATE pedidos SET telefono = '{$telefono}' WHERE id = {$idPedido}");

        // Lógica de enmascarado (idéntica a la del endpoint buscar.php)
        $solo  = preg_replace('/\D/', '', $telefono);
        $len   = strlen($solo);
        $mask  = $len > 4
            ? str_repeat('*', $len - 4) . substr($solo, -4)
            : str_repeat('*', $len);

        $this->assertStringNotContainsString($telefono, $mask);
        $this->assertStringContainsString('****', $mask);
        $this->assertStringEndsWith(substr($telefono, -4), $mask);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ── CATÁLOGOS ─────────────────────────────────────────────────────────────
    // ══════════════════════════════════════════════════════════════════════════

    /** U05. Catálogo de bodegas devuelve solo bodegas activas. */
    public function testU05_catalogoBodegasDevuelveSoloActivas(): void
    {
        $id1 = \LogisticaTestDataFactory::crearBodega($this->db, ['activa' => 1]);
        $id2 = \LogisticaTestDataFactory::crearBodega($this->db, ['activa' => 1]);

        $model   = new \BodegaModel($this->db);
        $bodegas = $model->listarActivas();
        // PDO puede devolver 'id' como string; normalizar a int para comparar
        $ids     = array_map('intval', array_column($bodegas, 'id'));
        $this->assertContains($id1, $ids);
        $this->assertContains($id2, $ids);
    }

    /** U06. Bodega inactiva no aparece en catálogo. */
    public function testU06_bodegaInactivaNoAparece(): void
    {
        $idInact = \LogisticaTestDataFactory::crearBodega($this->db, ['activa' => 0]);

        $model   = new \BodegaModel($this->db);
        $bodegas = $model->listarActivas();
        $ids     = array_map('intval', array_column($bodegas, 'id'));
        $this->assertNotContains($idInact, $ids);
    }

    /** U07. Catálogo de ubicaciones devuelve solo activas de la bodega. */
    public function testU07_catalogoUbicacionesDevuelveSoloActivas(): void
    {
        $idBodega = \LogisticaTestDataFactory::crearBodega($this->db);
        $idUbic1  = \LogisticaTestDataFactory::crearUbicacion($this->db, $idBodega, ['activa' => 1]);
        $idUbic2  = \LogisticaTestDataFactory::crearUbicacion($this->db, $idBodega, ['activa' => 1]);

        $model       = new \UbicacionModel($this->db);
        $ubicaciones = $model->listarActivasPorBodega($idBodega);
        // Normalizar a int para comparar correctamente
        $ids         = array_map('intval', array_column($ubicaciones, 'id'));
        $this->assertContains($idUbic1, $ids);
        $this->assertContains($idUbic2, $ids);
    }

    /** U08. Catálogo de ubicaciones de otra bodega no se mezcla. */
    public function testU08_ubicacionesDeOtraBodegaNoAparecen(): void
    {
        $idBodega1  = \LogisticaTestDataFactory::crearBodega($this->db);
        $idBodega2  = \LogisticaTestDataFactory::crearBodega($this->db);
        $idUbicOtra = \LogisticaTestDataFactory::crearUbicacion($this->db, $idBodega2);

        $model       = new \UbicacionModel($this->db);
        $ubicaciones = $model->listarActivasPorBodega($idBodega1);
        $ids         = array_map('intval', array_column($ubicaciones, 'id'));
        $this->assertNotContains($idUbicOtra, $ids);
    }

    /** U09. listarActivasPorBodega con id 0 devuelve array (sin excepción). */
    public function testU09_ubicacionesConIdBodegaCeroNoExplota(): void
    {
        $model = new \UbicacionModel($this->db);
        $result = $model->listarActivasPorBodega(0);
        $this->assertIsArray($result);
        $this->assertCount(0, $result); // 0 no debe matchear ninguna bodega real
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ── RECEPCIÓN ──────────────────────────────────────════════════════════════
    // ══════════════════════════════════════════════════════════════════════════

    /** U10. Registrar recepción con bodega válida devuelve 201. */
    public function testU10_registrarRecepcionDevuelve201(): void
    {
        $idBodega  = \LogisticaTestDataFactory::crearBodega($this->db);
        $idCliente = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli-u10');
        $idPedido  = \LogisticaTestDataFactory::crearPedido($this->db, $idCliente);

        $resp = $this->post('registrar', [
            'uuid'           => \LogisticaTestDataFactory::uuid(),
            'id_pedido'      => $idPedido,
            'id_bodega'      => $idBodega,
            'id_ubicacion'   => null,
            'id_escaneo'     => null,
            'tipo_recepcion' => 'COLECTA',
            'recibido_at'    => date('Y-m-d H:i:s'),
            'observacion'    => 'Test U10',
        ]);
        $this->assertSame(201, $resp['http']);
        $this->assertTrue($resp['body']['success']);
        $this->assertArrayHasKey('id_recepcion', $resp['body']['data']);
    }

    /** U11. Registrar recepción con ubicación inicial devuelve estado UBICADO. */
    public function testU11_recepcionConUbicacionInicialDevuelveUbicado(): void
    {
        $idBodega    = \LogisticaTestDataFactory::crearBodega($this->db);
        $idUbicacion = \LogisticaTestDataFactory::crearUbicacion($this->db, $idBodega);
        $idCliente   = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli-u11');
        $idPedido    = \LogisticaTestDataFactory::crearPedido($this->db, $idCliente);

        $resp = $this->post('registrar', [
            'uuid'           => \LogisticaTestDataFactory::uuid(),
            'id_pedido'      => $idPedido,
            'id_bodega'      => $idBodega,
            'id_ubicacion'   => $idUbicacion,
            'id_escaneo'     => null,
            'tipo_recepcion' => 'COLECTA',
            'recibido_at'    => date('Y-m-d H:i:s'),
        ]);
        $this->assertSame(201, $resp['http']);
        $this->assertSame('UBICADO', $resp['body']['data']['estado']);
    }

    /** U12. UUID repetido es idempotente: devuelve 200. */
    public function testU12_uuidRepetidoEsIdempotente(): void
    {
        $idBodega  = \LogisticaTestDataFactory::crearBodega($this->db);
        $idCliente = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli-u12');
        $idPedido  = \LogisticaTestDataFactory::crearPedido($this->db, $idCliente);
        $uuid      = \LogisticaTestDataFactory::uuid();

        $payload = [
            'uuid'           => $uuid,
            'id_pedido'      => $idPedido,
            'id_bodega'      => $idBodega,
            'id_ubicacion'   => null,
            'id_escaneo'     => null,
            'tipo_recepcion' => 'COLECTA',
            'recibido_at'    => date('Y-m-d H:i:s'),
        ];

        $r1 = $this->post('registrar', $payload);
        $this->assertSame(201, $r1['http']);

        $r2 = $this->post('registrar', $payload);
        $this->assertSame(200, $r2['http']);
        $this->assertTrue($r2['body']['data']['idempotente']);
    }

    /** U13. Bodega inexistente devuelve 404. */
    public function testU13_bodegaInexistenteDevuelve404(): void
    {
        $idCliente = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli-u13');
        $idPedido  = \LogisticaTestDataFactory::crearPedido($this->db, $idCliente);

        $resp = $this->post('registrar', [
            'uuid'           => \LogisticaTestDataFactory::uuid(),
            'id_pedido'      => $idPedido,
            'id_bodega'      => 999999,
            'id_ubicacion'   => null,
            'id_escaneo'     => null,
            'tipo_recepcion' => 'COLECTA',
            'recibido_at'    => date('Y-m-d H:i:s'),
        ]);
        // El controlador mapea BODEGA_NO_ENCONTRADA → HTTP 404
        $this->assertSame(404, $resp['http']);
        $this->assertFalse($resp['body']['success']);
    }

    /** U14. Bodega inactiva devuelve 422 (BODEGA_INACTIVA → HTTP 422). */
    public function testU14_bodegaInactivaDevuelve422(): void
    {
        $idBodegaInact = \LogisticaTestDataFactory::crearBodega($this->db, ['activa' => 0]);
        $idCliente     = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli-u14');
        $idPedido      = \LogisticaTestDataFactory::crearPedido($this->db, $idCliente);

        $resp = $this->post('registrar', [
            'uuid'           => \LogisticaTestDataFactory::uuid(),
            'id_pedido'      => $idPedido,
            'id_bodega'      => $idBodegaInact,
            'id_ubicacion'   => null,
            'id_escaneo'     => null,
            'tipo_recepcion' => 'COLECTA',
            'recibido_at'    => date('Y-m-d H:i:s'),
        ]);
        // El controlador mapea BODEGA_INACTIVA → HTTP 422
        $this->assertSame(422, $resp['http']);
        $this->assertFalse($resp['body']['success']);
    }

    /** U15. id_operador del cuerpo es ignorado; siempre se usa el del token. */
    public function testU15_idOperadorDelCuerpoEsIgnorado(): void
    {
        $idBodega  = \LogisticaTestDataFactory::crearBodega($this->db);
        $idCliente = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli-u15');
        $idPedido  = \LogisticaTestDataFactory::crearPedido($this->db, $idCliente);

        $resp = $this->post('registrar', [
            'uuid'           => \LogisticaTestDataFactory::uuid(),
            'id_pedido'      => $idPedido,
            'id_bodega'      => $idBodega,
            'id_ubicacion'   => null,
            'id_escaneo'     => null,
            'tipo_recepcion' => 'COLECTA',
            'recibido_at'    => date('Y-m-d H:i:s'),
            'id_operador'    => 99999, // debe ser ignorado
        ]);
        $this->assertSame(201, $resp['http']);

        $idRec = $resp['body']['data']['id_recepcion'];
        $stmt  = $this->db->prepare('SELECT id_operador FROM logistica_recepciones WHERE id = :id');
        $stmt->execute([':id' => $idRec]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotFalse($row);
        $this->assertSame($this->operadorActivo, (int) $row['id_operador']);
        $this->assertNotSame(99999, (int) $row['id_operador']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ── ASIGNAR UBICACIÓN ─────────────────────────────────────────────────────
    // ══════════════════════════════════════════════════════════════════════════

    /** U16. Asignar ubicación devuelve estado UBICADO. */
    public function testU16_asignarUbicacionDevuelveUbicado(): void
    {
        $ctx  = $this->crearRecepcion();
        $resp = $this->post('asignar', [
            'id_pedido'    => $ctx['id_pedido'],
            'id_recepcion' => $ctx['id_recepcion'],
            'id_ubicacion' => $ctx['id_ubicacion'],
            'motivo'       => 'Asignación inicial',
        ]);
        $this->assertSame(200, $resp['http']);
        $this->assertSame('UBICADO', $resp['body']['data']['estado']);
    }

    /** U17. Asignar ubicación de otra bodega devuelve 422. */
    public function testU17_asignarUbicacionDeOtraBodegaDevuelve422(): void
    {
        $ctx        = $this->crearRecepcion();
        $otraBodega = \LogisticaTestDataFactory::crearBodega($this->db);
        $otraUbic   = \LogisticaTestDataFactory::crearUbicacion($this->db, $otraBodega);

        $resp = $this->post('asignar', [
            'id_pedido'    => $ctx['id_pedido'],
            'id_recepcion' => $ctx['id_recepcion'],
            'id_ubicacion' => $otraUbic,
        ]);
        // El controlador mapea UBICACION_NO_PERTENECE_BODEGA → HTTP 422
        $this->assertSame(422, $resp['http']);
        $this->assertFalse($resp['body']['success']);
    }

    /** U18. Asignar sobre recepción ya UBICADA devuelve 409. */
    public function testU18_asignarSobreRecepcionUbicadaDevuelve409(): void
    {
        $ctx = $this->crearRecepcion();

        $r1 = $this->post('asignar', [
            'id_pedido'    => $ctx['id_pedido'],
            'id_recepcion' => $ctx['id_recepcion'],
            'id_ubicacion' => $ctx['id_ubicacion'],
        ]);
        $this->assertSame(200, $r1['http']);

        $r2 = $this->post('asignar', [
            'id_pedido'    => $ctx['id_pedido'],
            'id_recepcion' => $ctx['id_recepcion'],
            'id_ubicacion' => $ctx['id_ubicacion'],
        ]);
        // El controlador mapea RECEPCION_ACTIVA_EXISTENTE → HTTP 409
        $this->assertSame(409, $r2['http']);
        $this->assertFalse($r2['body']['success']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ── UBICACIÓN ACTUAL ──────────────────────────────────────────────────────
    // ══════════════════════════════════════════════════════════════════════════

    /** U19. Consultar ubicación actual devuelve id_bodega correcto. */
    public function testU19_consultarUbicacionActualDevuelveDatos(): void
    {
        $ctx = $this->crearRecepcion();
        $this->post('asignar', [
            'id_pedido'    => $ctx['id_pedido'],
            'id_recepcion' => $ctx['id_recepcion'],
            'id_ubicacion' => $ctx['id_ubicacion'],
        ]);

        $resp = $this->get('actual', ['id_pedido' => (string) $ctx['id_pedido']]);
        $this->assertSame(200, $resp['http']);
        $data = $this->data($resp);
        $this->assertArrayHasKey('id_bodega', $data);
        $this->assertSame($ctx['id_bodega'], (int) $data['id_bodega']);
    }

    /** U20. Pedido sin recepción activa devuelve 404. */
    public function testU20_sinRecepcionActivaDevuelve404(): void
    {
        $idCliente = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli-u20');
        $idPedido  = \LogisticaTestDataFactory::crearPedido($this->db, $idCliente);

        $resp = $this->get('actual', ['id_pedido' => (string) $idPedido]);
        $this->assertSame(404, $resp['http']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ── REUBICAR ──────────────────────────────────────────────────────────────
    // ══════════════════════════════════════════════════════════════════════════

    /** U21. Reubicar dentro de la misma bodega devuelve 200. */
    public function testU21_reubicarMismaBodegaDevuelve200(): void
    {
        $ctx  = $this->crearRecepcion();
        $dest = \LogisticaTestDataFactory::crearUbicacion($this->db, $ctx['id_bodega']);

        $this->post('asignar', [
            'id_pedido'    => $ctx['id_pedido'],
            'id_recepcion' => $ctx['id_recepcion'],
            'id_ubicacion' => $ctx['id_ubicacion'],
        ]);

        $resp = $this->post('reubicar', [
            'id_pedido'            => $ctx['id_pedido'],
            'id_ubicacion_destino' => $dest,
            'motivo'               => 'Optimización',
        ]);
        $this->assertSame(200, $resp['http']);
    }

    /** U22. Reubicar a la misma ubicación no genera entrada duplicada en historial. */
    public function testU22_reubicarMismaUbicacionNoDuplicaHistorial(): void
    {
        $ctx = $this->crearRecepcion();
        $this->post('asignar', [
            'id_pedido'    => $ctx['id_pedido'],
            'id_recepcion' => $ctx['id_recepcion'],
            'id_ubicacion' => $ctx['id_ubicacion'],
        ]);

        $r = $this->post('reubicar', [
            'id_pedido'            => $ctx['id_pedido'],
            'id_ubicacion_destino' => $ctx['id_ubicacion'],
        ]);
        $this->assertSame(200, $r['http']);
        // El servicio devuelve 'movimiento' => 'SIN_CAMBIO' cuando el destino es igual al actual
        $this->assertSame('SIN_CAMBIO', $r['body']['data']['movimiento'] ?? '');

        // Solo debe haber 1 entrada activa en el historial
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS cnt FROM logistica_ubicacion_historial
              WHERE id_recepcion = :id AND activo = 1'
        );
        $stmt->execute([':id' => $ctx['id_recepcion']]);
        $cnt = (int) $stmt->fetch(\PDO::FETCH_ASSOC)['cnt'];
        $this->assertSame(1, $cnt);
    }

    /** U23. Reubicar a otra bodega devuelve 422. */
    public function testU23_reubicarOtraBodegaDevuelve422(): void
    {
        $ctx    = $this->crearRecepcion();
        $otra   = \LogisticaTestDataFactory::crearBodega($this->db);
        $destOtra = \LogisticaTestDataFactory::crearUbicacion($this->db, $otra);

        $this->post('asignar', [
            'id_pedido'    => $ctx['id_pedido'],
            'id_recepcion' => $ctx['id_recepcion'],
            'id_ubicacion' => $ctx['id_ubicacion'],
        ]);

        $resp = $this->post('reubicar', [
            'id_pedido'            => $ctx['id_pedido'],
            'id_ubicacion_destino' => $destOtra,
        ]);
        // El controlador mapea TRASLADO_ENTRE_BODEGAS_NO_PERMITIDO → HTTP 422
        $this->assertSame(422, $resp['http']);
        $this->assertFalse($resp['body']['success']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ── RETIRAR ───────────────────────────────────────────────────────────────
    // ══════════════════════════════════════════════════════════════════════════

    /** U24. Retirar devuelve 200 y estado RETIRADO. */
    public function testU24_retirarDevuelve200(): void
    {
        $ctx = $this->crearRecepcion();
        $this->post('asignar', [
            'id_pedido'    => $ctx['id_pedido'],
            'id_recepcion' => $ctx['id_recepcion'],
            'id_ubicacion' => $ctx['id_ubicacion'],
        ]);

        $resp = $this->post('retirar', [
            'id_pedido' => $ctx['id_pedido'],
            'motivo'    => 'Reprogramación',
        ]);
        $this->assertSame(200, $resp['http']);
        $this->assertSame('RETIRADO', $resp['body']['data']['estado']);
    }

    /** U25. Retirar dos veces es idempotente (200). */
    public function testU25_retirarDosVecesEsIdempotente(): void
    {
        $ctx = $this->crearRecepcion();
        $this->post('asignar', [
            'id_pedido'    => $ctx['id_pedido'],
            'id_recepcion' => $ctx['id_recepcion'],
            'id_ubicacion' => $ctx['id_ubicacion'],
        ]);

        $r1 = $this->post('retirar', ['id_pedido' => $ctx['id_pedido']]);
        $this->assertSame(200, $r1['http']);

        $r2 = $this->post('retirar', ['id_pedido' => $ctx['id_pedido']]);
        $this->assertSame(200, $r2['http']);
        $this->assertTrue($r2['body']['data']['idempotente'] ?? false);
    }

    /** U26. Consultar ubicación después de retirar devuelve 404. */
    public function testU26_ubicacionDespuesDeRetirarDevuelve404(): void
    {
        $ctx = $this->crearRecepcion();
        $this->post('asignar', [
            'id_pedido'    => $ctx['id_pedido'],
            'id_recepcion' => $ctx['id_recepcion'],
            'id_ubicacion' => $ctx['id_ubicacion'],
        ]);
        $this->post('retirar', ['id_pedido' => $ctx['id_pedido']]);

        $resp = $this->get('actual', ['id_pedido' => (string) $ctx['id_pedido']]);
        $this->assertSame(404, $resp['http']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ── HISTORIAL ─────────────────────────────────────────────────────────────
    // ══════════════════════════════════════════════════════════════════════════

    /** U27. Historial devuelve movimientos cronológicos. */
    public function testU27_historialDevuelveMovimientos(): void
    {
        $ctx = $this->crearRecepcion();
        $this->post('asignar', [
            'id_pedido'    => $ctx['id_pedido'],
            'id_recepcion' => $ctx['id_recepcion'],
            'id_ubicacion' => $ctx['id_ubicacion'],
        ]);

        $resp = $this->get('historial', ['id_pedido' => (string) $ctx['id_pedido']]);
        $this->assertSame(200, $resp['http']);
        $data = $this->data($resp);
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(1, count($data));
        $this->assertArrayHasKey('id_recepcion', $data[0]);
    }

    /** U28. Historial vacío devuelve array vacío. */
    public function testU28_historialVacioDevuelveArrayVacio(): void
    {
        $idCliente = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli-u28');
        $idPedido  = \LogisticaTestDataFactory::crearPedido($this->db, $idCliente);

        $resp = $this->get('historial', ['id_pedido' => (string) $idPedido]);
        $this->assertSame(200, $resp['http']);
        $data = $this->data($resp);
        $this->assertIsArray($data);
        $this->assertCount(0, $data);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ── SEGURIDAD Y RESTRICCIONES ─────────────────────────────────────────────
    // ══════════════════════════════════════════════════════════════════════════

    /** U29. Las respuestas no contienen trace, file, line, password, SQL ni DSN. */
    public function testU29_respuestasNoContienenDatosSensibles(): void
    {
        // Forzar error conocido: bodega inexistente
        $idCliente = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli-u29');
        $idPedido  = \LogisticaTestDataFactory::crearPedido($this->db, $idCliente);

        $resp = $this->post('registrar', [
            'uuid'           => \LogisticaTestDataFactory::uuid(),
            'id_pedido'      => $idPedido,
            'id_bodega'      => 999999,
            'id_ubicacion'   => null,
            'id_escaneo'     => null,
            'tipo_recepcion' => 'COLECTA',
            'recibido_at'    => date('Y-m-d H:i:s'),
        ]);

        $body      = json_encode($resp['body']);
        $forbidden = ['trace', 'Trace', 'Exception', 'password', 'SELECT ', 'INSERT ', 'mysql:', 'host=', 'DSN'];
        foreach ($forbidden as $token) {
            $this->assertStringNotContainsString(
                $token,
                $body,
                "La respuesta contiene dato sensible: '{$token}'"
            );
        }
    }

    /** U30. pedidos.id_estado permanece intacto después de todos los flujos. */
    public function testU30_pedidoIdEstadoPermanece(): void
    {
        $ctx = $this->crearRecepcion();

        // Estado antes
        $stmt = $this->db->prepare('SELECT id_estado FROM pedidos WHERE id = :id');
        $stmt->execute([':id' => $ctx['id_pedido']]);
        $estadoAntes = (int) $stmt->fetch(\PDO::FETCH_ASSOC)['id_estado'];

        // Flujo completo
        $this->post('asignar', [
            'id_pedido'    => $ctx['id_pedido'],
            'id_recepcion' => $ctx['id_recepcion'],
            'id_ubicacion' => $ctx['id_ubicacion'],
        ]);

        $dest = \LogisticaTestDataFactory::crearUbicacion($this->db, $ctx['id_bodega']);
        $this->post('reubicar', [
            'id_pedido'            => $ctx['id_pedido'],
            'id_ubicacion_destino' => $dest,
        ]);

        $this->post('retirar', ['id_pedido' => $ctx['id_pedido']]);

        // Estado después
        $stmt->execute([':id' => $ctx['id_pedido']]);
        $estadoDespues = (int) $stmt->fetch(\PDO::FETCH_ASSOC)['id_estado'];

        $this->assertSame(
            $estadoAntes,
            $estadoDespues,
            'pedidos.id_estado fue modificado. ¡VIOLACIÓN DEL SHADOW MODE!'
        );
    }
}
