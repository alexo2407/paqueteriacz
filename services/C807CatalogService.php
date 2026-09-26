<?php
/**
 * C807CatalogService
 *
 * Servicio de sincronización y resolución de catálogos C807 (Departamentos y Municipios).
 * Garantiza que los IDs internos de RutaEx nunca se envíen directamente a C807.
 */

require_once __DIR__ . '/../modelo/conexion.php';

class C807CatalogService
{
    /**
     * Resolver la ubicación (departamento y municipio) de C807 a partir de datos internos.
     *
     * @param int|null $idDepInterno
     * @param int|null $idMunInterno
     * @param string $depNombre
     * @param string $munNombre
     * @return array ['success' => bool, 'c807_departamento_id' => int, 'c807_municipio_id' => int, 'message' => string]
     */
    public static function resolverUbicacionC807(?int $idDepInterno, ?int $idMunInterno, string $depNombre = '', string $munNombre = ''): array
    {
        try {
            $db = (new Conexion())->conectar();

            // 1. Intentar resolver por relación directa en c807_municipios y c807_departamentos
            if ($idMunInterno && $idMunInterno > 0) {
                $stmt = $db->prepare("
                    SELECT m.id AS c807_mun_id, m.c807_departamento_id AS c807_dep_id
                    FROM c807_municipios m
                    WHERE m.id_municipio_interno = :id_mun_interno
                    LIMIT 1
                ");
                $stmt->execute([':id_mun_interno' => $idMunInterno]);
                $direct = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($direct) {
                    return [
                        'success'               => true,
                        'c807_departamento_id'  => (int)$direct['c807_dep_id'],
                        'c807_municipio_id'     => (int)$direct['c807_mun_id'],
                        'message'               => 'Homologación directa encontrada por ID interno.',
                    ];
                }
            }

            // 2. Si no hay mapeo por ID numérico pero tenemos nombres, intentar coincidencia normalizada
            $munNorm = self::normalizarTexto($munNombre);
            $depNorm = self::normalizarTexto($depNombre);

            if ($munNorm !== '') {
                // Buscar municipios coincidentes en c807_municipios
                $stmt = $db->prepare("
                    SELECT m.id AS c807_mun_id, m.c807_departamento_id AS c807_dep_id, m.nombre AS mun_nombre,
                           d.nombre AS dep_nombre
                    FROM c807_municipios m
                    INNER JOIN c807_departamentos d ON d.id = m.c807_departamento_id
                ");
                $stmt->execute();
                $allMun = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $candidatos = [];
                foreach ($allMun as $row) {
                    $c807MunNorm = self::normalizarTexto($row['mun_nombre']);
                    $c807DepNorm = self::normalizarTexto($row['dep_nombre']);

                    // Coincidencia exacta de municipio
                    if ($c807MunNorm === $munNorm) {
                        // Si también coincide departamento o no se especificó departamento, es candidato fuerte
                        if ($depNorm === '' || $c807DepNorm === $depNorm || strpos($c807DepNorm, $depNorm) !== false || strpos($depNorm, $c807DepNorm) !== false) {
                            $candidatos[] = $row;
                        }
                    }
                }

                // Si encontramos exactamente una coincidencia inequívoca
                if (count($candidatos) === 1) {
                    $cand = $candidatos[0];
                    // Si tenemos ID interno, auto-asociarlo para optimizar futuras búsquedas
                    if ($idMunInterno && $idMunInterno > 0) {
                        try {
                            $upd = $db->prepare("UPDATE c807_municipios SET id_municipio_interno = :id_interno WHERE id = :id AND id_municipio_interno IS NULL");
                            $upd->execute([':id_interno' => $idMunInterno, ':id' => $cand['c807_mun_id']]);
                        } catch (Throwable $ignored) {}
                    }

                    return [
                        'success'              => true,
                        'c807_departamento_id' => (int)$cand['c807_dep_id'],
                        'c807_municipio_id'    => (int)$cand['c807_mun_id'],
                        'message'              => 'Homologación unívoca resuelta por nombre: ' . $cand['mun_nombre'],
                    ];
                }

                if (count($candidatos) > 1) {
                    return [
                        'success' => false,
                        'message' => "Ambigüedad geográfica: el municipio '{$munNombre}' coincide con múltiples opciones en C807. Especifica el departamento correcto.",
                    ];
                }
            }

            return [
                'success' => false,
                'message' => "No se encontró homologación geográfica en C807 para Municipio: '{$munNombre}' (ID: {$idMunInterno}) / Depto: '{$depNombre}' (ID: {$idDepInterno}). El pedido queda pendiente.",
            ];

        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => 'Error al consultar catálogo geográfico C807: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Sincronizar catálogo de departamentos y municipios desde la API de C807.
     *
     * @param string $baseUrl
     * @param string $token Bearer token
     * @return array ['success' => bool, 'departamentos_sincronizados' => int, 'municipios_sincronizados' => int]
     */
    public static function sincronizarDesdeApi(string $baseUrl, string $token): array
    {
        $db = (new Conexion())->conectar();
        $baseUrl = rtrim($baseUrl, '/');

        // 1. Obtener departamentos: GET /guia.php/catalogo/departamento
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $baseUrl . '/guia.php/catalogo/departamento',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$token}",
                "Accept: application/json",
            ],
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $depResp = curl_exec($ch);
        $depHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($depHttp !== 200 || !$depResp) {
            throw new Exception("Error al obtener catálogo de departamentos C807 (HTTP {$depHttp}): {$depResp}");
        }

        $deptos = json_decode($depResp, true);
        if (!is_array($deptos)) {
            throw new Exception("Respuesta inválida de departamentos C807: {$depResp}");
        }

        $stmtDep = $db->prepare("
            INSERT INTO c807_departamentos (id, nombre, codigo)
            VALUES (:id, :nombre, :codigo)
            ON DUPLICATE KEY UPDATE 
                nombre = VALUES(nombre),
                codigo = VALUES(codigo)
        ");

        $stmtMun = $db->prepare("
            INSERT INTO c807_municipios (id, c807_departamento_id, nombre)
            VALUES (:id, :c807_dep_id, :nombre)
            ON DUPLICATE KEY UPDATE 
                c807_departamento_id = VALUES(c807_departamento_id),
                nombre = VALUES(nombre)
        ");

        $depCount = 0;
        $munCount = 0;

        foreach ($deptos as $d) {
            $depId = (int)($d['id'] ?? 0);
            if ($depId <= 0) continue;
            $depNombre = trim($d['nombre'] ?? '');
            $depCodigo = trim($d['codigo'] ?? '');

            $stmtDep->execute([
                ':id'     => $depId,
                ':nombre' => $depNombre,
                ':codigo' => $depCodigo ?: null,
            ]);
            $depCount++;

            // 2. Obtener municipios para este departamento: GET /guia.php/catalogo/municipio?departamento={id}
            $chM = curl_init();
            curl_setopt_array($chM, [
                CURLOPT_URL            => $baseUrl . '/guia.php/catalogo/municipio?departamento=' . $depId,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_HTTPHEADER     => [
                    "Authorization: Bearer {$token}",
                    "Accept: application/json",
                ],
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $munResp = curl_exec($chM);
            $munHttp = curl_getinfo($chM, CURLINFO_HTTP_CODE);
            curl_close($chM);

            if ($munHttp === 200 && $munResp) {
                $munis = json_decode($munResp, true);
                if (is_array($munis)) {
                    foreach ($munis as $m) {
                        $munId = (int)($m['id'] ?? 0);
                        if ($munId <= 0) continue;
                        $stmtMun->execute([
                            ':id'          => $munId,
                            ':c807_dep_id' => $depId,
                            ':nombre'      => trim($m['nombre'] ?? ''),
                        ]);
                        $munCount++;
                    }
                }
            }
        }

        return [
            'success'                     => true,
            'departamentos_sincronizados' => $depCount,
            'municipios_sincronizados'    => $munCount,
        ];
    }

    /**
     * Normalizar cadenas para búsqueda sin acentos, mayúsculas ni caracteres especiales.
     */
    public static function normalizarTexto(string $str): string
    {
        $str = mb_strtolower(trim($str), 'UTF-8');
        $acentos = ['á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u', 'ñ'=>'n', 'ü'=>'u'];
        $str = strtr($str, $acentos);
        $str = preg_replace('/[^a-z0-9]/', '', $str);
        return $str;
    }
}
