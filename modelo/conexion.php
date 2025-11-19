<?php
require_once __DIR__ . '/../config/config.php'; // Ensure configuration constants are loaded

/**
 * Conexion
 *
 * Simple PDO-based database connection wrapper used across models.
 * Reads DB_* constants from `config/config.php` and returns a PDO instance
 * configured with sensible defaults (exceptions, native prepares, utf8mb4).
 *
 * Usage:
 *   $db = (new Conexion())->conectar();
 */
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

    /**
     * Establecer y retornar una conexión PDO a la base de datos.
     *
     * Configura PDO con:
     * - ERRMODE => EXCEPTION
     * - EMULATE_PREPARES => false
     * - DEFAULT_FETCH_MODE => ASSOC
     *
     * @return PDO Instancia de conexión a la base de datos
     */
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

    /**
     * Cerrar la conexión establecida (libera el objeto PDO).
     * Método opcional; en PHP la conexión se cierra al destruir el objeto.
     *
     * @return void
     */
    public function desconectar()
    {
        $this->conexion = null;
    }
}
