<?php
/**
 * CSVHelper - Utilidades para manejo de archivos CSV
 * 
 * Funciones helper para:
 * - Detección de delimitadores
 * - Normalización de cabeceras
 * - Exportación de errores
 * - Lectura por chunks
 * 
 * @author Sistema Paquetería CZ
 * @version 1.0
 */

class CSVHelper
{
    /**
     * Detectar delimitador del CSV analizando la primera línea
     * 
     * @param string $firstLine Primera línea del CSV
     * @return string Delimitador detectado (',' o ';')
     */
    public static function detectDelimiter($firstLine)
    {
        // Eliminar BOM si existe
        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);
        
        $countComma = substr_count($firstLine, ',');
        $countSemi = substr_count($firstLine, ';');
        $countTab = substr_count($firstLine, "\t");
        $countPipe = substr_count($firstLine, '|');
        
        // Obtener el delimitador con mayor frecuencia
        $delimiters = [
            ',' => $countComma,
            ';' => $countSemi,
            "\t" => $countTab,
            '|' => $countPipe
        ];
        
        arsort($delimiters);
        $detected = array_key_first($delimiters);
        
        // Si el más frecuente es 0, usar coma por defecto
        return $delimiters[$detected] > 0 ? $detected : ',';
    }
    
    /**
     * Normalizar cabeceras del CSV (trim, lowercase, sin BOM)
     * 
     * @param array $headers Array de cabeceras
     * @return array Cabeceras normalizadas
     */
    public static function normalizeHeaders(array $headers)
    {
        return array_map(function($h) {
            // Eliminar BOM, trim, lowercase
            $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);
            $h = trim($h);
            $h = mb_strtolower($h);
            // Reemplazar espacios por guiones bajos
            $h = str_replace(' ', '_', $h);
            return $h;
        }, $headers);
    }
    
    /**
     * Exportar filas con error a un archivo CSV
     * 
     * @param array $rows Filas con error
     * @param array $headers Cabeceras del CSV
     * @param array $errores Mensajes de error correspondientes a cada fila
     * @param string $delimiter Delimitador a usar
     * @return string Ruta del archivo generado
     */
    public static function exportErrorsToCSV(array $rows, array $headers, array $errores = [], $delimiter = ',')
    {
        $timestamp = date('Ymd_His');
        $filename = "import_errors_{$timestamp}.csv";
        $logDir = __DIR__ . '/../logs';
        
        // Crear directorio si no existe
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        $filepath = $logDir . '/' . $filename;
        $fp = fopen($filepath, 'w');
        
        if ($fp === false) {
            throw new Exception('No se pudo crear archivo de errores');
        }
        
        // Agregar BOM para UTF-8
        fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Escribir cabecera con columna extra para el error
        $headersConError = array_merge($headers, ['__ERROR__']);
        fputcsv($fp, $headersConError, $delimiter);
        
        // Escribir filas con sus errores
        foreach ($rows as $index => $row) {
            $error = isset($errores[$index]) ? $errores[$index] : 'Error desconocido';
            $rowConError = array_merge(array_values($row), [$error]);
            fputcsv($fp, $rowConError, $delimiter);
        }
        
        fclose($fp);
        
        return $filename;
    }
    
    /**
     * Crear generador para leer CSV por chunks (evita cargar todo en memoria)
     * 
     * @param resource $handle Handle del archivo CSV
     * @param array $headers Cabeceras normalizadas
     * @param string $delimiter Delimitador
     * @param int $chunkSize Tamaño del chunk
     * @return Generator Generador que yield arrays de filas
     */
    public static function createChunkedReader($handle, array $headers, $delimiter = ',', $chunkSize = 100)
    {
        $chunk = [];
        $lineNumber = 1; // Header es línea 1
        
        while (!feof($handle)) {
            $raw = fgets($handle);
            if ($raw === false) break;
            
            $lineNumber++;
            
            // Quitar BOM en segunda línea si existe
            if ($lineNumber === 2) {
                $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
            }
            
            // Parsear fila
            $row = str_getcsv(rtrim($raw, "\r\n"), $delimiter, '"');
            
            // Intentar con delimitador alternativo si no coincide
            $expectedCols = count($headers);
            if (count($row) !== $expectedCols) {
                $alt = $delimiter === ',' ? ';' : ',';
                $altRow = str_getcsv(rtrim($raw, "\r\n"), $alt, '"');
                if (count($altRow) === $expectedCols) {
                    $row = $altRow;
                }
            }
            
            // Mapear a asociativo
            $dataRow = [];
            foreach ($headers as $i => $colName) {
                $val = isset($row[$i]) ? $row[$i] : null;
                if (is_string($val)) {
                    $val = trim($val);
                }
                $dataRow[$colName] = $val;
            }
            
            // Omitir filas completamente vacías
            $allEmpty = true;
            foreach ($dataRow as $v) {
                if ($v !== null && $v !== '') {
                    $allEmpty = false;
                    break;
                }
            }
            if ($allEmpty) continue;
            
            // Agregar al chunk
            $chunk[] = $dataRow;
            
            // Si llegó al tamaño del chunk, yield y reiniciar
            if (count($chunk) >= $chunkSize) {
                yield $chunk;
                $chunk = [];
            }
        }
        
        // Yield chunk final si quedó algo
        if (!empty($chunk)) {
            yield $chunk;
        }
    }
    
    /**
     * Validar que el archivo subido es un CSV válido
     * 
     * @param array $file $_FILES['nombre_campo']
     * @return array ['valido' => bool, 'error' => string|null]
     */
    public static function validateUploadedFile($file)
    {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            $messages = [
                UPLOAD_ERR_INI_SIZE => 'El archivo excede upload_max_filesize.',
                UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño permitido por el formulario.',
                UPLOAD_ERR_PARTIAL => 'El archivo fue subido parcialmente.',
                UPLOAD_ERR_NO_FILE => 'No se envió ningún archivo.',
                UPLOAD_ERR_NO_TMP_DIR => 'Falta carpeta temporal en el servidor.',
                UPLOAD_ERR_CANT_WRITE => 'Fallo al escribir el archivo en disco.',
                UPLOAD_ERR_EXTENSION => 'Subida detenida por extensión PHP.'
            ];
            
            $error = $messages[$file['error']] ?? 'Error desconocido al subir el archivo.';
            return ['valido' => false, 'error' => $error];
        }
        
        // Validar extensión
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt'])) {
            return ['valido' => false, 'error' => 'El archivo debe ser .csv o .txt'];
        }
        
        // Validar tamaño máximo (10MB por defecto)
        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $maxSize) {
            return ['valido' => false, 'error' => 'El archivo excede el tamaño máximo de 10MB'];
        }
        
        // Validar MIME type (puede ser text/csv, text/plain, application/csv, etc)
        $allowedMimes = [
            'text/csv',
            'text/plain',
            'application/csv',
            'application/vnd.ms-excel',
            'text/comma-separated-values'
        ];
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedMimes)) {
            return ['valido' => false, 'error' => "Tipo de archivo no permitido: {$mimeType}"];
        }
        
        return ['valido' => true, 'error' => null];
    }
    
    /**
     * Obtener tamaño estimado de filas en el CSV sin cargarlo completo
     * 
     * @param string $filepath Ruta del archivo
     * @return int Número estimado de filas
     */
    public static function estimateRowCount($filepath)
    {
        $linecount = 0;
        $handle = fopen($filepath, "r");
        
        if ($handle === false) {
            return 0;
        }
        
        while (!feof($handle)) {
            $line = fgets($handle);
            if ($line !== false) {
                $linecount++;
            }
        }
        
        fclose($handle);
        
        // Restar 1 por el header
        return max(0, $linecount - 1);
    }
    
    /**
     * Convertir array asociativ a CSV en string (útil para APIs)
     * 
     * @param array $data Array de arrays asociativos
     * @param string $delimiter Delimitador
     * @return string CSV como string
     */
    public static function arrayToCSVString(array $data, $delimiter = ',')
    {
        if (empty($data)) {
            return '';
        }
        
        $output = fopen('php://temp', 'r+');
        
        // Escribir header
        $headers = array_keys($data[0]);
        fputcsv($output, $headers, $delimiter);
        
        // Escribir filas
        foreach ($data as $row) {
            fputcsv($output, array_values($row), $delimiter);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
}
