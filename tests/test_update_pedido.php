<?php
// Script de prueba: actualiza un pedido existente (o crea uno si no hay) y muestra los resultados
require_once __DIR__ . '/../modelo/conexion.php';
require_once __DIR__ . '/../modelo/pedido.php';

try {
    $db = (new Conexion())->conectar();

    // Obtener un pedido existente
    $row = $db->query('SELECT id FROM pedidos LIMIT 1')->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        // No hay pedidos: crear uno mínimo para la prueba
        $num = 'TEST-' . time();
        $stmt = $db->prepare("INSERT INTO pedidos (fecha_ingreso, numero_orden, destinatario, telefono, precio, producto, cantidad, pais, departamento, municipio, barrio, direccion, zona, comentario, coordenadas, id_estado) VALUES (NOW(), :numero_orden, :destinatario, :telefono, :precio, :producto, :cantidad, :pais, :departamento, :municipio, :barrio, :direccion, :zona, :comentario, ST_GeomFromText(:coordenadas), 1)");
        $coords = 'POINT(-86.2504 12.13282)';
        $stmt->execute([
            ':numero_orden' => $num,
            ':destinatario' => 'Pedido Prueba',
            ':telefono' => '000000000',
            ':precio' => '0',
            ':producto' => 'Prueba',
            ':cantidad' => 1,
            ':pais' => 'TestLand',
            ':departamento' => 'TestDept',
            ':municipio' => 'TestMun',
            ':barrio' => 'TestBarrio',
            ':direccion' => 'Test Address',
            ':zona' => 'TestZone',
            ':comentario' => 'Creado por test',
            ':coordenadas' => $coords
        ]);
        $id = $db->lastInsertId();
        echo "Pedido de prueba creado con id={$id}\n";
    } else {
        $id = $row['id'];
        echo "Usando pedido existente id={$id}\n";
    }

    // Preparar datos de actualización
    $data = [
        'id_pedido' => $id,
        'destinatario' => 'Updated Test User',
        'telefono' => '5551234',
        'pais' => 'PaisPrueba',
        'departamento' => 'DeptPrueba',
        'municipio' => 'MunPrueba',
        'barrio' => 'BarrioPrueba',
        'zona' => 'ZonaPrueba',
        'direccion' => 'Calle Ejemplo 123',
        'comentario' => 'Actualizado por script de prueba',
        'cantidad' => 10,
        'producto' => 'Producto Test',
        'precio' => '199.99',
        'estado' => 1,
        'vendedor' => null,
        'latitud' => '12.345678',
        'longitud' => '-86.789012'
    ];

    // Ejecutar actualización
    $result = PedidosModel::actualizarPedido($data);
    echo "Resultado actualizarPedido: ";
    var_export($result);
    echo "\n";

    // Obtener el pedido actualizado
    $stmt = $db->prepare('SELECT id, numero_orden, destinatario, telefono, pais, departamento, municipio, barrio, zona, direccion, comentario, cantidad, producto, precio, ST_Y(coordenadas) AS lat, ST_X(coordenadas) AS lng, id_estado, id_vendedor FROM pedidos WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($pedido) {
        echo "Pedido actualizados (fila):\n";
        print_r($pedido);
    } else {
        echo "No se encontró el pedido tras la actualización.\n";
    }

} catch (Exception $e) {
    echo "Error durante la prueba: " . $e->getMessage() . "\n";
    exit(1);
}
