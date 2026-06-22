<?php
/**
 * CSVHelper - Utilidades para manejo de archivos CSV/XLSX
 *
 * Funciones helper para:
 * - Detección de delimitadores
 * - Normalización de cabeceras (incluye alias para plantilla XLSX oficial)
 * - Lectura de archivos XLSX vía PhpSpreadsheet
 * - Exportación de errores
 * - Lectura por chunks
 *
 * @author Sistema Paquetería CZ
 * @version 2.0
 */

class CSVHelper
{
    /**
     * Mapa de alias: encabezado en la plantilla XLSX → clave interna del sistema.
     * Se normalizan a minúsculas y con guion bajo antes de buscar en este mapa.
     */
    private static $HEADER_ALIASES = [
        // Columnas fijas de la plantilla XLSX oficial
        'num._orden'            => 'numero_orden',
        'num_orden'             => 'numero_orden',
        'núm._orden'            => 'numero_orden',
        'núm_orden'             => 'numero_orden',
        'número_orden'          => 'numero_orden',
        'fecha_ing.'            => 'fecha_ingreso',
        'fecha_ing'             => 'fecha_ingreso',
        'fecha_ingreso'         => 'fecha_ingreso',
        'depto._(texto_libre)'  => 'departamento',
        'depto_(texto_libre)'   => 'departamento',
        'depto.'                => 'departamento',
        'departamento'          => 'departamento',
        'país_(texto_libre)'    => 'pais',
        'pais_(texto_libre)'    => 'pais',
        'país'                  => 'pais',
        'municipio_(texto_libre)' => 'municipio',
        'barrio_(texto_libre)'  => 'barrio',
        'entre_calles'          => 'entre_calles',
        'código_postal'         => 'codigo_postal',
        'codigo_postal'         => 'codigo_postal',
        'fecha_ent.'            => 'fecha_entrega',
        'fecha_ent'             => 'fecha_entrega',
        'fecha_entrega'         => 'fecha_entrega',
        'total'                 => 'precio_total_local',
        'precio_total_local'    => 'precio_total_local',
        'cliente_(id)'          => 'id_cliente',
        'cliente'               => 'id_cliente',
        'proveedor_(id)'        => 'id_proveedor',
        'proveedor'             => 'id_proveedor',
        'id_proveedor'          => 'id_proveedor',
        'es_combo_(0/1)'        => 'es_combo',
        'es_combo'              => 'es_combo',
        'teléfono'              => 'telefono',
        'telefono'              => 'telefono',
        'dirección'             => 'direccion',
        'direccion'             => 'direccion',
        'teléfono_(texto_libre)' => 'telefono',
        'telefono_(texto_libre)' => 'telefono',
        'dirección_(texto_libre)' => 'direccion',
        'direccion_(texto_libre)' => 'direccion',
        'postalcode'              => 'postalCode',
        'postal_code'             => 'postalCode',
        'postalcode_panama'       => 'postalCode',
        'postal_code_panama'      => 'postalCode',

        // ── Columnas especiales (pedidos LogisPro / proveedores externos) ──────
        'municipalitiesname'      => 'municipalitiesName',
        'municipalities_name'     => 'municipalitiesName',
        'municipio_(logispro)'    => 'municipalitiesName',
        'municipio_logispro'      => 'municipalitiesName',

        'departmentname'          => 'departmentName',
        'department_name'         => 'departmentName',
        'departamento_(logispro)' => 'departmentName',
        'departamento_logispro'   => 'departmentName',

        'location'                => 'Location',
        'ubicacion'               => 'Location',
        'ubicación'               => 'Location',
        'barrio_(logispro)'       => 'Location',
        'barrio_logispro'         => 'Location',

        'betweenstreets'          => 'betweenStreets',
        'between_streets'         => 'betweenStreets',
        'entre_calles_(logispro)' => 'betweenStreets',
        'entre_calles_logispro'   => 'betweenStreets',
    ];

    /**
     * Leer un archivo XLSX y retornar [ headers[], rows[][] ]
     * Requiere PhpSpreadsheet en vendor/
     *
     * @param string $filepath Ruta absoluta al .xlsx
     * @return array ['headers' => [], 'rows' => []]
     */
    public static function readXlsx(string $filepath): array
    {
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (!file_exists($autoload)) {
            throw new \Exception('PhpSpreadsheet no instalado. Ejecuta composer install.');
        }
        require_once $autoload;

        // Capturar cualquier warning/notice de PhpSpreadsheet para que no
        // corrompa la respuesta JSON del controlador.
        ob_start();
        try {
            // Sin setReadDataOnly(true) para que PhpSpreadsheet conserve el tipo
            // de dato de las celdas (necesario para detectar fechas).
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filepath);
            $reader->setLoadSheetsOnly(['Pedidos']);

            try {
                $spreadsheet = $reader->load($filepath);
            } catch (\Throwable $e) {
                // La hoja 'Pedidos' no existe; cargar la primera
                $reader2 = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filepath);
                $spreadsheet = $reader2->load($filepath);
            }

            $sheet      = $spreadsheet->getActiveSheet();
            $highRow    = $sheet->getHighestRow();
            $highCol    = $sheet->getHighestColumn();
            $highColIdx = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highCol);

            ob_end_clean(); // Descartar cualquier salida generada hasta aquí

            if ($highRow < 1) {
                return ['headers' => [], 'rows' => []];
            }

            // ── Leer encabezados (fila 1) ─────────────────────────────────────
            $rawHeaders = [];
            for ($c = 1; $c <= $highColIdx; $c++) {
                $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c) . '1';
                $rawHeaders[] = (string)$sheet->getCell($coord)->getValue();
            }
            $headers = self::normalizeHeaders($rawHeaders);

            // Índices de columnas que deben interpretarse como fecha
            $dateColNames = ['fecha_ingreso', 'fecha_entrega', 'fecha_ing', 'fecha_ent'];
            $dateColIndices = [];
            foreach ($headers as $idx => $name) {
                if (in_array($name, $dateColNames, true)) {
                    $dateColIndices[] = $idx;
                }
            }

            // ── Leer filas de datos ───────────────────────────────────────────
            $rows = [];
            for ($r = 2; $r <= $highRow; $r++) {
                $assoc = [];
                for ($c = 1; $c <= $highColIdx; $c++) {
                    $colName = $headers[$c - 1] ?? '';
                    $coord   = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c) . $r;
                    $cell    = $sheet->getCell($coord);
                    $rawVal  = $cell->getValue();

                    // Convertir número serial de Excel a fecha DD/MM/YYYY
                    if (
                        in_array($c - 1, $dateColIndices, true) &&
                        is_numeric($rawVal) &&
                        $rawVal > 0
                    ) {
                        try {
                            $dt    = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$rawVal);
                            $value = $dt->format('d/m/Y');
                        } catch (\Throwable $dtEx) {
                            $value = (string)$rawVal;
                        }
                    } else {
                        $value = ($rawVal === null) ? '' : (string)$rawVal;
                    }

                    $assoc[$colName] = $value;
                }

                // Omitir filas completamente vacías
                $empty = true;
                foreach ($assoc as $v) {
                    if ($v !== '' && $v !== null) { $empty = false; break; }
                }
                if (!$empty) {
                    $rows[] = $assoc;
                }
            }

            return ['headers' => $headers, 'rows' => $rows];

        } catch (\Throwable $outerEx) {
            ob_end_clean();
            throw $outerEx;
        }
    }
    /**
     * Detectar delimitador del CSV analizando la primera línea
     *
     * @param string $firstLine Primera línea del CSV
     * @return string Delimitador detectado (',', ';', '\t' o '|')
     */
    public static function detectDelimiter($firstLine)
    {
        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);
        $delimiters = [
            ',' => substr_count($firstLine, ','),
            ';' => substr_count($firstLine, ';'),
            "\t" => substr_count($firstLine, "\t"),
            '|' => substr_count($firstLine, '|'),
        ];
        arsort($delimiters);
        $detected = array_key_first($delimiters);
        return $delimiters[$detected] > 0 ? $detected : ',';
    }

    /**
     * Normalizar cabeceras: trim, lowercase, quitar acento en vocal inicial,
     * espacios → guion bajo, y aplicar alias de la plantilla XLSX.
     * Columnas de multi-producto ("Producto 1" → "producto_1") se normalizan automáticamente.
     *
     * @param array $headers
     * @return array
     */
    public static function normalizeHeaders(array $headers): array
    {
        return array_map(function ($h) {
            if ($h === null || $h === '') return '';
            // Quitar BOM, trim
            $h = preg_replace('/^\xEF\xBB\xBF/', '', (string)$h);
            $h = trim($h);
            // Lowercase manteniendo caracteres especiales
            $h = mb_strtolower($h, 'UTF-8');
            // Espacios y puntos especiales → guion bajo
            $h = str_replace([' ', '\u{00A0}'], '_', $h);

            // Buscar en alias (clave ya normalizada con guion bajo)
            if (isset(self::$HEADER_ALIASES[$h])) {
                return self::$HEADER_ALIASES[$h];
            }

            // Columnas multi-producto: "producto_n" y "cantidad_n"
            // Acepta: "producto_1", "producto 1", "producto1"
            if (preg_match('/^producto[_\s]?(\d+)$/', $h, $m)) {
                return 'producto_' . $m[1];
            }
            if (preg_match('/^cantidad[_\s]?(\d+)$/', $h, $m)) {
                return 'cantidad_' . $m[1];
            }

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
     * Validar que el archivo subido es un CSV o XLSX válido
     *
     * @param array $file $_FILES['nombre_campo']
     * @return array ['valido' => bool, 'error' => string|null, 'es_xlsx' => bool]
     */
    public static function validateUploadedFile($file): array
    {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            $messages = [
                UPLOAD_ERR_INI_SIZE   => 'El archivo excede upload_max_filesize.',
                UPLOAD_ERR_FORM_SIZE  => 'El archivo excede el tamaño permitido por el formulario.',
                UPLOAD_ERR_PARTIAL    => 'El archivo fue subido parcialmente.',
                UPLOAD_ERR_NO_FILE    => 'No se envió ningún archivo.',
                UPLOAD_ERR_NO_TMP_DIR => 'Falta carpeta temporal en el servidor.',
                UPLOAD_ERR_CANT_WRITE => 'Fallo al escribir el archivo en disco.',
                UPLOAD_ERR_EXTENSION  => 'Subida detenida por extensión PHP.',
            ];
            $error = $messages[$file['error']] ?? 'Error desconocido al subir el archivo.';
            return ['valido' => false, 'error' => $error, 'es_xlsx' => false];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExt = ['csv', 'txt', 'xlsx'];
        if (!in_array($ext, $allowedExt, true)) {
            return ['valido' => false, 'error' => 'El archivo debe ser .csv, .txt o .xlsx', 'es_xlsx' => false];
        }

        $maxSize = 10 * 1024 * 1024; // 10 MB
        if ($file['size'] > $maxSize) {
            return ['valido' => false, 'error' => 'El archivo excede el tamaño máximo de 10MB', 'es_xlsx' => false];
        }

        $esXlsx = ($ext === 'xlsx');

        if (!$esXlsx) {
            $finfo     = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType  = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            $allowedMimes = [
                'text/csv', 'text/plain', 'application/csv',
                'application/vnd.ms-excel', 'text/comma-separated-values',
            ];
            if (!in_array($mimeType, $allowedMimes, true)) {
                return ['valido' => false, 'error' => "Tipo de archivo no permitido: {$mimeType}", 'es_xlsx' => false];
            }
        }

        return ['valido' => true, 'error' => null, 'es_xlsx' => $esXlsx];
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
