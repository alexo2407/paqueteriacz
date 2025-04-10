<?php
include_once "conexion.php";

class PedidosModel
{

    /* ZONA API */

    /* VERFICAR SI EXISTE UN NUMERO DE ORDEN ANTES DE INSERTARLA */
    public static function existeNumeroOrden($numeroOrden) {
        try {
            $db = (new Conexion())->conectar();
            $query = "SELECT COUNT(*) FROM pedidos WHERE numero_orden = :numero_orden";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":numero_orden", $numeroOrden, PDO::PARAM_INT);
            $stmt->execute();
    
            // Retorna true si hay al menos un pedido con ese número de orden
            return $stmt->fetchColumn() > 0;
    
        } catch (Exception $e) {
            throw new Exception("Error al verificar el número de orden: " . $e->getMessage());
        }
    }
    

    /* CREA EL PEDIDO DESDE EL API */
    public static function crearPedido($data)
    {
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

            return [
                "numero_orden" => $data["numero_orden"],

                "pedido_id" => $db->lastInsertId()
            ];
        } catch (Exception $e) {
            throw new Exception("Error al insertar el pedido: " . $e->getMessage());
        }

        $stmt = null;
    }

    public function obtenerPedidoPorNumero($numeroOrden)
    {
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

    /*  OBTENER PEDIDOS LISTA DE PEDIDOS COMPLETA  */

    public static function obtenerPedidosExtendidos()
    {
        try {
            $db = (new Conexion())->conectar();

            // Consulta SQL para obtener los datos necesarios
            $query = "
                SELECT 
                    p.id AS ID_Pedido,
                    p.numero_orden AS Numero_Orden,
                    p.destinatario AS Cliente,
                    p.comentario AS Comentario,
                    ST_Y(p.coordenadas) AS latitud, 
                    ST_X(p.coordenadas) AS longitud,
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

    /*  OBTENER PEDIDOS POR ID  */

    public static function obtenerPedidoPorId($id_pedido)
    {
        try {
            $db = (new Conexion())->conectar();
            // Consulta para obtener los datos del pedido incluyendo las coordenadas descompuestas
            $query = "
        SELECT 
            p.*, 
            ST_Y(p.coordenadas) AS latitud, 
            ST_X(p.coordenadas) AS longitud
        FROM pedidos p
        WHERE p.id = :id_pedido
    ";

            $stmt = $db->prepare($query);
            $stmt->bindParam(':id_pedido', $id_pedido, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Error al obtener el pedido: " . $e->getMessage());
        }
    }

    /* ACTUALIZAR  */

    public static function actualizarPedido($data)
    {
        try {
            $db = (new Conexion())->conectar();
            // Crear el formato POINT para ST_GeomFromText
            $coordenadas = "POINT(" . $data['longitud'] . " " . $data['latitud'] . ")";

            // Consulta SQL con ST_GeomFromText
            $query = "
            UPDATE pedidos SET
                destinatario = :destinatario,
                telefono = :telefono,
                direccion = :direccion,
                comentario = :comentario,
                cantidad = :cantidad,
                producto = :producto,
                precio = :precio,
                id_estado = :estado,
                id_vendedor = :vendedor,
                coordenadas = ST_GeomFromText(:coordenadas)
            WHERE id = :id_pedido
        ";

            // Preparar la consulta
            $stmt = $db->prepare($query);

            // Asociar los valores con bindParam
            $stmt->bindParam(':destinatario', $data['destinatario'], PDO::PARAM_STR);
            $stmt->bindParam(':telefono', $data['telefono'], PDO::PARAM_STR);
            $stmt->bindParam(':direccion', $data['direccion'], PDO::PARAM_STR);
            $stmt->bindParam(':comentario', $data['comentario'], PDO::PARAM_STR);
            $stmt->bindParam(':cantidad', $data['cantidad'], PDO::PARAM_INT);
            $stmt->bindParam(':producto', $data['producto'], PDO::PARAM_STR);
            $stmt->bindParam(':precio', $data['precio'], PDO::PARAM_INT);
            $stmt->bindParam(':estado', $data['estado'], PDO::PARAM_INT);
            $stmt->bindParam(':vendedor', $data['vendedor'], PDO::PARAM_INT);
            $stmt->bindParam(':coordenadas', $coordenadas, PDO::PARAM_STR); // Pasamos el POINT como cadena
            $stmt->bindParam(':id_pedido', $data['id_pedido'], PDO::PARAM_INT);

            // Ejecutar la consulta
            $stmt->execute();

            // Retornar true si hubo cambios en la base de datos
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw new Exception("Error updating order: " . $e->getMessage());
        }
    }



    public static function obtenerEstados()
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("SELECT id, nombre_estado FROM estados");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Error al obtener los estados: " . $e->getMessage());
        }
    }


    public static function obtenerVendedores()
    {
        try {
            $db = (new Conexion())->conectar();
            $stmt = $db->prepare("SELECT id, nombre FROM vendedores");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw new Exception("Error al obtener los vendedores: " . $e->getMessage());
        }
    }



    /* cambiar  estados en los datatable */

    public static function actualizarEstado($id_pedido, $estado) {
        try {
            $db = (new Conexion())->conectar();
    
            $query = "UPDATE pedidos SET id_estado = :estado WHERE id = :id_pedido";
            $stmt = $db->prepare($query);
    
            $stmt->bindParam(":estado", $estado, PDO::PARAM_INT);
            $stmt->bindParam(":id_pedido", $id_pedido, PDO::PARAM_INT);
            
            try {
                $stmt->execute();
                return $stmt->rowCount() > 0;
            } catch (PDOException $e) {
                return ["success" => false, "message" => "Error SQL: " . $e->getMessage()];
            }
            
            
        } catch (PDOException $e) {
            return ["success" => false, "message" => "Error SQL: " . $e->getMessage()];
        }
        
    }
    
}
