<?php

class Conexion
{
    // Parámetros de configuración de la BD
    private $host;
    private $dataBase;
    private $userName;
    private $password;
    private $conexion;

    public function __construct()
    {
        $this->host = DB_HOST;
        $this->dataBase = DB_SCHEMA;
        $this->userName = DB_USER;
        $this->password = DB_PASSWORD;
    }

    // Método para conectar a la BD
    public function conectar()
    {
        $this->conexion = null;

        try {
            // Crear la conexión usando PDO
            $this->conexion = new PDO(
                "mysql:host={$this->host};dbname={$this->dataBase}",
                $this->userName,
                $this->password
            );

            // Configurar atributos de PDO
            $this->conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        } catch (PDOException $e) {
            // Manejo de errores
            error_log("Error en la conexión a la Base de Datos: " . $e->getMessage(), 3, "logs/errors.log");
            die("Ocurrió un problema al conectar con la base de datos. Inténtalo más tarde.");
        }

        return $this->conexion;
    }

    // Método para cerrar la conexión (opcional)
    public function desconectar()
    {
        $this->conexion = null;
    }
}
