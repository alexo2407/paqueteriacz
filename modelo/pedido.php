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

        $stmt = null;
    }

    public function obtenerPedidoPorNumero($numeroOrden) {
        try {

            $db = (new Conexion())->conectar();
            // Consulta para obtener los datos del pedido y su estado
            $sql = "SELECT 
                        p.numero_orden, 
                        p.destinatario, 
                        p.telefono, 
                        p.precio, 
                        p.producto, 
                        p.cantidad, 
                        p.pais, 
                        p.coordenadas, 
                        e.nombre_estado 
                    FROM pedidos p
                    INNER JOIN estados e ON p.estado_id = e.id_estado
                    WHERE p.numero_orden = :numero_orden";

            // Preparar la consulta
            $stmt = $db->prepare($sql);

            // Asignar el parámetro
            $stmt->bindParam(':numero_orden', $numeroOrden, PDO::PARAM_INT);

            // Ejecutar la consulta
            $stmt->execute();

            // Verificar si se encontró un resultado
            if ($stmt->rowCount() > 0) {
                return $stmt->fetch(PDO::FETCH_ASSOC); // Devuelve los datos como un array asociativo
            } else {
                return null; // No se encontró el pedido
            }
        } catch (PDOException $e) {
            // Manejar errores de conexión o consulta
            throw new Exception("Error while fetching the order: " . $e->getMessage());
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


