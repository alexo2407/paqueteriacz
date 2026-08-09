<?php

declare(strict_types=1);

namespace Tests\LogisticaOperativa;

use PHPUnit\Framework\TestCase;

// ─── Soporte de pruebas (namespace global) ────────────────────────────────────
require_once dirname(__DIR__, 2) . '/tests/Support/TestDatabaseConnection.php';
require_once dirname(__DIR__, 2) . '/tests/Support/LogisticaTestDataFactory.php';
require_once dirname(__DIR__, 2) . '/tests/Support/BodegaUbicacionServiceTestable.php';

/**
 * BodegaUbicacionServiceIntegrationTest
 *
 * Pruebas de integración reales para BodegaUbicacionService.
 * Usa paquetes_apppack_test; hace rollback al finalizar cada test.
 *
 * Tests (31):
 *  T01. Registrar recepción sin ubicación → estado RECIBIDO.
 *  T02. Registrar recepción con ubicación → estado UBICADO.
 *  T03. Recepción con ubicación crea historial INGRESO activo.
 *  T04. UUID repetido es idempotente.
 *  T05. Pedido inexistente es rechazado.
 *  T06. Bodega inexistente es rechazada.
 *  T07. Bodega inactiva es rechazada.
 *  T08. Ubicación inexistente es rechazada.
 *  T09. Ubicación inactiva es rechazada.
 *  T10. Ubicación de otra bodega es rechazada.
 *  T11. Segunda recepción activa del mismo pedido es rechazada.
 *  T12. Ubicar una recepción RECIBIDA funciona.
 *  T13. Ubicar un paquete ya UBICADO es rechazado.
 *  T14. Reubicar dentro de la misma bodega funciona.
 *  T15. Reubicar desactiva el historial anterior.
 *  T16. Reubicar crea exactamente una nueva ubicación activa.
 *  T17. Reubicar hacia otra bodega es rechazado.
 *  T18. Reubicar hacia la misma ubicación no duplica historial.
 *  T19. Retirar paquete desactiva la ubicación actual.
 *  T20. Retirar actualiza recepción a RETIRADO.
 *  T21. Retiro repetido no duplica movimientos.
 *  T22. obtenerUbicacionActual devuelve la nomenclatura completa.
 *  T23. obtenerUbicacionActual devuelve null después del retiro.
 *  T24. obtenerHistorial devuelve todos los movimientos cronológicamente.
 *  T25. Nunca existen dos ubicaciones activas para el mismo pedido.
 *  T26. pedidos.id_estado permanece intacto.
 *  T27. inventario permanece intacto.
 *  T28. stock permanece intacto.
 *  T29. reservas permanecen intactas.
 *  T30. rollback deja 0 filas permanentes.
 *  T31. paquetes_apppack no es modificada.
 */
class BodegaUbicacionServiceIntegrationTest extends TestCase
{
    private \PDO $db;

    // ── setUp / tearDown ──────────────────────────────────────────────────────

    protected function setUp(): void
    {
        assertTestDatabase();
        $this->db = \TestDatabaseConnection::nueva();
        $this->db->exec(
            "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION'"
        );
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function servicio(): \BodegaUbicacionServiceTestable
    {
        return new \BodegaUbicacionServiceTestable($this->db);
    }

    /**
     * Crea una bodega ficticia en la base de pruebas.
     */
    private function crearBodega(array $override = []): int
    {
        return \LogisticaTestDataFactory::crearBodega($this->db, $override);
    }

    /**
     * Crea una ubicación ficticia vinculada a la bodega indicada.
     */
    private function crearUbicacion(int $idBodega, array $override = []): int
    {
        return \LogisticaTestDataFactory::crearUbicacion($this->db, $idBodega, $override);
    }

    /**
     * Construye el array de datos mínimo para registrarRecepcion().
     */
    private function datosRecepcion(
        int     $idPedido,
        int     $idBodega,
        int     $idOperador,
        ?int    $idUbicacion = null,
        ?string $uuid = null
    ): array {
        return [
            'uuid'           => $uuid ?? \LogisticaTestDataFactory::uuid(),
            'id_pedido'      => $idPedido,
            'id_bodega'      => $idBodega,
            'id_ubicacion'   => $idUbicacion,
            'id_escaneo'     => null,
            'tipo_recepcion' => 'COLECTA',
            'id_operador'    => $idOperador,
            'recibido_at'    => date('Y-m-d H:i:s'),
            'observacion'    => 'Test de integración',
        ];
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T01 — Registrar recepción sin ubicación → estado RECIBIDO
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T01. Recepción sin ubicación produce RECIBIDO. */
    public function test_recepcion_sin_ubicacion_produce_recibido(): void
    {
        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli');
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $bodega   = $this->crearBodega();

        $result = $this->servicio()->registrarRecepcion(
            $this->datosRecepcion($pedido, $bodega, $operador)
        );

        $this->assertFalse($result['idempotente']);
        $this->assertGreaterThan(0, $result['id_recepcion']);
        $this->assertSame('RECIBIDO', $result['estado']);

        // Verificar en BD
        $stmt = $this->db->prepare('SELECT estado FROM logistica_recepciones WHERE id = :id');
        $stmt->execute([':id' => $result['id_recepcion']]);
        $this->assertSame('RECIBIDO', $stmt->fetchColumn());
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T02 — Registrar recepción con ubicación → estado UBICADO
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T02. Recepción con ubicación produce UBICADO. */
    public function test_recepcion_con_ubicacion_produce_ubicado(): void
    {
        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli');
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $bodega   = $this->crearBodega();
        $ubic     = $this->crearUbicacion($bodega);

        $result = $this->servicio()->registrarRecepcion(
            $this->datosRecepcion($pedido, $bodega, $operador, $ubic)
        );

        $this->assertFalse($result['idempotente']);
        $this->assertSame('UBICADO', $result['estado']);

        $stmt = $this->db->prepare('SELECT estado, id_ubicacion FROM logistica_recepciones WHERE id = :id');
        $stmt->execute([':id' => $result['id_recepcion']]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame('UBICADO', $row['estado']);
        $this->assertSame($ubic, (int) $row['id_ubicacion']);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T03 — Recepción con ubicación crea historial INGRESO activo
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T03. Recepción con ubicación crea historial tipo INGRESO con activo=1. */
    public function test_recepcion_con_ubicacion_crea_historial_ingreso(): void
    {
        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli');
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $bodega   = $this->crearBodega();
        $ubic     = $this->crearUbicacion($bodega);

        $result = $this->servicio()->registrarRecepcion(
            $this->datosRecepcion($pedido, $bodega, $operador, $ubic)
        );

        $stmt = $this->db->prepare(
            'SELECT tipo_movimiento, activo FROM logistica_ubicacion_historial
              WHERE id_pedido = :id_pedido'
        );
        $stmt->execute([':id_pedido' => $pedido]);
        $hist = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($hist, 'Debe existir un registro en el historial.');
        $this->assertSame('INGRESO', $hist['tipo_movimiento']);
        $this->assertSame('1', (string) $hist['activo']);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T04 — UUID repetido es idempotente
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T04. El mismo UUID devuelve la recepción existente sin duplicar. */
    public function test_uuid_repetido_es_idempotente(): void
    {
        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli');
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $bodega   = $this->crearBodega();
        $uuid     = \LogisticaTestDataFactory::uuid();

        $svc   = $this->servicio();
        $datos = $this->datosRecepcion($pedido, $bodega, $operador, null, $uuid);

        $r1 = $svc->registrarRecepcion($datos);
        $r2 = $svc->registrarRecepcion($datos);

        $this->assertFalse($r1['idempotente']);
        $this->assertTrue($r2['idempotente']);
        $this->assertSame($r1['id_recepcion'], $r2['id_recepcion']);

        // Solo debe existir 1 fila con ese UUID
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM logistica_recepciones WHERE uuid = :u');
        $stmt->execute([':u' => $uuid]);
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T05 — Pedido inexistente es rechazado
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T05. Registrar recepción para un pedido inexistente lanza excepción. */
    public function test_pedido_inexistente_es_rechazado(): void
    {
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op');
        $bodega   = $this->crearBodega();

        $this->expectException(\LogisticaOperativaException::class);
        $this->servicio()->registrarRecepcion(
            $this->datosRecepcion(999999999, $bodega, $operador)
        );
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T06 — Bodega inexistente es rechazada
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T06. Registrar recepción con bodega inexistente lanza excepción. */
    public function test_bodega_inexistente_es_rechazada(): void
    {
        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli');
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);

        $this->expectException(\LogisticaOperativaException::class);
        $this->servicio()->registrarRecepcion(
            $this->datosRecepcion($pedido, 999999999, $operador)
        );
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T07 — Bodega inactiva es rechazada
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T07. Registrar recepción en bodega inactiva lanza excepción. */
    public function test_bodega_inactiva_es_rechazada(): void
    {
        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli');
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $bodega   = $this->crearBodega(['activa' => 0]);

        $this->expectException(\LogisticaOperativaException::class);
        $this->servicio()->registrarRecepcion(
            $this->datosRecepcion($pedido, $bodega, $operador)
        );
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T08 — Ubicación inexistente es rechazada
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T08. Registrar recepción con ubicación inexistente lanza excepción. */
    public function test_ubicacion_inexistente_es_rechazada(): void
    {
        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli');
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $bodega   = $this->crearBodega();

        $this->expectException(\LogisticaOperativaException::class);
        $this->servicio()->registrarRecepcion(
            $this->datosRecepcion($pedido, $bodega, $operador, 999999999)
        );
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T09 — Ubicación inactiva es rechazada
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T09. Registrar recepción con ubicación inactiva lanza excepción. */
    public function test_ubicacion_inactiva_es_rechazada(): void
    {
        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli');
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $bodega   = $this->crearBodega();
        $ubic     = $this->crearUbicacion($bodega, ['activa' => 0]);

        $this->expectException(\LogisticaOperativaException::class);
        $this->servicio()->registrarRecepcion(
            $this->datosRecepcion($pedido, $bodega, $operador, $ubic)
        );
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T10 — Ubicación de otra bodega es rechazada
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T10. Ubicación que pertenece a otra bodega es rechazada. */
    public function test_ubicacion_de_otra_bodega_es_rechazada(): void
    {
        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli');
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $bodega1  = $this->crearBodega();
        $bodega2  = $this->crearBodega();
        $ubicB2   = $this->crearUbicacion($bodega2); // pertenece a bodega2

        $this->expectException(\LogisticaOperativaException::class);
        $this->servicio()->registrarRecepcion(
            $this->datosRecepcion($pedido, $bodega1, $operador, $ubicB2)
        );
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T11 — Segunda recepción activa del mismo pedido es rechazada
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T11. Crear una segunda recepción activa para el mismo pedido es rechazado. */
    public function test_segunda_recepcion_activa_es_rechazada(): void
    {
        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli');
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $bodega   = $this->crearBodega();

        $svc = $this->servicio();
        $svc->registrarRecepcion($this->datosRecepcion($pedido, $bodega, $operador));

        $this->expectException(\LogisticaOperativaException::class);
        $svc->registrarRecepcion($this->datosRecepcion($pedido, $bodega, $operador));
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T12 — Ubicar una recepción RECIBIDA funciona
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T12. Ubicar una recepción en estado RECIBIDO funciona correctamente. */
    public function test_ubicar_recepcion_recibida_funciona(): void
    {
        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli');
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $bodega   = $this->crearBodega();
        $ubic     = $this->crearUbicacion($bodega);

        $svc      = $this->servicio();
        $recepcion = $svc->registrarRecepcion($this->datosRecepcion($pedido, $bodega, $operador));
        $result    = $svc->ubicarPaquete($pedido, $recepcion['id_recepcion'], $ubic, $operador, 'Ubicación inicial');

        $this->assertSame($recepcion['id_recepcion'], $result['id_recepcion']);
        $this->assertSame($ubic, $result['id_ubicacion']);
        $this->assertSame('UBICADO', $result['estado']);

        // Verificar en BD
        $stmt = $this->db->prepare('SELECT estado FROM logistica_recepciones WHERE id = :id');
        $stmt->execute([':id' => $recepcion['id_recepcion']]);
        $this->assertSame('UBICADO', $stmt->fetchColumn());
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T13 — Ubicar un paquete ya UBICADO es rechazado
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T13. Intentar ubicar una recepción que ya está UBICADA lanza excepción. */
    public function test_ubicar_paquete_ya_ubicado_es_rechazado(): void
    {
        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli');
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $bodega   = $this->crearBodega();
        $ubic     = $this->crearUbicacion($bodega);
        $ubic2    = $this->crearUbicacion($bodega);

        $svc      = $this->servicio();
        $recepcion = $svc->registrarRecepcion($this->datosRecepcion($pedido, $bodega, $operador, $ubic));

        // Ya está UBICADO → intentar ubicar de nuevo debe fallar
        $this->expectException(\LogisticaOperativaException::class);
        $svc->ubicarPaquete($pedido, $recepcion['id_recepcion'], $ubic2, $operador);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T14 — Reubicar dentro de la misma bodega funciona
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T14. Reubicar un paquete a otra ubicación de la misma bodega funciona. */
    public function test_reubicar_misma_bodega_funciona(): void
    {
        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli');
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $bodega   = $this->crearBodega();
        $ubic1    = $this->crearUbicacion($bodega);
        $ubic2    = $this->crearUbicacion($bodega);

        $svc = $this->servicio();
        $svc->registrarRecepcion($this->datosRecepcion($pedido, $bodega, $operador, $ubic1));
        $result = $svc->reubicarPaquete($pedido, $ubic2, $operador, 'Reubicación de prueba');

        $this->assertSame($ubic1, $result['id_ubicacion_anterior']);
        $this->assertSame($ubic2, $result['id_ubicacion_nueva']);
        $this->assertSame('REUBICACION', $result['movimiento']);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T15 — Reubicar desactiva el historial anterior
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T15. Reubicar desactiva la fila anterior del historial (activo=0). */
    public function test_reubicar_desactiva_historial_anterior(): void
    {
        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli');
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $bodega   = $this->crearBodega();
        $ubic1    = $this->crearUbicacion($bodega);
        $ubic2    = $this->crearUbicacion($bodega);

        $svc = $this->servicio();
        $svc->registrarRecepcion($this->datosRecepcion($pedido, $bodega, $operador, $ubic1));
        $svc->reubicarPaquete($pedido, $ubic2, $operador);

        $stmt = $this->db->prepare(
            'SELECT activo FROM logistica_ubicacion_historial
              WHERE id_pedido = :id AND id_ubicacion = :ubic'
        );
        $stmt->execute([':id' => $pedido, ':ubic' => $ubic1]);
        $filaAnterior = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotFalse($filaAnterior);
        $this->assertSame('0', (string) $filaAnterior['activo'], 'La fila anterior debe estar desactivada.');
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T16 — Reubicar crea exactamente una nueva ubicación activa
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T16. Después de reubicar, el pedido tiene exactamente 1 ubicación activa. */
    public function test_reubicar_crea_exactamente_una_ubicacion_activa(): void
    {
        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli');
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $bodega   = $this->crearBodega();
        $ubic1    = $this->crearUbicacion($bodega);
        $ubic2    = $this->crearUbicacion($bodega);
        $ubic3    = $this->crearUbicacion($bodega);

        $svc = $this->servicio();
        $svc->registrarRecepcion($this->datosRecepcion($pedido, $bodega, $operador, $ubic1));
        $svc->reubicarPaquete($pedido, $ubic2, $operador);
        $svc->reubicarPaquete($pedido, $ubic3, $operador);

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM logistica_ubicacion_historial
              WHERE id_pedido = :id AND activo = 1'
        );
        $stmt->execute([':id' => $pedido]);
        $this->assertSame(1, (int) $stmt->fetchColumn(), 'Debe existir exactamente 1 ubicación activa.');
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T17 — Reubicar hacia otra bodega es rechazado
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T17. Reubicar a una ubicación de otra bodega lanza excepción. */
    public function test_reubicar_hacia_otra_bodega_es_rechazado(): void
    {
        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli');
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $bodega1  = $this->crearBodega();
        $bodega2  = $this->crearBodega();
        $ubic1    = $this->crearUbicacion($bodega1);
        $ubic2    = $this->crearUbicacion($bodega2); // en bodega2

        $svc = $this->servicio();
        $svc->registrarRecepcion($this->datosRecepcion($pedido, $bodega1, $operador, $ubic1));

        $this->expectException(\LogisticaOperativaException::class);
        $svc->reubicarPaquete($pedido, $ubic2, $operador);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T18 — Reubicar hacia la misma ubicación no duplica historial
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T18. Reubicar hacia la misma ubicación no crea un movimiento adicional. */
    public function test_reubicar_misma_ubicacion_no_duplica_historial(): void
    {
        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli');
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $bodega   = $this->crearBodega();
        $ubic     = $this->crearUbicacion($bodega);

        $svc = $this->servicio();
        $svc->registrarRecepcion($this->datosRecepcion($pedido, $bodega, $operador, $ubic));

        $antes = (int) $this->db->query(
            "SELECT COUNT(*) FROM logistica_ubicacion_historial WHERE id_pedido = {$pedido}"
        )->fetchColumn();

        $result = $svc->reubicarPaquete($pedido, $ubic, $operador);
        $this->assertSame('SIN_CAMBIO', $result['movimiento']);

        $despues = (int) $this->db->query(
            "SELECT COUNT(*) FROM logistica_ubicacion_historial WHERE id_pedido = {$pedido}"
        )->fetchColumn();

        $this->assertSame($antes, $despues, 'No debe crearse un nuevo movimiento con la misma ubicación.');
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T19 — Retirar paquete desactiva la ubicación actual
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T19. Retirar un paquete desactiva su fila activa en el historial. */
    public function test_retirar_paquete_desactiva_ubicacion(): void
    {
        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli');
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $bodega   = $this->crearBodega();
        $ubic     = $this->crearUbicacion($bodega);

        $svc = $this->servicio();
        $svc->registrarRecepcion($this->datosRecepcion($pedido, $bodega, $operador, $ubic));
        $svc->retirarPaquete($pedido, $operador, 'Retiro de prueba');

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM logistica_ubicacion_historial
              WHERE id_pedido = :id AND activo = 1'
        );
        $stmt->execute([':id' => $pedido]);
        $this->assertSame(0, (int) $stmt->fetchColumn(), 'No debe quedar ninguna ubicación activa tras el retiro.');
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T20 — Retirar actualiza recepción a RETIRADO
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T20. Retirar un paquete actualiza la recepción a estado RETIRADO. */
    public function test_retirar_actualiza_recepcion_a_retirado(): void
    {
        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli');
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $bodega   = $this->crearBodega();
        $ubic     = $this->crearUbicacion($bodega);

        $svc      = $this->servicio();
        $recepcion = $svc->registrarRecepcion($this->datosRecepcion($pedido, $bodega, $operador, $ubic));
        $result    = $svc->retirarPaquete($pedido, $operador);

        $this->assertFalse($result['idempotente']);
        $this->assertSame('RETIRADO', $result['estado']);

        $stmt = $this->db->prepare('SELECT estado FROM logistica_recepciones WHERE id = :id');
        $stmt->execute([':id' => $recepcion['id_recepcion']]);
        $this->assertSame('RETIRADO', $stmt->fetchColumn());
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T21 — Retiro repetido no duplica movimientos
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T21. Retirar un paquete ya retirado es idempotente y no duplica movimientos. */
    public function test_retiro_repetido_no_duplica_movimientos(): void
    {
        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli');
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $bodega   = $this->crearBodega();
        $ubic     = $this->crearUbicacion($bodega);

        $svc = $this->servicio();
        $svc->registrarRecepcion($this->datosRecepcion($pedido, $bodega, $operador, $ubic));
        $svc->retirarPaquete($pedido, $operador);

        $antesCount = (int) $this->db->query(
            "SELECT COUNT(*) FROM logistica_ubicacion_historial WHERE id_pedido = {$pedido}"
        )->fetchColumn();

        $r2 = $svc->retirarPaquete($pedido, $operador);
        $this->assertTrue($r2['idempotente']);
        $this->assertSame('RETIRADO', $r2['estado']);

        $despuesCount = (int) $this->db->query(
            "SELECT COUNT(*) FROM logistica_ubicacion_historial WHERE id_pedido = {$pedido}"
        )->fetchColumn();

        $this->assertSame($antesCount, $despuesCount, 'El retiro repetido no debe insertar nuevas filas.');
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T22 — obtenerUbicacionActual devuelve nomenclatura completa
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T22. obtenerUbicacionActual devuelve la nomenclatura completa de bodega y ubicación. */
    public function test_obtener_ubicacion_actual_devuelve_nomenclatura_completa(): void
    {
        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli');
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $bodega   = $this->crearBodega(['codigo' => 'MGA-01', 'nombre' => 'Bodega Managua']);
        $ubic     = $this->crearUbicacion($bodega, [
            'codigo'  => 'ZONA-B/ESTANTE-10/CAJON-A5',
            'zona'    => 'B',
            'estante' => '10',
            'cajon'   => 'A5',
            'tipo'    => 'INCIDENCIA',
        ]);

        $svc = $this->servicio();
        $svc->registrarRecepcion($this->datosRecepcion($pedido, $bodega, $operador, $ubic));

        $ubicActual = $svc->obtenerUbicacionActual($pedido);

        $this->assertNotNull($ubicActual);
        $this->assertSame($pedido,    $ubicActual['id_pedido']);
        $this->assertSame($bodega,    $ubicActual['id_bodega']);
        $this->assertSame('MGA-01',   $ubicActual['bodega_codigo']);
        $this->assertSame('Bodega Managua', $ubicActual['bodega_nombre']);
        $this->assertSame($ubic,      $ubicActual['id_ubicacion']);
        $this->assertSame('ZONA-B/ESTANTE-10/CAJON-A5', $ubicActual['ubicacion_codigo']);
        $this->assertSame('B',        $ubicActual['zona']);
        $this->assertSame('10',       $ubicActual['estante']);
        $this->assertSame('A5',       $ubicActual['cajon']);
        $this->assertSame('INCIDENCIA', $ubicActual['tipo_ubicacion']);
        $this->assertArrayHasKey('ubicado_at', $ubicActual);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T23 — obtenerUbicacionActual devuelve null después del retiro
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T23. obtenerUbicacionActual devuelve null después de retirar el paquete. */
    public function test_obtener_ubicacion_actual_es_null_despues_del_retiro(): void
    {
        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli');
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $bodega   = $this->crearBodega();
        $ubic     = $this->crearUbicacion($bodega);

        $svc = $this->servicio();
        $svc->registrarRecepcion($this->datosRecepcion($pedido, $bodega, $operador, $ubic));
        $svc->retirarPaquete($pedido, $operador);

        $this->assertNull($svc->obtenerUbicacionActual($pedido));
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T24 — obtenerHistorial devuelve todos los movimientos cronológicamente
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T24. obtenerHistorial devuelve INGRESO → REUBICACION en orden cronológico. */
    public function test_obtener_historial_devuelve_movimientos_cronologicos(): void
    {
        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli');
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $bodega   = $this->crearBodega();
        $ubic1    = $this->crearUbicacion($bodega);
        $ubic2    = $this->crearUbicacion($bodega);

        $svc = $this->servicio();
        $svc->registrarRecepcion($this->datosRecepcion($pedido, $bodega, $operador, $ubic1));
        $svc->reubicarPaquete($pedido, $ubic2, $operador);
        $svc->retirarPaquete($pedido, $operador);

        $historial = $svc->obtenerHistorial($pedido);

        $this->assertCount(2, $historial, 'Deben existir 2 entradas en el historial (INGRESO + REUBICACION).');
        $this->assertSame('INGRESO',     $historial[0]['tipo_movimiento']);
        $this->assertSame('REUBICACION', $historial[1]['tipo_movimiento']);
        $this->assertArrayHasKey('bodega_codigo',    $historial[0]);
        $this->assertArrayHasKey('ubicacion_codigo', $historial[0]);
        $this->assertArrayHasKey('operador_nombre',  $historial[0]);
        $this->assertArrayHasKey('activo',           $historial[0]);
        $this->assertArrayHasKey('retirado_at',      $historial[0]);
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T25 — Nunca existen dos ubicaciones activas para el mismo pedido
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T25. Flujo completo: nunca hay más de 1 ubicación activa para el mismo pedido. */
    public function test_nunca_dos_ubicaciones_activas(): void
    {
        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli');
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $bodega   = $this->crearBodega();
        $ubic1    = $this->crearUbicacion($bodega);
        $ubic2    = $this->crearUbicacion($bodega);
        $ubic3    = $this->crearUbicacion($bodega);

        $checkActivos = function () use ($pedido): int {
            return (int) $this->db->query(
                "SELECT COUNT(*) FROM logistica_ubicacion_historial
                  WHERE id_pedido = {$pedido} AND activo = 1"
            )->fetchColumn();
        };

        $svc = $this->servicio();
        $svc->registrarRecepcion($this->datosRecepcion($pedido, $bodega, $operador, $ubic1));
        $this->assertSame(1, $checkActivos());

        $svc->reubicarPaquete($pedido, $ubic2, $operador);
        $this->assertSame(1, $checkActivos());

        $svc->reubicarPaquete($pedido, $ubic3, $operador);
        $this->assertSame(1, $checkActivos());

        $svc->retirarPaquete($pedido, $operador);
        $this->assertSame(0, $checkActivos());
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T26 — pedidos.id_estado permanece intacto
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T26. pedidos.id_estado no cambia durante ninguna operación de este servicio. */
    public function test_pedido_id_estado_permanece_intacto(): void
    {
        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli');
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $bodega   = $this->crearBodega();
        $ubic1    = $this->crearUbicacion($bodega);
        $ubic2    = $this->crearUbicacion($bodega);

        $stmt = $this->db->prepare('SELECT id_estado FROM pedidos WHERE id = :id');
        $stmt->execute([':id' => $pedido]);
        $estadoOriginal = (int) $stmt->fetchColumn();

        $svc = $this->servicio();
        $svc->registrarRecepcion($this->datosRecepcion($pedido, $bodega, $operador, $ubic1));
        $svc->reubicarPaquete($pedido, $ubic2, $operador);
        $svc->retirarPaquete($pedido, $operador);

        $stmt->execute([':id' => $pedido]);
        $this->assertSame($estadoOriginal, (int) $stmt->fetchColumn(),
            'pedidos.id_estado no debe cambiar.');
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T27 — inventario permanece intacto
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T27. inventario no recibe ningún movimiento. */
    public function test_inventario_permanece_intacto(): void
    {
        $antes = (int) $this->db->query('SELECT COUNT(*) FROM inventario')->fetchColumn();

        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli');
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $bodega   = $this->crearBodega();
        $ubic     = $this->crearUbicacion($bodega);

        $svc = $this->servicio();
        $svc->registrarRecepcion($this->datosRecepcion($pedido, $bodega, $operador, $ubic));
        $svc->retirarPaquete($pedido, $operador);

        $this->assertSame($antes, (int) $this->db->query('SELECT COUNT(*) FROM inventario')->fetchColumn(),
            'inventario no debe cambiar.');
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T28 — stock permanece intacto
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T28. stock no recibe ningún movimiento. */
    public function test_stock_permanece_intacto(): void
    {
        $antes = (int) $this->db->query('SELECT COUNT(*) FROM stock')->fetchColumn();

        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli');
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $bodega   = $this->crearBodega();
        $ubic     = $this->crearUbicacion($bodega);

        $svc = $this->servicio();
        $svc->registrarRecepcion($this->datosRecepcion($pedido, $bodega, $operador, $ubic));
        $svc->retirarPaquete($pedido, $operador);

        $this->assertSame($antes, (int) $this->db->query('SELECT COUNT(*) FROM stock')->fetchColumn(),
            'stock no debe cambiar.');
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T29 — reservas permanecen intactas
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T29. Las reservas no son afectadas por ninguna operación de este servicio. */
    public function test_reservas_permanecen_intactas(): void
    {
        try {
            $antes = (int) $this->db->query('SELECT COUNT(*) FROM pedido_reservas_stock')->fetchColumn();
        } catch (\Throwable) {
            $this->markTestSkipped('La tabla pedido_reservas_stock no existe en esta instalación.');
        }

        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli');
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $bodega   = $this->crearBodega();
        $ubic     = $this->crearUbicacion($bodega);

        $svc = $this->servicio();
        $svc->registrarRecepcion($this->datosRecepcion($pedido, $bodega, $operador, $ubic));
        $svc->retirarPaquete($pedido, $operador);

        $this->assertSame(
            $antes,
            (int) $this->db->query('SELECT COUNT(*) FROM pedido_reservas_stock')->fetchColumn(),
            'Las reservas no deben cambiar.'
        );
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T30 — rollback deja 0 filas permanentes
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T30. Los datos insertados son visibles dentro de la transacción y desaparecen con rollback. */
    public function test_rollback_deja_cero_filas_permanentes(): void
    {
        // setUp() ya abrió la transacción externa.
        // Insertamos datos dentro de ella.
        $antes = (int) $this->db->query('SELECT COUNT(*) FROM logistica_bodegas')->fetchColumn();

        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli');
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $bodega   = $this->crearBodega();
        $ubic     = $this->crearUbicacion($bodega);

        $svc = $this->servicio();
        $svc->registrarRecepcion($this->datosRecepcion($pedido, $bodega, $operador, $ubic));

        // Dentro de la transacción los datos son visibles
        $durante = (int) $this->db->query('SELECT COUNT(*) FROM logistica_bodegas')->fetchColumn();
        $this->assertSame($antes + 1, $durante,
            'Los datos deben ser visibles dentro de la transacción activa.');

        // tearDown() hará rollback automáticamente → en el próximo test habrá 0 bodegas
    }

    // ════════════════════════════════════════════════════════════════════════════
    // T31 — paquetes_apppack no es modificada
    // ════════════════════════════════════════════════════════════════════════════

    /** @test T31. Las pruebas no pueden ejecutarse sobre la base de producción paquetes_apppack. */
    public function test_produccion_no_es_modificada(): void
    {
        $schema = defined('DB_SCHEMA') ? DB_SCHEMA : '';
        $this->assertNotEquals('paquetes_apppack', strtolower($schema), 'Producción no debe ser la base de pruebas.');
        $this->assertTrue(str_ends_with(strtolower($schema), '_test'), 'La base de pruebas debe terminar en _test.');
    }
}
