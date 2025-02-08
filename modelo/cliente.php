<?php

include_once "modelo/conexion.php";

class ClientesModel
{
    public int $ID_Cliente;
    public string $Nombre;

    public function __construct(int $ID_Cliente = null, string $Nombre = null)
    {
        $this->ID_Cliente = $ID_Cliente;
        $this->Nombre = $Nombre;
    }

    // Obtiene todos los clientes activos
    public static function getAll(): array
    {
        $resultado = [];
        try {
            // Instanciar conexión
            $dataBase = new Conexion();
            $db = $dataBase->conectar();

            // Preparar consulta
            $consulta = $db->prepare("SELECT ID_Cliente, Nombre FROM clientes WHERE activo = 1");

            // Ejecutar consulta
            $consulta->execute();

            // Convertir resultados en instancias de ClientesModel
            while ($fila = $consulta->fetch(PDO::FETCH_ASSOC)) {
                $resultado[] = new ClientesModel($fila['ID_Cliente'], $fila['Nombre']);
            }
        } catch (PDOException $e) {
            // Manejo de errores
            error_log("Error al obtener clientes: " . $e->getMessage(), 3, "logs/errors.log");
        } finally {
            // Limpiar recursos
            $consulta = null;
            $db = null;
        }


        return $resultado;
    }




    /**
     * Obtener un cliente por su ID
     *
     * @param int $idCliente
     * @return object|null Cliente como objeto o null si no existe
     */
    public static function obtenerClientePorId($idCliente)
    {
        try {
            // Instancia de la conexión a la base de datos
            $dataBase = new Conexion();
            $db = $dataBase->conectar();

            // Consulta preparada para evitar inyección SQL
            $consulta = $db->prepare("SELECT ID_Cliente, Nombre, activo FROM clientes WHERE ID_Cliente = :id");
            $consulta->bindParam(":id", $idCliente, PDO::PARAM_INT);

            // Ejecutar consulta
            $consulta->execute();

            // Retornar cliente como objeto
            $cliente = $consulta->fetch(PDO::FETCH_OBJ);
            return $cliente ?: null; // Si no se encuentra, devuelve null

        } catch (PDOException $e) {
            // Loguear errores en un archivo
            error_log("Error al obtener cliente: " . $e->getMessage(), 3, "logs/errors.log");
            return null;
        }
    }

    /**
     * Actualizar un cliente por su ID
     *
     * @param int $idCliente
     * @param string $nombre
     * @param int $activo
     * @return bool True si la actualización fue exitosa, False si falló
     */
    public static function actualizarCliente($idCliente, $nombre, $activo)
    {
        try {
            $dataBase = new Conexion();
            $db = $dataBase->conectar();

            // Consulta preparada para actualizar el cliente
            $consulta = $db->prepare("UPDATE clientes SET Nombre = :nombre, activo = :activo WHERE ID_Cliente = :id");
            $consulta->bindParam(":nombre", $nombre, PDO::PARAM_STR);
            $consulta->bindParam(":activo", $activo, PDO::PARAM_INT);
            $consulta->bindParam(":id", $idCliente, PDO::PARAM_INT);

            return $consulta->execute(); // Devuelve True si la actualización fue exitosa
        } catch (PDOException $e) {
            error_log("Error al actualizar cliente: " . $e->getMessage(), 3, "logs/errors.log");
            return false;
        }
    }

}
