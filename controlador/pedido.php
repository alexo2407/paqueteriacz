<?php


class PedidosController
{
    public function crearPedidoAPI($jsonData)
    {
        // Decodificar el JSON recibido
        $data = json_decode($jsonData, true);

        // Validar la estructura del pedido
        $validacion = $this->validarDatosPedido($data);

        if (!$validacion["success"]) {
            return $validacion; // Retorna los errores de validación

        }

        try {
            // Dividir datos del pedido y productos
            $pedidoData = [
                "Numero_Orden" => $data["Numero_Orden"],
                "cliente" => [
                    "Nombre" => $data["cliente"]["Nombre"],
                    "ID_Usuario" => $data["cliente"]["ID_Usuario"]
                ],
                "ID_Usuario" => $data["usuario"]["ID_Usuario"],
                "Zona" => $data["Zona"],
                "Departamento" => $data["Departamento"],
                "Municipio" => $data["Municipio"],
                "Barrio" => $data["Barrio"],
                "Direccion_Completa" => $data["Direccion_Completa"],
                "Comentario" => $data["Comentario"],
                "Latitud" => $data["Latitud"],
                "Longitud" => $data["Longitud"]
            ];
            $productos = $data["productos"];

            // Enviar los datos al modelo
            $idPedido = PedidosModel::crearPedido($pedidoData, $productos);

            if (!$idPedido) {
                return [
                    "success" => false,
                    "message" => "Error al crear el pedido. Verifica los datos enviados."
                ];
            }

            return [
                "success" => true,
                "message" => "Pedido creado correctamente",
                "data" => ["ID_Pedido" => $idPedido]
            ];
        } catch (Exception $e) {
            // Capturar cualquier excepción y devolverla como respuesta
            return [
                "success" => false,
                "message" => $e->getMessage()
            ];
        }
    }

private function consolidarProductos($productos)
{
    $productosConsolidados = [];

    foreach ($productos as $producto) {
        $idProducto = $producto["ID_Producto"];
        $cantidad = $producto["Cantidad"];

        if (isset($productosConsolidados[$idProducto])) {
            $productosConsolidados[$idProducto] += $cantidad;
        } else {
            $productosConsolidados[$idProducto] = $cantidad;
        }
    }

    $resultado = [];
    foreach ($productosConsolidados as $idProducto => $cantidad) {
        $resultado[] = [
            "ID_Producto" => $idProducto,
            "Cantidad" => $cantidad
        ];
    }

    return $resultado;
}



    private function validarDatosPedido($data)
    {
        // Campos obligatorios para el pedido
        $requeridos = [
            "Numero_Orden" => "string",
            "cliente" => "array",
            "usuario" => "array",
            "Zona" => "string",
            "Departamento" => "string",
            "Municipio" => "string",
            "Barrio" => "string",
            "Direccion_Completa" => "string",
            "Comentario" => "string",
            "Latitud" => "float",
            "Longitud" => "float",
            "productos" => "array"
        ];

        foreach ($requeridos as $campo => $tipo) {
            // Validar si el campo existe
            if (!isset($data[$campo])) {
                return [
                    "success" => false,
                    "message" => "El campo '$campo' es obligatorio."
                ];
            }

            // Validar el tipo de dato
            if ($tipo === "float" && !is_numeric($data[$campo])) {
                return [
                    "success" => false,
                    "message" => "El campo '$campo' debe ser de tipo $tipo."
                ];
            }

            if ($tipo === "string" && !is_string($data[$campo])) {
                return [
                    "success" => false,
                    "message" => "El campo '$campo' debe ser de tipo $tipo."
                ];
            }

            if ($tipo === "array" && !is_array($data[$campo])) {
                return [
                    "success" => false,
                    "message" => "El campo '$campo' debe ser de tipo $tipo."
                ];
            }
        }

        // Validar estructura de cliente
        if (!isset($data["cliente"]["Nombre"]) || !isset($data["cliente"]["ID_Usuario"])) {
            return [
                "success" => false,
                "message" => "El cliente debe tener 'Nombre' e 'ID_Usuario'."
            ];
        }

        // Validar estructura de productos
        foreach ($data["productos"] as $producto) {
            if (!isset($producto["ID_Producto"]) || !isset($producto["Cantidad"]) || !isset($producto["Nombre"])) {
                return [
                    "success" => false,
                    "message" => "Cada producto debe tener 'ID_Producto', 'Nombre' y 'Cantidad'."
                ];
            }

            if (!is_int($producto["ID_Producto"]) || !is_int($producto["Cantidad"])) {
                return [
                    "success" => false,
                    "message" => "Los campos 'ID_Producto' y 'Cantidad' deben ser enteros."
                ];
            }

            if (!is_string($producto["Nombre"]) || empty($producto["Nombre"])) {
                return [
                    "success" => false,
                    "message" => "El campo 'Nombre' debe ser una cadena de texto no vacía."
                ];
            }
        }


        return ["success" => true];
    }
}
