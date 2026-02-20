<?php
/**
 * Backfill Script: Migración de Códigos Postales
 * Este script recorre los pedidos existentes, extrae el (id_pais + codigo_postal),
 * crea el registro en `codigos_postales` si no existe, y actualiza el pedido.
 */

require_once 'config/config.php';
require_once 'modelo/conexion.php';

try {
    $db = (new Conexion())->conectar();
    
    // 1. Obtener pedidos que tienen país y código postal
    $query = "SELECT id, id_pais, codigo_postal FROM pedidos WHERE id_pais IS NOT NULL AND codigo_postal IS NOT NULL AND codigo_postal != ''";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Total de pedidos a procesar: " . count($pedidos) . "\n";
    
    $migrados = 0;
    $errores = 0;
    $cp_creados = 0;

    foreach ($pedidos as $pedido) {
        $id_pedido = $pedido['id'];
        $id_pais = $pedido['id_pais'];
        
        // Normalización básica del CP
        $cp = strtoupper(trim($pedido['codigo_postal']));
        $cp = str_replace([' ', '-'], '', $cp); // Quitar espacios y guiones para consistencia

        if (empty($cp)) continue;

        try {
            $db->beginTransaction();

            // 2. Buscar o crear el Código Postal en la nueva tabla
            $stmtCP = $db->prepare("SELECT id FROM codigos_postales WHERE id_pais = :id_pais AND codigo_postal = :cp");
            $stmtCP->execute([':id_pais' => $id_pais, ':cp' => $cp]);
            $cp_row = $stmtCP->fetch(PDO::FETCH_ASSOC);

            if ($cp_row) {
                $id_cp = $cp_row['id'];
            } else {
                // Crear nuevo registro de CP
                $stmtInsert = $db->prepare("INSERT INTO codigos_postales (id_pais, codigo_postal) VALUES (:id_pais, :cp)");
                $stmtInsert->execute([':id_pais' => $id_pais, ':cp' => $cp]);
                $id_cp = $db->lastInsertId();
                $cp_creados++;
            }

            // 3. Actualizar el pedido con el id_codigo_postal
            $stmtUpdate = $db->prepare("UPDATE pedidos SET id_codigo_postal = :id_cp WHERE id = :id_pedido");
            $stmtUpdate->execute([':id_cp' => $id_cp, ':id_pedido' => $id_pedido]);

            $db->commit();
            $migrados++;

        } catch (Exception $e) {
            $db->rollBack();
            $errores++;
            echo "Error en pedido ID $id_pedido: " . $e->getMessage() . "\n";
        }
    }

    echo "\n--- REPORTE DE MIGRACIÓN ---\n";
    echo "Pedidos migrados con éxito: $migrados\n";
    echo "Nuevos registros en codigos_postales: $cp_creados\n";
    echo "Errores encontrados: $errores\n";
    echo "----------------------------\n";

} catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
}
