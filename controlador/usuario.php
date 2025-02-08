<?php

class UsuariosController
{
    /******************************** */
    // MOSTRAR TODOS LOS USUARIOS
    /********************************* */
    public static function mostrarUsuariosController()
    {
        $repuesta = UsuariosModel::mostrarUsuariosModels();

        return $repuesta;
    }

}
