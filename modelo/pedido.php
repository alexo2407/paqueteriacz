<?php
include_once "modelo/conexion.php";


class PedidosModel
{
    public static function crearPedido($pedidoData, $productos)
{
    try {
        $db = (new Conexion())->conectar();

        // Validar si el Numero_Orden ya existe
        $stmt = $db->prepare("SELECT ID_Pedido FROM pedidos WHERE Numero_Orden = :Numero_Orden");
        $stmt->execute([":Numero_Orden" => $pedidoData["Numero_Orden"]]);
        $pedidoExistente = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($pedidoExistente) {
            throw new Exception("El Número de Orden '{$pedidoData["Numero_Orden"]}' ya existe.");
        }

        // Iniciar la transacción
        $db->beginTransaction();

        // Verificar o crear cliente
        $idCliente = self::verificarOCrearCliente($pedidoData["cliente"]);
        if (!$idCliente) {
            throw new Exception("Error al verificar o crear el cliente.");
        }
        $pedidoData["ID_Cliente"] = $idCliente;

        // Insertar pedido
        $stmt = $db->prepare("
            INSERT INTO pedidos (
                Numero_Orden, ID_Cliente, ID_Usuario, 
                Zona, Departamento, Municipio, Barrio, Direccion_Completa, 
                Comentario, Latitud, Longitud, created_at, updated_at
            ) VALUES (
                :Numero_Orden, :ID_Cliente, :ID_Usuario, 
                :Zona, :Departamento, :Municipio, :Barrio, :Direccion_Completa, 
                :Comentario, :Latitud, :Longitud, NOW(), NOW()
            )
        ");
        $stmt->execute([
            ":Numero_Orden" => $pedidoData["Numero_Orden"],
            ":ID_Cliente" => $pedidoData["ID_Cliente"],
            ":ID_Usuario" => $pedidoData["ID_Usuario"],
            ":Zona" => $pedidoData["Zona"],
            ":Departamento" => $pedidoData["Departamento"],
            ":Municipio" => $pedidoData["Municipio"],
            ":Barrio" => $pedidoData["Barrio"],
            ":Direccion_Completa" => $pedidoData["Direccion_Completa"],
            ":Comentario" => $pedidoData["Comentario"],
            ":Latitud" => $pedidoData["Latitud"],
            ":Longitud" => $pedidoData["Longitud"]
        ]);
        $idPedido = $db->lastInsertId();

        // Insertar productos
        foreach ($productos as $producto) {
            $stmt = $db->prepare("
                INSERT INTO pedidos_productos (ID_Pedido, ID_Producto, Cantidad)
                VALUES (:ID_Pedido, :ID_Producto, :Cantidad)
            ");
            $stmt->execute([
                ":ID_Pedido" => $idPedido,
                ":ID_Producto" => $producto["ID_Producto"],
                ":Cantidad" => $producto["Cantidad"]
            ]);
        }

        $db->commit();
        return $idPedido;

    } catch (Exception $e) {
        // Solo intentar rollback si la transacción está activa
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Error al crear pedido: " . $e->getMessage());
        throw $e; // Lanza el error para que el controlador lo maneje
    }
}


public static function verificarOCrearCliente($cliente)
{
    try {
        $db = (new Conexion())->conectar();

        // Verificar si el cliente ya existe por nombre
        $stmt = $db->prepare("SELECT ID_Cliente FROM clientes WHERE Nombre = :Nombre");
        $stmt->execute([':Nombre' => $cliente['Nombre']]);
        $clienteExistente = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($clienteExistente) {
            // Si el cliente existe, retornar su ID
            return $clienteExistente['ID_Cliente'];
        } else {
            // Crear cliente si no existe
            $stmt = $db->prepare("
                INSERT INTO clientes (Nombre, ID_Usuario, created_at, updated_at)
                VALUES (:Nombre, :ID_Usuario, NOW(), NOW())
            ");
            $stmt->execute([
                ':Nombre' => $cliente['Nombre'],
                ':ID_Usuario' => $cliente['ID_Usuario']
            ]);

            // Retornar el ID del cliente recién creado
            return $db->lastInsertId();
        }
    } catch (Exception $e) {
        error_log("Error en verificarOCrearCliente: " . $e->getMessage());
        return false; // Retornar false en caso de error
    }
}

}
