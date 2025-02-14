<?php


class PedidosController {


    /* ZONA API */

    public function crearPedidoAPI($jsonData) {
        // Decodificar el JSON recibido
        $data = $jsonData;

        // Validar la estructura del pedido
        $validacion = $this->validarDatosPedido($data);
        if (!$validacion["success"]) {
            return $validacion; // Retorna los errores de validación
        }

        try {
            // Llamar al modelo para insertar el pedido
            $resultado = PedidosModel::crearPedido($data);
            return [
                "success" => true,
                "message" => "Pedido creado correctamente.",
                "data" => $resultado
            ];
        } catch (Exception $e) {
            return [
                "success" => false,
                "message" => "Error al crear el pedido: " . $e->getMessage()
            ];
        }
    }

    private function validarDatosPedido($data) {
        $errores = [];

        // Validar campos obligatorios
        $camposObligatorios = [
            "numero_orden", "destinatario", "telefono", "producto", "cantidad",
            "pais", "departamento", "municipio", "direccion", "coordenadas"
        ];
        foreach ($camposObligatorios as $campo) {
            if (!isset($data[$campo]) || empty($data[$campo])) {
                $errores[] = "El campo '$campo' es obligatorio.";
            }
        }

        // Validar formato de las coordenadas
        if (isset($data["coordenadas"])) {
            $coords = explode(',', $data["coordenadas"]);
            if (count($coords) !== 2 || !is_numeric($coords[0]) || !is_numeric($coords[1])) {
                $errores[] = "El campo 'coordenadas' debe estar en el formato 'latitud,longitud'.";
            }
        }

        // Devolver errores si los hay
        if (!empty($errores)) {
            return ["success" => false, "message" => "Tus datos tienen errores de procedencia arreglalos.", "data" => $errores];
        }

        return ["success" => true];
    }

    public function buscarPedidoPorNumero($numeroOrden) {
        // Verificar si existe el pedido en la base de datos
        $pedido = PedidosModel::obtenerPedidoPorNumero($numeroOrden);

        if ($pedido) {
            // Devolver los datos del pedido encontrados
            return [
                'success' => true,
                'data' => [
                    "numero_orden" => $pedido['numero_orden'],
                    "destinatario" => $pedido['destinatario'],
                    "telefono" => $pedido['telefono'],
                    "precio" => $pedido['precio'],
                    "producto" => $pedido['producto'],
                    "cantidad" => $pedido['cantidad'],
                    "pais" => $pedido['pais'],
                    "coordenadas" => $pedido['coordenadas'],
                    "estado" => $pedido['nombre_estado'] // Nombre del estado del producto
                ]
            ];
        } else {
            // Si no se encuentra, devolver un mensaje de error
            return [
                'success' => false,
                'message' => 'Order not found'
            ];
        }
    }


    /* ZONA DEL FRONT END */

    public function listarPedidosExtendidos() {
        // Llamar al modelo para obtener los pedidos
        $pedidos = PedidosModel::obtenerPedidosExtendidos();
        return $pedidos;
    }


}
