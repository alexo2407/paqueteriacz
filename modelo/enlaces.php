<?php 

class EnlacesModel
{
    public static function enlacesModel($link)
    {
        // Dividimos la URL en partes
        $ruta = explode("/", $link);


        // var_dump($ruta);
        // Lista blanca de módulos permitidos para vistas
        $modulosPermitidos = [
            "inicio",
            "dashboard",
            "clientes",
            "usuarios",
            "pedidos",
            "productos",
            "salir"
        ];

        // Verifica si es un módulo regular (vista)
        if (in_array($ruta[0], $modulosPermitidos)) {
            $archivo = "vista/modulos/" . $ruta[0];

            // Si hay una acción (como "editar"), añádela
            if (isset($ruta[1])) {
                $archivo .= "/" . $ruta[1] . ".php";
            } else {
                $archivo .= ".php";
            }

            // Devuelve la ruta si el archivo existe
            if (file_exists($archivo)) {
                return [
                    "archivo" => $archivo,
                    "parametros" => array_slice($ruta, 2) // Extraer parámetros adicionales
                ];
            }
        }

       /*  // Verifica si es una ruta de la API
        if ($ruta[0] === "api") {
            $archivo = "api/" . (isset($ruta[1]) ? $ruta[1] : "index") . "/" . (isset($ruta[2]) ? $ruta[2] : "index") . ".php";

            // Devuelve el archivo y parámetros si existe
            if (file_exists($archivo)) {
                return [
                    "archivo" => $archivo,
                    "parametros" => array_slice($ruta, 3)
                ];
            }
        } */

        // Si no se encuentra el módulo o la ruta API, redirigir a inicio
        return [
            "archivo" => "vista/modulos/inicio.php",
            "parametros" => []
        ];
    }
}
