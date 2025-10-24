<?php
/**
 * CSV generator helper
 *
 * Requisitos cubiertos:
 * - Usa fputcsv y streaming a un recurso (por defecto php://output)
 * - Sanitiza celdas que comienzan con = + - @ (prefija con apostrofe) 
 * - Delimitador configurable (',' por defecto)
 * - EOL CRLF (\r\n)
 * - No carga todo en memoria (itera sobre iterator)
 * - Manejo de errores mediante excepciones
 */

if (!function_exists('generate_csv_stream')) {
    /**
     * Generate CSV to a stream
     *
     * @param iterable $rows Iterator of associative arrays (keys must match headers order)
     * @param array $headers Ordered array of column keys to output (stable order)
     * @param resource|null $outStream Resource to write to (defaults to php://output)
     * @param string $delimiter Delimiter to use (',' or ';')
     * @param bool $bom Whether to output UTF-8 BOM at start
     * @throws Exception on open/write errors
     */
    function generate_csv_stream(iterable $rows, array $headers, $outStream = null, string $delimiter = ',', bool $bom = true)
    {
        $allowedDelims = [',', ';'];
        if (!in_array($delimiter, $allowedDelims, true)) {
            throw new InvalidArgumentException('Delimiter must be "," or ";"');
        }

        $closeStream = false;
        if ($outStream === null) {
            $outStream = @fopen('php://output', 'w');
            if ($outStream === false) throw new Exception('Unable to open php://output for writing');
            $closeStream = true;
        }

        // If running in web context and headers not sent, set appropriate headers
        if (php_sapi_name() !== 'cli' && !headers_sent()) {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="pedidos_template.csv"');
            header('Pragma: public');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Expires: 0');
        }

        // BOM
        if ($bom) {
            // Write BOM directly to stream
            fwrite($outStream, chr(0xEF) . chr(0xBB) . chr(0xBF));
        }

        // Helper to sanitize cells to avoid CSV injection for formulas
        $sanitize = function($cell) {
            if ($cell === null) return '';
            // Cast non-scalar
            if (is_array($cell) || is_object($cell)) {
                $cell = json_encode($cell);
            }
            $cell = (string)$cell;
            if ($cell === '') return '';
            // If starts with = + - @ then prefix with apostrophe
            if (preg_match('/^[=+\-@]/', $cell)) {
                return "'" . $cell;
            }
            return $cell;
        };

        // Write header row in stable order
        $outTemp = fopen('php://temp', 'r+');
        if ($outTemp === false) throw new Exception('Unable to open temp stream');
        // fputcsv uses LF; we'll normalize to CRLF when writing to output
        $headerRow = [];
        foreach ($headers as $h) {
            $headerRow[] = $sanitize($h);
        }
        fputcsv($outTemp, $headerRow, $delimiter, '"');
        rewind($outTemp);
        $line = stream_get_contents($outTemp);
        $line = rtrim($line, "\r\n") . "\r\n";
        fwrite($outStream, $line);
        fflush($outStream);

        // Iterate rows streaming
        foreach ($rows as $row) {
            if (!is_array($row) && !($row instanceof Traversable)) {
                // skip invalid row types
                continue;
            }
            // Build ordered row according to $headers
            $outRow = [];
            $allEmpty = true;
            foreach ($headers as $key) {
                $val = array_key_exists($key, (array)$row) ? $row[$key] : null;
                $san = $sanitize($val);
                if ($san !== '') $allEmpty = false;
                $outRow[] = $san;
            }
            if ($allEmpty) {
                // skip entirely empty rows
                continue;
            }
            // write to temp and normalize EOL to CRLF
            ftruncate($outTemp, 0);
            rewind($outTemp);
            fputcsv($outTemp, $outRow, $delimiter, '"');
            rewind($outTemp);
            $line = stream_get_contents($outTemp);
            $line = rtrim($line, "\r\n") . "\r\n";
            if (fwrite($outStream, $line) === false) {
                throw new Exception('Error writing CSV output');
            }
            fflush($outStream);
        }

        if ($closeStream) fclose($outStream);
        fclose($outTemp);
    }
}
