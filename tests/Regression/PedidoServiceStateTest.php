<?php
/**
 * tests/Regression/PedidoServiceStateTest.php
 *
 * Pruebas de regresión para PedidoService, EntregaModel y el catálogo de estados.
 *
 * Objetivo:
 * - Verificar que las constantes de PedidoService coincidan con los IDs reales.
 * - Verificar que EntregaModel::ESTADO_ENTREGADO_EXITOSO sea el correcto.
 * - Proteger contra regresiones en cambios futuros.
 *
 * Diseño: Pruebas 100% UNITARIAS (sin conexión a BD).
 * Catálogo extraído de la BD el 2026-07-22, corregido en Fase 0.1.
 *
 * Compatibilidad: PHPUnit 10.x (PHP 8.2).
 *
 * Historial:
 * - Fase 0    (2026-07-22): creación inicial, documentación de bugs con fwrite(STDOUT).
 * - Fase 0.1  (2026-07-22): correcciones aplicadas, pruebas actualizadas para
 *                            verificar comportamiento correcto en lugar de documentar bugs.
 *
 * @see docs/specs/logistica-operativa/v1/02-modelo-estados.md
 * @see docs/specs/logistica-operativa/v1/06-riesgos-compatibilidad.md
 */

declare(strict_types=1);

namespace Tests\Regression;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;

// Carga PedidoService (namespace global \PedidoService).
// Incluye modelo/conexion.php y modelo/stock.php sin abrir conexión.
require_once dirname(__DIR__, 2) . '/services/PedidoService.php';

// Carga EntregaModel (namespace global \EntregaModel).
// Incluye modelo/conexion.php (ya cargado; require_once es idempotente).
require_once dirname(__DIR__, 2) . '/modelo/entrega.php';

#[Group('regression')]
#[Group('estados')]
class PedidoServiceStateTest extends TestCase
{
    /**
     * Catálogo real de estados_pedidos extraído de la BD paquetes_apppack.
     * Consulta ejecutada el 2026-07-22. No se realizó ninguna modificación en BD.
     *
     * ⚠️ Si el catálogo cambia en la BD, actualizar este fixture
     *    y documentar el cambio en 02-modelo-estados.md
     */
    private const CATALOGO_REAL = [
        1  => 'En bodega',
        2  => 'En ruta o proceso',
        3  => 'Entregado',
        4  => 'Reprogramado',
        5  => 'Domicilio cerrado',
        6  => 'No hay quien reciba en domicilio',
        7  => 'Devuelto',
        8  => 'Domicilio no encontrado',
        9  => 'Rechazado',
        10 => 'No puede pagar recaudo',
        11 => 'Pendiente recolección por mensajería',
        12 => 'Recolectado por mensajería',
        13 => 'Traslado a punto de distribución',
        14 => 'Entregado → liquidado',
        15 => 'Devolución → entregado a bodega',
        16 => 'Incidencia',
        17 => 'Cancelado',
    ];

    /**
     * Catálogo real de estados_entrega extraído de la BD el 2026-07-22.
     */
    private const CATALOGO_ESTADOS_ENTREGA = [
        1 => 'Asignado',
        2 => 'En camino',
        3 => 'Entregado con éxito',
        4 => 'Entrega fallida',
        5 => 'Reagendado',
        6 => 'Cancelado',
    ];

    // =========================================================================
    // PRUEBAS DE CONSISTENCIA — Constantes reales de PedidoService vs Catálogo
    // =========================================================================

    #[DataProvider('estadosCorrectosProvider')]
    public function test_constante_coincide_con_catalogo(
        string $nombreConstante,
        int    $valorConstante,
        string $nombreEsperadoEnBD
    ): void {
        $nombreRealEnBD = self::CATALOGO_REAL[$valorConstante] ?? null;

        $this->assertNotNull(
            $nombreRealEnBD,
            "PedidoService::{$nombreConstante} = {$valorConstante} " .
            "no existe en el catálogo real de estados_pedidos."
        );

        $this->assertSame(
            $nombreEsperadoEnBD,
            $nombreRealEnBD,
            "PedidoService::{$nombreConstante} = {$valorConstante} " .
            "apunta a '{$nombreRealEnBD}' en BD, " .
            "pero se esperaba '{$nombreEsperadoEnBD}'."
        );
    }

    /**
     * Todos los estados incluyendo ESTADO_CANCELADO (ahora corregido a 17).
     */
    public static function estadosCorrectosProvider(): array
    {
        return [
            'ESTADO_EN_BODEGA (ID 1)' => [
                'ESTADO_EN_BODEGA', \PedidoService::ESTADO_EN_BODEGA, 'En bodega',
            ],
            'ESTADO_EN_RUTA (ID 2)' => [
                'ESTADO_EN_RUTA', \PedidoService::ESTADO_EN_RUTA, 'En ruta o proceso',
            ],
            'ESTADO_ENTREGADO (ID 3)' => [
                'ESTADO_ENTREGADO', \PedidoService::ESTADO_ENTREGADO, 'Entregado',
            ],
            'ESTADO_CANCELADO (ID 17) — corregido en Fase 0.1' => [
                'ESTADO_CANCELADO', \PedidoService::ESTADO_CANCELADO, 'Cancelado',
            ],
            'ESTADO_DEVUELTO (ID 7)' => [
                'ESTADO_DEVUELTO', \PedidoService::ESTADO_DEVUELTO, 'Devuelto',
            ],
            'ESTADO_RECHAZADO (ID 9)' => [
                'ESTADO_RECHAZADO', \PedidoService::ESTADO_RECHAZADO, 'Rechazado',
            ],
            'ESTADO_DEVUELTO_BODEGA (ID 15)' => [
                'ESTADO_DEVUELTO_BODEGA', \PedidoService::ESTADO_DEVUELTO_BODEGA,
                'Devolución → entregado a bodega',
            ],
        ];
    }

    // =========================================================================
    // PRUEBA DE CORRECCIÓN — ESTADO_CANCELADO = 17
    // =========================================================================

    /**
     * Verifica que ESTADO_CANCELADO apunta al estado correcto "Cancelado" (ID 17).
     * Corregido en Fase 0.1 (antes era 5 = "Domicilio cerrado").
     */
    public function test_ESTADO_CANCELADO_apunta_a_cancelado_correcto(): void
    {
        $this->assertSame(
            17,
            \PedidoService::ESTADO_CANCELADO,
            'ESTADO_CANCELADO debe ser 17 (Cancelado). ' .
            'Fue corregido en Fase 0.1 desde el valor incorrecto 5 (Domicilio cerrado).'
        );

        $nombreEnBD = self::CATALOGO_REAL[\PedidoService::ESTADO_CANCELADO] ?? 'NO EXISTE';

        $this->assertSame(
            'Cancelado',
            $nombreEnBD,
            'El ID apuntado por ESTADO_CANCELADO debe llamarse "Cancelado" en estados_pedidos.'
        );

        // Protección adicional: el ID 5 ya no debe ser tratado como cancelación
        $this->assertNotSame(
            5,
            \PedidoService::ESTADO_CANCELADO,
            'ESTADO_CANCELADO NO debe ser 5 (Domicilio cerrado) — ese era el bug.'
        );
    }

    /**
     * Verifica que el estado 5 ("Domicilio cerrado") NO tiene asociada
     * la lógica de liberación de reservas en PedidoService.
     *
     * Esto es estructuralmente garantizado porque ESTADO_CANCELADO ya no es 5.
     * Esta prueba protege contra regresión a ese comportamiento.
     */
    public function test_domicilio_cerrado_ID5_no_es_ESTADO_CANCELADO(): void
    {
        $nombreId5 = self::CATALOGO_REAL[5];

        $this->assertSame('Domicilio cerrado', $nombreId5,
            'El ID 5 debe seguir siendo "Domicilio cerrado" en el catálogo.');

        $this->assertNotSame(
            5,
            \PedidoService::ESTADO_CANCELADO,
            '"Domicilio cerrado" (ID 5) no debe coincidir con ESTADO_CANCELADO. ' .
            'Si coinciden, el stock se liberaría incorrectamente en cada domicilio cerrado.'
        );
    }

    // =========================================================================
    // PRUEBA DE CORRECCIÓN — EntregaModel::ESTADO_ENTREGADO_EXITOSO = 3
    // =========================================================================

    /**
     * Verifica que EntregaModel expone la constante ESTADO_ENTREGADO_EXITOSO = 3.
     * Corregido en Fase 0.1 (antes marcarEntregado() usaba el literal 1 = "Asignado").
     */
    public function test_EntregaModel_tiene_constante_ESTADO_ENTREGADO_EXITOSO(): void
    {
        $this->assertTrue(
            defined('\EntregaModel::ESTADO_ENTREGADO_EXITOSO'),
            'EntregaModel::ESTADO_ENTREGADO_EXITOSO debe estar definida como constante pública.'
        );

        $this->assertSame(
            3,
            \EntregaModel::ESTADO_ENTREGADO_EXITOSO,
            'EntregaModel::ESTADO_ENTREGADO_EXITOSO debe ser 3 (Entregado con éxito).'
        );
    }

    /**
     * Verifica que ESTADO_ENTREGADO_EXITOSO apunta al nombre correcto en el catálogo.
     */
    public function test_EntregaModel_ESTADO_ENTREGADO_EXITOSO_corresponde_a_catalogo(): void
    {
        $id        = \EntregaModel::ESTADO_ENTREGADO_EXITOSO;
        $nombreEnBD = self::CATALOGO_ESTADOS_ENTREGA[$id] ?? 'NO EXISTE';

        $this->assertSame(
            'Entregado con éxito',
            $nombreEnBD,
            "EntregaModel::ESTADO_ENTREGADO_EXITOSO = {$id} debe corresponder " .
            "a 'Entregado con éxito' en estados_entrega."
        );
    }

    /**
     * Verifica que el estado incorrecto "Asignado" (ID 1) ya no es el usado
     * en marcarEntregado() — esto es garantizado por la constante.
     */
    public function test_EntregaModel_no_usa_estado_asignado_para_entrega(): void
    {
        $this->assertNotSame(
            1,
            \EntregaModel::ESTADO_ENTREGADO_EXITOSO,
            'ESTADO_ENTREGADO_EXITOSO no debe ser 1 ("Asignado"). ' .
            'Ese era el bug anterior a Fase 0.1.'
        );

        $nombreId1 = self::CATALOGO_ESTADOS_ENTREGA[1];
        $this->assertSame('Asignado', $nombreId1,
            'Confirmar que el ID 1 en estados_entrega sigue siendo "Asignado".');
    }

    /**
     * Verifica que el método marcarEntregado() sigue existiendo con la misma firma pública.
     * No ejecuta el método porque requeriría base de datos de integración.
     */
    public function test_EntregaModel_marcarEntregado_metodo_existe(): void
    {
        $this->assertTrue(
            method_exists(\EntregaModel::class, 'marcarEntregado'),
            'EntregaModel::marcarEntregado() debe existir con su firma pública intacta.'
        );
    }

    /**
     * Verifica que asignar() también sigue existiendo (sin cambios en Fase 0.1).
     */
    public function test_EntregaModel_asignar_metodo_existe(): void
    {
        $this->assertTrue(
            method_exists(\EntregaModel::class, 'asignar'),
            'EntregaModel::asignar() debe existir.'
        );
    }

    // =========================================================================
    // PRUEBAS DE COMPLETITUD DEL CATÁLOGO
    // =========================================================================

    public function test_catalogo_tiene_exactamente_17_estados(): void
    {
        $this->assertCount(17, self::CATALOGO_REAL,
            'El catálogo debe tener exactamente 17 estados según la BD actual.');
    }

    public function test_catalogo_contiene_cancelado_en_id_17(): void
    {
        $this->assertArrayHasKey(17, self::CATALOGO_REAL);
        $this->assertSame('Cancelado', self::CATALOGO_REAL[17]);
    }

    public function test_catalogo_contiene_incidencia_en_id_16(): void
    {
        $this->assertArrayHasKey(16, self::CATALOGO_REAL);
        $this->assertSame('Incidencia', self::CATALOGO_REAL[16]);
    }

    public function test_catalogo_contiene_pendiente_recoleccion_en_id_11(): void
    {
        $this->assertArrayHasKey(11, self::CATALOGO_REAL);
        $this->assertStringContainsString(
            'recolección',
            mb_strtolower(self::CATALOGO_REAL[11]),
            'ID 11 debe contener "recolección" en su nombre.'
        );
    }

    public function test_catalogo_contiene_devuelto_bodega_en_id_15(): void
    {
        $this->assertArrayHasKey(15, self::CATALOGO_REAL);
        $this->assertStringContainsString(
            'bodega',
            mb_strtolower(self::CATALOGO_REAL[15]),
            'ID 15 debe contener "bodega" en su nombre.'
        );
    }

    // =========================================================================
    // PRUEBA DE CONSTANTES REALES DE PedidoService
    // =========================================================================

    /**
     * Verifica que PedidoService expone todas las constantes con los valores correctos.
     */
    public function test_pedido_service_expone_todas_las_constantes_correctas(): void
    {
        $this->assertSame(1,  \PedidoService::ESTADO_EN_BODEGA,       'ESTADO_EN_BODEGA = 1.');
        $this->assertSame(2,  \PedidoService::ESTADO_EN_RUTA,         'ESTADO_EN_RUTA = 2.');
        $this->assertSame(3,  \PedidoService::ESTADO_ENTREGADO,       'ESTADO_ENTREGADO = 3.');
        $this->assertSame(17, \PedidoService::ESTADO_CANCELADO,       'ESTADO_CANCELADO = 17 (corregido en Fase 0.1).');
        $this->assertSame(7,  \PedidoService::ESTADO_DEVUELTO,        'ESTADO_DEVUELTO = 7.');
        $this->assertSame(9,  \PedidoService::ESTADO_RECHAZADO,       'ESTADO_RECHAZADO = 9.');
        $this->assertSame(15, \PedidoService::ESTADO_DEVUELTO_BODEGA, 'ESTADO_DEVUELTO_BODEGA = 15.');
    }

    // =========================================================================
    // PRUEBA DE ESTADOS QUE MUEVEN INVENTARIO
    // =========================================================================

    /**
     * Verifica que todos los IDs que disparan movimientos de stock
     * existen en el catálogo real de estados_pedidos.
     */
    public function test_estados_con_movimiento_de_stock_existen_en_catalogo(): void
    {
        $estadosConStock = [
            'ESTADO_EN_BODEGA'       => \PedidoService::ESTADO_EN_BODEGA,
            'ESTADO_EN_RUTA'         => \PedidoService::ESTADO_EN_RUTA,
            'ESTADO_ENTREGADO'       => \PedidoService::ESTADO_ENTREGADO,
            'ESTADO_CANCELADO'       => \PedidoService::ESTADO_CANCELADO,
            'ESTADO_DEVUELTO_BODEGA' => \PedidoService::ESTADO_DEVUELTO_BODEGA,
        ];

        foreach ($estadosConStock as $nombre => $id) {
            $this->assertArrayHasKey(
                $id,
                self::CATALOGO_REAL,
                "PedidoService::{$nombre} = {$id} no existe en el catálogo real de estados_pedidos."
            );
        }
    }

    // =========================================================================
    // PRUEBA DE FEATURE FLAGS (documentación de Fase 1)
    // =========================================================================

    /**
     * Los feature flags son inyectados por phpunit.xml como constantes.
     * Verifica que tienen valores seguros.
     */
    public function test_feature_flags_logistica_tienen_valores_seguros(): void
    {
        $flagsEsperados = [
            'LOGISTICA_OPERATIVA_ENABLED'            => false,
            'LOGISTICA_OPERATIVA_SHADOW_MODE'         => true,
            'LOGISTICA_OPERATIVA_UPDATE_STATES'       => false,
            'LOGISTICA_OPERATIVA_INVENTORY_ENABLED'   => false,
            'LOGISTICA_OPERATIVA_ROUTES_ENABLED'      => false,
            'LOGISTICA_OPERATIVA_SETTLEMENT_ENABLED'  => false,
        ];

        foreach ($flagsEsperados as $flag => $valorEsperado) {
            if (defined($flag)) {
                $this->assertSame(
                    $valorEsperado,
                    constant($flag),
                    "El feature flag {$flag} debe tener el valor " .
                    var_export($valorEsperado, true) . "."
                );
            } else {
                // Flag no definido en Fase 0: es esperado, pasa con nota
                $this->assertTrue(true, "Flag '{$flag}' pendiente de Fase 1.");
            }
        }
    }
}
