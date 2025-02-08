<?php 


class Conexion {

//parametros de la BD

private $host  = DB_HOST;
private $dataBase = DB_SCHEMA;
private $userName = DB_USER;
private $password = DB_PASSWORD;

private $conexion;

//creamos el metodo de conexion'

public function conectar()
{
    $this->conexion = null;

    try
    {
        // instanciamos la conexion con propiedades privadas a PDO
        $this->conexion = new PDO("mysql:host=".$this->host.";dbname=".$this->dataBase,$this->userName,$this->password);

        $this->conexion->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);

    } catch (PDOException $e)
    {
        echo "Error en la conexión a la Base de Datos".$e->getMessage();
    }

    // retornamos la conexion

    return $this->conexion;

}






}
