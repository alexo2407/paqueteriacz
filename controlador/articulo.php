<?php

class ArticuloController
{

     /******************************** */
    // MOSTRAR TODOS LOS Articulos
    /********************************* */
    public static function mostrarArticulosController()
    {
        $repuesta = ArticulosModels::mostrarArticulosModels();

        return $repuesta;
    }

 /******************************** */
    // CREAR Articulos
    /********************************* */
    public static function crearArticuloController()
    {

        //validamos que los input no vengan vacios
        if ($_POST['titulo'] != '' && $_POST['texto']) {


            //obtenemos el nombre del archivo
            $imagen = $_FILES["imagenArticulo"]["name"];
            $imagenArray = explode('.', $imagen);
            $randoNumero = rand(111111, 999999);

            //asignamos nuevo nombre
            $nuevoNombreImagen = $imagenArray[0] . $randoNumero . "." . $imagenArray[1];

            //nuestra ruta asignada
            $nuevaRuta = "vista/public/galeria/" . $nuevoNombreImagen;


            //movemos nuestra imagen a nuestra ruta asignada las imagenes
            //asignar esas variables 
            $tmpFile = $_FILES['imagenArticulo']['tmp_name'];

            if (move_uploaded_file($tmpFile, $nuevaRuta)) {

                //captamos los datos del input y lo enviamos al modelo
                $datoController = array(
                    "titulo" => $_POST['titulo'],
                    "texto"  => $_POST['texto'],
                    "imagen" => $nuevaRuta
                );

                //    var_dump($nuevaRuta);

                //    var_dump($datoController);
                $repuestaModel = ArticulosModels::crearArticulosModels($datoController);

                // var_dump($repuestaModel);

                if ($repuestaModel == "regExitoso") {
                    header("location:" . RUTA_BACK . "articulos");
                    exit();
                } else {
                    echo "Fallo la actualizacion";
                }
            } else {
            }
        }
    }

 /******************************** */
    // EDITAR ARTICULOS
    /********************************* */

    public static function editarArticuloController()
    {
        $idArticulo = explode('/', $_SERVER['REQUEST_URI']);

    //   var_dump($idArticulo[3]);

         if (isset($idArticulo[3]) && is_numeric($idArticulo[3])) {
           
            $repuestaModel = ArticulosModels::editarArticuloModels($idArticulo[3]);

            if ($repuestaModel == TRUE) {
                return $repuestaModel;
            } else {
                header("location:" . RUTA_BACK . "articulos/" . $repuestaModel);
            }
        } 
    }
}
