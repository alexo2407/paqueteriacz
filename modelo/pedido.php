<?php
include_once "conexion.php";

class PedidosModel {

    /* ZONA API */

    /* crear pedido desde el API */
    public static function crearPedido($data) {
        try {
            $db = (new Conexion())->conectar();

            // Preparar los datos
            $coordenadas = explode(',', $data["coordenadas"]);
            $latitud = $coordenadas[0];
            $longitud = $coordenadas[1];


            // var_dump($coordenadas);
            // Insertar el pedido en la base de datos
            $stmt = $db->prepare("
                INSERT INTO pedidos (
                    fecha_ingreso, numero_orden, destinatario, telefono, precio, producto, cantidad,
                    pais, departamento, municipio, barrio, direccion, zona, comentario, coordenadas, id_estado
                ) VALUES (
                    NOW(), :numero_orden, :destinatario, :telefono, :precio, :producto, :cantidad,
                    :pais, :departamento, :municipio, :barrio, :direccion, :zona, :comentario, ST_GeomFromText(:coordenadas), 1
                )
            ");
            $stmt->execute([
                ":numero_orden" => $data["numero_orden"],
                ":destinatario" => $data["destinatario"],
                ":telefono" => $data["telefono"],
                ":precio" => $data["precio"] ?? null,
                ":producto" => $data["producto"],
                ":cantidad" => $data["cantidad"],
                ":pais" => $data["pais"],
                ":departamento" => $data["departamento"],
                ":municipio" => $data["municipio"],
                ":barrio" => $data["barrio"] ?? null,
                ":direccion" => $data["direccion"],
                ":zona" => $data["zona"] ?? null,
                ":comentario" => $data["comentario"] ?? null,
                ":coordenadas" => "POINT($longitud $latitud)"
            ]);

            return [ "numero_orden" => $data["numero_orden"],
                
                        "pedido_id" => $db->lastInsertId()];
        } catch (Exception $e) {
            throw new Exception("Error al insertar el pedido: " . $e->getMessage());
        }
    }



    /*  ZONA DEL FRONT END */


    public static function obtenerPedidosExtendidos() {
        try {
            $db = (new Conexion())->conectar();

            // Consulta SQL para obtener los datos necesarios
            $query = "
                SELECT 
                    p.id AS ID_Pedido,
                    p.numero_orden AS Numero_Orden,
                    p.destinatario AS Cliente,
                    p.comentario AS Comentario,
                    e.nombre_estado AS Estado
                FROM pedidos p
                LEFT JOIN estados e ON p.id_estado = e.id
                ORDER BY p.fecha_ingreso DESC
            ";

            $stmt = $db->prepare($query);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Error al obtener los pedidos: " . $e->getMessage());
        }
    }
}


