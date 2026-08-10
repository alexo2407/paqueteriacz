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

        // 2. Pedidos por recolección (Estado 11) y recolectados (Estado 12)
        $stmtEst = $this->db->query("
            SELECT 
                SUM(CASE WHEN id_estado = 11 THEN 1 ELSE 0 END) as pendientes_colecta,
                SUM(CASE WHEN id_estado = 12 THEN 1 ELSE 0 END) as recolectados_mensajeria,
                SUM(CASE WHEN id_estado = 1 THEN 1 ELSE 0 END) as en_bodega,
                SUM(CASE WHEN id_estado = 2 THEN 1 ELSE 0 END) as en_ruta
            FROM pedidos
        ");
        $estadosPed = $stmtEst->fetch(PDO::FETCH_ASSOC) ?: [
            'pendientes_colecta' => 0,
            'recolectados_mensajeria' => 0,
            'en_bodega' => 0,
            'en_ruta' => 0
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
