<?php 

class EnlacesBackendController
{
    public static function enlacesBackendController(){

    if(isset($_GET['enlaceBack']))
    {
        //obtenemos del URL el enlace
        $url = $_GET['enlaceBack'];

    }
    else {

        //si no existe por default sera index
        $url = "index";
    }



    $repuesta = EnlaceBackendModel::enlaceBackendModel($url);

    include $repuesta;

    }
}