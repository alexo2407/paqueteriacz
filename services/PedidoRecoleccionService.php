<?php
/**
 * PedidoRecoleccionService
 *
 * Centraliza la normalización, validación geográfica, persistencia y consulta
 * de los datos de recolección (origen) por pedido.
 *
 * Reutilizable desde:
 * 1. API (PedidoApiController / api/pedidos)
 * 2. Importador masivo Excel / CSV (CSVPedidoValidator / PedidosController)
 * 3. Formularios Web de creación y edición (PedidosController / vistas)
 */

require_once __DIR__ . '/../modelo/conexion.php';

class PedidoRecoleccionService
{
    protected ?PDO $dbInstance = null;

    public function __construct(?PDO $db = null)
    {
        $this->dbInstance = $db;
    }

    public function getDb(): PDO
    {
        if ($this->dbInstance === null) {
            $this->dbInstance = (new Conexion())->conectar();
        }
        return $this->dbInstance;
    }

    /**
     * Valida los datos de recolección y retorna array de errores.
     * Retorna array vacío [] si los datos son válidos.
     *
     * @param array $input
     * @param bool $esActualizacion
     * @param ?array $existente
     * @return array
     */
    public function validar(array $input, bool $esActualizacion = false, ?array $existente = null): array
    {
        $res = self::validarYNormalizar($input, $this->getDb(), $esActualizacion, $existente);
        return $res['errors'] ?? [];
    }

    /**
     * Normaliza un texto para comparaciones: minúsculas UTF-8 y espacios recortados.
     */
    public static function normalizarTexto(?string $str): string
    {
        if ($str === null) return '';
        return mb_strtolower(trim($str), 'UTF-8');
    }

    /**
     * Remueve acentos y caracteres diacríticos para búsqueda flexible.
     */
    public static function removerAcentos(string $str): string
    {
        $map = [
            'á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u', 'ü'=>'u', 'ñ'=>'n',
            'Á'=>'a', 'É'=>'e', 'Í'=>'i', 'Ó'=>'o', 'Ú'=>'u', 'Ü'=>'u', 'Ñ'=>'n',
            'à'=>'a', 'è'=>'e', 'ì'=>'i', 'ò'=>'o', 'ù'=>'u',
            'À'=>'a', 'È'=>'e', 'Ì'=>'i', 'Ò'=>'o', 'Ù'=>'u'
        ];
        return strtr($str, $map);
    }

    /**
     * Resuelve un país mediante ID o nombre/código ISO.
     * Valida que no existan contradicciones entre ID y nombre si ambos se envían.
     *
     * @param mixed $idPais
     * @param mixed $nombrePais
     * @param PDO $db
     * @return array ['success' => bool, 'id' => ?int, 'error' => ?string]
     */
    public static function resolverPais($idPais, $nombrePais, PDO $db): array
    {
        $idVal = (!empty($idPais) && is_numeric($idPais) && (int)$idPais > 0) ? (int)$idPais : null;
        $nomVal = (!empty($nombrePais) && is_string($nombrePais)) ? trim($nombrePais) : null;

        if (!$idVal && !$nomVal) {
            return ['success' => false, 'id' => null, 'error' => 'El país de recolección es obligatorio (id_pais o pais).'];
        }

        // Obtener catálogo de países
        $stmt = $db->query("SELECT id, nombre FROM paises");
        $catalogo = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $paisPorId = null;
        if ($idVal) {
            foreach ($catalogo as $p) {
                if ((int)$p['id'] === $idVal) {
                    $paisPorId = $p;
                    break;
                }
            }
            if (!$paisPorId) {
                return ['success' => false, 'id' => null, 'error' => "El país de recolección con ID {$idVal} no existe en el catálogo."];
            }
        }

        $paisPorNombre = null;
        if ($nomVal) {
            $norm = self::normalizarTexto($nomVal);
            $sinAcento = self::removerAcentos($norm);

            $coincidenciasExactas = [];
            $coincidenciasFlexibles = [];

            foreach ($catalogo as $p) {
                $pNorm = self::normalizarTexto($p['nombre']);
                $pSinAcento = self::removerAcentos($pNorm);

                if ($pNorm === $norm) {
                    $coincidenciasExactas[] = $p;
                } elseif ($pSinAcento === $sinAcento) {
                    $coincidenciasFlexibles[] = $p;
                }
            }

            if (count($coincidenciasExactas) === 1) {
                $paisPorNombre = $coincidenciasExactas[0];
            } elseif (count($coincidenciasExactas) > 1) {
                return ['success' => false, 'id' => null, 'error' => "Existen múltiples coincidencias para el país de recolección '{$nomVal}'."];
            } elseif (count($coincidenciasFlexibles) === 1) {
                $paisPorNombre = $coincidenciasFlexibles[0];
            } elseif (count($coincidenciasFlexibles) > 1) {
                return ['success' => false, 'id' => null, 'error' => "Existen múltiples coincidencias para el país de recolección '{$nomVal}'."];
            } else {
                return ['success' => false, 'id' => null, 'error' => "El país de recolección '{$nomVal}' no existe en el catálogo."];
            }
        }

        // Si se enviaron ambos, verificar que coincidan
        if ($paisPorId && $paisPorNombre) {
            if ((int)$paisPorId['id'] !== (int)$paisPorNombre['id']) {
                return [
                    'success' => false,
                    'id' => null,
                    'error' => "Conflicto entre id_pais ({$idVal} - {$paisPorId['nombre']}) y el nombre de país ('{$nomVal}')."
                ];
            }
        }

        $finalId = $paisPorId ? (int)$paisPorId['id'] : (int)$paisPorNombre['id'];
        return ['success' => true, 'id' => $finalId, 'error' => null];
    }

    /**
     * Resuelve un departamento dentro de un país.
     *
     * @param int $idPais
     * @param mixed $idDepto
     * @param mixed $nombreDepto
     * @param PDO $db
     * @return array ['success' => bool, 'id' => ?int, 'error' => ?string]
     */
    public static function resolverDepartamento(int $idPais, $idDepto, $nombreDepto, PDO $db): array
    {
        $idVal = (!empty($idDepto) && is_numeric($idDepto) && (int)$idDepto > 0) ? (int)$idDepto : null;
        $nomVal = (!empty($nombreDepto) && is_string($nombreDepto)) ? trim($nombreDepto) : null;

        if (!$idVal && !$nomVal) {
            return ['success' => false, 'id' => null, 'error' => 'El departamento de recolección es obligatorio (id_departamento o departamento).'];
        }

        // Consultar departamentos pertenecientes a este país
        $stmt = $db->prepare("SELECT id, nombre, id_pais FROM departamentos WHERE id_pais = :id_pais");
        $stmt->execute([':id_pais' => $idPais]);
        $deptosPais = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $deptoPorId = null;
        if ($idVal) {
            // Verificar si existe en la BD en general para dar mensaje específico
            $stmtGen = $db->prepare("SELECT id, nombre, id_pais FROM departamentos WHERE id = :id LIMIT 1");
            $stmtGen->execute([':id' => $idVal]);
            $deptoGen = $stmtGen->fetch(PDO::FETCH_ASSOC);

            if (!$deptoGen) {
                return ['success' => false, 'id' => null, 'error' => "El departamento de recolección con ID {$idVal} no existe en el catálogo."];
            }
            if ((int)$deptoGen['id_pais'] !== $idPais) {
                return ['success' => false, 'id' => null, 'error' => "El departamento '{$deptoGen['nombre']}' (ID {$idVal}) no pertenece al país de recolección seleccionado (ID {$idPais})."];
            }
            $deptoPorId = $deptoGen;
        }

        $deptoPorNombre = null;
        if ($nomVal) {
            $norm = self::normalizarTexto($nomVal);
            $sinAcento = self::removerAcentos($norm);

            $coincidenciasExactas = [];
            $coincidenciasFlexibles = [];

            foreach ($deptosPais as $d) {
                $dNorm = self::normalizarTexto($d['nombre']);
                $dSinAcento = self::removerAcentos($dNorm);

                if ($dNorm === $norm) {
                    $coincidenciasExactas[] = $d;
                } elseif ($dSinAcento === $sinAcento) {
                    $coincidenciasFlexibles[] = $d;
                }
            }

            if (count($coincidenciasExactas) === 1) {
                $deptoPorNombre = $coincidenciasExactas[0];
            } elseif (count($coincidenciasExactas) > 1) {
                return ['success' => false, 'id' => null, 'error' => "Existen múltiples coincidencias para el departamento de recolección '{$nomVal}' en el país seleccionado."];
            } elseif (count($coincidenciasFlexibles) === 1) {
                $deptoPorNombre = $coincidenciasFlexibles[0];
            } elseif (count($coincidenciasFlexibles) > 1) {
                return ['success' => false, 'id' => null, 'error' => "Existen múltiples coincidencias para el departamento de recolección '{$nomVal}' en el país seleccionado."];
            } else {
                return ['success' => false, 'id' => null, 'error' => "El departamento de recolección '{$nomVal}' no existe en el país de recolección seleccionado."];
            }
        }

        if ($deptoPorId && $deptoPorNombre) {
            if ((int)$deptoPorId['id'] !== (int)$deptoPorNombre['id']) {
                return [
                    'success' => false,
                    'id' => null,
                    'error' => "Conflicto entre id_departamento ({$idVal} - {$deptoPorId['nombre']}) y el nombre de departamento ('{$nomVal}')."
                ];
            }
        }

        $finalId = $deptoPorId ? (int)$deptoPorId['id'] : (int)$deptoPorNombre['id'];
        return ['success' => true, 'id' => $finalId, 'error' => null];
    }

    /**
     * Resuelve un municipio dentro de un departamento.
     *
     * @param int $idDepto
     * @param mixed $idMuni
     * @param mixed $nombreMuni
     * @param PDO $db
     * @return array ['success' => bool, 'id' => ?int, 'error' => ?string]
     */
    public static function resolverMunicipio(int $idDepto, $idMuni, $nombreMuni, PDO $db): array
    {
        $idVal = (!empty($idMuni) && is_numeric($idMuni) && (int)$idMuni > 0) ? (int)$idMuni : null;
        $nomVal = (!empty($nombreMuni) && is_string($nombreMuni)) ? trim($nombreMuni) : null;

        if (!$idVal && !$nomVal) {
            return ['success' => false, 'id' => null, 'error' => 'El municipio de recolección es obligatorio (id_municipio o municipio).'];
        }

        $stmt = $db->prepare("SELECT id, nombre, id_departamento FROM municipios WHERE id_departamento = :id_depto");
        $stmt->execute([':id_depto' => $idDepto]);
        $munisDepto = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $muniPorId = null;
        if ($idVal) {
            $stmtGen = $db->prepare("SELECT id, nombre, id_departamento FROM municipios WHERE id = :id LIMIT 1");
            $stmtGen->execute([':id' => $idVal]);
            $muniGen = $stmtGen->fetch(PDO::FETCH_ASSOC);

            if (!$muniGen) {
                return ['success' => false, 'id' => null, 'error' => "El municipio de recolección con ID {$idVal} no existe en el catálogo."];
            }
            if ((int)$muniGen['id_departamento'] !== $idDepto) {
                return ['success' => false, 'id' => null, 'error' => "El municipio '{$muniGen['nombre']}' (ID {$idVal}) no pertenece al departamento de recolección seleccionado (ID {$idDepto})."];
            }
            $muniPorId = $muniGen;
        }

        $muniPorNombre = null;
        if ($nomVal) {
            $norm = self::normalizarTexto($nomVal);
            $sinAcento = self::removerAcentos($norm);

            $coincidenciasExactas = [];
            $coincidenciasFlexibles = [];

            foreach ($munisDepto as $m) {
                $mNorm = self::normalizarTexto($m['nombre']);
                $mSinAcento = self::removerAcentos($mNorm);

                if ($mNorm === $norm) {
                    $coincidenciasExactas[] = $m;
                } elseif ($mSinAcento === $sinAcento) {
                    $coincidenciasFlexibles[] = $m;
                }
            }

            if (count($coincidenciasExactas) === 1) {
                $muniPorNombre = $coincidenciasExactas[0];
            } elseif (count($coincidenciasExactas) > 1) {
                return ['success' => false, 'id' => null, 'error' => "Existen múltiples coincidencias para el municipio de recolección '{$nomVal}' en el departamento seleccionado."];
            } elseif (count($coincidenciasFlexibles) === 1) {
                $muniPorNombre = $coincidenciasFlexibles[0];
            } elseif (count($coincidenciasFlexibles) > 1) {
                return ['success' => false, 'id' => null, 'error' => "Existen múltiples coincidencias para el municipio de recolección '{$nomVal}' en el departamento seleccionado."];
            } else {
                return ['success' => false, 'id' => null, 'error' => "El municipio de recolección '{$nomVal}' no existe en el departamento de recolección seleccionado."];
            }
        }

        if ($muniPorId && $muniPorNombre) {
            if ((int)$muniPorId['id'] !== (int)$muniPorNombre['id']) {
                return [
                    'success' => false,
                    'id' => null,
                    'error' => "Conflicto entre id_municipio ({$idVal} - {$muniPorId['nombre']}) y el nombre de municipio ('{$nomVal}')."
                ];
            }
        }

        $finalId = $muniPorId ? (int)$muniPorId['id'] : (int)$muniPorNombre['id'];
        return ['success' => true, 'id' => $finalId, 'error' => null];
    }

    /**
     * Valida y normaliza un bloque de datos de recolección.
     * Soporta creación o actualización parcial (cuando $existente está disponible).
     *
     * @param array $input Datos de recolección recibidos
     * @param ?PDO $db Conexión opcional (se obtiene si no se envía)
     * @param bool $esActualizacion
     * @param ?array $existente Datos previos en BD si es actualización
     * @return array [
     *    'success' => bool,
     *    'errors'  => array ['recoleccion.campo' => 'mensaje'],
     *    'data'    => array o null si hubo error
     * ]
     */
    public static function validarYNormalizar(array $input, ?PDO $db = null, bool $esActualizacion = false, ?array $existente = null): array
    {
        if ($db === null) {
            $db = (new Conexion())->conectar();
        }

        // Si es actualización parcial y existe registro previo, fusionar campos no enviados
        $merged = $input;
        if ($esActualizacion && !empty($existente)) {
            foreach (['id_pais', 'id_departamento', 'id_municipio', 'direccion', 'contacto', 'telefono', 'referencia'] as $campo) {
                if (!array_key_exists($campo, $merged) && !array_key_exists(str_replace('id_', '', $campo), $merged)) {
                    $merged[$campo] = $existente[$campo] ?? null;
                }
            }
        }

        $errors = [];

        // 1. País
        $resPais = self::resolverPais(
            $merged['id_pais'] ?? null,
            $merged['pais'] ?? null,
            $db
        );
        if (!$resPais['success']) {
            $errors['recoleccion.id_pais'] = $resPais['error'];
        }

        // 2. Departamento (requiere país válido)
        $idPaisResuelto = $resPais['id'] ?? 0;
        $idDeptoResuelto = 0;
        if ($idPaisResuelto > 0) {
            $resDepto = self::resolverDepartamento(
                $idPaisResuelto,
                $merged['id_departamento'] ?? null,
                $merged['departamento'] ?? null,
                $db
            );
            if (!$resDepto['success']) {
                $errors['recoleccion.id_departamento'] = $resDepto['error'];
            } else {
                $idDeptoResuelto = $resDepto['id'];
            }
        } else {
            if (empty($merged['id_departamento']) && empty($merged['departamento'])) {
                $errors['recoleccion.id_departamento'] = 'El departamento de recolección es obligatorio.';
            }
        }

        // 3. Municipio (requiere depto válido)
        $idMuniResuelto = 0;
        if ($idDeptoResuelto > 0) {
            $resMuni = self::resolverMunicipio(
                $idDeptoResuelto,
                $merged['id_municipio'] ?? null,
                $merged['municipio'] ?? null,
                $db
            );
            if (!$resMuni['success']) {
                $errors['recoleccion.id_municipio'] = $resMuni['error'];
            } else {
                $idMuniResuelto = $resMuni['id'];
            }
        } else {
            if (empty($merged['id_municipio']) && empty($merged['municipio'])) {
                $errors['recoleccion.id_municipio'] = 'El municipio de recolección es obligatorio.';
            }
        }

        // 4. Dirección
        $direccion = trim((string)($merged['direccion'] ?? ''));
        if ($direccion === '') {
            $errors['recoleccion.direccion'] = 'La dirección de recolección es obligatoria.';
        } elseif (mb_strlen($direccion, 'UTF-8') < 3) {
            $errors['recoleccion.direccion'] = 'La dirección de recolección debe tener al menos 3 caracteres.';
        }

        // 5. Contacto (opcional, máx 150)
        $contacto = isset($merged['contacto']) ? trim((string)$merged['contacto']) : null;
        if ($contacto === '') $contacto = null;
        if ($contacto !== null && mb_strlen($contacto, 'UTF-8') > 150) {
            $errors['recoleccion.contacto'] = 'El contacto de recolección no debe exceder 150 caracteres.';
        }

        // 6. Teléfono (opcional, texto máx 30, conserva prefijo intl)
        $telefono = isset($merged['telefono']) ? trim((string)$merged['telefono']) : null;
        if ($telefono === '') $telefono = null;
        if ($telefono !== null && mb_strlen($telefono, 'UTF-8') > 30) {
            $errors['recoleccion.telefono'] = 'El teléfono de recolección no debe exceder 30 caracteres.';
        }

        // 7. Referencia (opcional, máx 255)
        $referencia = isset($merged['referencia']) ? trim((string)$merged['referencia']) : null;
        if ($referencia === '') $referencia = null;
        if ($referencia !== null && mb_strlen($referencia, 'UTF-8') > 255) {
            $errors['recoleccion.referencia'] = 'La referencia de recolección no debe exceder 255 caracteres.';
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'errors'  => $errors,
                'data'    => null
            ];
        }

        return [
            'success' => true,
            'errors'  => [],
            'data'    => [
                'id_pais'         => $idPaisResuelto,
                'id_departamento' => $idDeptoResuelto,
                'id_municipio'    => $idMuniResuelto,
                'direccion'       => $direccion,
                'contacto'        => $contacto,
                'telefono'        => $telefono,
                'referencia'      => $referencia
            ]
        ];
    }

    /**
     * Guarda (crea o actualiza) los datos de recolección para un pedido dentro
     * de la transacción provista por el llamador.
     *
     * @param int $idPedido
     * @param array $datos Datos validados listos para insertar/actualizar
     * @param PDO $db Instancia PDO con transacción activa
     * @return bool
     * @throws Exception
     */
    public static function guardar(int $idPedido, array $datos, PDO $db): bool
    {
        $sql = "
            INSERT INTO pedido_recoleccion 
                (id_pedido, id_pais, id_departamento, id_municipio, direccion, contacto, telefono, referencia, created_at, updated_at)
            VALUES 
                (:id_pedido, :id_pais, :id_departamento, :id_municipio, :direccion, :contacto, :telefono, :referencia, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                id_pais         = VALUES(id_pais),
                id_departamento = VALUES(id_departamento),
                id_municipio    = VALUES(id_municipio),
                direccion       = VALUES(direccion),
                contacto        = VALUES(contacto),
                telefono        = VALUES(telefono),
                referencia      = VALUES(referencia),
                updated_at      = NOW()
        ";

        $stmt = $db->prepare($sql);
        return $stmt->execute([
            ':id_pedido'       => $idPedido,
            ':id_pais'         => (int)$datos['id_pais'],
            ':id_departamento' => (int)$datos['id_departamento'],
            ':id_municipio'    => (int)$datos['id_municipio'],
            ':direccion'       => $datos['direccion'],
            ':contacto'        => $datos['contacto'] ?? null,
            ':telefono'        => $datos['telefono'] ?? null,
            ':referencia'      => $datos['referencia'] ?? null,
        ]);
    }

    /**
     * Elimina los datos de recolección asociados a un pedido.
     *
     * @param int $idPedido
     * @param PDO $db
     * @return bool
     */
    public static function eliminar(int $idPedido, PDO $db): bool
    {
        $stmt = $db->prepare("DELETE FROM pedido_recoleccion WHERE id_pedido = :id_pedido");
        return $stmt->execute([':id_pedido' => $idPedido]);
    }

    /**
     * Obtiene los datos de recolección de un pedido incluyendo nombres resueltos.
     * Devuelve null si el pedido no tiene datos de recolección.
     *
     * @param int $idPedido
     * @param ?PDO $db
     * @return ?array
     */
    public static function obtenerPorPedidoId(int $idPedido, ?PDO $db = null): ?array
    {
        if ($db === null) {
            $db = (new Conexion())->conectar();
        }

        try {
            $stmt = $db->prepare("
                SELECT 
                    pr.id,
                    pr.id_pedido,
                    pr.id_pais,
                    p.nombre AS pais_nombre,
                    pr.id_departamento,
                    d.nombre AS departamento_nombre,
                    pr.id_municipio,
                    m.nombre AS municipio_nombre,
                    pr.direccion,
                    pr.contacto,
                    pr.telefono,
                    pr.referencia,
                    pr.created_at,
                    pr.updated_at
                FROM pedido_recoleccion pr
                LEFT JOIN paises p ON p.id = pr.id_pais
                LEFT JOIN departamentos d ON d.id = pr.id_departamento
                LEFT JOIN municipios m ON m.id = pr.id_municipio
                WHERE pr.id_pedido = :id_pedido
                LIMIT 1
            ");
            $stmt->execute([':id_pedido' => $idPedido]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) return null;

            // Formatear tipos enteros
            $row['id']              = (int)$row['id'];
            $row['id_pedido']       = (int)$row['id_pedido'];
            $row['id_pais']         = (int)$row['id_pais'];
            $row['id_departamento'] = (int)$row['id_departamento'];
            $row['id_municipio']    = (int)$row['id_municipio'];

            return $row;
        } catch (PDOException $e) {
            // Si la tabla aún no existe (antes de migración), devolver null silenciosamente
            return null;
        }
    }

    /**
     * Consulta por lote para múltiples pedidos (previene consultas N+1 en listados o reportes).
     *
     * @param array $pedidosIds
     * @param ?PDO $db
     * @return array Mapa de [id_pedido => array recolección]
     */
    public static function obtenerPorPedidosIds(array $pedidosIds, ?PDO $db = null): array
    {
        if (empty($pedidosIds)) return [];

        if ($db === null) {
            $db = (new Conexion())->conectar();
        }

        $cleanIds = array_filter(array_map('intval', $pedidosIds), fn($id) => $id > 0);
        if (empty($cleanIds)) return [];

        try {
            $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
            $sql = "
                SELECT 
                    pr.id,
                    pr.id_pedido,
                    pr.id_pais,
                    p.nombre AS pais_nombre,
                    pr.id_departamento,
                    d.nombre AS departamento_nombre,
                    pr.id_municipio,
                    m.nombre AS municipio_nombre,
                    pr.direccion,
                    pr.contacto,
                    pr.telefono,
                    pr.referencia,
                    pr.created_at,
                    pr.updated_at
                FROM pedido_recoleccion pr
                LEFT JOIN paises p ON p.id = pr.id_pais
                LEFT JOIN departamentos d ON d.id = pr.id_departamento
                LEFT JOIN municipios m ON m.id = pr.id_municipio
                WHERE pr.id_pedido IN ($placeholders)
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute(array_values($cleanIds));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $map = [];
            foreach ($rows as $r) {
                $r['id']              = (int)$r['id'];
                $r['id_pedido']       = (int)$r['id_pedido'];
                $r['id_pais']         = (int)$r['id_pais'];
                $r['id_departamento'] = (int)$r['id_departamento'];
                $r['id_municipio']    = (int)$r['id_municipio'];
                $map[$r['id_pedido']] = $r;
            }

            return $map;
        } catch (PDOException $e) {
            return [];
        }
    }
}
