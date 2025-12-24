<?php
/**
 * Helper para subida de imágenes de productos
 */

/**
 * Subir imagen de producto
 * 
 * @param array $archivo Array de $_FILES (ej: $_FILES['imagen'])
 * @return array ['success' => bool, 'path' => string o 'error' => string]
 */
function subirImagenProducto($archivo) {
    // Configuración
    $directorioDestino = __DIR__ . '/../assets/productos/';
    $tamanoMaximo = 5 * 1024 * 1024; // 5MB
    $extensionesPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    // Verificar que hay archivo
    if (!isset($archivo) || $archivo['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => false, 'error' => 'No se seleccionó ningún archivo'];
    }
    
    // Verificar errores de upload
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        $errores = [
            UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo del formulario',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal',
            UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo',
            UPLOAD_ERR_EXTENSION => 'Extensión de PHP bloqueó la subida'
        ];
        return ['success' => false, 'error' => $errores[$archivo['error']] ?? 'Error desconocido al subir'];
    }
    
    // Verificar tamaño
    if ($archivo['size'] > $tamanoMaximo) {
        return ['success' => false, 'error' => 'El archivo excede el límite de 5MB'];
    }
    
    // Verificar extensión
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $extensionesPermitidas)) {
        return ['success' => false, 'error' => 'Formato no permitido. Use: ' . implode(', ', $extensionesPermitidas)];
    }
    
    // Verificar tipo MIME real
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $archivo['tmp_name']);
    finfo_close($finfo);
    
    $mimePermitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mimeType, $mimePermitidos)) {
        return ['success' => false, 'error' => 'El archivo no es una imagen válida'];
    }
    
    // Crear directorio si no existe
    if (!is_dir($directorioDestino)) {
        mkdir($directorioDestino, 0755, true);
    }
    
    // Generar nombre único
    $nombreArchivo = 'prod_' . uniqid() . '_' . time() . '.' . $extension;
    $rutaCompleta = $directorioDestino . $nombreArchivo;
    
    // Mover archivo
    if (move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
        // Retornar ruta relativa para guardar en BD
        return [
            'success' => true,
            'path' => 'assets/productos/' . $nombreArchivo,
            'filename' => $nombreArchivo
        ];
    }
    
    return ['success' => false, 'error' => 'No se pudo guardar el archivo'];
}

/**
 * Eliminar imagen de producto
 * 
 * @param string $ruta Ruta relativa de la imagen (ej: assets/productos/abc.jpg)
 * @return bool
 */
function eliminarImagenProducto($ruta) {
    if (empty($ruta)) return true;
    
    // Si es URL externa, no hacer nada
    if (str_starts_with($ruta, 'http://') || str_starts_with($ruta, 'https://')) {
        return true;
    }
    
    $rutaCompleta = __DIR__ . '/../' . $ruta;
    
    if (file_exists($rutaCompleta)) {
        return unlink($rutaCompleta);
    }
    
    return true;
}

/**
 * Obtener URL completa de imagen de producto
 * 
 * @param string|null $ruta Ruta relativa o URL completa
 * @return string URL completa de la imagen o placeholder
 */
function getUrlImagenProducto($ruta) {
    if (empty($ruta)) {
        return RUTA_URL . 'assets/img/producto-placeholder.png';
    }
    
    // Si ya es URL completa, retornarla
    if (str_starts_with($ruta, 'http://') || str_starts_with($ruta, 'https://')) {
        return $ruta;
    }
    
    // Construir URL completa
    return RUTA_URL . $ruta;
}
