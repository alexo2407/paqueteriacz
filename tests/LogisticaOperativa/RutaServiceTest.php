<?php

declare(strict_types=1);

namespace Tests\LogisticaOperativa;

use PHPUnit\Framework\TestCase;
use PDO;
use RutaService;
use RutaModel;
use LogisticaOperativaException;

require_once dirname(__DIR__, 2) . '/tests/Support/TestDatabaseConnection.php';
require_once dirname(__DIR__, 2) . '/tests/Support/LogisticaTestDataFactory.php';
require_once dirname(__DIR__, 2) . '/tests/Support/RutaServiceTestable.php';
require_once dirname(__DIR__, 2) . '/modelo/logistica_operativa/RutaModel.php';
require_once dirname(__DIR__, 2) . '/services/logistica_operativa/RutaService.php';

/**
 * RutaServiceTest — Fase 5
 *
 * Pruebas unitarias e integración para RutaService y RutaModel.
 */
class RutaServiceTest extends TestCase
{
    private PDO $db;
    private \RutaServiceTestable $service;
    private RutaModel $model;

    protected function setUp(): void
    {
        assertTestDatabase();
        $this->db = \TestDatabaseConnection::nueva();
        $this->db->beginTransaction();

        $this->model = new RutaModel($this->db);
        $this->service = new \RutaServiceTestable($this->db);
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    /**
     * Auxiliar: crear un pedido dummy en la BD para asociar a una ruta.
     */
    private function crearPedidoDummy(float $montoCod = 150.00): int
    {
        $clienteId = \LogisticaTestDataFactory::crearUsuario($this->db, 'cliente');
        $pedId = \LogisticaTestDataFactory::crearPedido($this->db, $clienteId, 0, 4);
        $stmt = $this->db->prepare("UPDATE pedidos SET precio_total_local = :cod, destinatario = 'Cliente Test' WHERE id = :id");
        $stmt->execute([':cod' => $montoCod, ':id' => $pedId]);
        return $pedId;
    }

    /**
     * Test 1: Crear ruta exitosamente.
     */
    public function test_crear_ruta_exitosamente(): void
    {
        $p1 = $this->crearPedidoDummy(250.50);
        $p2 = $this->crearPedidoDummy(149.50);

        $repartidorId = \LogisticaTestDataFactory::crearUsuario($this->db, 'repartidor');
        $creadorId    = \LogisticaTestDataFactory::crearUsuario($this->db, 'operador');

        $res = $this->service->crearRuta([
            'nombre'        => 'Ruta Managua Norte',
            'fecha'         => date('Y-m-d'),
            'id_repartidor' => $repartidorId,
            'id_creada_por' => $creadorId,
            'pedidos'       => [$p1, $p2]
        ]);

        $this->assertArrayHasKey('id_ruta', $res);
        $this->assertEquals(2, $res['cantidad_pedidos']);
        $this->assertEquals(400.00, $res['total_cod']);
        $this->assertStringStartsWith('RUT-', $res['codigo']);

        // Verificar persistencia en logistica_rutas
        $rutaData = $this->model->obtenerPorId($res['id_ruta']);
        $this->assertNotNull($rutaData);
        $this->assertEquals('ASIGNADA', $rutaData['estado']);
        $this->assertEquals('Ruta Managua Norte', $rutaData['nombre']);

        // Verificar pedidos de la ruta
        $pedidosEnRuta = $this->model->obtenerPedidosDeRuta($res['id_ruta']);
        $this->assertCount(2, $pedidosEnRuta);
        $this->assertEquals(1, $pedidosEnRuta[0]['orden_visita']);
        $this->assertEquals(2, $pedidosEnRuta[1]['orden_visita']);
    }

    /**
     * Test 2: Validaciones al crear ruta con datos inválidos.
     */
    public function test_crear_ruta_validacion_datos_vacios(): void
    {
        $this->expectException(LogisticaOperativaException::class);
        $this->expectExceptionMessage('El nombre de la ruta es obligatorio.');

        $this->service->crearRuta([
            'nombre'        => '',
            'fecha'         => date('Y-m-d'),
            'id_repartidor' => 1,
            'pedidos'       => [100]
        ]);
    }

    /**
     * Test 3: Sellar una ruta exitosamente.
     */
    public function test_sellar_ruta_exitosamente(): void
    {
        $p1 = $this->crearPedidoDummy(100.00);
        $repartidorId = \LogisticaTestDataFactory::crearUsuario($this->db, 'repartidor');
        $creadorId    = \LogisticaTestDataFactory::crearUsuario($this->db, 'operador');

        $res = $this->service->crearRuta([
            'nombre'        => 'Ruta Sellado Test',
            'fecha'         => date('Y-m-d'),
            'id_repartidor' => $repartidorId,
            'id_creada_por' => $creadorId,
            'pedidos'       => [$p1]
        ]);

        $this->service->sellarRuta($res['id_ruta'], $creadorId);

        $rutaData = $this->model->obtenerPorId($res['id_ruta']);
        $this->assertEquals('SELLADA', $rutaData['estado']);
        $this->assertNotNull($rutaData['sellada_at']);
        $this->assertEquals($creadorId, (int)$rutaData['id_sellada_por']);
    }

    /**
     * Test 4: Sellar una ruta ya sellada debe lanzar excepción.
     */
    public function test_sellar_ruta_ya_sellada_lanza_excepcion(): void
    {
        $p1 = $this->crearPedidoDummy(100.00);
        $repartidorId = \LogisticaTestDataFactory::crearUsuario($this->db, 'repartidor');
        $creadorId    = \LogisticaTestDataFactory::crearUsuario($this->db, 'operador');

        $res = $this->service->crearRuta([
            'nombre'        => 'Ruta Repetida Test',
            'fecha'         => date('Y-m-d'),
            'id_repartidor' => $repartidorId,
            'id_creada_por' => $creadorId,
            'pedidos'       => [$p1]
        ]);

        $this->service->sellarRuta($res['id_ruta'], $creadorId);

        $this->expectException(LogisticaOperativaException::class);
        $this->expectExceptionMessage('ya está sellada');

        $this->service->sellarRuta($res['id_ruta'], $creadorId);
    }

    /**
     * Test 5: Listar rutas con filtros.
     */
    public function test_listar_rutas_con_filtros(): void
    {
        $p1 = $this->crearPedidoDummy(50.00);
        $repartidorId = \LogisticaTestDataFactory::crearUsuario($this->db, 'repartidor');
        $creadorId    = \LogisticaTestDataFactory::crearUsuario($this->db, 'operador');

        $fechaHoy = date('Y-m-d');
        $this->service->crearRuta([
            'nombre'        => 'Ruta Filtro Test',
            'fecha'         => $fechaHoy,
            'id_repartidor' => $repartidorId,
            'id_creada_por' => $creadorId,
            'pedidos'       => [$p1]
        ]);

        $list = $this->model->listarConFiltros(['fecha' => $fechaHoy, 'estado' => 'ASIGNADA']);
        $this->assertNotEmpty($list);
        $this->assertEquals('Ruta Filtro Test', $list[0]['nombre']);
    }
}
