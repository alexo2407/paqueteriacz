<?php
require_once __DIR__ . '/../config/config.php'; // Ensure configuration constants are loaded

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
            $dsn = "mysql:host={$this->host};dbname={$this->dataBase};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ];

            $this->conexion = new PDO(
                $dsn,
                $this->userName,
                $this->password,
                $options
            );

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
