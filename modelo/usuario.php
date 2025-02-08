<?php

include_once "modelo/conexion.php";


class UsuariosModel

{

    /******************************** */
    // MOSTRAR TODOS LOS USUARIOS
    /********************************* */
    public static function mostrarUsuariosModels()
    {

        //instanciamos la BD

        $dataBase = new Conexion();
        $db = $dataBase->conectar();

        //preparamos la consulta

        $consulta = $db->prepare("SELECT 
    usuarios.ID_Usuario AS id,
    usuarios.Nombre AS nombre,
    usuarios.Email AS email,
    usuarios.created_at AS fecha,
    roles.Nombre AS rol
FROM 
    usuarios
INNER JOIN 
    usuarios_roles ON usuarios.ID_Usuario = usuarios_roles.ID_Usuario
INNER JOIN 
    roles ON usuarios_roles.ID_Rol = roles.ID_Rol;
");

        //ejecutamos la consulta

        $consulta->execute();

        //la repuesta la enviamos como un objetos
        $repuesta = $consulta->fetchAll(PDO::FETCH_ASSOC);

        return $repuesta;

        //limpiar consulta
        $consulta = null;
    }


}
