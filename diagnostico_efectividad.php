<?php
// Diagnóstico de Efectividad
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/modelo/pedido.php';
require_once __DIR__ . '/modelo/conexion.php';
require_once __DIR__ . '/modelo/pais.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Diagnóstico de Efectividad</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background: #d4edda; }
        .error { background: #f8d7da; }
        .info { background: #d1ecf1; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
        h2 { color: #333; }
    </style>
</head>
<body>
    <h1>🔍 Diagnóstico de Dashboard de Efectividad</h1>

    <?php
    // 1. Verificar conexión a base de datos
    echo '<div class="section">';
    echo '<h2>1. Conexión a Base de Datos</h2>';
    try {
        $db = (new Conexion())->conectar();
        echo '<p class="success">✅ Conexión exitosa</p>';
    } catch (Exception $e) {
        echo '<p class="error">❌ Error de conexión: ' . $e->getMessage() . '</p>';
        exit;
    }
    echo '</div>';

    // 2. Contar pedidos totales
    echo '<div class="section">';
    echo '<h2>2. Pedidos en Base de Datos</h2>';
    try {
        $stmt = $db->query('SELECT COUNT(*) as total, MIN(fecha_ingreso) as primera, MAX(fecha_ingreso) as ultima FROM pedidos');
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo '<p><strong>Total de pedidos:</strong> ' . $result['total'] . '</p>';
        echo '<p><strong>Primer pedido:</strong> ' . $result['primera'] . '</p>';
        echo '<p><strong>Último pedido:</strong> ' . $result['ultima'] . '</p>';
        
        if ($result['total'] == 0) {
            echo '<p class="error">⚠️ No hay pedidos en la base de datos. Las gráficas estarán vacías.</p>';
        }
    } catch (Exception $e) {
        echo '<p class="error">❌ Error: ' . $e->getMessage() . '</p>';
    }
    echo '</div>';

    // 3. Verificar estados
    echo '<div class="section">';
    echo '<h2>3. Estados de Pedidos</h2>';
    try {
        $stmt = $db->query('SELECT ep.nombre_estado, COUNT(*) as total FROM pedidos p LEFT JOIN estados_pedidos ep ON p.id_estado = ep.id GROUP BY ep.nombre_estado ORDER BY total DESC');
        $estados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo '<table border="1" cellpadding="5">';
        echo '<tr><th>Estado</th><th>Cantidad</th></tr>';
        foreach ($estados as $estado) {
            echo '<tr><td>' . htmlspecialchars($estado['nombre_estado'] ?? 'Sin estado') . '</td><td>' . $estado['total'] . '</td></tr>';
        }
        echo '</table>';
    } catch (Exception $e) {
        echo '<p class="error">❌ Error: ' . $e->getMessage() . '</p>';
    }
    echo '</div>';

    // 4. Probar método obtenerEfectividadPorPais
    echo '<div class="section">';
    echo '<h2>4. Prueba: obtenerEfectividadPorPais()</h2>';
    try {
        $fechaDesde = date('Y-m-01'); // Primer día del mes actual
        $fechaHasta = date('Y-m-t');  // Último día del mes actual
        echo '<p><strong>Rango:</strong> ' . $fechaDesde . ' a ' . $fechaHasta . '</p>';
        
        $datos = PedidosModel::obtenerEfectividadPorPais(null, $fechaDesde, $fechaHasta);
        echo '<p><strong>Registros encontrados:</strong> ' . count($datos) . '</p>';
        
        if (count($datos) > 0) {
            echo '<pre>' . json_encode($datos, JSON_PRETTY_PRINT) . '</pre>';
        } else {
            echo '<p class="error">⚠️ No se encontraron datos para este rango de fechas.</p>';
            
            // Intentar con un rango más amplio
            echo '<p>Intentando con rango de todo el año...</p>';
            $datos2 = PedidosModel::obtenerEfectividadPorPais(null, date('Y') . '-01-01', date('Y') . '-12-31');
            echo '<p><strong>Registros encontrados (año completo):</strong> ' . count($datos2) . '</p>';
            if (count($datos2) > 0) {
                echo '<pre>' . json_encode($datos2, JSON_PRETTY_PRINT) . '</pre>';
            }
        }
    } catch (Exception $e) {
        echo '<p class="error">❌ Error: ' . $e->getMessage() . '</p>';
    }
    echo '</div>';

    // 5. Probar método obtenerEfectividadTemporal
    echo '<div class="section">';
    echo '<h2>5. Prueba: obtenerEfectividadTemporal()</h2>';
    try {
        $fechaDesde = date('Y-m-01');
        $fechaHasta = date('Y-m-t');
        echo '<p><strong>Rango:</strong> ' . $fechaDesde . ' a ' . $fechaHasta . '</p>';
        
        $datos = PedidosModel::obtenerEfectividadTemporal(null, null, $fechaDesde, $fechaHasta);
        echo '<p><strong>Registros encontrados:</strong> ' . count($datos) . '</p>';
        
        if (count($datos) > 0) {
            echo '<pre>' . json_encode($datos, JSON_PRETTY_PRINT) . '</pre>';
        } else {
            echo '<p class="error">⚠️ No se encontraron datos para este rango de fechas.</p>';
            
            // Intentar con un rango más amplio
            echo '<p>Intentando con rango de todo el año...</p>';
            $datos2 = PedidosModel::obtenerEfectividadTemporal(null, null, date('Y') . '-01-01', date('Y') . '-12-31');
            echo '<p><strong>Registros encontrados (año completo):</strong> ' . count($datos2) . '</p>';
            if (count($datos2) > 0) {
                echo '<pre>' . json_encode($datos2, JSON_PRETTY_PRINT) . '</pre>';
            }
        }
    } catch (Exception $e) {
        echo '<p class="error">❌ Error: ' . $e->getMessage() . '</p>';
    }
    echo '</div>';

    // 6. Probar API endpoint
    echo '<div class="section">';
    echo '<h2>6. Prueba de API Endpoint</h2>';
    $apiUrl = RUTA_URL . 'api/dashboard/efectividad-temporal?fecha_desde=' . date('Y-m-01') . '&fecha_hasta=' . date('Y-m-t');
    echo '<p><strong>URL:</strong> <a href="' . $apiUrl . '" target="_blank">' . $apiUrl . '</a></p>';
    echo '<p>Haz clic en el enlace para probar el endpoint directamente.</p>';
    echo '</div>';

    // 7. Verificar países
    echo '<div class="section">';
    echo '<h2>7. Países en Base de Datos</h2>';
    try {
        $paises = PaisModel::listar();
        echo '<p><strong>Total de países:</strong> ' . count($paises) . '</p>';
        echo '<ul>';
        foreach ($paises as $pais) {
            echo '<li>' . htmlspecialchars($pais['nombre']) . ' (ID: ' . $pais['id'] . ')</li>';
        }
        echo '</ul>';
    } catch (Exception $e) {
        echo '<p class="error">❌ Error: ' . $e->getMessage() . '</p>';
    }
    echo '</div>';
    ?>

    <div class="section info">
        <h2>📝 Recomendaciones</h2>
        <ul>
            <li>Si no hay pedidos, necesitas crear algunos datos de prueba</li>
            <li>Verifica que los estados en la base de datos coincidan con los definidos en el código:
                <ul>
                    <li><strong>Entregados:</strong> 'Entregado', 'Completado', 'Finalizado'</li>
                    <li><strong>Devueltos:</strong> 'Devuelto', 'No entregado', 'Rechazado'</li>
                </ul>
            </li>
            <li>Asegúrate de que los pedidos tengan fechas dentro del rango seleccionado en el dashboard</li>
            <li>Verifica que los pedidos tengan asignado un país (id_pais no NULL)</li>
        </ul>
    </div>
</body>
</html>
