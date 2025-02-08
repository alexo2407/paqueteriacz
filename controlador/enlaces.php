<?php 

class EnlacesController
{
    public static function enlacesController(){

    if(isset($_GET['enlace']))
    {
        //obtenemos del URL el enlace
        $url = $_GET['enlace'];

    }
    else {

        //si no existe por default sera index
        $url = "index";
    }



    $repuesta = EnlaceBackendModel::enlacesModel($url);

    include $repuesta;

    }
}