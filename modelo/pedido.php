<?php
include_once "modelo/conexion.php";


class PedidosModel
{
    // Atributos del modelo que representan las columnas de la tabla pedidos
    public $ID_Pedido;
    public $Numero_Orden;
    public $ID_Cliente;
    public $ID_Usuario;
    public $Fecha_Ingreso;
    public $Zona;
    public $Departamento;
    public $Municipio;
    public $Barrio;
    public $Direccion_Completa;
    public $Comentario;
    public $Latitud;
    public $Longitud;
    public $ID_Estado;
    public $created_at;
    public $updated_at;

    /**
     * Constructor del modelo para inicializar atributos
     */
    public function __construct($datos)
    {
        $this->ID_Pedido = $datos['ID_Pedido'] ?? null;
        $this->Numero_Orden = $datos['Numero_Orden'] ?? null;
        $this->ID_Cliente = $datos['ID_Cliente'] ?? null;
        $this->ID_Usuario = $datos['ID_Usuario'] ?? null;
        $this->Fecha_Ingreso = $datos['Fecha_Ingreso'] ?? null;
        $this->Zona = $datos['Zona'] ?? null;
        $this->Departamento = $datos['Departamento'] ?? null;
        $this->Municipio = $datos['Municipio'] ?? null;
        $this->Barrio = $datos['Barrio'] ?? null;
        $this->Direccion_Completa = $datos['Direccion_Completa'] ?? null;
        $this->Comentario = $datos['Comentario'] ?? null;
        $this->Latitud = $datos['Latitud'] ?? null;
        $this->Longitud = $datos['Longitud'] ?? null;
        $this->ID_Estado = $datos['ID_Estado'] ?? null;
        $this->created_at = $datos['created_at'] ?? null;
        $this->updated_at = $datos['updated_at'] ?? null;
    }

    /**
     * Obtener todos los pedidos
     *
     * @return array Lista de pedidos en formato asociativo
     */
    public static function obtenerTodos()
    {
        try {
            // Conexión a la base de datos
            $db = (new Conexion())->conectar();

            // Consulta SQL para obtener todos los pedidos
            $consulta = $db->prepare("SELECT * FROM pedidos");
            $consulta->execute();

            // Convertir cada resultado a un arreglo asociativo
            return $consulta->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Registrar errores en el log y devolver un arreglo vacío
            error_log("Error al obtener pedidos: " . $e->getMessage());
            return [];
        }
    }

    public static function obtenerPedidosExtendidos()
    {
        try {
            $db = (new Conexion())->conectar();
    
            // Consulta SQL para incluir el cliente
            $consulta = $db->prepare("
                SELECT 
                    p.ID_Pedido,
                    p.Numero_Orden,
                    c.Nombre AS Cliente,
                    p.Comentario,
                    ep.Estado AS Estado
                FROM Pedidos p
                INNER JOIN Clientes c ON p.ID_Cliente = c.ID_Cliente
                INNER JOIN Estado_Pedidos ep ON p.ID_Estado = ep.ID_Estado
            ");
            $consulta->execute();
    
            // Retorna los resultados como un arreglo asociativo
            return $consulta->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener pedidos simplificados: " . $e->getMessage());
            return [];
        }
    }

    public static function obtenerDetallesPedido($idPedido)
{
    try {
        $db = (new Conexion())->conectar();


        // Consulta SQL para obtener detalles completos del pedido
        $consulta = $db->prepare("
            SELECT 
                p.ID_Pedido,
                p.Numero_Orden,
                p.Fecha_Ingreso,
                p.Zona,
                p.Departamento,
                p.Municipio,
                p.Barrio,
                p.Direccion_Completa,
                p.Comentario,
                CONCAT(p.Latitud, ', ', p.Longitud) AS COORDINATES,
                ep.Estado AS Estado,
                c.Nombre AS Cliente,
                c.ID_Cliente,
                u.Nombre AS Usuario,
                u.Email AS UsuarioEmail,
                sp.Nombre AS Producto,
                sp.Precio,
                pp.Cantidad,
                p.created_at,
                p.updated_at
            FROM Pedidos p
            INNER JOIN Clientes c ON p.ID_Cliente = c.ID_Cliente
            INNER JOIN Usuarios u ON p.ID_Usuario = u.ID_Usuario
            INNER JOIN Pedidos_Productos pp ON p.ID_Pedido = pp.ID_Pedido
            INNER JOIN Stock_Productos sp ON pp.ID_Producto = sp.ID_Producto
            INNER JOIN Estado_Pedidos ep ON p.ID_Estado = ep.ID_Estado
            WHERE p.ID_Pedido = :idPedido
        ");

        // Vincular el parámetro idPedido
        $consulta->bindValue(':idPedido', $idPedido, PDO::PARAM_INT);

        // Ejecutar la consulta
        $consulta->execute();

        
       // Retornar los resultados como un arreglo asociativo
        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Registrar el error en el log
        error_log("Error al obtener detalles del pedido: " . $e->getMessage());
        return [];
    }
}


    /**
     * Obtener un pedido por su ID
     *
     * @param int $idPedido ID del pedido
     * @return PedidosModel|null El pedido encontrado o null si no existe
     */
    public static function obtenerPorId($idPedido)
    {
        try {
            $db = (new Conexion())->conectar();

            // Consulta SQL para obtener un pedido por su ID
            $consulta = $db->prepare("SELECT * FROM pedidos WHERE ID_Pedido = :id");
            $consulta->bindParam(":id", $idPedido, PDO::PARAM_INT);
            $consulta->execute();

            // Obtener un solo resultado
            $resultado = $consulta->fetch(PDO::FETCH_ASSOC);

            // Si hay resultado, devolver un objeto del modelo, si no, null
            return $resultado ? new self($resultado) : null;
        } catch (PDOException $e) {
            error_log("Error al obtener pedido: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Crear un nuevo pedido
     *
     * @param array $datos Datos del pedido
     * @return bool true si se creó correctamente, false en caso contrario
     */
    public static function crear($datos)
    {
        try {
            $db = (new Conexion())->conectar();

            // Consulta SQL para insertar un nuevo pedido
            $consulta = $db->prepare("
                INSERT INTO pedidos (
                    Numero_Orden, ID_Cliente, ID_Usuario, Fecha_Ingreso, Zona, Departamento,
                    Municipio, Barrio, Direccion_Completa, Comentario, Latitud, Longitud, ID_Estado
                ) VALUES (
                    :Numero_Orden, :ID_Cliente, :ID_Usuario, :Fecha_Ingreso, :Zona, :Departamento,
                    :Municipio, :Barrio, :Direccion_Completa, :Comentario, :Latitud, :Longitud, :ID_Estado
                )
            ");

            // Ejecutar la consulta con los datos proporcionados
            return $consulta->execute($datos);
        } catch (PDOException $e) {
            error_log("Error al crear pedido: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualizar un pedido existente
     *
     * @param int $idPedido ID del pedido
     * @param array $datos Datos actualizados del pedido
     * @return bool true si se actualizó correctamente, false en caso contrario
     */
    public static function actualizar($idPedido, $datos)
    {
        try {
            $db = (new Conexion())->conectar();

            // Consulta SQL para actualizar un pedido
            $consulta = $db->prepare("
                UPDATE pedidos SET
                    Numero_Orden = :Numero_Orden, ID_Cliente = :ID_Cliente, ID_Usuario = :ID_Usuario,
                    Fecha_Ingreso = :Fecha_Ingreso, Zona = :Zona, Departamento = :Departamento,
                    Municipio = :Municipio, Barrio = :Barrio, Direccion_Completa = :Direccion_Completa,
                    Comentario = :Comentario, Latitud = :Latitud, Longitud = :Longitud, ID_Estado = :ID_Estado
                WHERE ID_Pedido = :ID_Pedido
            ");

            // Añadir el ID del pedido al arreglo de datos
            $datos['ID_Pedido'] = $idPedido;

            // Ejecutar la consulta con los datos actualizados
            return $consulta->execute($datos);
        } catch (PDOException $e) {
            error_log("Error al actualizar pedido: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Eliminar un pedido por su ID
     *
     * @param int $idPedido ID del pedido
     * @return bool true si se eliminó correctamente, false en caso contrario
     */
    public static function eliminar($idPedido)
    {
        try {
            $db = (new Conexion())->conectar();

            // Consulta SQL para eliminar un pedido por su ID
            $consulta = $db->prepare("DELETE FROM pedidos WHERE ID_Pedido = :id");
            $consulta->bindParam(":id", $idPedido, PDO::PARAM_INT);

            // Ejecutar la consulta
            return $consulta->execute();
        } catch (PDOException $e) {
            error_log("Error al eliminar pedido: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cambiar el estado de un pedido
     *
     * @param int $idPedido ID del pedido
     * @param int $nuevoEstado Nuevo estado del pedido
     * @return bool true si se actualizó correctamente, false en caso contrario
     */
    public static function cambiarEstado($idPedido, $nuevoEstado)
    {
        try {
            $db = (new Conexion())->conectar();

            // Consulta para actualizar el estado del pedido
            $consulta = $db->prepare("UPDATE pedidos SET ID_Estado = :nuevoEstado WHERE ID_Pedido = :id");
            $consulta->bindParam(":nuevoEstado", $nuevoEstado, PDO::PARAM_INT);
            $consulta->bindParam(":id", $idPedido, PDO::PARAM_INT);

            return $consulta->execute();
        } catch (PDOException $e) {
            error_log("Error al cambiar estado del pedido: " . $e->getMessage());
            return false;
        }
    }
}
