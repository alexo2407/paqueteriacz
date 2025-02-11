<?php

class PedidosController
{
    /**
     * Obtener todos los pedidos
     *
     * @return array Lista de pedidos
     */
    public function listarPedidos()
    {
        // Llama al modelo para obtener todos los pedidos
        return PedidosModel::obtenerTodos();
    }

    public function listarPedidosExtendidos()
    {
        return PedidosModel::obtenerPedidosExtendidos();
    }

    public function verPedido($idPedido)
    {


        return  PedidosModel::obtenerDetallesPedido($idPedido);
    }



    /**
     * Obtener un pedido por su ID
     *
     * @param int $idPedido ID del pedido
     * @return PedidosModel|null Pedido encontrado o null si no existe
     */
    public function mostrarPedido($idPedido)
    {
        // Llama al modelo para obtener un pedido específico
        return PedidosModel::obtenerPorId($idPedido);
    }

    /**
     * Crear un nuevo pedido
     *
     * @param array $datos Datos del pedido
     * @return bool true si se creó correctamente, false en caso contrario
     */
    public function crearPedido($datos)
    {
        // Validar los datos antes de enviarlos al modelo
        if ($this->validarDatosPedido($datos)) {
            return PedidosModel::crear($datos);
        } else {
            return false;
        }
    }

    /**
     * Actualizar un pedido existente
     *
     * @param int $idPedido ID del pedido
     * @param array $datos Datos actualizados del pedido
     * @return bool true si se actualizó correctamente, false en caso contrario
     */
    public function actualizarPedido($idPedido, $datos)
    {
        // Validar los datos antes de enviarlos al modelo
        if ($this->validarDatosPedido($datos)) {
            return PedidosModel::actualizar($idPedido, $datos);
        } else {
            return false;
        }
    }

    /**
     * Eliminar un pedido por su ID
     *
     * @param int $idPedido ID del pedido
     * @return bool true si se eliminó correctamente, false en caso contrario
     */
    public function eliminarPedido($idPedido)
    {
        // Llama al modelo para eliminar el pedido
        return PedidosModel::eliminar($idPedido);
    }


    /**
     * Cambiar el estado de un pedido
     *
     * @param int $idPedido ID del pedido
     * @param int $nuevoEstado Nuevo estado del pedido
     * @return bool true si se cambió correctamente, false en caso contrario
     */
    public function cambiarEstadoPedido($idPedido, $nuevoEstado)
    {
        return PedidosModel::cambiarEstado($idPedido, $nuevoEstado);
    }

    /**
     * Endpoint para cambiar el estado de un pedido vía Ajax
     */
    public function cambiarEstadoAjax()
    {
        // Verificar que sea una solicitud POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Decodificar el JSON recibido
            $data = json_decode(file_get_contents('php://input'), true);

            // Validar los datos recibidos
            if (isset($data['idPedido']) && isset($data['nuevoEstado'])) {
                $idPedido = intval($data['idPedido']);
                $nuevoEstado = intval($data['nuevoEstado']);

                // Llamar al modelo para cambiar el estado
                $resultado = PedidosModel::cambiarEstado($idPedido, $nuevoEstado);

                // Enviar respuesta JSON
                if ($resultado) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al actualizar en la base de datos.']);
                }
            } else {
                // Respuesta en caso de datos incompletos
                echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
            }
        } else {
            // Respuesta en caso de método no permitido
            echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
        }
        exit; // Asegurarse de que no continúe ejecutando más código
    }

    /**
     * Validar datos del pedido
     *
     * @param array $datos Datos del pedido
     * @return bool true si los datos son válidos, false en caso contrario
     */
    private function validarDatosPedido($datos)
    {
        // Verificar que no falten datos obligatorios
        $requeridos = [
            'Numero_Orden',
            'ID_Cliente',
            'ID_Usuario',
            'Fecha_Ingreso',
            'Zona',
            'Departamento',
            'Municipio',
            'Barrio',
            'Direccion_Completa',
            'Comentario',
            'Latitud',
            'Longitud',
            'ID_Estado'
        ];

        foreach ($requeridos as $campo) {
            if (empty($datos[$campo])) {
                return false; // Faltan datos obligatorios
            }
        }

        return true;
    }
    public function crearPedidoAPI($jsonData)
    {
        try {
            // Decodificar el JSON
            if (is_string($jsonData)) {
                $pedido = json_decode($jsonData, true);
            } else {
                throw new Exception("El formato del JSON no es válido.");
            }
    
            if (!$pedido) {
                return ['success' => false, 'message' => 'JSON inválido o no se pudo decodificar.'];
            }
    
            // Verificar o crear cliente
            $idCliente = PedidosModel::verificarOCrearCliente($pedido['cliente']);
            if (!$idCliente) {
                return ['success' => false, 'message' => 'Error al verificar o crear cliente'];
            }
    
            // Preparar datos del pedido
            $pedidoData = [
                'Numero_Orden' => $pedido['Numero_Orden'],
                'ID_Cliente' => $idCliente,
                'ID_Usuario' => $pedido['usuario']['ID_Usuario'],
                'Zona' => $pedido['Zona'],
                'Departamento' => $pedido['Departamento'],
                'Municipio' => $pedido['Municipio'],
                'Barrio' => $pedido['Barrio'],
                'Direccion_Completa' => $pedido['Direccion_Completa'],
                'Comentario' => $pedido['Comentario'],
                'Latitud' => $pedido['Latitud'],
                'Longitud' => $pedido['Longitud']
            ];
    
            // Crear pedido
            $idPedido = PedidosModel::crearPedido($pedidoData);
            if (!$idPedido) {
                return ['success' => false, 'message' => 'Error al crear el pedido'];
            }
    
            // Insertar productos
            foreach ($pedido['productos'] as $producto) {
                $idProducto = PedidosModel::verificarOCrearProducto($producto);
                if (!$idProducto) {
                    return ['success' => false, 'message' => 'Error al verificar o crear producto: ' . $producto['Nombre']];
                }
    
                if (!PedidosModel::insertarProductoEnPedido($idPedido, $idProducto, $producto['Cantidad'])) {
                    return ['success' => false, 'message' => 'Error al insertar producto en pedido'];
                }
            }
    
            // Insertar estado inicial
            if (!PedidosModel::insertarEstadoInicial($idPedido, $pedido['usuario']['ID_Usuario'])) {
                return ['success' => false, 'message' => 'Error al insertar estado inicial'];
            }
    
            // Respuesta de éxito
            return [
                'success' => true,
                'message' => 'Pedido creado correctamente',
                'data' => [
                    'ID_Pedido' => $idPedido,
                    'Numero_Orden' => $pedido['Numero_Orden'],
                    'Estado_Actual' => 'En bodega',
                    'productos' => $pedido['productos']
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
}
