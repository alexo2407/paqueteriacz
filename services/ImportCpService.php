<?php
/**
 * ImportCpService.php
 * Servicio completo para la importación masiva de Códigos Postales.
 *
 * Soporta CSV (autodetect separator) y XLSX (PhpSpreadsheet).
 * Maneja preview y commit en dos fases, usando sesión como job temporal.
 */

require_once __DIR__ . '/../modelo/conexion.php';
require_once __DIR__ . '/../modelo/importacion.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportCpService
{
    const MAX_FILAS       = 10000;
    const CHUNK_SIZE      = 500;   // para IN (...) al validar CPs existentes
    const JOB_TTL_SECONDS = 3600;  // 1 hora

    // ─── Sinónimos de columnas ───────────────────────────────────────────────
    private static $MAP_HEADERS = [
        'id_pais'          => ['id_pais', 'pais', 'country', 'id_country'],
        'codigo_postal'    => ['codigo_postal', 'cp', 'postal_code', 'zip', 'zip_code'],
        'departamento'     => ['departamento', 'provincia', 'state', 'estado'],
        'municipio'        => ['municipio', 'ciudad', 'distrito', 'county', 'city'],
        'barrio'           => ['barrio', 'zona', 'corregimiento', 'neighborhood', 'colonia', 'sector'],
        'nombre_localidad' => ['nombre_localidad', 'localidad', 'referencia', 'nombre', 'locality'],
        'activo'           => ['activo', 'active', 'status'],
    ];

    // ════════════════════════════════════════════════════════════════════════
    // PREVIEW — Parsea, valida, registra log parcial y guarda job en sesión
    // ════════════════════════════════════════════════════════════════════════
    public static function previewImport(array $file, array $opciones, int $userId): array
    {
        $tiempoInicio = microtime(true);

        // Validar archivo
        $validacion = self::validarArchivoSubido($file);
        if (!$validacion['ok']) {
            return ['ok' => false, 'message' => $validacion['message']];
        }

        // Parsear
        $parsed = self::parsearArchivo($file['tmp_name'], $file['name']);
        if (!$parsed['ok']) {
            return ['ok' => false, 'message' => $parsed['message']];
        }

        $filas = $parsed['filas'];
        if (count($filas) > self::MAX_FILAS) {
            $filas = array_slice($filas, 0, self::MAX_FILAS);
        }

        // Insertar log inicial en importaciones_csv
        $logId = ImportacionModel::registrar([
            'id_usuario'           => $userId,
            'archivo_nombre'       => $file['name'],
            'archivo_size_bytes'   => $file['size'],
            'tipo_plantilla'       => 'custom',
            'estado'               => 'parcial',
            'filas_totales'        => count($filas),
            'filas_exitosas'       => 0,
            'filas_error'          => 0,
            'filas_advertencias'   => 0,
            'valores_defecto'      => $opciones,
            'errores_detallados'   => [],
        ]);

        // Precargar países desde BD (un solo query)
        $paisesMap = self::precargarPaises();

        // Validar todas las filas
        $errores       = [];
        $advertencias  = [];
        $filasValidas  = [];
        $duplicadosEnArchivo = []; // key: "id_pais|cp_norm"

        $filasNormalizadas = [];

        foreach ($filas as $i => $fila) {
            $linea  = $i + 2; // +2: 1 por header, 1 por base-1
            $issues = [];
            $warns  = [];

            // Normalizar CP
            $cpRaw  = trim((string)($fila['codigo_postal'] ?? ''));
            $cpNorm = self::normalizarCP($cpRaw);

            // Resolver país
            $idPais   = null;
            $nombrePais = '';
            $paisRaw  = trim((string)($fila['id_pais'] ?? $fila['pais'] ?? ''));

            if ($paisRaw !== '') {
                if (is_numeric($paisRaw)) {
                    $pid = (int)$paisRaw;
                    if (isset($paisesMap['by_id'][$pid])) {
                        $idPais     = $pid;
                        $nombrePais = $paisesMap['by_id'][$pid]['nombre'];
                    }
                } else {
                    $key = mb_strtolower(trim($paisRaw));
                    if (isset($paisesMap['by_nombre'][$key])) {
                        $idPais     = $paisesMap['by_nombre'][$key]['id'];
                        $nombrePais = $paisesMap['by_nombre'][$key]['nombre'];
                    } elseif (isset($paisesMap['by_iso'][$key])) {
                        $idPais     = $paisesMap['by_iso'][$key]['id'];
                        $nombrePais = $paisesMap['by_iso'][$key]['nombre'];
                    }
                }
            }

            if ($cpNorm === '' || $cpNorm === null) {
                $issues[] = ['line' => $linea, 'field' => 'codigo_postal', 'message' => 'Código postal requerido y no puede estar vacío.'];
            }
            if ($idPais === null) {
                if ($paisRaw === '') {
                    $issues[] = ['line' => $linea, 'field' => 'id_pais', 'message' => 'País requerido (id_pais o pais).'];
                } else {
                    $issues[] = ['line' => $linea, 'field' => 'id_pais', 'message' => "País '{$paisRaw}' no encontrado en la base de datos."];
                }
            }

            // activo
            $activoRaw = trim((string)($fila['activo'] ?? ''));
            if ($activoRaw === '') {
                $activo = (int)($opciones['default_activo'] ?? 1);
            } elseif (in_array($activoRaw, ['0', '1'], true)) {
                $activo = (int)$activoRaw;
            } else {
                $issues[] = ['line' => $linea, 'field' => 'activo', 'message' => "Valor inválido para 'activo': '{$activoRaw}'. Solo se aceptan 0 o 1."];
                $activo   = (int)($opciones['default_activo'] ?? 1);
            }

            // Duplicados dentro del archivo
            $dupeKey = $idPais . '|' . $cpNorm;
            if ($idPais && $cpNorm) {
                if (isset($duplicadosEnArchivo[$dupeKey])) {
                    $warns[] = ['line' => $linea, 'field' => 'codigo_postal', 'message' => "CP duplicado dentro del archivo (ya aparece en línea {$duplicadosEnArchivo[$dupeKey]})."];
                } else {
                    $duplicadosEnArchivo[$dupeKey] = $linea;
                }
            }

            $filaNorm = [
                'line'             => $linea,
                'id_pais'          => $idPais,
                'pais'             => $nombrePais,
                'codigo_postal'    => $cpNorm,
                'departamento'     => trim((string)($fila['departamento'] ?? '')),
                'municipio'        => trim((string)($fila['municipio'] ?? '')),
                'barrio'           => trim((string)($fila['barrio'] ?? '')),
                'nombre_localidad' => trim((string)($fila['nombre_localidad'] ?? '')),
                'activo'           => $activo,
                'status'           => empty($issues) ? (empty($warns) ? 'OK' : 'WARN') : 'ERROR',
                '_errors'          => $issues,
                '_warns'           => $warns,
            ];

            $filasNormalizadas[] = $filaNorm;
            $errores             = array_merge($errores, $issues);
            $advertencias        = array_merge($advertencias, $warns);
            if (empty($issues)) {
                $filasValidas[] = $filaNorm;
            }
        }

        // Validar en batch CPs que ya existen en BD (solo informativo para preview)
        $existentes = self::validarExistentesEnBD($filasNormalizadas);
        foreach ($filasNormalizadas as &$fr) {
            $clave = $fr['id_pais'] . '|' . $fr['codigo_postal'];
            if (isset($existentes[$clave]) && $fr['status'] !== 'ERROR') {
                $fr['_existe_en_bd'] = true;
                if ($fr['status'] === 'OK') {
                    $fr['status'] = 'WARN';
                    $warn = ['line' => $fr['line'], 'field' => 'codigo_postal', 'message' => 'CP ya existe en BD (será procesado según el modo de importación).'];
                    $fr['_warns'][] = $warn;
                    $advertencias[] = $warn;
                }
            }
        }
        unset($fr);

        // Preview: primeras 50 filas
        $previewRows = array_slice($filasNormalizadas, 0, 50);
        // Limpiar _errors/_warns del preview (solo para display)
        $previewDisplay = array_map(function($r) {
            unset($r['_errors'], $r['_warns'], $r['_existe_en_bd']);
            return $r;
        }, $previewRows);

        $total          = count($filasNormalizadas);
        $totalErrores   = 0;
        $totalWarn      = 0;
        $totalValidas   = 0;
        foreach ($filasNormalizadas as $fr) {
            if ($fr['status'] === 'ERROR')    $totalErrores++;
            elseif ($fr['status'] === 'WARN') $totalWarn++;
            else                               $totalValidas++;
        }

        // Guardar job en sesión
        $jobId = bin2hex(random_bytes(16));
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['cp_import_jobs'][$jobId] = [
            'created_at'     => time(),
            'import_log_id'  => $logId,
            'opciones'       => $opciones,
            'filas'          => $filasNormalizadas,
            'archivo_nombre' => $file['name'],
            'archivo_size'   => $file['size'],
        ];
        // Limpiar jobs expirados
        self::limpiarJobsExpirados();

        return [
            'ok'             => true,
            'job_id'         => $jobId,
            'import_log_id'  => $logId,
            'summary'        => [
                'total'        => $total,
                'validas'      => $totalValidas,
                'errores'      => $totalErrores,
                'advertencias' => $totalWarn,
            ],
            'errors'         => $errores,
            'warnings'       => $advertencias,
            'preview_rows'   => $previewDisplay,
        ];
    }

    // ════════════════════════════════════════════════════════════════════════
    // COMMIT — Ejecuta la importación real en transacción
    // ════════════════════════════════════════════════════════════════════════
    public static function commitImport(string $jobId, int $userId): array
    {
        $tiempoInicio = microtime(true);

        if (session_status() === PHP_SESSION_NONE) session_start();
        $job = $_SESSION['cp_import_jobs'][$jobId] ?? null;

        if (!$job) {
            return ['ok' => false, 'message' => 'Job expirado o no encontrado. Por favor reinicia la importación.'];
        }

        $filas          = $job['filas'];
        $opciones       = $job['opciones'];
        $logId          = $job['import_log_id'];
        $modo           = $opciones['modo'] ?? 'upsert';
        $crearGeo       = (bool)($opciones['crear_geo'] ?? true);
        $defaultActivo  = (int)($opciones['default_activo'] ?? 1);

        try {
            $db = (new Conexion())->conectar();
            $db->beginTransaction();

            // Caches en memoria para geo
            $cacheDepto  = []; // "id_pais|nombre_lower" => id
            $cacheMuni   = []; // "id_depto|nombre_lower" => id
            $cacheBarrio = []; // "id_muni|nombre_lower"  => id

            // Precargar geografía existente
            self::precargarGeo($db, $cacheDepto, $cacheMuni, $cacheBarrio);

            $insertadas   = 0;
            $actualizadas = 0;
            $omitidas     = 0;
            $fallidas     = 0;
            $erroresCommit = [];

            foreach ($filas as $fila) {
                if ($fila['status'] === 'ERROR') {
                    $omitidas++;
                    continue;
                }

                try {
                    $idDepto  = null;
                    $idMuni   = null;
                    $idBarrio = null;

                    // Resolver / crear Departamento
                    if ($fila['departamento'] !== '') {
                        $idDepto = self::resolverOCrearDepto(
                            $db, $fila['id_pais'], $fila['departamento'],
                            $cacheDepto, $crearGeo
                        );
                    }

                    // Resolver / crear Municipio
                    if ($fila['municipio'] !== '' && $idDepto) {
                        $idMuni = self::resolverOCrearMuni(
                            $db, $idDepto, $fila['municipio'],
                            $cacheMuni, $crearGeo
                        );
                    }

                    // Resolver / crear Barrio
                    if ($fila['barrio'] !== '' && $idMuni) {
                        $idBarrio = self::resolverOCrearBarrio(
                            $db, $idMuni, $fila['barrio'],
                            $cacheBarrio, $crearGeo
                        );
                    }

                    $cpNorm         = $fila['codigo_postal'];
                    $idPais         = $fila['id_pais'];
                    $nombreLocalidad = $fila['nombre_localidad'] ?: null;
                    $activo         = $fila['activo'];

                    if ($modo === 'solo_nuevos') {
                        // INSERT IGNORE
                        $sql = "INSERT IGNORE INTO codigos_postales
                                    (id_pais, codigo_postal, id_departamento, id_municipio, id_barrio, nombre_localidad, activo)
                                VALUES
                                    (:id_pais, :cp, :id_dep, :id_mun, :id_bar, :localidad, :activo)";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([
                            ':id_pais'   => $idPais,
                            ':cp'        => $cpNorm,
                            ':id_dep'    => $idDepto,
                            ':id_mun'    => $idMuni,
                            ':id_bar'    => $idBarrio,
                            ':localidad' => $nombreLocalidad,
                            ':activo'    => $activo,
                        ]);
                        $afect = $stmt->rowCount();
                        if ($afect > 0) $insertadas++;
                        else            $omitidas++;

                    } elseif ($modo === 'upsert') {
                        // INSERT ... ON DUPLICATE KEY UPDATE
                        // Usamos COALESCE(VALUES(col), col) para no repetir parámetros
                        $sql = "INSERT INTO codigos_postales
                                    (id_pais, codigo_postal, id_departamento, id_municipio, id_barrio, nombre_localidad, activo)
                                VALUES
                                    (:id_pais, :cp, :id_dep, :id_mun, :id_bar, :localidad, :activo)
                                ON DUPLICATE KEY UPDATE
                                    id_departamento  = COALESCE(VALUES(id_departamento),  id_departamento),
                                    id_municipio     = COALESCE(VALUES(id_municipio),     id_municipio),
                                    id_barrio        = COALESCE(VALUES(id_barrio),        id_barrio),
                                    nombre_localidad = COALESCE(VALUES(nombre_localidad), nombre_localidad),
                                    activo           = VALUES(activo),
                                    updated_at       = NOW()";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([
                            ':id_pais'   => $idPais,
                            ':cp'        => $cpNorm,
                            ':id_dep'    => $idDepto,
                            ':id_mun'    => $idMuni,
                            ':id_bar'    => $idBarrio,
                            ':localidad' => $nombreLocalidad,
                            ':activo'    => $activo,
                        ]);
                        $afect = $stmt->rowCount();
                        if ($afect == 1)     $insertadas++;
                        elseif ($afect == 2) $actualizadas++;
                        else                  $omitidas++; // 0 rows = nada cambió

                    } elseif ($modo === 'sobrescribir_ubicacion') {
                        // Insertar si no existe; si existe, sobreescribir ubicación
                        // VALUES(col) es la función de MySQL que devuelve el valor propuesto
                        $sql = "INSERT INTO codigos_postales
                                    (id_pais, codigo_postal, id_departamento, id_municipio, id_barrio, nombre_localidad, activo)
                                VALUES
                                    (:id_pais, :cp, :id_dep, :id_mun, :id_bar, :localidad, :activo)
                                ON DUPLICATE KEY UPDATE
                                    id_departamento  = VALUES(id_departamento),
                                    id_municipio     = VALUES(id_municipio),
                                    id_barrio        = VALUES(id_barrio),
                                    nombre_localidad = VALUES(nombre_localidad),
                                    activo           = VALUES(activo),
                                    updated_at       = NOW()";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([
                            ':id_pais'   => $idPais,
                            ':cp'        => $cpNorm,
                            ':id_dep'    => $idDepto,
                            ':id_mun'    => $idMuni,
                            ':id_bar'    => $idBarrio,
                            ':localidad' => $nombreLocalidad,
                            ':activo'    => $activo,
                        ]);
                        $afect = $stmt->rowCount();
                        if ($afect == 1)     $insertadas++;
                        elseif ($afect == 2) $actualizadas++;
                        else                  $omitidas++;
                    }

                } catch (Exception $e) {
                    $fallidas++;
                    $erroresCommit[] = [
                        'line'    => $fila['line'],
                        'field'   => 'general',
                        'message' => $e->getMessage(),
                    ];
                }
            }

            $db->commit();

        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            return ['ok' => false, 'message' => 'Error en transacción: ' . $e->getMessage()];
        }

        $tiempoTotal = round(microtime(true) - $tiempoInicio, 2);
        $totalFilas  = count($filas);
        $filasError  = count(array_filter($filas, fn($r) => $r['status'] === 'ERROR'));

        // Generar archivo de errores si hubo
        $archivoErrores = null;
        if (!empty($erroresCommit)) {
            $archivoErrores = self::generarArchivoErrores($erroresCommit);
        }

        // Calcular estado final
        if ($fallidas === 0 && $filasError === 0) {
            $estado = 'completado';
        } elseif ($insertadas > 0 || $actualizadas > 0) {
            $estado = 'parcial';
        } else {
            $estado = 'fallido';
        }

        // Actualizar log en importaciones_csv
        $db2 = (new Conexion())->conectar();
        $db2->prepare("UPDATE importaciones_csv SET
                filas_totales               = :total,
                filas_exitosas              = :exitosas,
                filas_error                 = :errores,
                filas_advertencias          = :advertencias,
                tiempo_procesamiento_segundos = :tiempo,
                errores_detallados          = :errores_det,
                estado                      = :estado,
                archivo_errores             = :arch_err
            WHERE id = :id")
            ->execute([
                ':total'        => $totalFilas,
                ':exitosas'     => $insertadas + $actualizadas,
                ':errores'      => $fallidas + $filasError,
                ':advertencias' => count(array_filter($filas, fn($r) => $r['status'] === 'WARN')),
                ':tiempo'       => $tiempoTotal,
                ':errores_det'  => json_encode($erroresCommit, JSON_UNESCAPED_UNICODE),
                ':estado'       => $estado,
                ':arch_err'     => $archivoErrores,
                ':id'           => $logId,
            ]);

        // Limpiar job de sesión
        unset($_SESSION['cp_import_jobs'][$jobId]);

        return [
            'ok'             => true,
            'import_log_id'  => $logId,
            'result' => [
                'total'       => $totalFilas,
                'insertadas'  => $insertadas,
                'actualizadas'=> $actualizadas,
                'omitidas'    => $omitidas,
                'fallidas'    => $fallidas,
                'tiempo'      => $tiempoTotal,
                'estado'      => $estado,
            ],
            'archivo_errores' => $archivoErrores,
        ];
    }

    // ════════════════════════════════════════════════════════════════════════
    // HELPERS INTERNOS
    // ════════════════════════════════════════════════════════════════════════

    /** Parsea CSV o XLSX y retorna filas con headers normalizados */
    private static function parsearArchivo(string $tmpPath, string $nombre): array
    {
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));

        if ($ext === 'xlsx' || $ext === 'xls') {
            return self::parsearXLSX($tmpPath);
        }
        return self::parsearCSV($tmpPath);
    }

    private static function parsearCSV(string $path): array
    {
        $handle = fopen($path, 'r');
        if (!$handle) {
            return ['ok' => false, 'message' => 'No se pudo abrir el archivo CSV.'];
        }
        // Detectar BOM UTF-8
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // Leer primera línea para detectar separador
        $firstLine = fgets($handle);
        rewind($handle);
        // Si tiene BOM, saltar
        $bom2 = fread($handle, 3);
        if ($bom2 !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $separador = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

        $filas   = [];
        $headers = null;
        $i       = 0;

        while (($row = fgetcsv($handle, 4096, $separador)) !== false) {
            if ($headers === null) {
                $headers = self::normalizarHeaders($row);
                continue;
            }
            if ($i >= self::MAX_FILAS) break;

            // Mapear fila a headers normalizados
            $fila = [];
            foreach ($headers as $idx => $campo) {
                $fila[$campo] = isset($row[$idx]) ? trim($row[$idx]) : '';
            }
            $filas[] = $fila;
            $i++;
        }
        fclose($handle);

        if ($headers === null) {
            return ['ok' => false, 'message' => 'El archivo CSV está vacío o no tiene encabezados.'];
        }

        return ['ok' => true, 'filas' => $filas];
    }

    private static function parsearXLSX(string $path): array
    {
        try {
            $spreadsheet = IOFactory::load($path);
            $sheet       = $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray(null, true, true, false);

            if (empty($rows)) {
                return ['ok' => false, 'message' => 'El archivo XLSX está vacío.'];
            }

            $headers = self::normalizarHeaders(array_shift($rows));
            $filas   = [];

            foreach ($rows as $row) {
                if (count(array_filter($row, fn($c) => $c !== null && $c !== '')) === 0) continue;
                $fila = [];
                foreach ($headers as $idx => $campo) {
                    $fila[$campo] = isset($row[$idx]) ? trim((string)$row[$idx]) : '';
                }
                $filas[] = $fila;
                if (count($filas) >= self::MAX_FILAS) break;
            }

            return ['ok' => true, 'filas' => $filas];

        } catch (Exception $e) {
            return ['ok' => false, 'message' => 'Error al leer XLSX: ' . $e->getMessage()];
        }
    }

    /** Normaliza headers: lowercase, trim, mapea sinónimos */
    private static function normalizarHeaders(array $rawHeaders): array
    {
        // Construir índice inverso sinónimo => campo_canónico
        $sinonimos = [];
        foreach (self::$MAP_HEADERS as $campo => $alias) {
            foreach ($alias as $a) {
                $sinonimos[mb_strtolower(trim($a))] = $campo;
            }
        }

        $resultado = [];
        foreach ($rawHeaders as $raw) {
            $key = mb_strtolower(trim((string)$raw));
            $resultado[] = $sinonimos[$key] ?? $key; // si no se reconoce, pasa tal cual
        }
        return $resultado;
    }

    /** Valida archivo subido: tamaño y extensión */
    private static function validarArchivoSubido(array $file): array
    {
        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'message' => 'Error al subir el archivo: código ' . ($file['error'] ?? 'desconocido')];
        }
        $maxBytes = 10 * 1024 * 1024; // 10 MB
        if ($file['size'] > $maxBytes) {
            return ['ok' => false, 'message' => 'El archivo supera el tamaño máximo permitido (10 MB).'];
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xlsx', 'xls'], true)) {
            return ['ok' => false, 'message' => "Extensión no permitida: .{$ext}. Use CSV o XLSX."];
        }
        return ['ok' => true];
    }

    /** Normaliza CP: trim, uppercase, quita espacios y guiones */
    public static function normalizarCP(string $cp): ?string
    {
        if (trim($cp) === '') return null;
        $cp = strtoupper(trim($cp));
        return str_replace([' ', '-', '.'], '', $cp);
    }

    /** Precarga toda la tabla paises en arrays indexados por id, nombre e iso */
    private static function precargarPaises(): array
    {
        $db   = (new Conexion())->conectar();
        $stmt = $db->query('SELECT id, nombre, codigo_iso FROM paises ORDER BY id ASC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $byId     = [];
        $byNombre = [];
        $byIso    = [];
        foreach ($rows as $r) {
            $byId[$r['id']] = $r;
            $byNombre[mb_strtolower($r['nombre'])] = $r;
            if ($r['codigo_iso']) {
                $byIso[mb_strtolower($r['codigo_iso'])] = $r;
            }
        }
        return ['by_id' => $byId, 'by_nombre' => $byNombre, 'by_iso' => $byIso];
    }

    /** Valida en batch si CPs ya existen en BD (consultas IN por chunks) */
    private static function validarExistentesEnBD(array &$filas): array
    {
        $byPais = [];
        foreach ($filas as $f) {
            if ($f['id_pais'] && $f['codigo_postal']) {
                $byPais[$f['id_pais']][] = $f['codigo_postal'];
            }
        }

        $db        = (new Conexion())->conectar();
        $existentes = [];

        foreach ($byPais as $paisId => $cps) {
            $chunks = array_chunk(array_unique($cps), self::CHUNK_SIZE);
            foreach ($chunks as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                $params       = array_merge([$paisId], $chunk);
                $stmt         = $db->prepare(
                    "SELECT codigo_postal FROM codigos_postales WHERE id_pais = ? AND codigo_postal IN ({$placeholders})"
                );
                $stmt->execute($params);
                foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $cp) {
                    $existentes[$paisId . '|' . $cp] = true;
                }
            }
        }

        return $existentes;
    }

    /** Precarga toda la geografía en caches de memoria */
    private static function precargarGeo(\PDO $db, array &$cacheDepto, array &$cacheMuni, array &$cacheBarrio): void
    {
        // Departamentos
        $rows = $db->query('SELECT id, id_pais, LOWER(nombre) AS n FROM departamentos')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $cacheDepto[$r['id_pais'] . '|' . $r['n']] = (int)$r['id'];
        }
        // Municipios
        $rows = $db->query('SELECT id, id_departamento, LOWER(nombre) AS n FROM municipios')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $cacheMuni[$r['id_departamento'] . '|' . $r['n']] = (int)$r['id'];
        }
        // Barrios
        $rows = $db->query('SELECT id, id_municipio, LOWER(nombre) AS n FROM barrios')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $cacheBarrio[$r['id_municipio'] . '|' . $r['n']] = (int)$r['id'];
        }
    }

    private static function resolverOCrearDepto(\PDO $db, int $idPais, string $nombre, array &$cache, bool $crear): ?int
    {
        $key = $idPais . '|' . mb_strtolower($nombre);
        if (isset($cache[$key])) return $cache[$key];
        if (!$crear) return null;

        $stmt = $db->prepare('INSERT INTO departamentos (nombre, id_pais) VALUES (:n, :p)');
        $stmt->execute([':n' => $nombre, ':p' => $idPais]);
        $id = (int)$db->lastInsertId();
        $cache[$key] = $id;
        return $id;
    }

    private static function resolverOCrearMuni(\PDO $db, int $idDepto, string $nombre, array &$cache, bool $crear): ?int
    {
        $key = $idDepto . '|' . mb_strtolower($nombre);
        if (isset($cache[$key])) return $cache[$key];
        if (!$crear) return null;

        $stmt = $db->prepare('INSERT INTO municipios (nombre, id_departamento) VALUES (:n, :d)');
        $stmt->execute([':n' => $nombre, ':d' => $idDepto]);
        $id = (int)$db->lastInsertId();
        $cache[$key] = $id;
        return $id;
    }

    private static function resolverOCrearBarrio(\PDO $db, int $idMuni, string $nombre, array &$cache, bool $crear): ?int
    {
        $key = $idMuni . '|' . mb_strtolower($nombre);
        if (isset($cache[$key])) return $cache[$key];
        if (!$crear) return null;

        $stmt = $db->prepare('INSERT INTO barrios (nombre, id_municipio) VALUES (:n, :m)');
        $stmt->execute([':n' => $nombre, ':m' => $idMuni]);
        $id = (int)$db->lastInsertId();
        $cache[$key] = $id;
        return $id;
    }

    /** Genera un CSV con las filas erróneas y retorna el nombre del archivo */
    private static function generarArchivoErrores(array $errores): ?string
    {
        $dir = __DIR__ . '/../cache/import_errors/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $nombre  = 'cp_errores_' . date('Ymd_His') . '_' . substr(uniqid(), -6) . '.csv';
        $ruta    = $dir . $nombre;
        $handle  = fopen($ruta, 'w');
        if (!$handle) return null;

        fputcsv($handle, ['linea', 'campo', 'mensaje']);
        foreach ($errores as $e) {
            fputcsv($handle, [$e['line'], $e['field'], $e['message']]);
        }
        fclose($handle);
        return $nombre;
    }

    /** Limpia jobs de sesión que hayan expirado */
    private static function limpiarJobsExpirados(): void
    {
        if (!isset($_SESSION['cp_import_jobs'])) return;
        $ahora = time();
        foreach ($_SESSION['cp_import_jobs'] as $jid => $job) {
            if (($ahora - ($job['created_at'] ?? 0)) > self::JOB_TTL_SECONDS) {
                unset($_SESSION['cp_import_jobs'][$jid]);
            }
        }
    }
}
