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

        $tabla = "usuarios";

        $dataBase = new Conexion();
        $db = $dataBase->conectar();

        //preparamos la consulta

        $consulta = $db->prepare("SELECT id,nombre, email, password, rol_id, fecha_creacion from $tabla");

        //ejecutamos la consulta

        $consulta->execute();

        //la repuesta la enviamos como un objetos
        $repuesta = $consulta->fetchAll(PDO::FETCH_ASSOC);

        return $repuesta;

        //limpiar consulta
        $consulta = null;
    }


    /******************************** */
    // ACTUALIZAR USUARIOS
    /********************************* */

    public static function registrarUsuariosModels($datosModel)
    {
        //instanciamos la BD

        $tabla = "usuarios";
        $dataBase = new Conexion();
        $db = $dataBase->conectar();

      //preparamos la consulta

      //  var_dump($datosModel);

     $consulta = $db->prepare("INSERT INTO  $tabla  ( nombre, email, password) VALUES ( :nombre, :email, :password )");

     //preparamos los valores
     $consulta->bindParam(":nombre",$datosModel["nombre"],PDO::PARAM_STR);
     $consulta->bindParam(":email",$datosModel["email"],PDO::PARAM_STR);
     $consulta->bindParam(":password",$datosModel["password"],PDO::PARAM_STR);
    

     //ejecutamos y enviamos un mensaje si resulto mal
     if ($consulta->execute()) {
        $resp = "regExitoso";
        return $resp;
     }
     else
     {
        return "error";
     }


    }


    /******************************** */
    // EDITAR USUARIOS
    /********************************* */

    public static function editarUsuariosModels($idUsuario)
    {
        //instanciamos la BD

        $tabla = "usuarios";

        $dataBase = new Conexion();
        $db = $dataBase->conectar();

      //preparamos la consulta

     $consulta = $db->prepare("SELECT id,nombre, email, password, rol_id, fecha_creacion from $tabla WHERE id = :id");

     //preparamos los valores

     $consulta->bindParam(':id',$idUsuario,PDO::PARAM_INT);

     //ejecutamos y enviamos un mensaje si resulto mal
     if ($consulta->execute()) {
        $usuarioRepuesta = $consulta->fetch(PDO::FETCH_OBJ);
        return $usuarioRepuesta;
     }
     else
     {
        return "noMatch";
     }


    }

    
    /******************************** */
    // ACTUALIZAR USUARIOS
    /********************************* */

    public static function actualizarUsuariosModels($datosModel)
    {
        //instanciamos la BD

        $tabla = "usuarios";
        $dataBase = new Conexion();
        $db = $dataBase->conectar();

      //preparamos la consulta

      //  var_dump($datosModel);

     $consulta = $db->prepare("UPDATE $tabla SET nombre= :nombre, email= :email, rol_id = :rol WHERE id = :id");

     //preparamos los valores
     $consulta->bindParam(":id",$datosModel["id"],PDO::PARAM_INT);
     $consulta->bindParam(":nombre",$datosModel["nombre"],PDO::PARAM_STR);
     $consulta->bindParam(":email",$datosModel["email"],PDO::PARAM_STR);
     $consulta->bindParam(":rol",$datosModel['rol'],PDO::PARAM_INT);
    

     //ejecutamos y enviamos un mensaje si resulto mal
     if ($consulta->execute()) {
        $resp = ["exitoso", $datosModel["id"]];
        return $resp;
     }
     else
     {
        return "error";
     }


    }

 /******************************** */
    // BORRAR USUARIOS
    /********************************* */

    public static function borrarUsuariosModels($idUsuario)
    {
        //instanciamos la BD

        $tabla = "usuarios";
        $dataBase = new Conexion();
        $db = $dataBase->conectar();

      //preparamos la consulta

      //  var_dump($datosModel);

     $consulta = $db->prepare("DELETE FROM $tabla WHERE id = :id");

     //preparamos los valores
     $consulta->bindParam(":id",$idUsuario,PDO::PARAM_INT);
    

     //ejecutamos y enviamos un mensaje si resulto mal
     if ($consulta->execute()) {
        $resp = "usuarioBorrado";
        return $resp;
     }
     else
     {
        return "error";
     }


    }
}
