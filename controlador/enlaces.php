<?php 
class EnlacesController
{
    public static function enlacesController()
    {
        // Obtén la URL solicitada
        $url = isset($_GET['enlace']) ? $_GET['enlace'] : "inicio";

        // Obtén el archivo y los parámetros desde el modelo
        $respuesta = EnlacesModel::enlacesModel($url);
        $archivo = $respuesta['archivo'];
        $parametros = $respuesta['parametros'];

        // Enforce active session for private modules
        $segmentos = explode('/', trim($url));
        $modulo = $segmentos[0] ?? 'inicio';
        $modulosPublicos = ['inicio', 'login', '', 'api'];

        if (!in_array($modulo, $modulosPublicos, true)) {
            require_once __DIR__ . '/../utils/session.php';
            require_login();
        }

        // Incluye la vista correspondiente
        if (file_exists($archivo)) {
            // Los parámetros estarán disponibles en la vista
            include $archivo;
        } else {
            include "vista/modulos/404.php";
        }
    }
}

