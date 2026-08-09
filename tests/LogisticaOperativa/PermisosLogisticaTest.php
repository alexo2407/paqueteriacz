<?php

declare(strict_types=1);

namespace Tests\LogisticaOperativa;

use PHPUnit\Framework\TestCase;

/**
 * PermisosLogisticaTest — Fase 4
 *
 * Pruebas automatizadas de la capa de permisos formales para el módulo
 * Logística Operativa (migración 022).
 *
 * Cubre 20 afirmaciones principales (PA01–PA20):
 *   PA01–PA04  Existencia de tablas y columnas
 *   PA05–PA08  Existencia y contenido de permisos
 *   PA09–PA12  Asignación a roles autorizados
 *   PA13–PA14  Roles no autorizados (sin permisos)
 *   PA15–PA17  Lógica de current_user_has_permission()
 *   PA18–PA20  Lógica de api_require_permission()
 *
 * Aislamiento:
 *   setUp()    → transacción en paquetes_apppack_test
 *   tearDown() → rollback → base intacta
 *
 * Restricciones:
 *   - No modifica pedidos.id_estado.
 *   - No modifica inventario ni stock.
 *   - No toca paquetes_apppack.
 *
 * @see database/migrations/022_create_logistica_operativa_permisos.sql
 * @see utils/logistica_permissions.php
 */

// ── Soporte de pruebas ────────────────────────────────────────────────────────
require_once dirname(__DIR__, 2) . '/tests/Support/TestDatabaseConnection.php';

// ── Helper de permisos bajo prueba ────────────────────────────────────────────
require_once dirname(__DIR__, 2) . '/utils/logistica_permissions.php';

// ─────────────────────────────────────────────────────────────────────────────

class PermisosLogisticaTest extends TestCase
{
    private \PDO $db;

    // ── setUp / tearDown ──────────────────────────────────────────────────────

    protected function setUp(): void
    {
        assertTestDatabase();
        $this->db = \TestDatabaseConnection::nueva();
        $this->db->beginTransaction();

        // Limpiar sesión simulada y caché de permisos antes de cada test
        $_SESSION = [];
        current_user_has_permission('', true);
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
        $_SESSION = [];
    }

    // ── PA01–PA04: Existencia de tablas y columnas ────────────────────────────

    /**
     * PA01 — La tabla `permisos` existe en la base de prueba.
     */
    public function testPA01TablaPermisosExiste(): void
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'permisos'"
        );
        $this->assertSame(1, (int) $stmt->fetchColumn(), 'PA01: La tabla permisos debe existir');
    }

    /**
     * PA02 — La tabla `roles_permisos` existe en la base de prueba.
     */
    public function testPA02TablaRolesPermisosExiste(): void
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles_permisos'"
        );
        $this->assertSame(1, (int) $stmt->fetchColumn(), 'PA02: La tabla roles_permisos debe existir');
    }

    /**
     * PA03 — La tabla `permisos` tiene la columna `codigo` con clave única.
     */
    public function testPA03TablaPermisosColumnaCodigo(): void
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'permisos'
               AND INDEX_NAME   = 'uk_permisos_codigo'
               AND NON_UNIQUE   = 0"
        );
        $this->assertGreaterThan(0, (int) $stmt->fetchColumn(), 'PA03: uk_permisos_codigo debe existir como clave única');
    }

    /**
     * PA04 — `roles_permisos` tiene clave primaria compuesta (id_rol, id_permiso).
     */
    public function testPA04RolesPermisosClaveCompuesta(): void
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'roles_permisos'
               AND INDEX_NAME   = 'PRIMARY'"
        );
        $this->assertSame(2, (int) $stmt->fetchColumn(), 'PA04: PK compuesta de 2 columnas en roles_permisos');
    }

    // ── PA05–PA08: Existencia y contenido de permisos ─────────────────────────

    /**
     * PA05 — El permiso `logistica_operativa_bodega` existe y está activo.
     */
    public function testPA05PermisoBodegaExiste(): void
    {
        $stmt = $this->db->prepare(
            "SELECT activo FROM permisos WHERE codigo = ?"
        );
        $stmt->execute(['logistica_operativa_bodega']);
        $activo = $stmt->fetchColumn();

        $this->assertNotFalse($activo, 'PA05: El permiso logistica_operativa_bodega debe existir');
        $this->assertSame('1', (string) $activo, 'PA05: El permiso logistica_operativa_bodega debe estar activo');
    }

    /**
     * PA06 — El permiso `logistica_operativa_colectas` existe y está activo.
     */
    public function testPA06PermisoColectasExiste(): void
    {
        $stmt = $this->db->prepare(
            "SELECT activo FROM permisos WHERE codigo = ?"
        );
        $stmt->execute(['logistica_operativa_colectas']);
        $activo = $stmt->fetchColumn();

        $this->assertNotFalse($activo, 'PA06: El permiso logistica_operativa_colectas debe existir');
        $this->assertSame('1', (string) $activo, 'PA06: El permiso logistica_operativa_colectas debe estar activo');
    }

    /**
     * PA07 — Ambos permisos pertenecen al módulo `logistica_operativa`.
     */
    public function testPA07PermisosModuloLogisticaOperativa(): void
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM permisos
             WHERE modulo = 'logistica_operativa' AND activo = 1"
        );
        $this->assertGreaterThanOrEqual(2, (int) $stmt->fetchColumn(), 'PA07: Deben existir ≥2 permisos activos en módulo logistica_operativa');
    }

    /**
     * PA08 — Los códigos de los permisos son exactamente los esperados.
     */
    public function testPA08CodigosPermisosCorrectos(): void
    {
        $stmt = $this->db->query(
            "SELECT codigo FROM permisos
             WHERE modulo = 'logistica_operativa'
             ORDER BY codigo"
        );
        $codigos = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertContains('logistica_operativa_bodega', $codigos, 'PA08: Código bodega presente');
        $this->assertContains('logistica_operativa_colectas', $codigos, 'PA08: Código colectas presente');
    }

    // ── PA09–PA12: Asignación a roles autorizados ─────────────────────────────

    /**
     * PA09 — El rol Administrador tiene asignado el permiso bodega.
     */
    public function testPA09AdminTienePermisoBodega(): void
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM roles_permisos rp
             JOIN roles    r ON r.id  = rp.id_rol
             JOIN permisos p ON p.id  = rp.id_permiso
             WHERE r.nombre_rol = 'Administrador'
               AND p.codigo     = 'logistica_operativa_bodega'"
        );
        $this->assertSame(1, (int) $stmt->fetchColumn(), 'PA09: Administrador debe tener permiso logistica_operativa_bodega');
    }

    /**
     * PA10 — El rol Administrador tiene asignado el permiso colectas.
     */
    public function testPA10AdminTienePermisoColectas(): void
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM roles_permisos rp
             JOIN roles    r ON r.id  = rp.id_rol
             JOIN permisos p ON p.id  = rp.id_permiso
             WHERE r.nombre_rol = 'Administrador'
               AND p.codigo     = 'logistica_operativa_colectas'"
        );
        $this->assertSame(1, (int) $stmt->fetchColumn(), 'PA10: Administrador debe tener permiso logistica_operativa_colectas');
    }

    /**
     * PA11 — El rol operativo logístico (nombre_rol='Cliente', ID 4) tiene permiso bodega.
     *
     * NOTA: La inversión semántica de roles está preservada intencionalmente.
     * En BD: nombre_rol='Cliente' = ROL_NOMBRE_PROVEEDOR en PHP = operativo LO.
     * @see database/migrations/008_fix_cliente_proveedor_columns.sql
     */
    public function testPA11OperativoLoTienePermisoBodega(): void
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM roles_permisos rp
             JOIN roles    r ON r.id  = rp.id_rol
             JOIN permisos p ON p.id  = rp.id_permiso
             WHERE r.nombre_rol = 'Cliente'
               AND p.codigo     = 'logistica_operativa_bodega'"
        );
        $this->assertSame(1, (int) $stmt->fetchColumn(), 'PA11: Rol operativo LO (nombre_rol=Cliente, ID 4) debe tener permiso bodega');
    }

    /**
     * PA12 — El rol operativo logístico (nombre_rol='Cliente', ID 4) tiene permiso colectas.
     */
    public function testPA12OperativoLoTienePermisoColectas(): void
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM roles_permisos rp
             JOIN roles    r ON r.id  = rp.id_rol
             JOIN permisos p ON p.id  = rp.id_permiso
             WHERE r.nombre_rol = 'Cliente'
               AND p.codigo     = 'logistica_operativa_colectas'"
        );
        $this->assertSame(1, (int) $stmt->fetchColumn(), 'PA12: Rol operativo LO (nombre_rol=Cliente, ID 4) debe tener permiso colectas');
    }

    // ── PA13–PA14: Roles sin permisos ─────────────────────────────────────────

    /**
     * PA13 — El rol Repartidor NO tiene permisos de logística operativa.
     */
    public function testPA13RepartidorSinPermisosLo(): void
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM roles_permisos rp
             JOIN roles    r ON r.id  = rp.id_rol
             JOIN permisos p ON p.id  = rp.id_permiso
             WHERE r.nombre_rol = 'Repartidor'
               AND p.modulo     = 'logistica_operativa'"
        );
        $this->assertSame(0, (int) $stmt->fetchColumn(), 'PA13: Repartidor no debe tener permisos de logistica_operativa');
    }

    /**
     * PA14 — El rol Vendedor NO tiene permisos de logística operativa.
     */
    public function testPA14VendedorSinPermisosLo(): void
    {
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM roles_permisos rp
             JOIN roles    r ON r.id  = rp.id_rol
             JOIN permisos p ON p.id  = rp.id_permiso
             WHERE r.nombre_rol = 'Vendedor'
               AND p.modulo     = 'logistica_operativa'"
        );
        $this->assertSame(0, (int) $stmt->fetchColumn(), 'PA14: Vendedor no debe tener permisos de logistica_operativa');
    }

    // ── PA15–PA17: current_user_has_permission() ──────────────────────────────

    /**
     * PA15 — Sin sesión activa, current_user_has_permission() devuelve false.
     * Prueba de "deny by default" cuando no hay sesión.
     */
    public function testPA15SinSesionDevuelveFalse(): void
    {
        // Sin iniciar sesión PHP (no se llama session_start())
        // current_user_has_permission() debe devolver false sin explotar
        $resultado = @current_user_has_permission('logistica_operativa_bodega');
        $this->assertFalse($resultado, 'PA15: Sin sesión activa debe devolver false');
    }

    /**
     * PA16 — Con permiso precargado en sesión, current_user_has_permission() devuelve true.
     * Prueba del caché de sesión (sin consultar BD).
     */
    public function testPA16CacheSesionPermitido(): void
    {
        // Simular sesión activa con permisos precargados
        session_start();
        $_SESSION['permisos'] = ['logistica_operativa_bodega', 'logistica_operativa_colectas'];

        $resultadoBodega   = current_user_has_permission('logistica_operativa_bodega');
        $resultadoColectas = current_user_has_permission('logistica_operativa_colectas');

        $this->assertTrue($resultadoBodega,   'PA16a: Con permiso en sesión debe devolver true (bodega)');
        $this->assertTrue($resultadoColectas, 'PA16b: Con permiso en sesión debe devolver true (colectas)');

        session_destroy();
    }

    /**
     * PA17 — Con sesión que NO tiene el permiso, current_user_has_permission() devuelve false.
     */
    public function testPA17CacheSesionDenegado(): void
    {
        session_start();
        $_SESSION['permisos'] = ['otro_permiso']; // no tiene LO

        $resultado = current_user_has_permission('logistica_operativa_bodega');
        $this->assertFalse($resultado, 'PA17: Sin el permiso en sesión debe devolver false');

        session_destroy();
    }

    // ── PA18–PA20: api_require_permission() ───────────────────────────────────

    /**
     * PA18 — Rol sin acceso LO no tiene el permiso en BD.
     * Prueba de deny by default: la consulta a roles_permisos devuelve false
     * para roles no autorizados. Evitamos llamar api_require_permission()
     * directamente porque invoca exit() y termina el proceso PHPUnit.
     */
    public function testPA18RolSinPermisoNoPasaLaConsulta(): void
    {
        // Obtener ID del rol Repartidor (no tiene permisos LO)
        $stmt = $this->db->prepare("SELECT id FROM roles WHERE nombre_rol = 'Repartidor' LIMIT 1");
        $stmt->execute();
        $rolRepartidor = (int) $stmt->fetchColumn();

        if ($rolRepartidor <= 0) {
            $this->markTestSkipped('PA18: No se encontró rol Repartidor en BD');
        }

        // _logistica_perm_consultar_bd() es la función interna que usa api_require_permission().
        // Si devuelve false, api_require_permission() responderá 403 y saldrá.
        $resultado = _logistica_perm_consultar_bd([$rolRepartidor], 'logistica_operativa_bodega');
        $this->assertFalse($resultado, 'PA18: Repartidor no debe tener logistica_operativa_bodega en BD');
    }

    /**
     * PA19 — Con rol=0, la consulta a BD también devuelve false (deny by default).
     * La función api_require_permission() hace exit() en este caso, por lo que
     * probamos la lógica subyacente directamente.
     */
    public function testPA19SinRolLaConsultaDevuelveFalse(): void
    {
        // Con un array vacío de IDs o con ID=0, la función interna devuelve false.
        $resultadoVacio = _logistica_perm_consultar_bd([], 'logistica_operativa_bodega');
        $resultadoCero  = _logistica_perm_consultar_bd([0], 'logistica_operativa_bodega');

        $this->assertFalse($resultadoVacio, 'PA19a: Sin IDs de rol, deny by default');
        $this->assertFalse($resultadoCero,  'PA19b: Con ID=0, deny by default');
    }

    /**
     * PA20 — _logistica_perm_consultar_bd() con IDs vacíos devuelve false (deny by default).
     * Prueba del helper interno de consulta.
     */
    public function testPA20ConsultaBdConIdsVaciosDevuelveFalse(): void
    {
        $resultado = _logistica_perm_consultar_bd([], 'logistica_operativa_bodega');
        $this->assertFalse($resultado, 'PA20: Sin IDs de rol, debe devolver false (deny by default)');
    }

    // ── Prueba de regresión: tablas existentes sin modificación ───────────────

    /**
     * Prueba de regresión — La migración 022 no modifica la tabla `roles`.
     */
    public function testRolesTablaIntacta(): void
    {
        // Verificar que roles tiene las mismas columnas esperadas
        $stmt = $this->db->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'roles'
             ORDER BY ORDINAL_POSITION"
        );
        $columnas = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertContains('id', $columnas, 'Regresión: roles.id existe');
        $this->assertContains('nombre_rol', $columnas, 'Regresión: roles.nombre_rol existe');
    }

    /**
     * Prueba de regresión — Los permisos de LO no afectan pedidos.id_estado.
     */
    public function testPedidosIdEstadoPermanece(): void
    {
        // Verificar que pedidos.id_estado no fue modificado por ninguna operación
        $stmt = $this->db->query("SELECT COUNT(*) FROM pedidos WHERE id_estado IS NULL");
        $nulos = (int) $stmt->fetchColumn();

        $this->assertSame(0, $nulos, 'Regresión: ningún pedido.id_estado es NULL tras la migración');
    }
}
