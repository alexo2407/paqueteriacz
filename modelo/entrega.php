<?php
require_once 'conexion.php';

class EntregaModel {

    public const ESTADO_ENTREGADO_EXITOSO = 3; // estados_entrega: 1=Asignado, 3=Entregado con éxito
    
    /**
     * Asignar una entrega a un repartidor.
     * Si ya existe una entrega para este pedido, actualiza el repartidor y la fecha de asignación.
     * Si no, crea una nueva.
     */
    public static function asignar($idPedido, $idRepartidor) {
        try {
            $db = (new Conexion())->conectar();
            
            // Verificar si ya existe
            $stmtCheck = $db->prepare("SELECT id FROM entregas WHERE id_pedido = :id_pedido");
            $stmtCheck->execute([':id_pedido' => $idPedido]);
            $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                // Actualizar
                $stmt = $db->prepare("UPDATE entregas SET id_repartidor = :id_repartidor, fecha_asignacion = NOW() WHERE id = :id");
                $stmt->execute([':id_repartidor' => $idRepartidor, ':id' => $existing['id']]);
            } else {
                // Crear
                $stmt = $db->prepare("INSERT INTO entregas (id_pedido, id_repartidor, fecha_asignacion) VALUES (:id_pedido, :id_repartidor, NOW())");
                $stmt->execute([':id_pedido' => $idPedido, ':id_repartidor' => $idRepartidor]);
            }
            return true;
        } catch (Exception $e) {
            // Log error?
            return false;
        }
    }
    
    /**
     * Marcar una entrega como realizada.
     */
    public static function marcarEntregado($idPedido) {
        try {
            $db = (new Conexion())->conectar();
            // Actualizamos la fecha de entrega de la última asignación de este pedido
            // Ojo: Si no existe registro en entregas, ¿deberíamos crearlo? 
            // Idealmente debería existir si hubo asignación. Si no, lo creamos con repartidor nulo o el actual del pedido.
            
            $stmtCheck = $db->prepare("SELECT id FROM entregas WHERE id_pedido = :id_pedido ORDER BY id DESC LIMIT 1");
            $stmtCheck->execute([':id_pedido' => $idPedido]);
            $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                $stmt = $db->prepare("UPDATE entregas SET fecha_entrega = NOW(), id_estado_entrega = :id_estado_entrega WHERE id = :id");
                $stmt->execute([':id_estado_entrega' => self::ESTADO_ENTREGADO_EXITOSO, ':id' => $existing['id']]);
            } else {
                // Fallback: intentar obtener el repartidor del pedido para crear el registro
                $stmtPed = $db->prepare("SELECT id_vendedor FROM pedidos WHERE id = :id");
                $stmtPed->execute([':id' => $idPedido]);
                $ped = $stmtPed->fetch(PDO::FETCH_ASSOC);
                
                if ($ped && $ped['id_vendedor']) {
                    $stmt = $db->prepare("INSERT INTO entregas (id_pedido, id_repartidor, fecha_entrega, id_estado_entrega) VALUES (:id_pedido, :id_repartidor, NOW(), :id_estado_entrega)");
                    $stmt->execute([':id_pedido' => $idPedido, ':id_repartidor' => $ped['id_vendedor'], ':id_estado_entrega' => self::ESTADO_ENTREGADO_EXITOSO]);
                }
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
