<?php

class ClientesController
{
    /******************************** */
    // MOSTRAR TODOS LOS USUARIOS
    /********************************* */
    public function mostrarClientesController()
    {
        $repuesta = ClientesModel::getAll();

        return $repuesta;
    }

    /******************************** */
    // EDITAR USUARIOS
    /********************************* */

    public static function editarUsuariosController()
    {
        $idUsuario = explode('/', $_SERVER['REQUEST_URI']);

        // var_dump($idUsuario[4]);

        if (isset($idUsuario[4]) && is_numeric($idUsuario[4])) {
            $repuestaModel = UsuariosModel::editarUsuariosModels($idUsuario[4]);

            if ($repuestaModel == TRUE) {
                return $repuestaModel;
            } else {
                header("location:" . RUTA_URL . "editarUsuario/" . $repuestaModel);
            }
        }
    }

    /******************************** */
    // ACTUALIZAR USUARIOS
    /********************************* */

    public static function actualizarUsuariosController()
    {


        if (isset($_POST['actualizarUsuario']) && $_POST['nombre'] != '') {

            $datoController = array(
                "id" => (int) $_POST['id'],
                "nombre" => $_POST['nombre'],
                "email"  => $_POST['email'],
                "rol" => (int) $_POST['rol']

            );

            //    var_dump($datoController);
            $repuestaModel = UsuariosModel::actualizarUsuariosModels($datoController);


            // var_dump($repuestaModel);

            if ($repuestaModel[0] == "exitoso") {
                header("location:" . RUTA_URL . "editarUsuario/" . $repuestaModel[1]);
                exit();
            } else {
                echo "Fallo la actualizacion";
            }
        }
    }

    /* ***************************************************
    BORRAR USUARIO
         *********************************************/

    public static function borrarUsuariosController()
    {
        $repuestaModel = UsuariosModel::borrarUsuariosModels($_POST['id']);

        if ($repuestaModel == "usuarioBorrado") {

            header("location:" . RUTA_URL . "usuarios");
            exit();
        } else {
            echo "Fallo la actualizacion";
        }
    }

    /******************************** */
    // REGISTRAR USUARIOS
    /********************************* */

    public static function registrarUsuarioController()
    {


        if (isset($_POST['registrarse']) && $_POST['nombre'] != '') {

            $datoController = array(
                "nombre" => $_POST['nombre'],
                "email"  => $_POST['email'],
                "password"  => $_POST['password']
            );

            //    var_dump($datoController);
            $repuestaModel = UsuariosModel::registrarUsuariosModels($datoController);

            // var_dump($repuestaModel);

            if ($repuestaModel == "regExitoso") {
                header("location:" . RUTA_URL . "usuarios");
                exit();
            } else {
                echo "Fallo la actualizacion";
            }
        }
    }
    /******************************** */
    // LOGIN USUARIO
    /********************************* */
}
