<?php 

if (isset($_GET['enlace'])) {
    // Dividir la ruta
    $ruta = explode("/", $_GET['enlace']);

    // Verificar si el primer segmento es "activar" y el segundo es numérico
    if (isset($ruta[1]) && $ruta[1] === 'desactivar' && isset($ruta[2]) && is_numeric($ruta[2])) {
        $idCliente = intval($ruta[2]); // Obtener el ID del cliente

        // Instanciar el controlador y activar el cliente
        $clienteAct = new ClientesController();
        $resultadoact = $clienteAct->estadoCliente($idCliente, 0);

        // Redirigir a la lista de clientes activos si se activa correctamente
        if ($resultadoact) {
            require_once __DIR__ . '/../../utils/session.php';
            set_flash('success', 'Cliente desactivado correctamente.');
            header("Location: " . RUTA_URL . "clientes/listar");
            exit;
        } else {
            require_once __DIR__ . '/../../utils/session.php';
            set_flash('error', 'Error al desactivar el cliente. Inténtelo nuevamente.');
            header("Location: " . RUTA_URL . "clientes/listar");
            exit;
        }
    } else {
        echo "<div class='alert alert-danger'>Ruta inválida o cliente no especificado.</div>";
    }
} else {
    echo "<div class='alert alert-danger'>No se recibió una solicitud válida.</div>";
}
?>
