<?php
/**
 * CSVPedidoValidator - Validador robusto para importación de pedidos via CSV
 * 
 * Valida cada fila del CSV antes de insertar, detectando:
 * - Coordenadas geográficas inválidas
 * - Referencias foráneas inexistentes
 * - Duplicados de número de orden
 * - Productos faltantes
 * 
 * @author Sistema Paquetería CZ
 * @version 1.0
 */

require_once __DIR__ . '/../modelo/pedido.php';
require_once __DIR__ . '/../modelo/producto.php';

class CSVPedidoValidator
{
    private $pedidosExistentes = [];
    private $productosCache = [];
    private $estadosCache = [];
    private $proveedoresCache = [];
    private $monedasCache = [];
    private $vendedoresCache = [];
    
    /**
     * Constructor - Pre-carga caches para optimizar validaciones
     */
    public function __construct()
    {
        $this->cargarCaches();
    }
    
    /**
     * Cargar en memoria las listas necesarias para validar
     */
    private function cargarCaches()
    {
        try {
            // Cache de números de orden existentes
            $this->pedidosExistentes = PedidosModel::obtenerNumerosOrdenExistentes();
            
            // Cache de productos
            $productos = ProductoModel::listarConInventario();
            foreach ($productos as $p) {
                $this->productosCache[$p['id']] = $p;
                // Index también por nombre normalizado para búsqueda
                $nombreNorm = mb_strtolower(trim($p['nombre']));
                $this->productosCache['nombre:' . $nombreNorm] = $p;
            }
            
            // Cache de estados (indexar por ID y por nombre)
            $estados = PedidosModel::obtenerEstados();
            foreach ($estados as $e) {
                $this->estadosCache[$e['id']] = $e;
                $nombreNorm = mb_strtolower(trim($e['nombre_estado']));
                $this->estadosCache['nombre:' . $nombreNorm] = $e;
            }
            
            // Cache de proveedores (indexar por ID y por nombre)
            $proveedores = PedidosModel::obtenerProveedores();
            foreach ($proveedores as $p) {
                $this->proveedoresCache[$p['id']] = $p;
                $nombreNorm = mb_strtolower(trim($p['nombre']));
                $this->proveedoresCache['nombre:' . $nombreNorm] = $p;
            }
            
            // Cache de monedas (indexar por ID y por código)
            $monedas = PedidosModel::obtenerMonedas();
            foreach ($monedas as $m) {
                $this->monedasCache[$m['id']] = $m;
                $codigoNorm = mb_strtoupper(trim($m['codigo']));
                $this->monedasCache['codigo:' . $codigoNorm] = $m;
            }
            
            // Cache de vendedores (indexar por ID y por nombre)
            $usuarioModel = new UsuarioModel();
            $vendedores = $usuarioModel->mostrarUsuarios();
            foreach ($vendedores as $v) {
                $this->vendedoresCache[$v['id']] = $v;
                $nombreNorm = mb_strtolower(trim($v['nombre']));
                $this->vendedoresCache['nombre:' . $nombreNorm] = $v;
            }
            
        } catch (Exception $e) {
            error_log('Error al cargar caches de validación: ' . $e->getMessage());
        }
    }
    
    /**
     * Validar una fila completa del CSV
     * 
     * @param array $row Fila del CSV como array asociativo
     * @param int $lineNumber Número de línea (para mensajes de error)
     * @return array ['errores' => [], 'advertencias' => [], 'valido' => bool]
     */
    public function validarFila($row, $lineNumber)
    {
        $errores = [];
        $advertencias = [];
        
        // 1. Validar número de orden
        $numeroOrden = trim($row['numero_orden'] ?? '');
        if ($numeroOrden === '') {
            $errores[] = "numero_orden vacío";
        } elseif (!preg_match('/^\d+$/', $numeroOrden)) {
            $errores[] = "numero_orden debe ser numérico entero";
        } elseif (in_array($numeroOrden, $this->pedidosExistentes)) {
            $errores[] = "numero_orden {$numeroOrden} ya existe en la base de datos";
        }
        
        // 2. Validar coordenadas geográficas
        $resultadoCoordenadas = $this->validarCoordenadas($row);
        if (!empty($resultadoCoordenadas['errores'])) {
            $errores = array_merge($errores, $resultadoCoordenadas['errores']);
        }
        if (!empty($resultadoCoordenadas['advertencias'])) {
            $advertencias = array_merge($advertencias, $resultadoCoordenadas['advertencias']);
        }
        
        // 3. Validar producto
        $resultadoProducto = $this->validarProducto($row);
        if (!empty($resultadoProducto['errores'])) {
            $errores = array_merge($errores, $resultadoProducto['errores']);
        }
        if (!empty($resultadoProducto['advertencias'])) {
            $advertencias = array_merge($advertencias, $resultadoProducto['advertencias']);
        }
        
        // 4. Validar cantidad (Opcional, default 1)
        $cantidad = $row['cantidad'] ?? $row['qty'] ?? 1; // Default a 1 si no viene en el CSV
        if ($cantidad !== '' && (!is_numeric($cantidad) || (int)$cantidad < 1)) {
            $errores[] = "cantidad debe ser un número entero mayor a 0";
        }
        
        // 5. Validar referencias foráneas opcionales
        $this->validarReferenciasFKOpcionales($row, $errores, $advertencias);
        
        // 6. Validaciones de campos opcionales pero recomendados
        if (empty(trim($row['destinatario'] ?? ''))) {
            $advertencias[] = "destinatario vacío (recomendado)";
        }
        if (empty(trim($row['telefono'] ?? ''))) {
            $advertencias[] = "telefono vacío (recomendado)";
        }
        if (empty(trim($row['direccion'] ?? ''))) {
            $advertencias[] = "direccion vacía (recomendado)";
        }
        
        return [
            'errores' => $errores,
            'advertencias' => $advertencias,
            'valido' => empty($errores)
        ];
    }
    
    /**
     * Validar coordenadas geográficas
     */
    private function validarCoordenadas($row)
    {
        $errores = [];
        $advertencias = [];
        
        $lat = $row['latitud'] ?? null;
        $lng = $row['longitud'] ?? null;
        
        // Normalizar decimales con coma -> punto
        if (is_string($lat)) $lat = str_replace(',', '.', $lat);
        if (is_string($lng)) $lng = str_replace(',', '.', $lng);
        
        if (empty($lat) || empty($lng)) {
            // Coordenadas opcionales ahora
            return ['errores' => [], 'advertencias' => []];
        }
        
        if (!is_numeric($lat) || !is_numeric($lng)) {
            $errores[] = "coordenadas no son numéricas";
            return ['errores' => $errores, 'advertencias' => $advertencias];
        }
        
        $latF = (float)$lat;
        $lngF = (float)$lng;
        
        // Validar rangos geográficos
        if ($latF < -90 || $latF > 90) {
            $errores[] = "latitud fuera de rango válido (-90 a 90): {$latF}";
        }
        
        if ($lngF < -180 || $lngF > 180) {
            $errores[] = "longitud fuera de rango válido (-180 a 180): {$lngF}";
        }
        
        // Advertencia para coordenadas sospechosas
        if ($latF == 0 && $lngF == 0) {
            $advertencias[] = "coordenadas (0,0) probablemente inválidas (Null Island)";
        }
        
        // Detectar posible inversión lat/long
        if (abs($latF) > 90 && abs($lngF) <= 90) {
            $advertencias[] = "posible inversión de latitud/longitud detectada (se corregirá automáticamente)";
        }
        
        return ['errores' => $errores, 'advertencias' => $advertencias];
    }
    
    /**
     * Validar producto (por ID o por nombre)
     */
    private function validarProducto($row)
    {
        $errores = [];
        $advertencias = [];
        
        $productoId = $row['id_producto'] ?? $row['producto_id'] ?? null;
        $productoNombre = trim($row['producto'] ?? $row['producto_nombre'] ?? '');
        
        // Si viene id_producto, validar que existe
        if (!empty($productoId)) {
            if (!is_numeric($productoId)) {
                $errores[] = "id_producto no es numérico: {$productoId}";
            } elseif (!isset($this->productosCache[(int)$productoId])) {
                $errores[] = "id_producto {$productoId} no existe en la base de datos";
            }
        }
        // Si viene producto por nombre
        elseif (!empty($productoNombre)) {
            $nombreNorm = mb_strtolower($productoNombre);
            $existe = isset($this->productosCache['nombre:' . $nombreNorm]);
            
            if (!$existe) {
                $advertencias[] = "producto '{$productoNombre}' no existe, se creará automáticamente";
            }
        }
        // Ninguno de los dos
        else {
            $errores[] = "debe especificar id_producto o producto/producto_nombre";
        }
        
        return ['errores' => $errores, 'advertencias' => $advertencias];
    }
    
    /**
     * Validar referencias foráneas opcionales (estado, proveedor, moneda, vendedor)
     * Acepta tanto IDs como nombres/códigos
     */
    private function validarReferenciasFKOpcionales($row, &$errores, &$advertencias)
    {
        // Validar id_estado o estado_nombre
        if (!empty($row['id_estado'])) {
            $idEstado = (int)$row['id_estado'];
            if (!isset($this->estadosCache[$idEstado])) {
                $estadosDisponibles = implode(', ', array_filter(array_keys($this->estadosCache), 'is_int'));
                $errores[] = "id_estado {$idEstado} no existe. IDs disponibles: {$estadosDisponibles}";
            }
        } elseif (!empty($row['estado_nombre'])) {
            $nombreBuscado = mb_strtolower(trim($row['estado_nombre']));
            if (!isset($this->estadosCache['nombre:' . $nombreBuscado])) {
                $nombresDisponibles = [];
                foreach ($this->estadosCache as $key => $val) {
                    if (is_int($key)) {
                        $nombresDisponibles[] = $val['nombre_estado'];
                    }
                }
                $errores[] = "estado_nombre '{$row['estado_nombre']}' no encontrado. Disponibles: " . implode(', ', $nombresDisponibles);
            }
        }
        
        // Validar id_proveedor o proveedor_nombre
        if (!empty($row['id_proveedor'])) {
            $idProveedor = (int)$row['id_proveedor'];
            if (!isset($this->proveedoresCache[$idProveedor])) {
                $proveedoresDisponibles = implode(', ', array_filter(array_keys($this->proveedoresCache), 'is_int'));
                $errores[] = "id_proveedor {$idProveedor} no existe. IDs disponibles: {$proveedoresDisponibles}";
            }
        } elseif (!empty($row['proveedor_nombre'])) {
            $nombreBuscado = mb_strtolower(trim($row['proveedor_nombre']));
            if (!isset($this->proveedoresCache['nombre:' . $nombreBuscado])) {
                $errores[] = "proveedor_nombre '{$row['proveedor_nombre']}' no encontrado. Ver referencia de valores.";
            }
        } else {
            $advertencias[] = "proveedor no especificado (se usará valor por defecto si está configurado)";
        }
        
        // Validar id_moneda o moneda_codigo
        if (!empty($row['id_moneda'])) {
            $idMoneda = (int)$row['id_moneda'];
            if (!isset($this->monedasCache[$idMoneda])) {
                $monedasDisponibles = implode(', ', array_filter(array_keys($this->monedasCache), 'is_int'));
                $errores[] = "id_moneda {$idMoneda} no existe. IDs disponibles: {$monedasDisponibles}";
            }
        } elseif (!empty($row['moneda_codigo'])) {
            $codigoBuscado = mb_strtoupper(trim($row['moneda_codigo']));
            if (!isset($this->monedasCache['codigo:' . $codigoBuscado])) {
                $codigosDisponibles = [];
                foreach ($this->monedasCache as $key => $val) {
                    if (is_int($key)) {
                        $codigosDisponibles[] = $val['codigo'];
                    }
                }
                $errores[] = "moneda_codigo '{$row['moneda_codigo']}' no encontrado. Disponibles: " . implode(', ', $codigosDisponibles);
            }
        } else {
            $advertencias[] = "moneda no especificada (se usará valor por defecto si está configurado)";
        }
        
        // Validar id_vendedor o vendedor_nombre  
        if (!empty($row['id_vendedor'])) {
            $idVendedor = (int)$row['id_vendedor'];
            if (!isset($this->vendedoresCache[$idVendedor])) {
                $errores[] = "id_vendedor {$idVendedor} no existe. Ver referencia de valores.";
            }
        } elseif (!empty($row['vendedor_nombre'])) {
            $nombreBuscado = mb_strtolower(trim($row['vendedor_nombre']));
            if (!isset($this->vendedoresCache['nombre:' . $nombreBuscado])) {
                $errores[] = "vendedor_nombre '{$row['vendedor_nombre']}' no encontrado. Ver referencia de valores.";
            }
        }
        
        // Validar precios legacy si vienen
        if (isset($row['precio_local']) && $row['precio_local'] !== '' && !is_numeric($row['precio_local'])) {
            $errores[] = "precio_local no es numérico";
        }
        if (isset($row['precio_usd']) && $row['precio_usd'] !== '' && !is_numeric($row['precio_usd'])) {
            $errores[] = "precio_usd no es numérico";
        }
        
        // Validar campos de combo pricing (nuevos)
        if (isset($row['precio_total_local']) && $row['precio_total_local'] !== '' && !is_numeric($row['precio_total_local'])) {
            $errores[] = "precio_total_local no es numérico";
        }
        if (isset($row['precio_total_usd']) && $row['precio_total_usd'] !== '' && !is_numeric($row['precio_total_usd'])) {
            $errores[] = "precio_total_usd no es numérico";
        }
        if (isset($row['tasa_conversion_usd']) && $row['tasa_conversion_usd'] !== '' && !is_numeric($row['tasa_conversion_usd'])) {
            $errores[] = "tasa_conversion_usd no es numérico";
        }
    }
    
    /**
     * Validar lote completo y retornar resumen
     * 
     * @param array $rows Array de filas a validar
     * @return array Resumen con estadísticas
     */
    public function validarLote($rows)
    {
        $totalFilas = count($rows);
        $filasValidas = 0;
        $filasConErrores = 0;
        $filasConAdvertencias = 0;
        $todosErrores = [];
        $todasAdvertencias = [];
        
        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2; // +2 porque primera línea es header y arrays empiezan en 0
            $resultado = $this->validarFila($row, $lineNumber);
            
            if ($resultado['valido']) {
                $filasValidas++;
            } else {
                $filasConErrores++;
            }
            
            if (!empty($resultado['advertencias'])) {
                $filasConAdvertencias++;
            }
            
            // Agregar errores con número de línea
            foreach ($resultado['errores'] as $error) {
                $todosErrores[] = "Línea {$lineNumber}: {$error}";
            }
            
            foreach ($resultado['advertencias'] as $adv) {
                $todasAdvertencias[] = "Línea {$lineNumber}: {$adv}";
            }
        }
        
        return [
            'total' => $totalFilas,
            'validas' => $filasValidas,
            'con_errores' => $filasConErrores,
            'con_advertencias' => $filasConAdvertencias,
            'errores' => $todosErrores,
            'advertencias' => $todasAdvertencias,
            'puede_importar' => $filasValidas > 0
        ];
    }

    /**
     * Completar IDs faltantes en la fila basándose en los nombres/códigos validados
     * 
     * @param array $row Fila del CSV (por referencia)
     */
    public function completarIDs(&$row)
    {
        // Estado
        if (empty($row['id_estado']) && !empty($row['estado_nombre'])) {
            $nombre = mb_strtolower(trim($row['estado_nombre']));
            if (isset($this->estadosCache['nombre:' . $nombre])) {
                $row['id_estado'] = $this->estadosCache['nombre:' . $nombre]['id'];
            }
        }
        
        // Proveedor
        if (empty($row['id_proveedor']) && !empty($row['proveedor_nombre'])) {
            $nombre = mb_strtolower(trim($row['proveedor_nombre']));
            if (isset($this->proveedoresCache['nombre:' . $nombre])) {
                $row['id_proveedor'] = $this->proveedoresCache['nombre:' . $nombre]['id'];
            }
        }
        
        // Moneda
        if (empty($row['id_moneda']) && !empty($row['moneda_codigo'])) {
            $codigo = mb_strtoupper(trim($row['moneda_codigo']));
            if (isset($this->monedasCache['codigo:' . $codigo])) {
                $row['id_moneda'] = $this->monedasCache['codigo:' . $codigo]['id'];
            }
        }
        
        // Vendedor
        if (empty($row['id_vendedor']) && !empty($row['vendedor_nombre'])) {
            $nombre = mb_strtolower(trim($row['vendedor_nombre']));
            if (isset($this->vendedoresCache['nombre:' . $nombre])) {
                $row['id_vendedor'] = $this->vendedoresCache['nombre:' . $nombre]['id'];
            }
        }
    }
}
