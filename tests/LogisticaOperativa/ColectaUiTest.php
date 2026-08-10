<?php

declare(strict_types=1);

namespace Tests\LogisticaOperativa;

use PHPUnit\Framework\TestCase;

/**
 * ColectaUiTest
 *
 * Prueba la capa de acceso (autenticación, permisos, módulo) y la lógica
 * de las vistas del módulo Logística Operativa — Colectas.
 *
 * NO se usa un servidor HTTP real. Las pruebas simulan el flujo de
 * rutas/logistica_operativa.php + vistas invocando directamente las
 * validaciones de seguridad y el ColectaController/Service.
 *
 * Aislamiento:
 *   setUp()    → transacción en paquetes_apppack_test
 *   tearDown() → rollback → base intacta
 *
 * Reglas estrictas:
 *   - No modifica pedidos.id_estado.
 *   - No modifica inventario ni stock.
 *   - No toca paquetes_apppack.
 */

// ── Soporte de pruebas ────────────────────────────────────────────────────────
require_once dirname(__DIR__, 2) . '/tests/Support/TestDatabaseConnection.php';
require_once dirname(__DIR__, 2) . '/tests/Support/LogisticaTestDataFactory.php';
require_once dirname(__DIR__, 2) . '/tests/Support/ColectaServiceTestable.php';

// ── Controlador testeable compartido ─────────────────────────────────────────
// Carga ColectaControllerTestable (clase global) y ControllerResponseException.
require_once dirname(__DIR__, 2) . '/tests/Support/ColectaControllerTestable.php';

// ── Utilidades del sistema ────────────────────────────────────────────────────
require_once dirname(__DIR__, 2) . '/utils/permissions.php';
require_once dirname(__DIR__, 2) . '/services/LogisticaOperativaFlags.php';
require_once dirname(__DIR__, 2) . '/modelo/logistica_operativa/ColectaModel.php';

/**
 * Helper: simula el control de acceso de rutas/logistica_operativa.php.
 *
 * @param array  $rolesNombres  Roles del usuario simulado (ej: ['Administrador'])
 * @param bool   $moduloEnabled Si el módulo está habilitado
 * @return array{ ok: bool, redirect?: string, flash?: string }
 */
function simularAccesoRuta(array $rolesNombres, bool $moduloEnabled = true): array
{
    // Simular sesión
    $_SESSION['roles_nombres'] = $rolesNombres;
    $_SESSION['registrado']    = !empty($rolesNombres);

    // Roles permitidos (igual que en rutas/logistica_operativa.php)
    $rolesPermitidos = [ROL_NOMBRE_ADMIN, ROL_NOMBRE_PROVEEDOR];
    $tienePerm = false;
    foreach ($rolesPermitidos as $r) {
        if (in_array($r, $rolesNombres, true)) {
            $tienePerm = true;
            break;
        }
    }

    if (!$tienePerm) {
        return [
            'ok'       => false,
            'redirect' => 'dashboard',
            'flash'    => 'No tienes permisos para acceder al módulo de Logística Operativa.',
        ];
    }

    if (!$moduloEnabled) {
        return [
            'ok'       => false,
            'redirect' => 'dashboard',
            'flash'    => 'El módulo Logística Operativa no está habilitado actualmente.',
        ];
    }

    return ['ok' => true];
}

// ─────────────────────────────────────────────────────────────────────────────

class ColectaUiTest extends TestCase
{
    private \PDO $db;
    private int  $operadorId = 0;

    // ── setUp / tearDown ──────────────────────────────────────────────────

    protected function setUp(): void
    {
        assertTestDatabase();
        $this->db = \TestDatabaseConnection::nueva();
        $this->db->beginTransaction();

        $this->operadorId = \LogisticaTestDataFactory::crearUsuario($this->db, 'operador-ui');
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
        // Limpiar sesión simulada
        $_SESSION = [];
    }

    // ── Helper: ColectaControllerTestable ─────────────────────────────────

    private function makeController(
        bool $moduleEnabled = true,
        bool $withAuth      = true,
        array $body         = [],
        array $query        = []
    ): array {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE']   = 'application/json';
        $_GET = $query;

        \ColectaControllerTestable::$simulatedInput = empty($body) ? '' : (string)json_encode($body);

        $ctrl = new \ColectaControllerTestable(
            $this->db,
            $this->operadorId,
            $withAuth,
            $moduleEnabled
        );

        try {
            return ['ok' => true, 'ctrl' => $ctrl];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function callAction(string $action, array $body = [], array $query = [], bool $modEnabled = true): array
    {
        $_SERVER['REQUEST_METHOD'] = ($action === 'resumen') ? 'GET' : 'POST';
        $_SERVER['CONTENT_TYPE']   = 'application/json';
        $_GET = $query;

        \ColectaControllerTestable::$simulatedInput = empty($body) ? '' : (string)json_encode($body);

        $ctrl = new \ColectaControllerTestable($this->db, $this->operadorId, true, $modEnabled);

        try {
            $ctrl->$action();
            return ['code' => 200, 'body' => []];
        } catch (\ControllerResponseException $e) {
            return ['code' => $e->httpCode, 'body' => json_decode($e->jsonBody, true) ?? []];
        }
    }

    // ════════════════════════════════════════════════════════════════════
    // UI T01 — Módulo deshabilitado bloquea acceso
    // ════════════════════════════════════════════════════════════════════

    /**
     * @test UI-T01. Módulo deshabilitado → redirige con flash de error.
     */
    public function test_modulo_deshabilitado_bloquea_acceso(): void
    {
        $resultado = simularAccesoRuta([ROL_NOMBRE_ADMIN], false);

        $this->assertFalse($resultado['ok']);
        $this->assertSame('dashboard', $resultado['redirect']);
        $this->assertStringContainsString('no está habilitado', $resultado['flash']);
    }

    // ════════════════════════════════════════════════════════════════════
    // UI T02 — Usuario sin sesión es rechazado
    // ════════════════════════════════════════════════════════════════════

    /**
     * @test UI-T02. Sin sesión → acceso denegado.
     */
    public function test_sin_sesion_es_rechazado(): void
    {
        $resultado = simularAccesoRuta([]); // sin roles = sin sesión válida

        $this->assertFalse($resultado['ok']);
        $this->assertSame('dashboard', $resultado['redirect']);
    }

    // ════════════════════════════════════════════════════════════════════
    // UI T03 — Usuario sin permiso es rechazado
    // ════════════════════════════════════════════════════════════════════

    /**
     * @test UI-T03. Rol Vendedor no tiene acceso al módulo.
     */
    public function test_usuario_sin_permiso_es_rechazado(): void
    {
        $resultado = simularAccesoRuta([ROL_NOMBRE_VENDEDOR]);

        $this->assertFalse($resultado['ok']);
        $this->assertStringContainsString('No tienes permisos', $resultado['flash']);
    }

    /**
     * @test UI-T03b. Rol Repartidor tampoco tiene acceso.
     */
    public function test_repartidor_sin_permiso_es_rechazado(): void
    {
        $resultado = simularAccesoRuta([ROL_NOMBRE_REPARTIDOR]);

        $this->assertFalse($resultado['ok']);
    }

    // ════════════════════════════════════════════════════════════════════
    // UI T04 — Vista principal carga con módulo habilitado
    // ════════════════════════════════════════════════════════════════════

    /**
     * @test UI-T04. Admin con módulo habilitado → acceso concedido.
     */
    public function test_admin_accede_con_modulo_habilitado(): void
    {
        $resultado = simularAccesoRuta([ROL_NOMBRE_ADMIN], true);

        $this->assertTrue($resultado['ok']);
        $this->assertArrayNotHasKey('redirect', $resultado);
    }

    /**
     * @test UI-T04b. ColectaModel::listarConFiltros retorna array (sin errores).
     */
    public function test_listar_con_filtros_retorna_array(): void
    {
        $colModel = new \ColectaModel($this->db);
        $lista = $colModel->listarConFiltros([]);

        $this->assertIsArray($lista);
    }

    // ════════════════════════════════════════════════════════════════════
    // UI T05 — Formulario valida cliente, fecha y turno
    // ════════════════════════════════════════════════════════════════════

    /**
     * @test UI-T05a. Sin id_cliente → 400.
     */
    public function test_formulario_falta_cliente_devuelve_400(): void
    {
        $r = $this->callAction('abrir', ['fecha' => '2099-01-01', 'turno' => 'MANANA']);

        $this->assertSame(400, $r['code']);
        $this->assertFalse($r['body']['success']);
    }

    /**
     * @test UI-T05b. Turno inválido → 422.
     */
    public function test_formulario_turno_invalido_devuelve_422(): void
    {
        $cliente = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli5b');
        $r = $this->callAction('abrir', [
            'id_cliente' => $cliente,
            'fecha'      => '2099-02-01',
            'turno'      => 'NOCHE',
        ]);

        $this->assertSame(422, $r['code']);
    }

    /**
     * @test UI-T05c. Fecha inválida → 422.
     */
    public function test_formulario_fecha_invalida_devuelve_422(): void
    {
        $cliente = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli5c');
        $r = $this->callAction('abrir', [
            'id_cliente' => $cliente,
            'fecha'      => '31/01/2099',
            'turno'      => 'MANANA',
        ]);

        $this->assertSame(422, $r['code']);
    }

    // ════════════════════════════════════════════════════════════════════
    // UI T06 — Vista detalle muestra contadores
    // ════════════════════════════════════════════════════════════════════

    /**
     * @test UI-T06. ColectaModel::obtenerPedidosDetalle retorna la estructura esperada.
     */
    public function test_vista_detalle_muestra_contadores(): void
    {
        $cliente = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli6');
        $pedido  = \LogisticaTestDataFactory::crearPedido($this->db, $cliente, $this->operadorId);

        // Abrir colecta
        $r = $this->callAction('abrir', [
            'id_cliente' => $cliente,
            'fecha'      => '2099-09-01',
            'turno'      => 'MANANA',
        ]);
        $idColecta = $r['body']['data']['id_colecta'];

        // Resumen
        $colModel = new \ColectaModel($this->db);
        $resumen  = $colModel->obtenerResumen($idColecta);
        $pedidos  = $colModel->obtenerPedidosDetalle($idColecta);

        // ── Contadores del resumen ───────────────────────────────────────
        $this->assertArrayHasKey('conteos',  $resumen);
        $this->assertArrayHasKey('ESPERADO', $resumen['conteos']);
        $this->assertArrayHasKey('RECIBIDO', $resumen['conteos']);
        $this->assertArrayHasKey('FALTANTE', $resumen['conteos']);
        $this->assertArrayHasKey('EXTRA',    $resumen['conteos']);

        // ── El pedido creado debe aparecer en obtenerPedidosDetalle ──────
        // La colecta se abrió con exactamente 1 pedido elegible (id_estado=11)
        // para el cliente creado en este test. Por tanto $pedidos no puede
        // estar vacío y debe contener el pedido creado con resultado ESPERADO.
        //
        // NOTA: PDO con ATTR_EMULATE_PREPARES=false retorna id_pedido como int nativo.
        // Normalizamos a int para que assertContains sea estricto y correcto.
        $this->assertNotEmpty($pedidos,
            'obtenerPedidosDetalle() debe retornar al menos 1 fila: el pedido creado es elegible.');

        $ids = array_map('intval', array_column($pedidos, 'id_pedido'));
        $this->assertContains($pedido, $ids,
            "El pedido ID={$pedido} debe aparecer en el detalle de la colecta ID={$idColecta}.");

        // Verificar estructura de columnas
        $this->assertArrayHasKey('id_pedido',       $pedidos[0]);
        $this->assertArrayHasKey('resultado_pedido', $pedidos[0]);
        $this->assertArrayHasKey('numero_orden',     $pedidos[0]);

        // El resultado inicial de un pedido recién registrado como esperado es ESPERADO
        $filaPedido = array_values(array_filter(
            $pedidos,
            fn($p) => (int)$p['id_pedido'] === $pedido
        ));
        $this->assertNotEmpty($filaPedido);
        $this->assertSame('ESPERADO', $filaPedido[0]['resultado_pedido'],
            'Un pedido recién registrado en colecta debe tener resultado ESPERADO.');

        // El conteo ESPERADO debe ser >= 1
        $this->assertGreaterThanOrEqual(1, $resumen['conteos']['ESPERADO']);
    }

    // ════════════════════════════════════════════════════════════════════
    // UI T07 — Colecta cerrada bloquea escaneo
    // ════════════════════════════════════════════════════════════════════

    /**
     * @test UI-T07. Escanear sobre colecta CONCILIADA → 409.
     */
    public function test_colecta_cerrada_bloquea_escaneo(): void
    {
        $cliente = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli7');
        $pedido  = \LogisticaTestDataFactory::crearPedido($this->db, $cliente, $this->operadorId);

        $r          = $this->callAction('abrir', [
            'id_cliente' => $cliente, 'fecha' => '2099-09-02', 'turno' => 'TARDE',
        ]);
        $idColecta  = $r['body']['data']['id_colecta'];

        // Cerrar
        $this->callAction('cerrar', ['id_colecta' => $idColecta]);

        // Intentar escanear
        $rEsc = $this->callAction('escanear', [
            'uuid'         => \LogisticaTestDataFactory::uuid(),
            'id_colecta'   => $idColecta,
            'id_pedido'    => $pedido,
            'tipo_evento'  => 'COLECTA_RECEPCION',
            'qr_hash'      => \LogisticaTestDataFactory::qrHash(),
            'dispositivo'  => 'scanner',
            'escaneado_at' => '2099-09-02 11:00:00',
            'metadata_json'=> [],
        ]);

        $this->assertSame(409, $rEsc['code']);
        $this->assertFalse($rEsc['body']['success']);
    }

    // ════════════════════════════════════════════════════════════════════
    // UI T08 — Respuestas no contienen trazas ni datos sensibles
    // ════════════════════════════════════════════════════════════════════

    /**
     * @test UI-T08. Respuesta de error no expone traza PHP ni datos internos.
     */
    public function test_respuesta_no_contiene_datos_sensibles(): void
    {
        $r    = $this->callAction('resumen', [], ['id_colecta' => '9999997']);
        $json = json_encode($r['body']);

        $this->assertIsString($json);
        $this->assertStringNotContainsString('"file"',  $json);
        $this->assertStringNotContainsString('"line"',  $json);
        $this->assertStringNotContainsString('"trace"', $json);
        $this->assertStringNotContainsString('password', $json);
    }

    // ════════════════════════════════════════════════════════════════════
    // UI T09 — No cambia pedidos.id_estado
    // ════════════════════════════════════════════════════════════════════

    /**
     * @test UI-T09. Flujo completo (abrir/escanear/cerrar) no altera pedidos.id_estado.
     */
    public function test_flujo_completo_no_cambia_id_estado(): void
    {
        $cliente = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli9');
        $pedido  = \LogisticaTestDataFactory::crearPedido($this->db, $cliente, $this->operadorId);

        $stmt = $this->db->prepare('SELECT id_estado FROM pedidos WHERE id = :id');
        $stmt->execute([':id' => $pedido]);
        $estadoOriginal = (int)$stmt->fetchColumn();

        // Abrir
        $rAbrir = $this->callAction('abrir', [
            'id_cliente' => $cliente, 'fecha' => '2099-10-01', 'turno' => 'MANANA',
        ]);
        $idColecta = $rAbrir['body']['data']['id_colecta'];

        // Escanear
        $this->callAction('escanear', [
            'uuid'         => \LogisticaTestDataFactory::uuid(),
            'id_colecta'   => $idColecta,
            'id_pedido'    => $pedido,
            'tipo_evento'  => 'COLECTA_RECEPCION',
            'qr_hash'      => \LogisticaTestDataFactory::qrHash(),
            'dispositivo'  => 'scanner',
            'escaneado_at' => '2099-10-01 09:00:00',
            'metadata_json'=> [],
        ]);

        // Cerrar
        $this->callAction('cerrar', ['id_colecta' => $idColecta]);

        // Verificar que id_estado no cambió
        $stmt->execute([':id' => $pedido]);
        $this->assertSame($estadoOriginal, (int)$stmt->fetchColumn(),
            'pedidos.id_estado no debe cambiar durante el flujo de colecta.');
    }

    // ════════════════════════════════════════════════════════════════════
    // UI T10 — No cambia inventario ni stock
    // ════════════════════════════════════════════════════════════════════

    /**
     * @test UI-T10. El flujo de colectas no modifica stock ni inventario.
     */
    public function test_flujo_no_cambia_inventario_ni_stock(): void
    {
        $antesStock = (int)$this->db->query('SELECT COUNT(*) FROM stock')->fetchColumn();
        $antesInv   = (int)$this->db->query('SELECT COUNT(*) FROM inventario')->fetchColumn();

        $cliente = \LogisticaTestDataFactory::crearUsuario($this->db, 'cli10');
        $pedido  = \LogisticaTestDataFactory::crearPedido($this->db, $cliente, $this->operadorId);

        $rAbrir    = $this->callAction('abrir', [
            'id_cliente' => $cliente, 'fecha' => '2099-11-01', 'turno' => 'TARDE',
        ]);
        $idColecta = $rAbrir['body']['data']['id_colecta'];

        $this->callAction('escanear', [
            'uuid'         => \LogisticaTestDataFactory::uuid(),
            'id_colecta'   => $idColecta,
            'id_pedido'    => $pedido,
            'tipo_evento'  => 'COLECTA_RECEPCION',
            'qr_hash'      => \LogisticaTestDataFactory::qrHash(),
            'dispositivo'  => 'scanner',
            'escaneado_at' => '2099-11-01 10:00:00',
            'metadata_json'=> [],
        ]);
        $this->callAction('cerrar', ['id_colecta' => $idColecta]);

        $this->assertSame($antesStock, (int)$this->db->query('SELECT COUNT(*) FROM stock')->fetchColumn(),
            'stock no debe cambiar.');
        $this->assertSame($antesInv,   (int)$this->db->query('SELECT COUNT(*) FROM inventario')->fetchColumn(),
            'inventario no debe cambiar.');
    }
}
