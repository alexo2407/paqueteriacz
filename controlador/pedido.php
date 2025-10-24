<?php

/* ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);  */

ob_start();

class PedidosController {


    /* ZONA API */

    public function crearPedidoAPI($jsonData) {
        $data = $jsonData;
    
        // Validar la estructura del pedido
        $validacion = $this->validarDatosPedido($data);
        if (!$validacion["success"]) {
            return $validacion;
        }
    
        try {
            // Verificar si el número de orden ya existe
            if (PedidosModel::existeNumeroOrden($data["numero_orden"])) {
                return [
                    "success" => false,
                    "message" => "El número de orden ya existe en la base de datos."
                ];
            }
    
            // Insertar el pedido si no existe
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
                require_once __DIR__ . '/../utils/session.php';
                set_flash('error', 'Las coordenadas no tienen un formato válido.');
                header('Location: ' . RUTA_URL . 'pedidos/editar/' . $data['id_pedido'] . '/errorLatLong');
            }
            // Validación básica para cantidad y precio (si vienen)
            if (isset($data['cantidad']) && $data['cantidad'] !== '' && !is_numeric($data['cantidad'])) {
                require_once __DIR__ . '/../utils/session.php';
                set_flash('error', 'La cantidad debe ser un número.');
                header('Location: ' . RUTA_URL . 'pedidos/editar/' . $data['id_pedido'] . '/errorCantidad');
                exit;
            }
            if (isset($data['precio']) && $data['precio'] !== '' && !is_numeric($data['precio'])) {
                require_once __DIR__ . '/../utils/session.php';
                set_flash('error', 'El precio debe ser un valor numérico.');
                header('Location: ' . RUTA_URL . 'pedidos/editar/' . $data['id_pedido'] . '/errorPrecio');
                exit;
            }
            
          
            // Llama al modelo para actualizar el pedido
            $resultado = PedidosModel::actualizarPedido($data);
    
           //var_dump($data);

            
            if ($resultado) {
                // Redirigir con éxito
                require_once __DIR__ . '/../utils/session.php';
                set_flash('success', 'Pedido actualizado correctamente.');
                header('Location: '. RUTA_URL . 'pedidos/listar');
            } else {
                // Redirigir con un mensaje de error si no hubo cambios
                require_once __DIR__ . '/../utils/session.php';
                set_flash('error', 'No se realizaron cambios en el pedido.');
                header('Location: ' . RUTA_URL . 'pedidos/editar/' . $data['id_pedido'] . '/error');
            }
        } catch (Exception $e) {
            // Redirigir con mensaje de error en caso de excepción
            header('Location: ' . RUTA_URL . 'pedidos/editar/' . $data['id_pedido'] . '/error'. urlencode($e->getMessage()));
        }
        exit;
    }
    
    /* cambiar estados en los datatable */
    public static function actualizarEstadoAjax($datos) {

            $id_pedido = intval($datos["id_pedido"]);
            $nuevo_estado = intval($datos["estado"]);
                if (empty($id_pedido) || empty($nuevo_estado)) {
                    echo json_encode(["success" => false, "message" => "Datos inválidos. ID o Estado vacío."]);
                    exit();
                }

                $resultado = PedidosModel::actualizarEstado($id_pedido, $nuevo_estado);

                header('Content-Type: application/json');
                echo json_encode([
                    "success" => $resultado,
                    "message" => $resultado ? "Estado actualizado correctamente." : "Error al actualizar el estado."
                ]);
                exit();
            }

        /**
         * Importar pedidos desde archivo CSV subido por formulario
         */
    public function importarPedidosCSV()
    {
        require_once __DIR__ . '/../utils/session.php';

        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            set_flash('error', 'No se recibió el archivo CSV o hubo un error al subirlo.');
            header('Location: ' . RUTA_URL . 'pedidos/listar');
            exit;
        }

        $tmp = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($tmp, 'r');
        if ($handle === false) {
            set_flash('error', 'No se pudo abrir el archivo CSV.');
            header('Location: ' . RUTA_URL . 'pedidos/listar');
            exit;
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            set_flash('error', 'El CSV parece estar vacío.');
            fclose($handle);
            header('Location: ' . RUTA_URL . 'pedidos/listar');
            exit;
        }

        // Normalizar cabeceras
        $cols = array_map(function($c){ return strtolower(trim($c)); }, $header);

        // Campos mínimos requeridos
        $required = ['numero_orden','destinatario','telefono','producto','cantidad','direccion','latitud','longitud'];
        $missing = [];
        foreach ($required as $r) {
            if (!in_array($r, $cols)) $missing[] = $r;
        }
        if (!empty($missing)) {
            set_flash('error', 'Faltan columnas requeridas en el CSV: ' . implode(', ', $missing));
            fclose($handle);
            header('Location: ' . RUTA_URL . 'pedidos/listar');
            exit;
        }

        $line = 1;
        $success = 0;
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            // Mapear fila a asociativo según cabeceras
            $dataRow = [];
            foreach ($cols as $i => $colName) {
                $dataRow[$colName] = isset($row[$i]) ? $row[$i] : null;
            }

            // Preparar datos para crearPedido
            $lat = $dataRow['latitud'] ?? null;
            $lng = $dataRow['longitud'] ?? null;
            if ($lat === null || $lng === null || !is_numeric($lat) || !is_numeric($lng)) {
                $errors[] = "Línea $line: coordenadas inválidas.";
                continue;
            }

            $pedidoData = [
                'numero_orden' => $dataRow['numero_orden'] ?? null,
                'destinatario' => $dataRow['destinatario'] ?? null,
                'telefono' => $dataRow['telefono'] ?? null,
                'precio' => $dataRow['precio'] ?? null,
                'producto' => $dataRow['producto'] ?? null,
                'cantidad' => isset($dataRow['cantidad']) && $dataRow['cantidad'] !== '' ? (int)$dataRow['cantidad'] : 1,
                'pais' => $dataRow['pais'] ?? null,
                'departamento' => $dataRow['departamento'] ?? null,
                'municipio' => $dataRow['municipio'] ?? null,
                'barrio' => $dataRow['barrio'] ?? null,
                'direccion' => $dataRow['direccion'] ?? null,
                'zona' => $dataRow['zona'] ?? null,
                'comentario' => $dataRow['comentario'] ?? null,
                'coordenadas' => trim($lat) . ',' . trim($lng)
            ];

            try {
                // Evitar duplicados por numero_orden
                if (PedidosModel::existeNumeroOrden($pedidoData['numero_orden'])) {
                    $errors[] = "Línea $line: el número de orden {$pedidoData['numero_orden']} ya existe.";
                    continue;
                }

                $res = PedidosModel::crearPedido($pedidoData);
                if (!empty($res['pedido_id'])) {
                    $success++;
                } else {
                    $errors[] = "Línea $line: no se pudo insertar el pedido (resultado inesperado).";
                }
            } catch (Exception $e) {
                $errors[] = "Línea $line: error al insertar - " . $e->getMessage();
            }
        }

        fclose($handle);

        $msg = "$success pedidos importados correctamente.";
        if (!empty($errors)) {
            $msg .= " Problemas: " . count($errors) . ". Revisa errores detallados.";
            // Guardar errores extendidos en sesión para revisar
            $_SESSION['import_errors'] = $errors;
            set_flash('error', $msg);
        } else {
            set_flash('success', $msg);
        }

        header('Location: ' . RUTA_URL . 'pedidos/listar');
        exit;
    }
    
    
}

