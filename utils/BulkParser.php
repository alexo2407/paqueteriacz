<?php
/**
 * BulkParser — Utilidad para parsear archivos CSV/XLSX de actualización masiva.
 *
 * Uso:
 *   $result = BulkParser::parseFile($_FILES['archivo']);
 *   // $result['rows']    => array de arrays asociativos por fila
 *   // $result['headers'] => columnas detectadas
 */
class BulkParser
{
    /** Máximo de filas permitidas por archivo */
    const MAX_ROWS = 10000;

    /** Columnas reconocidas (normalizadas a minúsculas) */
    const KNOWN_COLS = ['id_pedido', 'numero_orden', 'comentario', 'estado', 'id_estado', 'motivo'];

    /**
     * Punto de entrada principal.
     *
     * @param  array $file  Elemento de $_FILES
     * @return array        ['headers' => string[], 'rows' => array[]]
     * @throws RuntimeException si el archivo es inválido o excede MAX_ROWS
     */
    public static function parseFile(array $file): array
    {
        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Error al recibir el archivo. Código: ' . ($file['error'] ?? 'desconocido'));
        }

        $tmpPath  = $file['tmp_name'];
        $original = $file['name'] ?? 'archivo';
        $ext      = strtolower(pathinfo($original, PATHINFO_EXTENSION));

        if ($ext === 'csv') {
            return self::parseCsv($tmpPath);
        }

        if (in_array($ext, ['xlsx', 'xls'], true)) {
            return self::parseXlsx($tmpPath);
        }

        throw new RuntimeException("Formato no soportado: .{$ext}. Use .csv o .xlsx");
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CSV
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Parsea un archivo CSV detectando el separador automáticamente.
     */
    public static function parseCsv(string $tmpPath): array
    {
        $handle = fopen($tmpPath, 'r');
        if ($handle === false) {
            throw new RuntimeException('No se pudo abrir el archivo CSV.');
        }

        // Detectar separador usando la primera línea
        $firstLine = fgets($handle);
        rewind($handle);
        $sep = (substr_count($firstLine, ';') >= substr_count($firstLine, ',')) ? ';' : ',';

        $headers = null;
        $rows    = [];
        $lineNum = 0;

        while (($raw = fgetcsv($handle, 0, $sep)) !== false) {
            $lineNum++;

            // Ignorar líneas completamente vacías
            if ($raw === [null]) {
                continue;
            }

            if ($headers === null) {
                $headers = self::normalizeHeaders($raw);
                continue;
            }

            if (count($rows) >= self::MAX_ROWS) {
                fclose($handle);
                throw new RuntimeException('El archivo excede el límite de ' . self::MAX_ROWS . ' filas.');
            }

            $row = self::mapRow($headers, $raw, $lineNum + 1); // +1 por header
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        fclose($handle);

        if ($headers === null) {
            throw new RuntimeException('El archivo CSV está vacío o no tiene encabezados.');
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // XLSX
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Parsea un archivo Excel (.xlsx/.xls) usando PhpSpreadsheet.
     */
    public static function parseXlsx(string $tmpPath): array
    {
        // PhpSpreadsheet debe estar instalado via composer
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (!file_exists($autoload)) {
            throw new RuntimeException('PhpSpreadsheet no encontrado. Ejecute: composer require phpoffice/phpspreadsheet');
        }
        require_once $autoload;

        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tmpPath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($tmpPath);
            $sheet       = $spreadsheet->getActiveSheet();
            $data        = $sheet->toArray(null, true, true, false);
        } catch (\Exception $e) {
            throw new RuntimeException('Error al leer el archivo Excel: ' . $e->getMessage());
        }

        if (empty($data)) {
            throw new RuntimeException('El archivo Excel está vacío.');
        }

        $headers = self::normalizeHeaders(array_shift($data));
        $rows    = [];
        $lineNum = 1;

        foreach ($data as $raw) {
            $lineNum++;

            // Saltar filas completamente vacías
            if (empty(array_filter($raw, fn($v) => $v !== null && $v !== ''))) {
                continue;
            }

            if (count($rows) >= self::MAX_ROWS) {
                throw new RuntimeException('El archivo excede el límite de ' . self::MAX_ROWS . ' filas.');
            }

            $row = self::mapRow($headers, $raw, $lineNum);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Normaliza encabezados: minúsculas, trim, reemplaza espacios por _.
     * También elimina el BOM UTF-8 (EF BB BF / \uFEFF) que Excel
     * y los archivos descargados con charset=utf-8 añaden al inicio.
     */
    private static function normalizeHeaders(array $raw): array
    {
        $result = [];
        foreach ($raw as $i => $h) {
            $h = (string)$h;
            // Eliminar BOM UTF-8 del primer encabezado (3 bytes: EF BB BF)
            if ($i === 0) {
                $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);
            }
            $result[] = strtolower(trim(str_replace(' ', '_', $h)));
        }
        return $result;
    }

    /**
     * Combina encabezados con valores de la fila en un array asociativo.
     * Retorna null si la fila está completamente vacía.
     *
     * @param string[] $headers
     * @param mixed[]  $raw
     * @param int      $lineNum  Número de línea en el archivo (para referencia en errores)
     */
    private static function mapRow(array $headers, array $raw, int $lineNum): ?array
    {
        // Igualar longitud
        $raw = array_pad($raw, count($headers), null);

        $row = [];
        foreach ($headers as $i => $col) {
            $val = isset($raw[$i]) ? trim((string)$raw[$i]) : '';
            $row[$col] = $val === '' ? null : $val;
        }

        // Fila completamente nula: ignorar
        if (empty(array_filter($row, fn($v) => $v !== null))) {
            return null;
        }

        $row['_line'] = $lineNum;
        return $row;
    }

    /**
     * Verifica que el resultado del parser contenga al menos las columnas
     * necesarias para la operación bulk.
     *
     * @param string[] $headers
     * @return string|null  Mensaje de error, o null si es válido
     */
    public static function validateHeaders(array $headers): ?string
    {
        $hasId    = in_array('id_pedido', $headers, true);
        $hasOrden = in_array('numero_orden', $headers, true);

        if (!$hasId && !$hasOrden) {
            return 'El archivo debe tener al menos una columna: id_pedido o numero_orden.';
        }

        $hasComentario = in_array('comentario', $headers, true);
        $hasEstado     = in_array('estado', $headers, true) || in_array('id_estado', $headers, true);

        if (!$hasComentario && !$hasEstado) {
            return 'El archivo debe tener al menos una columna para actualizar: comentario, estado o id_estado.';
        }

        return null;
    }
}
