<?php



require_once __DIR__ . '/../modelo/proveedor.php';

class ProveedorController
{
    public function listarProveedores()
    {
        return ProveedorModel::getAll();
    }

    public function verProveedor($id)
    {
        return ProveedorModel::getById($id);
    }

    public function crearProveedor($data)
    {
        return ProveedorModel::create($data);
    }

    public function actualizarProveedor($id, $data)
    {
        return ProveedorModel::update($id, $data);
    }

    public function eliminarProveedor($id)
    {
        $idProveedor = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$idProveedor) {
            return [
                'success' => false,
                'message' => 'Identificador de proveedor inválido.'
            ];
        }

        $resultado = ProveedorModel::delete($idProveedor);

        if ($resultado) {
            return [
                'success' => true,
                'message' => 'Proveedor eliminado correctamente.'
            ];
        }

        return [
            'success' => false,
            'message' => 'No fue posible eliminar el proveedor.'
        ];
    }

    public function crearProveedorAPI($jsonData)
    {
        $data = $jsonData;

        // Validar la estructura del proveedor
        $validacion = $this->validarDatosProveedor($data);
        if (!$validacion["success"]) {
            return $validacion;
        }

        try {
            // Insertar el proveedor
            $resultado = ProveedorModel::create($data);
            return [
                "success" => true,
                "message" => "Proveedor creado correctamente.",
                "data" => $resultado
            ];
        } catch (Exception $e) {
            return [
                "success" => false,
                "message" => "Error al crear el proveedor: " . $e->getMessage()
            ];
        }
    }

    private function validarDatosProveedor($data)
    {
        $errores = [];

        // Validar campos obligatorios
        $camposObligatorios = ["nombre", "email", "telefono", "pais", "contrasena"];
        foreach ($camposObligatorios as $campo) {
            if (!isset($data[$campo]) || empty($data[$campo])) {
                $errores[] = "El campo '$campo' es obligatorio.";
            }
        }

        // Validar formato del email
        if (isset($data["email"]) && !filter_var($data["email"], FILTER_VALIDATE_EMAIL)) {
            $errores[] = "El campo 'email' debe ser una dirección válida.";
        }

        // Devolver errores si los hay
        if (!empty($errores)) {
            return ["success" => false, "message" => "Tus datos tienen errores.", "data" => $errores];
        }

        return ["success" => true];
    }
}
