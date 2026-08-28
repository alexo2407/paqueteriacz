<?php

declare(strict_types=1);

/**
 * DashboardOperativoModel
 *
 * Agregaciones y métricas cuantitativas para el tablero principal de Logística Operativa.
 */
class DashboardOperativoModel
{
    public function __construct(private PDO $db) {}

    public function obtenerMétricasResumen(): array
    {
        // 1. Colectas hoy
        $stmtCol = $this->db->query("
            SELECT 
                COUNT(*) as total_colectas_hoy,
                SUM(cantidad_esperada) as total_esperado_hoy,
                SUM(cantidad_escaneada) as total_escaneado_hoy
            FROM logistica_colectas
            WHERE fecha = CURDATE()
        ");
        $colectasHoy = $stmtCol->fetch(PDO::FETCH_ASSOC) ?: [
            'total_colectas_hoy' => 0,
            'total_esperado_hoy' => 0,
            'total_escaneado_hoy' => 0
        ];

        // 2. Inventario en Bodega, Pendientes de Colecta y En Ruta de Logística Operativa
        $stmtBod = $this->db->query("
            SELECT COUNT(*) FROM logistica_recepciones WHERE estado IN ('RECIBIDO', 'UBICADO')
        ");
        $enBodega = (int)($stmtBod ? $stmtBod->fetchColumn() : 0);

        $stmtPendCol = $this->db->query("
            SELECT COALESCE(SUM(GREATEST(0, cantidad_esperada - cantidad_escaneada)), 0) 
            FROM logistica_colectas 
            WHERE estado = 'ABIERTA'
        ");
        $pendientesColecta = (int)($stmtPendCol ? $stmtPendCol->fetchColumn() : 0);

        $stmtEnRuta = $this->db->query("
            SELECT COUNT(*) 
            FROM logistica_ruta_pedidos rp
            JOIN logistica_rutas r ON r.id = rp.id_ruta
            WHERE r.estado IN ('SELLADA', 'EN_CURSO') AND rp.estado_entrega = 'PENDIENTE'
        ");
        $enRuta = (int)($stmtEnRuta ? $stmtEnRuta->fetchColumn() : 0);

        $estadosPed = [
            'pendientes_colecta'      => $pendientesColecta,
            'recolectados_mensajeria' => 0,
            'en_bodega'               => $enBodega,
            'en_ruta'                 => $enRuta
        ];

        // 3. Rutas activas hoy
        $stmtRut = $this->db->query("
            SELECT 
                COUNT(*) as total_rutas_hoy,
                SUM(CASE WHEN estado = 'SELLADA' THEN 1 ELSE 0 END) as rutas_selladas,
                SUM(CASE WHEN estado = 'LIQUIDADA' THEN 1 ELSE 0 END) as rutas_liquidadas
            FROM logistica_rutas
            WHERE fecha = CURDATE()
        ");
        $rutasHoy = $stmtRut->fetch(PDO::FETCH_ASSOC) ?: [
            'total_rutas_hoy' => 0,
            'rutas_selladas' => 0,
            'rutas_liquidadas' => 0
        ];

        // 4. Recepciones por tipo
        $stmtRec = $this->db->query("
            SELECT tipo_recepcion, COUNT(*) as conteo
            FROM logistica_recepciones
            GROUP BY tipo_recepcion
        ");
        $recepciones = $stmtRec->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        return [
            'colectas'    => $colectasHoy,
            'estados'     => $estadosPed,
            'rutas'       => $rutasHoy,
            'recepciones' => $recepciones
        ];
    }
}
