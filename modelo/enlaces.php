<?php 

class EnlaceBackendModel
{

    public static function enlacesModel($link)
    {

        $ruta = explode("/",$link);

        // var_dump($ruta);
        //lista blanca de url
        if($ruta[0] == "inicio" ||
        $ruta[0] == "dashboard" ||
        $ruta[0] == "clientes"|| 
        $ruta[0] == "usuarios"|| 
        $ruta[0] == "pedidos"||     
        $ruta[0] == "productos" ||        
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
