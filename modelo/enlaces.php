<?php 

/**
 * EnlacesModel
 *
 * Modelo que resuelve qué archivo de vista debe incluirse para una ruta
 * dada. Devuelve la ruta del archivo y parámetros extra.
 */
class EnlacesModel
{
    /**
     * Resolver la ruta de la vista a partir del parámetro `link`.
     *
     * - Retorna ['archivo' => <ruta>, 'parametros' => []]
     * - Sanitiza segmentos para evitar directory traversal.
     * - Si no encuentra la vista retorna la vista por defecto (inicio/dashboard).
     *
     * @param string $link Ruta solicitada (por ejemplo 'pedidos/editar/123')
     * @return array
     */
    public static function enlacesModel($link)
    {
        // Dividimos la URL en partes
        $ruta = explode("/", $link);


        // var_dump($ruta);
        // Normalizar ruta de login al módulo inicio
        if ($ruta[0] === 'login') {
            $ruta[0] = 'inicio';
        }
        
        // Convertir a minúsculas para evitar problemas de case-sensitivity
        $ruta[0] = strtolower($ruta[0]);

        // Lista blanca de módulos permitidos para vistas
        $modulosPermitidos = [
            "inicio",
            "dashboard",
            "usuarios",
            "pedidos",
            "productos",
            "stock",
            "monedas",
            "paises",
            "departamentos",
            "municipios",
            "seguimiento",
            "barrios",
            "cambiarEstados",
            "salir"
        ];

        // Verifica si es un módulo regular (vista)
        if (in_array($ruta[0], $modulosPermitidos)) {
            // Sanitizar partes para evitar traversal
            $modulo = preg_replace('/[^a-zA-Z0-9_-]/', '', $ruta[0]);
            $archivo = __DIR__ . "/../vista/modulos/" . $modulo;

            // Si hay una acción (como "editar"), añádela y sanitiza
            if (isset($ruta[1])) {
                $accion = preg_replace('/[^a-zA-Z0-9_-]/', '', $ruta[1]);
                $archivo .= "/" . $accion . ".php";
            } else {
                // Casos especiales: módulos sin acción que necesitan redirección
                if ($modulo === 'seguimiento') {
                    // Redirigir seguimiento sin acción a seguimiento/listar
                    $archivo .= "/listar.php";
                } else {
                    $archivo .= ".php";
                }
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
        $defaultView = "vista/modulos/inicio.php";
        if (!empty($_SESSION['registrado'])) {
            $defaultView = "vista/modulos/dashboard.php";
        }

        return [
            "archivo" => $defaultView,
            "parametros" => []
        ];
    }
}
