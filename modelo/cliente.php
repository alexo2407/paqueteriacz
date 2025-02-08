<?php

include_once "modelo/conexion.php";


class ClientesModel
{
    public $ID_Cliente;
    public $Nombre;

    public function __construct($ID_Cliente = null, $Nombre = null)
    {
        $this->ID_Cliente = $ID_Cliente;
        $this->Nombre = $Nombre;
    }

    // Obtiene todos los clientes
    public static function getAll()
    {
        //instanciamos la BD

        $tabla = "clientes";

        $dataBase = new Conexion();
        $db = $dataBase->conectar();

         //preparamos la consulta

         $consulta = $db->prepare("SELECT * FROM $tabla WHERE activo = 1");

         //ejecutamos la consulta
 
         $consulta->execute();
 
         //la repuesta la enviamos como un objetos
         $repuesta = $consulta->fetchAll(PDO::FETCH_ASSOC);
 
         return $repuesta;
 
         //limpiar consulta
         $consulta = null;
       
    }

    
}
