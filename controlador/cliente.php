<?php

class ClientesController
{
    /**
     * Mostrar todos los clientes activos
     * 
     * @return array Lista de clientes o un mensaje de error
     */
    public function mostrarClientesController(): array
    {
        try {
            // Llama al modelo para obtener todos los clientes
            $respuesta = ClientesModel::getAll();

            // Verifica si la respuesta está vacía
            if (empty($respuesta)) {
                return ["error" => "No se encontraron clientes activos."];
            }

            
            return $respuesta;

        } catch (Exception $e) {
            // Manejo de errores
            error_log("Error en ClientesController: " . $e->getMessage(), 3, "logs/errors.log");
            return ["error" => "Ocurrió un problema al obtener la lista de clientes. Por favor, inténtalo más tarde."];
        }
    }

     /**
     * Obtener un cliente por su ID
     *
     * @param int $idCliente
     * @return object|null Cliente como objeto o null si no existe
     */
    public function obtenerClientePorId($idCliente)
    {
        // Llamar al modelo para obtener los datos del cliente
        $cliente = ClientesModel::obtenerClientePorId($idCliente);

        // Manejar caso en el que no se encuentra el cliente
        if (!$cliente) {
            error_log("Cliente con ID {$idCliente} no encontrado.");
        }

        return $cliente;
    }

    /**
     * Guardar la actualización de un cliente
     *
     * @param int $idCliente
     * @param string $nombre
     * @param int $activo
     * @return bool True si la actualización fue exitosa, False si falló
     */
    public function actualizarCliente($idCliente, $nombre, $activo)
    {
        return ClientesModel::actualizarCliente($idCliente, $nombre, $activo);
    }
}





