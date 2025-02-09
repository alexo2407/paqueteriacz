<?php 

if (isset($_GET['enlace'])) {
    // Dividir la ruta
    $ruta = explode("/", $_GET['enlace']);

    // Verificar si el primer segmento es "activar" y el segundo es numérico
    if (isset($ruta[1]) && $ruta[1] === 'activar' && isset($ruta[2]) && is_numeric($ruta[2])) {
        $idCliente = intval($ruta[2]); // Obtener el ID del cliente

        // Instanciar el controlador y activar el cliente
        $clienteAct = new ClientesController();
        $resultadoact = $clienteAct->estadoCliente($idCliente,1);

        // Redirigir a la lista de clientes activos si se activa correctamente
        if ($resultadoact) {
            header("Location: " . RUTA_URL . "clientes/listar");
            exit;
        } else {
            echo "<div class='alert alert-danger'>Error al activar el cliente. Inténtelo nuevamente.</div>";
        }
    } else {
        echo "<div class='alert alert-danger'>Ruta inválida o cliente no especificado.</div>";
    }
} else {
    echo "<div class='alert alert-danger'>No se recibió una solicitud válida.</div>";
}
?>
