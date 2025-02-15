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



    /* ZONA DEL FRONT END */

    public function listarPedidosExtendidos() {
        // Llamar al modelo para obtener los pedidos
        $pedidos = PedidosModel::obtenerPedidosExtendidos();
        return $pedidos;
    }


    public function obtenerPedido($pedidoID ) {
      if (!$pedidoID) {
          echo "<div class='alert alert-danger'>No order ID provided.</div>";
          exit;
      }
      
        return PedidosModel::obtenerPedidoPorId($pedidoID[2]);
    }


    public function actualizarPedido($data) {
        $resultado = PedidosModel::actualizarPedido($data);
        if ($resultado) {
            return [
                "success" => true,
                "message" => "Pedido actualizado correctamente."
            ];
        } else {
            return [
                "success" => false,
                "message" => "No se realizaron cambios en el pedido."
            ];
        }
    }
    
    public function obtenerEstados() {
        return PedidosModel::obtenerEstados();
    }
    
    public function obtenerVendedores() {
        return PedidosModel::obtenerVendedores();
    }


    public function guardarEdicion($data) {
        try {


            if (!is_numeric($data['latitud']) || !is_numeric($data['longitud'])) {
                header('Location: ' . RUTA_URL . 'pedidos/editar/' . $data['id_pedido'] . '/errorLatLong');
            }
            
          
            // Llama al modelo para actualizar el pedido
            $resultado = PedidosModel::actualizarPedido($data);
    
           //var_dump($data);

            
            if ($resultado) {
                // Redirigir con éxito
                header('Location: ' . RUTA_URL . 'pedidos/editar/'. $data['id_pedido'] . '/success');
            } else {
                // Redirigir con un mensaje de error si no hubo cambios
                header('Location: ' . RUTA_URL . 'pedidos/editar/' . $data['id_pedido'] . '/error');
            }
        } catch (Exception $e) {
            // Redirigir con mensaje de error en caso de excepción
            header('Location: ' . RUTA_URL . 'pedidos/editar/' . $data['id_pedido'] . '/error'. urlencode($e->getMessage()));
        }
        exit;
    }
    
    
}
