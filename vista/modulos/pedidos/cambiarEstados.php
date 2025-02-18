<?php 

require_once __DIR__ . '/../../../controlador/pedido.php';
require_once __DIR__ . '/../../../modelo/pedido.php';


#CLASE Y MÉTODOS
#-------------------------------------------------------------
class Ajax{

	
	#ACTUALIZAR Estado Pedido
	#---------------------------------------------
	public $actualizarEstadoPedido;
    public $actualizarIdPedido;

	public function actualizarPedidoAjax(){	

		$datos = array("estado" => $this->actualizarEstadoPedido,
                        "id_pedido"  => $this->actualizarIdPedido);

		$respuesta = PedidosController::actualizarEstadoAjax($datos);

		echo $respuesta;

	}

}


#OBJETOS
#-----------------------------------------------------------

header('Content-Type: application/json');
ob_clean(); // Limpia cualquier salida previa

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["id_pedido"], $_POST["estado"])) {
    $b = new Ajax();
    $b->actualizarEstadoPedido = $_POST["estado"];
    $b->actualizarIdPedido = $_POST["id_pedido"];
    echo json_encode($b->actualizarPedidoAjax());
} else {
    echo json_encode(["success" => false, "message" => "Método no permitido"]);
}
exit();