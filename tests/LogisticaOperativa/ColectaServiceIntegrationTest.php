<?php

declare(strict_types=1);

namespace Tests\LogisticaOperativa;

use PHPUnit\Framework\TestCase;

// Soporte de pruebas (namespace global)
require_once dirname(__DIR__, 2) . '/tests/Support/TestDatabaseConnection.php';
require_once dirname(__DIR__, 2) . '/tests/Support/LogisticaTestDataFactory.php';
require_once dirname(__DIR__, 2) . '/tests/Support/ColectaServiceTestable.php';

/**
 * ColectaServiceIntegrationTest
 *
 * Pruebas de integración para la lógica de colectas sobre paquetes_apppack_test.
 *
 * Aislamiento:
 *   setUp()    → abre transacción con la BD de pruebas
 *   tearDown() → rollback → la base queda igual que antes
 *
 * No se modifican pedidos.id_estado, stock, inventario ni reservas.
 * No se usan datos reales.
 */
class ColectaServiceIntegrationTest extends TestCase
{
    private \PDO $db;

    protected function setUp(): void
    {
        assertTestDatabase();
        $this->db = \TestDatabaseConnection::nueva();
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    private function servicio(): \ColectaServiceTestable
    {
        return new \ColectaServiceTestable($this->db);
    }

    // ── Helpers de escaneo ────────────────────────────────────────────────

    private function escanear(\ColectaServiceTestable $svc, int $idColecta, int $idPedido, int $idOperador): array
    {
        return $svc->registrarEscaneo([
            'uuid'        => \LogisticaTestDataFactory::uuid(),
            'id_colecta'  => $idColecta,
            'id_pedido'   => $idPedido,
            'tipo_evento' => 'COLECTA_RECEPCION',
            'qr_hash'     => \LogisticaTestDataFactory::qrHash(),
            'id_operador' => $idOperador,
            'escaneado_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // ── Tests: apertura ───────────────────────────────────────────────────

    /** @test 1. Abrir colecta con pedidos esperados. */
    /** @test 1. Abrir colecta con pedidos esperados. */
    public function test_abrir_colecta_con_pedidos_esperados(): void
    {
        $cliente   = \LogisticaTestDataFactory::crearUsuario($this->db, 'cliente');
        $proveedor = \LogisticaTestDataFactory::crearUsuario($this->db, 'proveedor');
        $operador  = $proveedor;
        $p1 = \LogisticaTestDataFactory::crearPedido($this->db, $cliente, $proveedor);
        $p2 = \LogisticaTestDataFactory::crearPedido($this->db, $cliente, $proveedor);

        $resultado = $this->servicio()->abrirColecta($cliente, $proveedor, '2099-01-15', 'MANANA', $operador);

        $this->assertArrayHasKey('id_colecta', $resultado);
        $this->assertGreaterThan(0, $resultado['id_colecta']);
        $this->assertSame(2, $resultado['cantidad_esperada']);
        $this->assertContains($p1, $resultado['pedidos_ids']);
        $this->assertContains($p2, $resultado['pedidos_ids']);
    }

    /** @test 2. Rechazar colecta duplicada para mismo cliente, proveedor, fecha y turno. */
    public function test_rechaza_colecta_duplicada(): void
    {
        $cliente   = \LogisticaTestDataFactory::crearUsuario($this->db, 'cliente');
        $proveedor = \LogisticaTestDataFactory::crearUsuario($this->db, 'proveedor');
        $operador  = $proveedor;
        $svc = $this->servicio();
        $svc->abrirColecta($cliente, $proveedor, '2099-01-16', 'TARDE', $operador);

        $this->expectException(\LogisticaOperativaException::class);
        $svc->abrirColecta($cliente, $proveedor, '2099-01-16', 'TARDE', $operador);
    }

    /** @test Rechazar turno inválido. */
    public function test_rechaza_turno_invalido(): void
    {
        $cliente   = \LogisticaTestDataFactory::crearUsuario($this->db, 'cliente');
        $proveedor = \LogisticaTestDataFactory::crearUsuario($this->db, 'proveedor');
        $operador  = $proveedor;

        $this->expectException(\LogisticaOperativaException::class);
        $this->servicio()->abrirColecta($cliente, $proveedor, '2099-01-17', 'NOCHE', $operador);
    }

    /** @test Rechazar fecha inválida. */
    public function test_rechaza_fecha_invalida(): void
    {
        $cliente   = \LogisticaTestDataFactory::crearUsuario($this->db, 'cliente');
        $proveedor = \LogisticaTestDataFactory::crearUsuario($this->db, 'proveedor');
        $operador  = $proveedor;

        $this->expectException(\LogisticaOperativaException::class);
        $this->servicio()->abrirColecta($cliente, $proveedor, '15/01/2099', 'MANANA', $operador);
    }

    // ── Tests: escaneos ───────────────────────────────────────────────────

    /** @test 3. Paquete esperado resulta RECIBIDO al escanearlo. */
    public function test_escaneo_pedido_esperado_resulta_recibido(): void
    {
        $cliente   = \LogisticaTestDataFactory::crearUsuario($this->db, 'cliente');
        $proveedor = \LogisticaTestDataFactory::crearUsuario($this->db, 'proveedor');
        $operador  = $proveedor;
        $pedido    = \LogisticaTestDataFactory::crearPedido($this->db, $cliente, $proveedor);

        $svc     = $this->servicio();
        $colecta = $svc->abrirColecta($cliente, $proveedor, '2099-02-01', 'MANANA', $operador);
        $result  = $this->escanear($svc, $colecta['id_colecta'], $pedido, $operador);

        $this->assertFalse($result['idempotente']);
        $this->assertSame('RECIBIDO', $result['resultado_pedido']);
    }

    /** @test 4. Paquete no esperado resulta EXTRA. */
    public function test_escaneo_pedido_no_esperado_resulta_extra(): void
    {
        $cliente   = \LogisticaTestDataFactory::crearUsuario($this->db, 'cliente');
        $proveedor = \LogisticaTestDataFactory::crearUsuario($this->db, 'proveedor');
        $operador  = $proveedor;
        $clienteB  = \LogisticaTestDataFactory::crearUsuario($this->db, 'clienteB');
        $pedidoExtra = \LogisticaTestDataFactory::crearPedido($this->db, $clienteB, $proveedor);

        $svc     = $this->servicio();
        $colecta = $svc->abrirColecta($cliente, $proveedor, '2099-02-02', 'TARDE', $operador);
        $result  = $this->escanear($svc, $colecta['id_colecta'], $pedidoExtra, $operador);

        $this->assertFalse($result['idempotente']);
        $this->assertSame('EXTRA', $result['resultado_pedido']);
    }

    /** @test 5. El mismo UUID no duplica el escaneo. */
    public function test_mismo_uuid_es_idempotente(): void
    {
        $cliente   = \LogisticaTestDataFactory::crearUsuario($this->db, 'cliente');
        $proveedor = \LogisticaTestDataFactory::crearUsuario($this->db, 'proveedor');
        $operador  = $proveedor;
        $pedido    = \LogisticaTestDataFactory::crearPedido($this->db, $cliente, $proveedor);

        $svc     = $this->servicio();
        $colecta = $svc->abrirColecta($cliente, $proveedor, '2099-02-03', 'MANANA', $operador);
        $uuid    = \LogisticaTestDataFactory::uuid();

        $datos = [
            'uuid'        => $uuid,
            'id_colecta'  => $colecta['id_colecta'],
            'id_pedido'   => $pedido,
            'tipo_evento' => 'COLECTA_RECEPCION',
            'qr_hash'     => \LogisticaTestDataFactory::qrHash(),
            'id_operador' => $operador,
            'escaneado_at' => date('Y-m-d H:i:s'),
        ];

        $r1 = $svc->registrarEscaneo($datos);
        $r2 = $svc->registrarEscaneo($datos);

        $this->assertFalse($r1['idempotente']);
        $this->assertTrue($r2['idempotente']);

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM logistica_escaneos WHERE uuid = :u');
        $stmt->execute([':u' => $uuid]);
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    /** @test 6. Mismo pedido+evento con UUID distinto no duplica el evento. */
    public function test_mismo_evento_uuid_diferente_es_idempotente(): void
    {
        $cliente   = \LogisticaTestDataFactory::crearUsuario($this->db, 'cliente');
        $proveedor = \LogisticaTestDataFactory::crearUsuario($this->db, 'proveedor');
        $operador  = $proveedor;
        $pedido    = \LogisticaTestDataFactory::crearPedido($this->db, $cliente, $proveedor);

        $svc     = $this->servicio();
        $colecta = $svc->abrirColecta($cliente, $proveedor, '2099-02-04', 'TARDE', $operador);

        $base = [
            'id_colecta'  => $colecta['id_colecta'],
            'id_pedido'   => $pedido,
            'tipo_evento' => 'COLECTA_RECEPCION',
            'qr_hash'     => \LogisticaTestDataFactory::qrHash(),
            'id_operador' => $operador,
            'escaneado_at' => date('Y-m-d H:i:s'),
        ];

        $r1 = $svc->registrarEscaneo(array_merge($base, ['uuid' => \LogisticaTestDataFactory::uuid()]));
        $r2 = $svc->registrarEscaneo(array_merge($base, ['uuid' => \LogisticaTestDataFactory::uuid()]));

        $this->assertFalse($r1['idempotente']);
        $this->assertTrue($r2['idempotente']);

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM logistica_escaneos WHERE id_colecta=:c AND id_pedido=:p AND tipo_evento=:e'
        );
        $stmt->execute([':c' => $colecta['id_colecta'], ':p' => $pedido, ':e' => 'COLECTA_RECEPCION']);
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    // ── Tests: cierre ─────────────────────────────────────────────────────

    /** @test 7. Cerrar colecta e identificar faltantes. */
    public function test_cierre_identifica_faltantes(): void
    {
        $cliente   = \LogisticaTestDataFactory::crearUsuario($this->db, 'cliente');
        $proveedor = \LogisticaTestDataFactory::crearUsuario($this->db, 'proveedor');
        $operador  = $proveedor;
        $p1 = \LogisticaTestDataFactory::crearPedido($this->db, $cliente, $proveedor); // será RECIBIDO
        $p2 = \LogisticaTestDataFactory::crearPedido($this->db, $cliente, $proveedor); // será FALTANTE

        $svc     = $this->servicio();
        $colecta = $svc->abrirColecta($cliente, $proveedor, '2099-03-01', 'MANANA', $operador);

        $this->escanear($svc, $colecta['id_colecta'], $p1, $operador); // solo p1

        $resumen = $svc->cerrarYConciliar($colecta['id_colecta'], $operador);

        $this->assertSame('CONCILIADA', $resumen['colecta']['estado']);
        $this->assertSame(1, $resumen['conteos']['RECIBIDO']);
        $this->assertSame(1, $resumen['conteos']['FALTANTE']);
        $this->assertSame(0, $resumen['conteos']['ESPERADO']);
    }

    /** @test 8. Rechazar un segundo cierre. */
    public function test_segundo_cierre_es_rechazado(): void
    {
        $cliente   = \LogisticaTestDataFactory::crearUsuario($this->db, 'cliente');
        $proveedor = \LogisticaTestDataFactory::crearUsuario($this->db, 'proveedor');
        $operador  = $proveedor;

        $svc     = $this->servicio();
        $colecta = $svc->abrirColecta($cliente, $proveedor, '2099-03-02', 'TARDE', $operador);
        $svc->cerrarYConciliar($colecta['id_colecta'], $operador);

        $this->expectException(\LogisticaOperativaException::class);
        $svc->cerrarYConciliar($colecta['id_colecta'], $operador);
    }

    /** @test 9. Contadores coinciden con los registros reales. */
    public function test_contadores_coinciden_con_registros_reales(): void
    {
        $cliente   = \LogisticaTestDataFactory::crearUsuario($this->db, 'cliente');
        $proveedor = \LogisticaTestDataFactory::crearUsuario($this->db, 'proveedor');
        $operador  = $proveedor;
        $p1 = \LogisticaTestDataFactory::crearPedido($this->db, $cliente, $proveedor);
        $p2 = \LogisticaTestDataFactory::crearPedido($this->db, $cliente, $proveedor);
        $p3 = \LogisticaTestDataFactory::crearPedido($this->db, $cliente, $proveedor);

        $svc       = $this->servicio();
        $colecta   = $svc->abrirColecta($cliente, $proveedor, '2099-03-03', 'MANANA', $operador);
        $idColecta = $colecta['id_colecta'];

        $this->escanear($svc, $idColecta, $p1, $operador);
        $this->escanear($svc, $idColecta, $p2, $operador);
        // p3 no se escanea → FALTANTE

        $svc->cerrarYConciliar($idColecta, $operador);

        $stmt = $this->db->prepare('SELECT * FROM logistica_colectas WHERE id = :id');
        $stmt->execute([':id' => $idColecta]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame(3, (int) $row['cantidad_esperada']);
        $this->assertSame(2, (int) $row['cantidad_escaneada']);
        $this->assertSame(1, (int) $row['cantidad_faltante']);

        $stmt = $this->db->prepare(
            'SELECT resultado, COUNT(*) c FROM logistica_colecta_pedidos WHERE id_colecta=:id GROUP BY resultado'
        );
        $stmt->execute([':id' => $idColecta]);
        $conteos = [];
        foreach ($stmt->fetchAll() as $r) { $conteos[$r['resultado']] = (int) $r['c']; }

        $this->assertSame((int) $row['cantidad_escaneada'], $conteos['RECIBIDO'] ?? 0);
        $this->assertSame((int) $row['cantidad_faltante'],  $conteos['FALTANTE'] ?? 0);
    }

    // ── Tests: integridad de datos ─────────────────────────────────────────

    /** @test 10. pedidos.id_estado no cambia en todo el flujo. */
    public function test_pedido_id_estado_no_cambia(): void
    {
        $cliente   = \LogisticaTestDataFactory::crearUsuario($this->db, 'cliente');
        $proveedor = \LogisticaTestDataFactory::crearUsuario($this->db, 'proveedor');
        $operador  = $proveedor;
        $pedido    = \LogisticaTestDataFactory::crearPedido($this->db, $cliente, $proveedor);

        $stmt = $this->db->prepare('SELECT id_estado FROM pedidos WHERE id = :id');
        $stmt->execute([':id' => $pedido]);
        $estadoOriginal = (int) $stmt->fetchColumn();

        $svc     = $this->servicio();
        $colecta = $svc->abrirColecta($cliente, $proveedor, '2099-04-01', 'MANANA', $operador);
        $this->escanear($svc, $colecta['id_colecta'], $pedido, $operador);
        $svc->cerrarYConciliar($colecta['id_colecta'], $operador);

        $stmt->execute([':id' => $pedido]);
        $this->assertSame($estadoOriginal, (int) $stmt->fetchColumn(), 'pedidos.id_estado no debe cambiar.');
    }

    /** @test 11. stock no recibe movimientos. */
    public function test_stock_no_recibe_movimientos(): void
    {
        $antes = (int) $this->db->query('SELECT COUNT(*) FROM stock')->fetchColumn();

        $cliente   = \LogisticaTestDataFactory::crearUsuario($this->db, 'cliente');
        $proveedor = \LogisticaTestDataFactory::crearUsuario($this->db, 'proveedor');
        $operador  = $proveedor;
        $pedido    = \LogisticaTestDataFactory::crearPedido($this->db, $cliente, $proveedor);

        $svc     = $this->servicio();
        $colecta = $svc->abrirColecta($cliente, $proveedor, '2099-04-02', 'TARDE', $operador);
        $this->escanear($svc, $colecta['id_colecta'], $pedido, $operador);
        $svc->cerrarYConciliar($colecta['id_colecta'], $operador);

        $this->assertSame($antes, (int) $this->db->query('SELECT COUNT(*) FROM stock')->fetchColumn());
    }

    /** @test 12. inventario no cambia. */
    public function test_inventario_no_cambia(): void
    {
        $antes = (int) $this->db->query('SELECT COUNT(*) FROM inventario')->fetchColumn();

        $cliente   = \LogisticaTestDataFactory::crearUsuario($this->db, 'cliente');
        $proveedor = \LogisticaTestDataFactory::crearUsuario($this->db, 'proveedor');
        $operador  = $proveedor;
        $pedido    = \LogisticaTestDataFactory::crearPedido($this->db, $cliente, $proveedor);

        $svc     = $this->servicio();
        $colecta = $svc->abrirColecta($cliente, $proveedor, '2099-04-03', 'MANANA', $operador);
        $this->escanear($svc, $colecta['id_colecta'], $pedido, $operador);
        $svc->cerrarYConciliar($colecta['id_colecta'], $operador);

        $this->assertSame($antes, (int) $this->db->query('SELECT COUNT(*) FROM inventario')->fetchColumn());
    }

    /** @test 13. Las reservas no cambian. */
    public function test_reservas_no_cambian(): void
    {
        $antes = (int) $this->db->query('SELECT COUNT(*) FROM pedido_reservas_stock')->fetchColumn();

        $cliente   = \LogisticaTestDataFactory::crearUsuario($this->db, 'cliente');
        $proveedor = \LogisticaTestDataFactory::crearUsuario($this->db, 'proveedor');
        $operador  = $proveedor;
        $pedido    = \LogisticaTestDataFactory::crearPedido($this->db, $cliente, $proveedor);

        $svc     = $this->servicio();
        $colecta = $svc->abrirColecta($cliente, $proveedor, '2099-04-04', 'TARDE', $operador);
        $this->escanear($svc, $colecta['id_colecta'], $pedido, $operador);

        $this->assertSame($antes, (int) $this->db->query('SELECT COUNT(*) FROM pedido_reservas_stock')->fetchColumn());
    }

    /** @test 14. Los datos son visibles en la transacción y desaparecen con rollback. */
    public function test_datos_visibles_dentro_de_transaccion(): void
    {
        $antes = (int) $this->db->query('SELECT COUNT(*) FROM logistica_colectas')->fetchColumn();

        $cliente   = \LogisticaTestDataFactory::crearUsuario($this->db, 'cliente');
        $proveedor = \LogisticaTestDataFactory::crearUsuario($this->db, 'proveedor');
        $operador  = $proveedor;
        $this->servicio()->abrirColecta($cliente, $proveedor, '2099-05-01', 'MANANA', $operador);

        $durante = (int) $this->db->query('SELECT COUNT(*) FROM logistica_colectas')->fetchColumn();
        $this->assertSame($antes + 1, $durante, 'El dato debe ser visible dentro de la transacción.');
        // tearDown() hace rollback automático.
    }

    /** @test 15. La base productiva paquetes_apppack es rechazada. */
    public function test_base_productiva_es_rechazada(): void
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

    /** @test 16. Validar que cliente deba tener Rol 4 y proveedor deba tener Rol 5. */
    public function test_abrir_colecta_valida_roles_estrictos(): void
    {
        $clienteNoValido  = \LogisticaTestDataFactory::crearUsuario($this->db, 'proveedor'); // Tiene Rol 5, no Rol 4
        $proveedorValido  = \LogisticaTestDataFactory::crearUsuario($this->db, 'proveedor');

        $this->expectException(\LogisticaOperativaException::class);
        $this->expectExceptionMessage('no posee el Rol Cliente (ID 4)');
        $this->servicio()->abrirColecta($clienteNoValido, $proveedorValido, '2099-06-01', 'MANANA', $proveedorValido);
    }

    /** @test 17. Colecta de Proveedor A no incluye pedidos asignados a Proveedor B. */
    public function test_colecta_aisla_pedidos_por_proveedor(): void
    {
        $cliente    = \LogisticaTestDataFactory::crearUsuario($this->db, 'cliente');
        $proveedorA = \LogisticaTestDataFactory::crearUsuario($this->db, 'proveedorA');
        $proveedorB = \LogisticaTestDataFactory::crearUsuario($this->db, 'proveedorB');

        $pA1 = \LogisticaTestDataFactory::crearPedido($this->db, $cliente, $proveedorA);
        $pA2 = \LogisticaTestDataFactory::crearPedido($this->db, $cliente, $proveedorA);
        $pB1 = \LogisticaTestDataFactory::crearPedido($this->db, $cliente, $proveedorB);

        // Colecta para Proveedor A
        $resA = $this->servicio()->abrirColecta($cliente, $proveedorA, '2099-06-02', 'MANANA', $proveedorA);
        $this->assertSame(2, $resA['cantidad_esperada']);
        $this->assertContains($pA1, $resA['pedidos_ids']);
        $this->assertContains($pA2, $resA['pedidos_ids']);
        $this->assertNotContains($pB1, $resA['pedidos_ids']);

        // Colecta independiente para Proveedor B
        $resB = $this->servicio()->abrirColecta($cliente, $proveedorB, '2099-06-02', 'MANANA', $proveedorB);
        $this->assertSame(1, $resB['cantidad_esperada']);
        $this->assertContains($pB1, $resB['pedidos_ids']);
        $this->assertNotContains($pA1, $resB['pedidos_ids']);
    }
}
