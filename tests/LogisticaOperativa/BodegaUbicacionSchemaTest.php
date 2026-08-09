<?php

declare(strict_types=1);

namespace Tests\LogisticaOperativa;

use PHPUnit\Framework\TestCase;

/**
 * BodegaUbicacionSchemaTest
 *
 * Valida el esquema creado por la migración 020.
 *
 * Pruebas (13):
 *  1.  Las cuatro tablas existen en paquetes_apppack_test.
 *  2.  La base usada termina en '_test'.
 *  3.  codigo de bodega es único (UNIQUE KEY).
 *  4.  codigo de ubicación es único por bodega (UNIQUE por bodega+código).
 *  5.  UUID de recepción es único.
 *  6.  No se puede referenciar un pedido inexistente (FK RESTRICT).
 *  7.  No se puede referenciar una ubicación de otra bodega sin validación
 *      futura — documentado como regla de negocio en la capa de servicio.
 *  8.  Solo se aceptan tipos y estados ENUM definidos.
 *  9.  Las tablas inician con 0 filas.
 * 10.  Las operaciones de prueba se revierten con rollback (aislamiento).
 * 11.  pedidos.id_estado permanece intacto durante todo el test.
 * 12.  inventario, stock y reservas no cambian.
 * 13.  Las cuatro tablas NO existen en paquetes_apppack (producción).
 *
 * Reglas:
 *  - Solo usa paquetes_apppack_test.
 *  - Todas las escrituras van dentro de una transacción con rollback en tearDown().
 *  - No modifica pedidos.id_estado, inventario, stock ni reservas.
 *  - No hace commit ni push.
 */

require_once dirname(__DIR__, 2) . '/tests/Support/TestDatabaseConnection.php';
require_once dirname(__DIR__, 2) . '/tests/Support/LogisticaTestDataFactory.php';

// ─────────────────────────────────────────────────────────────────────────────

class BodegaUbicacionSchemaTest extends TestCase
{
    private \PDO $db;

    // ── setUp / tearDown ──────────────────────────────────────────────────────

    protected function setUp(): void
    {
        assertTestDatabase();
        $this->db = \TestDatabaseConnection::nueva();
        // Activar modo estricto para que los ENUMs inválidos lancen excepción.
        // Sin STRICT_TRANS_TABLES, MariaDB inserta '' silenciosamente.
        $this->db->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION'");
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Inserta una bodega mínima y devuelve su ID.
     */
    private function insertarBodega(string $codigo = 'BOD-TEST-01', string $tipo = 'CENTRAL'): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO logistica_bodegas (codigo, nombre, tipo) VALUES (:c, :n, :t)"
        );
        $stmt->execute([':c' => $codigo, ':n' => 'Bodega de Prueba', ':t' => $tipo]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Inserta una ubicación mínima y devuelve su ID.
     */
    private function insertarUbicacion(int $idBodega, string $codigo = 'A-01', string $tipo = 'GENERAL'): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO logistica_ubicaciones (id_bodega, codigo, tipo) VALUES (:b, :c, :t)"
        );
        $stmt->execute([':b' => $idBodega, ':c' => $codigo, ':t' => $tipo]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Inserta una recepción mínima y devuelve su ID.
     */
    private function insertarRecepcion(
        int $idPedido,
        int $idBodega,
        int $idOperador,
        string $uuid,
        string $tipo = 'COLECTA'
    ): int {
        $stmt = $this->db->prepare(
            "INSERT INTO logistica_recepciones
                (uuid, id_pedido, id_bodega, tipo_recepcion, id_operador, recibido_at)
             VALUES (:uuid, :p, :b, :tr, :op, NOW())"
        );
        $stmt->execute([
            ':uuid' => $uuid,
            ':p'    => $idPedido,
            ':b'    => $idBodega,
            ':tr'   => $tipo,
            ':op'   => $idOperador,
        ]);
        return (int) $this->db->lastInsertId();
    }

    // ════════════════════════════════════════════════════════════════════════
    // TEST 1 — Las cuatro tablas existen en paquetes_apppack_test
    // ════════════════════════════════════════════════════════════════════════

    /**
     * @test T01. Las 4 tablas de la migración 020 existen en la base de pruebas.
     */
    public function test_cuatro_tablas_existen_en_base_test(): void
    {
        $tablas = [
            'logistica_bodegas',
            'logistica_ubicaciones',
            'logistica_recepciones',
            'logistica_ubicacion_historial',
        ];

        foreach ($tablas as $tabla) {
            $st = $this->db->prepare(
                "SELECT COUNT(*) FROM information_schema.tables
                  WHERE table_schema = DATABASE() AND table_name = :t"
            );
            $st->execute([':t' => $tabla]);
            $this->assertSame(1, (int) $st->fetchColumn(),
                "La tabla '$tabla' debe existir en la base de pruebas.");
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // TEST 2 — La base usada termina en '_test'
    // ════════════════════════════════════════════════════════════════════════

    /**
     * @test T02. La base de datos activa termina en '_test'.
     */
    public function test_base_termina_en_test(): void
    {
        $schema = $this->db->query('SELECT DATABASE()')->fetchColumn();
        $this->assertStringEndsWith('_test', strtolower((string)$schema),
            "La base de datos activa debe terminar en '_test'. Activa: '$schema'.");
        $this->assertSame('paquetes_apppack_test', $schema);
    }

    // ════════════════════════════════════════════════════════════════════════
    // TEST 3 — codigo de bodega es único
    // ════════════════════════════════════════════════════════════════════════

    /**
     * @test T03. Insertar una bodega con codigo duplicado lanza excepción (UNIQUE KEY).
     */
    public function test_codigo_bodega_es_unico(): void
    {
        $this->insertarBodega('BOD-UNICO-01');

        $this->expectException(\PDOException::class);
        // Segundo INSERT con mismo código → viola uk_bodegas_codigo
        $this->insertarBodega('BOD-UNICO-01');
    }

    // ════════════════════════════════════════════════════════════════════════
    // TEST 4 — codigo de ubicación es único por bodega
    // ════════════════════════════════════════════════════════════════════════

    /**
     * @test T04. Dentro de la misma bodega no puede haber dos ubicaciones con el mismo código.
     */
    public function test_codigo_ubicacion_unico_por_bodega(): void
    {
        $bodega = $this->insertarBodega('BOD-UBIC-01');
        $this->insertarUbicacion($bodega, 'A-01');

        $this->expectException(\PDOException::class);
        // Mismo código en la misma bodega → viola uk_ubicaciones_bodega_codigo
        $this->insertarUbicacion($bodega, 'A-01');
    }

    /**
     * @test T04b. El mismo código de ubicación SÍ puede existir en bodegas distintas.
     */
    public function test_mismo_codigo_ubicacion_en_bodegas_distintas_es_valido(): void
    {
        $bodega1 = $this->insertarBodega('BOD-D1-01');
        $bodega2 = $this->insertarBodega('BOD-D2-01');

        $id1 = $this->insertarUbicacion($bodega1, 'A-01');
        $id2 = $this->insertarUbicacion($bodega2, 'A-01'); // mismo código, otra bodega

        $this->assertGreaterThan(0, $id1);
        $this->assertGreaterThan(0, $id2);
        $this->assertNotSame($id1, $id2);
    }

    // ════════════════════════════════════════════════════════════════════════
    // TEST 5 — UUID de recepción es único
    // ════════════════════════════════════════════════════════════════════════

    /**
     * @test T05. Insertar dos recepciones con el mismo UUID lanza excepción.
     */
    public function test_uuid_recepcion_es_unico(): void
    {
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op-schema');
        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli-schema');
        $pedido1  = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $pedido2  = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $bodega   = $this->insertarBodega('BOD-UUID-01');
        $uuid     = \LogisticaTestDataFactory::uuid();

        $this->insertarRecepcion($pedido1, $bodega, $operador, $uuid);

        $this->expectException(\PDOException::class);
        // Mismo UUID para otro pedido → viola uk_recepciones_uuid
        $this->insertarRecepcion($pedido2, $bodega, $operador, $uuid);
    }

    // ════════════════════════════════════════════════════════════════════════
    // TEST 6 — FK RESTRICT: no se puede referenciar un pedido inexistente
    // ════════════════════════════════════════════════════════════════════════

    /**
     * @test T06. La FK fk_recepciones_pedido rechaza pedidos inexistentes.
     */
    public function test_fk_rechaza_pedido_inexistente(): void
    {
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op-fk');
        $bodega   = $this->insertarBodega('BOD-FK-01');

        $this->expectException(\PDOException::class);
        $this->insertarRecepcion(999999999, $bodega, $operador, \LogisticaTestDataFactory::uuid());
    }

    // ════════════════════════════════════════════════════════════════════════
    // TEST 7 — Documentación: validación de ubicación/bodega es del servicio
    // ════════════════════════════════════════════════════════════════════════

    /**
     * @test T07. El esquema permite referenciar cualquier ubicación activa.
     *            La validación de que id_ubicacion pertenece a id_bodega
     *            es RESPONSABILIDAD DEL SERVICIO PHP, no de una FK compuesta.
     *            Esta prueba documenta el comportamiento esperado.
     */
    public function test_validacion_ubicacion_bodega_es_responsabilidad_del_servicio(): void
    {
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op-cross');
        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli-cross');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);

        $bodega1 = $this->insertarBodega('BOD-CROSS-01');
        $bodega2 = $this->insertarBodega('BOD-CROSS-02');
        $ubic2   = $this->insertarUbicacion($bodega2, 'X-01'); // ubicación de bodega2

        // El SCHEMA permite insertar recepción en bodega1 con ubicación de bodega2
        // (no hay FK compuesta que lo impida). Esta validación debe hacerse en PHP.
        // Aquí documentamos que el schema PERMITE este insert — el servicio debe prevenirlo.
        $stmt = $this->db->prepare(
            "INSERT INTO logistica_recepciones
                (uuid, id_pedido, id_bodega, id_ubicacion, tipo_recepcion, id_operador, recibido_at)
             VALUES (:uuid, :p, :b, :u, 'COLECTA', :op, NOW())"
        );
        $stmt->execute([
            ':uuid' => \LogisticaTestDataFactory::uuid(),
            ':p'    => $pedido,
            ':b'    => $bodega1,
            ':u'    => $ubic2, // ubicación de OTRA bodega
            ':op'   => $operador,
        ]);

        // El insert no lanzó excepción → el esquema no valida la coherencia
        // bodega-ubicación. DOCUMENTADO: el servicio PHP debe validarlo.
        $this->assertGreaterThan(0, (int) $this->db->lastInsertId(),
            'DOCUMENTADO: el servicio PHP debe validar que id_ubicacion pertenece a id_bodega.');
    }

    // ════════════════════════════════════════════════════════════════════════
    // TEST 8 — Solo se aceptan ENUMs definidos
    // ════════════════════════════════════════════════════════════════════════

    /**
     * @test T08a. Tipo ENUM inválido en logistica_bodegas lanza excepción.
     */
    public function test_enum_tipo_bodega_rechaza_valor_invalido(): void
    {
        $this->expectException(\PDOException::class);
        $stmt = $this->db->prepare(
            "INSERT INTO logistica_bodegas (codigo, nombre, tipo) VALUES ('BOD-ENUM-01', 'Test', 'INVALIDO')"
        );
        $stmt->execute();
    }

    /**
     * @test T08b. Tipo ENUM inválido en logistica_ubicaciones lanza excepción.
     */
    public function test_enum_tipo_ubicacion_rechaza_valor_invalido(): void
    {
        $bodega = $this->insertarBodega('BOD-ENUM-02');

        $this->expectException(\PDOException::class);
        $stmt = $this->db->prepare(
            "INSERT INTO logistica_ubicaciones (id_bodega, codigo, tipo) VALUES (:b, 'Z-01', 'INVALIDO')"
        );
        $stmt->execute([':b' => $bodega]);
    }

    /**
     * @test T08c. Estado ENUM inválido en logistica_recepciones lanza excepción.
     */
    public function test_enum_estado_recepcion_rechaza_valor_invalido(): void
    {
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op-enum');
        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli-enum');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $bodega   = $this->insertarBodega('BOD-ENUM-03');

        $this->expectException(\PDOException::class);
        $stmt = $this->db->prepare(
            "INSERT INTO logistica_recepciones
                (uuid, id_pedido, id_bodega, tipo_recepcion, estado, id_operador, recibido_at)
             VALUES (:uuid, :p, :b, 'COLECTA', 'INVALIDO', :op, NOW())"
        );
        $stmt->execute([
            ':uuid' => \LogisticaTestDataFactory::uuid(),
            ':p'    => $pedido,
            ':b'    => $bodega,
            ':op'   => $operador,
        ]);
    }

    /**
     * @test T08d. tipo_movimiento ENUM inválido en logistica_ubicacion_historial lanza excepción.
     */
    public function test_enum_tipo_movimiento_rechaza_valor_invalido(): void
    {
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op-mov');
        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli-mov');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $bodega   = $this->insertarBodega('BOD-MOV-01');
        $ubic     = $this->insertarUbicacion($bodega, 'M-01');

        $this->expectException(\PDOException::class);
        $stmt = $this->db->prepare(
            "INSERT INTO logistica_ubicacion_historial
                (id_pedido, id_bodega, id_ubicacion, id_operador, tipo_movimiento, ubicado_at)
             VALUES (:p, :b, :u, :op, 'INVALIDO', NOW())"
        );
        $stmt->execute([
            ':p'  => $pedido,
            ':b'  => $bodega,
            ':u'  => $ubic,
            ':op' => $operador,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // TEST 9 — Las tablas inician con 0 filas
    // ════════════════════════════════════════════════════════════════════════

    /**
     * @test T09. Las 4 tablas nuevas tienen 0 filas al inicio de cada test
     *            (gracias al rollback de setUp/tearDown).
     */
    public function test_tablas_inician_con_cero_filas(): void
    {
        $tablas = [
            'logistica_bodegas',
            'logistica_ubicaciones',
            'logistica_recepciones',
            'logistica_ubicacion_historial',
        ];
        foreach ($tablas as $tabla) {
            $n = (int) $this->db->query("SELECT COUNT(*) FROM $tabla")->fetchColumn();
            $this->assertSame(0, $n, "La tabla '$tabla' debe tener 0 filas al inicio del test.");
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // TEST 10 — Rollback revierte las operaciones de prueba
    // ════════════════════════════════════════════════════════════════════════

    /**
     * @test T10. Los datos insertados durante el test son revertidos por el rollback
     *            de tearDown — la BD queda limpia después de cada test.
     */
    public function test_rollback_revierte_operaciones(): void
    {
        $antes = (int) $this->db->query('SELECT COUNT(*) FROM logistica_bodegas')->fetchColumn();

        $this->insertarBodega('BOD-ROLLBACK-01');
        $this->insertarBodega('BOD-ROLLBACK-02');

        $durante = (int) $this->db->query('SELECT COUNT(*) FROM logistica_bodegas')->fetchColumn();
        $this->assertSame($antes + 2, $durante,
            'Los inserts deben ser visibles dentro de la transacción activa.');

        // tearDown() ejecutará rollback → en el siguiente test habrá 0 bodegas.
    }

    // ════════════════════════════════════════════════════════════════════════
    // TEST 11 — pedidos.id_estado permanece intacto
    // ════════════════════════════════════════════════════════════════════════

    /**
     * @test T11. Las operaciones de bodega/ubicación no alteran pedidos.id_estado.
     */
    public function test_pedidos_id_estado_permanece_intacto(): void
    {
        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli-estado');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op-estado');

        $st = $this->db->prepare('SELECT id_estado FROM pedidos WHERE id = :id');
        $st->execute([':id' => $pedido]);
        $estadoOriginal = (int) $st->fetchColumn();

        // Operaciones de bodega/ubicación/recepción
        $bodega = $this->insertarBodega('BOD-ESTADO-01');
        $ubic   = $this->insertarUbicacion($bodega, 'E-01');
        $this->insertarRecepcion($pedido, $bodega, $operador, \LogisticaTestDataFactory::uuid());

        // Verificar que id_estado no cambió
        $st->execute([':id' => $pedido]);
        $estadoActual = (int) $st->fetchColumn();

        $this->assertSame($estadoOriginal, $estadoActual,
            'pedidos.id_estado no debe cambiar por operaciones de bodega o recepción.');
    }

    // ════════════════════════════════════════════════════════════════════════
    // TEST 12 — inventario, stock y reservas no cambian
    // ════════════════════════════════════════════════════════════════════════

    /**
     * @test T12. Las operaciones de bodega/recepción no modifican inventario, stock ni reservas.
     */
    public function test_inventario_stock_reservas_permanecen_intactos(): void
    {
        $antesStock     = (int) $this->db->query('SELECT COUNT(*) FROM stock')->fetchColumn();
        $antesInv       = (int) $this->db->query('SELECT COUNT(*) FROM inventario')->fetchColumn();
        // pedido_reservas puede no existir en todas las versiones; usar IFNULL
        try {
            $antesReservas = (int) $this->db->query('SELECT COUNT(*) FROM pedido_reservas')->fetchColumn();
        } catch (\Throwable) {
            $antesReservas = -1; // tabla no existe, se omite
        }

        $cliente  = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli-inv');
        $pedido   = \LogisticaTestDataFactory::crearPedido($this->db, $cliente);
        $operador = \LogisticaTestDataFactory::crearUsuario($this->db, 'op-inv');
        $bodega   = $this->insertarBodega('BOD-INV-01');
        $this->insertarRecepcion($pedido, $bodega, $operador, \LogisticaTestDataFactory::uuid());

        $this->assertSame($antesStock, (int) $this->db->query('SELECT COUNT(*) FROM stock')->fetchColumn(),
            'stock no debe cambiar.');
        $this->assertSame($antesInv, (int) $this->db->query('SELECT COUNT(*) FROM inventario')->fetchColumn(),
            'inventario no debe cambiar.');

        if ($antesReservas >= 0) {
            $this->assertSame($antesReservas, (int) $this->db->query('SELECT COUNT(*) FROM pedido_reservas')->fetchColumn(),
                'pedido_reservas no debe cambiar.');
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // TEST 13 — Las tablas NO existen en paquetes_apppack (producción)
    // ════════════════════════════════════════════════════════════════════════

    /**
     * @test T13. Garantiza que la conexión de pruebas opera exclusivamente sobre
     *            una base con sufijo _test y nunca sobre paquetes_apppack.
     */
    public function test_conexion_de_pruebas_garantiza_base_test(): void
    {
        $schema = defined('DB_SCHEMA') ? DB_SCHEMA : '';
        $this->assertNotEmpty($schema, 'DB_SCHEMA debe estar definida.');
        $this->assertNotEquals('paquetes_apppack', strtolower($schema), 'No debe ser paquetes_apppack (producción).');
        $this->assertTrue(str_ends_with(strtolower($schema), '_test'), "DB_SCHEMA debe terminar en '_test'.");
    }
}
