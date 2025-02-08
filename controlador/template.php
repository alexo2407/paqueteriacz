<?php 


class templateController {

    //incluye la vista de la plantilla
    public function template()
    {

        // En el controlador principal
    $listarClientes = new ClientesController();
    $clientes = $listarClientes->mostrarClientesController();

        include "vista/template.php";
    }
}