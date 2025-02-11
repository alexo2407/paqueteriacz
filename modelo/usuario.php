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

        $consulta = $db->prepare("SELECT Usuarios.ID_Usuario AS id, Usuarios.Nombre AS nombre, Usuarios.Email AS email, Usuarios.created_at AS fecha, Roles.Nombre AS rol FROM Usuarios INNER JOIN Usuarios_Roles ON Usuarios.ID_Usuario = Usuarios_Roles.ID_Usuario INNER JOIN Roles ON Usuarios_Roles.ID_Rol = Roles.ID_Rol;
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
