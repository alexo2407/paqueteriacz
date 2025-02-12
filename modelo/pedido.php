<?php
include_once "modelo/conexion.php";


class PedidosModel
{
    public static function crearPedido($pedidoData, $productos)
{
    try {
        $db = (new Conexion())->conectar();

        // Verificar si ya hay una transacción activa
        if (!$db->inTransaction()) {
            $db->beginTransaction();
        }

        

        // Verificar si el usuario existe
        if (!self::verificarUsuario($pedidoData["ID_Usuario"])) {
            throw new Exception("El ID_Usuario '{$pedidoData["ID_Usuario"]}' no existe en la tabla usuarios.");
        }

        // Insertar el cliente (o verificar si existe)
        $idCliente = self::verificarOCrearCliente($pedidoData["cliente"]);
        if (!$idCliente) {
            throw new Exception("Error al verificar o crear el cliente.");
        }
        $pedidoData["ID_Cliente"] = $idCliente;

        // Insertar el pedido
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

        foreach ($productos as $producto) {
            if (!self::verificarProducto($producto["ID_Producto"])) {
                throw new Exception("El ID_Producto '{$producto["ID_Producto"]}' no existe en la tabla stock_productos.");
            }
        
            // Insertar el producto en pedidos_productos
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
        
        foreach ($productos as $producto) {
            if (!self::verificarProductoPorNombre($producto["ID_Producto"], $producto["Nombre"])) {
                throw new Exception("El ID_Producto '{$producto["ID_Producto"]}' no coincide con el Nombre '{$producto["Nombre"]}'.");
            }
        
            // Insertar el producto en pedidos_productos
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
        
        // Confirmar la transacción
        $db->commit();
        return $idPedido;

    } catch (Exception $e) {
        // Si hay una transacción activa, hacer rollback
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Error al crear pedido: " . $e->getMessage());
        throw $e; // Relanzar la excepción para que el controlador la maneje
    }
}



public static function verificarUsuario($idUsuario)
{
    try {
        $db = (new Conexion())->conectar();

        // Verificar si el ID_Usuario existe en la tabla usuarios
        $stmt = $db->prepare("SELECT ID_Usuario FROM usuarios WHERE ID_Usuario = :ID_Usuario");
        $stmt->execute([':ID_Usuario' => $idUsuario]);

        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    } catch (Exception $e) {
        error_log("Error al verificar usuario: " . $e->getMessage());
        return false;
    }
}

public static function verificarOCrearCliente($cliente)
{
    try {
        $db = (new Conexion())->conectar();

        // Verificar si el usuario existe
        if (!self::verificarUsuario($cliente['ID_Usuario'])) {
            throw new Exception("El ID_Usuario '{$cliente['ID_Usuario']}' no existe en la tabla usuarios.");
        }

        // Verificar si el cliente ya existe por nombre
        $stmt = $db->prepare("SELECT ID_Cliente FROM clientes WHERE Nombre = :Nombre");
        $stmt->execute([':Nombre' => $cliente['Nombre']]);
        $clienteExistente = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($clienteExistente) {
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

            return $db->lastInsertId();
        }
    } catch (Exception $e) {
        error_log("Error en verificarOCrearCliente: " . $e->getMessage());
        return false;
    }
}

public static function verificarProducto($idProducto)
{
    try {
        $db = (new Conexion())->conectar();

        // Verificar si el producto existe en la tabla stock_productos
        $stmt = $db->prepare("SELECT ID_Producto FROM stock_productos WHERE ID_Producto = :ID_Producto");
        $stmt->execute([':ID_Producto' => $idProducto]);

        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    } catch (Exception $e) {
        error_log("Error al verificar producto: " . $e->getMessage());
        return false;
    }
}

public static function verificarProductoPorNombre($idProducto, $nombreProducto)
{
    try {
        $db = (new Conexion())->conectar();

        // Verificar si el producto con ese ID y nombre existe
        $stmt = $db->prepare("
            SELECT ID_Producto FROM stock_productos 
            WHERE ID_Producto = :ID_Producto AND Nombre = :Nombre
        ");
        $stmt->execute([
            ':ID_Producto' => $idProducto,
            ':Nombre' => $nombreProducto
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    } catch (Exception $e) {
        error_log("Error al verificar producto por nombre: " . $e->getMessage());
        return false;
    }
}


}
