<?php
/* require_once '../../config/config.php';
require_once '../../modelo/PedidosModel.php';
require_once '../utils/responder.php';

$idPedido = isset($_GET['id']) ? intval($_GET['id']) : null;
$data = json_decode(file_get_contents('php://input'), true);

if (!$idPedido || !$data) {
    responder(false, "Datos inválidos o ID no especificado", null, 400);
    exit;
}

$controller = new PedidosController();
$result = $controller->editarPedido($idPedido, $data);

if ($result) {
    responder(true, "Pedido actualizado correctamente", null, 200);
} else {
    responder(false, "Error al actualizar el pedido", null, 500);
} */
?>
<h1>Actualizar</h1>