<?php 

class ArticulosModels{

    /******************************** */
    // MOSTRAR TODOS LOS USUARIOS
    /********************************* */
    public static function mostrarArticulosModels()
    {

        //instanciamos la BD

        $tabla = "articulos";

        $dataBase = new Conexion();
        $db = $dataBase->conectar();

        //preparamos la consulta

        $consulta = $db->prepare("SELECT id,titulo, imagen, texto, fecha_creacion from $tabla");

        //ejecutamos la consulta

        $consulta->execute();

        //la repuesta la enviamos como un objetos
        $repuesta = $consulta->fetchAll(PDO::FETCH_ASSOC);

        return $repuesta;

        //limpiar consulta
        $consulta = null;
    }

    /******************************** */
    // crear Articulo
    /********************************* */

    public static function crearArticulosModels($datosModel)
    {
        //instanciamos la BD

        $tabla = "articulos";
        $dataBase = new Conexion();
        $db = $dataBase->conectar();

      //preparamos la consulta


     $consulta = $db->prepare("INSERT INTO  $tabla  ( titulo, imagen, texto) VALUES ( :titulo, :imagen, :texto )");

     //preparamos los valores
     $consulta->bindParam(":titulo",$datosModel["titulo"],PDO::PARAM_STR);
     $consulta->bindParam(":imagen",$datosModel["imagen"],PDO::PARAM_STR);
     $consulta->bindParam(":texto",$datosModel["texto"],PDO::PARAM_STR);
    

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
    // EDITAR ARTICULOS
    /********************************* */

    public static function editarArticuloModels($idArticulo)
    {
        //instanciamos la BD

        $tabla = "articulos";

        $dataBase = new Conexion();
        $db = $dataBase->conectar();

      //limpiar datos
      $idParametro = htmlspecialchars(strip_tags($idArticulo));      
      
       //preparamos la consulta

       $consulta = $db->prepare("SELECT * from $tabla WHERE id = :id");

     //preparamos los valores

     $consulta->bindParam(':id',$idParametro,PDO::PARAM_INT);

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


    


}