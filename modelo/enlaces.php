<?php 

class EnlaceBackendModel
{

    public static function enlaceBackendModel($link)
    {

        $ruta = explode("/",$link);

        // var_dump($ruta);
        //lista blanca de url
        if($ruta[0] == "inicio" ||
        $ruta[0] == "dashboard" ||
        $ruta[0] == "articulos"||
        $ruta[0] == "crearArticulo"||
        $ruta[0] == "editarArticulo"||
        $ruta[0] == "usuarios" ||
        $ruta[0] == "crearUsuario"||
        $ruta[0] == "editarUsuario"||
        $ruta[0] == "comentarios" ||
        $ruta[0] == "editarComentario" ||
        $ruta[0] == "salir")
        {
            $modulo = "vista/modulos/".$ruta[0].".php";
        }
        else {
            $modulo = "vista/modulos/inicio.php";
        }

        return $modulo;
    }
}
