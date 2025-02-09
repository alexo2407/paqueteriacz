<?php 


class EnlacesModel
{
    public static function enlacesModel($link)
    {
        // Dividimos la URL en partes
        $ruta = explode("/", $link);

        // Lista blanca de módulos permitidos
        $modulosPermitidos = [
            "inicio",
            "dashboard",
            "clientes",
            "usuarios",
            "pedidos",
            "inactivos",
            "activar",
            "desactivar",
            "productos",
            "salir"
        ];

        // Verifica si el módulo está en la lista blanca
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

        // Si no se encuentra el módulo, devolver inicio.php
        return [
            "archivo" => "vista/modulos/inicio.php",
            "parametros" => []
        ];
    }
}
