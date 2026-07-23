<?php
require_once __DIR__ . '/../modelo/conexion.php';
/**
 * PayloadBuilderService
 *
 * Motor dinámico que construye el payload (JSON/XML/SOAP) de una petición a proveedor
 * externo a partir de un array de reglas de mapeo almacenadas en BD.
 *
 * Soporta:
 *   - Dot-notation para rutas anidadas:  "shipment_destination.address"
 *   - Arreglos de productos con []:      "contains[].name"
 *   - Type casting:                      string, int, float, boolean
 *   - Transform rules:                   to_int, to_float, to_bool, limit:N
 *   - Valores por defecto                si el campo interno está vacío
 *   - SOAP Envelope completo             con namespaces, SOAPAction, auth en body
 */
class PayloadBuilderService
{
    /**
     * Campos internos del sistema disponibles para mapear desde $pedido.
     * Se usa para poblar la UI de configuración de mapeos.
     */
    public static function getCamposInternos(): array
    {
        return [
            // Pedido principal
            ['key' => 'numero_orden',         'label' => 'Número de Orden'],
            ['key' => 'destinatario',         'label' => 'Destinatario (Nombre)'],
            ['key' => 'telefono',             'label' => 'Teléfono del Destinatario'],
            ['key' => 'direccion',            'label' => 'Dirección de Entrega'],
            ['key' => 'comentario',           'label' => 'Comentario / Observaciones'],
            ['key' => 'codigo_postal',        'label' => 'Código Postal'],
            ['key' => 'postalCode',           'label' => 'Postal Code (alt.)'],
            ['key' => 'precio_total_local',   'label' => 'Precio Total (moneda local)'],
            ['key' => 'fecha_entrega',        'label' => 'Fecha de Entrega'],
            ['key' => 'municipalitiesName',   'label' => 'Nombre del Municipio'],
            ['key' => 'departmentName',       'label' => 'Nombre del Departamento'],
            ['key' => 'Location',             'label' => 'Ubicación (texto)'],
            ['key' => 'betweenStreets',       'label' => 'Entre Calles'],
            ['key' => 'nit',                  'label' => 'NIT del Destinatario'],
            ['key' => 'lat',                  'label' => 'Latitud'],
            ['key' => 'lng',                  'label' => 'Longitud'],
            ['key' => 'code_city',            'label' => 'Código de Ciudad (City DANE Code)'],
            // Productos (arreglo — usar prefijo productos[])
            ['key' => 'productos[].producto_nombre',     'label' => 'Productos → Nombre'],
            ['key' => 'productos[].sku',                 'label' => 'Productos → SKU'],
            ['key' => 'productos[].cantidad',            'label' => 'Productos → Cantidad (bruta)'],
            ['key' => 'productos[].cantidad_neta',       'label' => 'Productos → Cantidad (neta, -devueltos)'],
            ['key' => 'productos[].precio_unitario_usd', 'label' => 'Productos → Precio Unitario USD'],
            ['key' => 'productos[].nombre_con_cantidad', 'label' => 'Productos → Nombre formateado con cantidad (ej: 2x Producto)'],
            // Claves virtuales especiales
            ['key' => '_total_units',   'label' => 'Total de Unidades (suma cantidad_neta) — genera N elementos vacíos repetidos'],
            ['key' => '_now_datetime',  'label' => 'Fecha y Hora Actual (ISO 8601: 2024-01-15T10:30:00)'],
            ['key' => '_today',         'label' => 'Fecha Actual (YYYY-MM-DD)'],
            ['key' => '_caex_poblado',  'label' => 'Código Poblado CAEX (busca por municipalitiesName en catálogo CAEX)'],
        ];
    }

    /**
     * Construir el payload final a partir del $pedido y las reglas de mapeo.
     *
     * @param array $pedido  Array completo del pedido (incluye $pedido['productos'])
     * @param array $mapeos  Array de mapeos desde ForwardingModel::obtenerMapeosDeProveedor()
     *                       Cada elemento: [field_path, field_type, is_required,
     *                                       default_value, internal_key, transform_rule]
     * @return array         Array PHP estructurado listo para json_encode / arrayToXml
     * @throws Exception     Si un campo requerido no tiene valor
     */
    public static function build(array $pedido, array $mapeos): array
    {
        $salida = [];

        // Inyectar claves virtuales calculadas en tiempo de ejecucion
        $totalUnits = 0;
        foreach ($pedido['productos'] ?? [] as $p) {
            $totalUnits += max(0, (int)($p['cantidad'] ?? 0) - (int)($p['cantidad_devuelta'] ?? 0));
        }
        $pedido['_total_units']  = max(1, $totalUnits);
        $pedido['_now_datetime'] = date('Y-m-d\TH:i:s');
        $pedido['_today']        = date('Y-m-d');

        // _caex_poblado: buscar codigo en la tabla caex_poblados por municipio del pedido
        $pedido['_caex_poblado'] = self::buscarCodigoCaexPoblado(
            $pedido['municipalitiesName'] ?? '',
            $pedido['departmentName'] ?? ''
        );

        // Separar mapeos simples de mapeos de arreglo
        $simples  = [];
        $arrayMap = [];

        foreach ($mapeos as $m) {
            if (self::esRutaDeArreglo($m['field_path'])) {
                $arrayMap[] = $m;
            } else {
                $simples[] = $m;
            }
        }

        // 1. Resolver campos simples
        foreach ($simples as $m) {
            $valor = self::resolverValorSimple($pedido, $m);
            self::setDotPath($salida, $m['field_path'], $valor);
        }

        // 2. Resolver campos de arreglo (productos o repeticion por cantidad)
        if (!empty($arrayMap)) {
            $salida = self::resolverArreglos($salida, $pedido, $arrayMap);
        }

        return $salida;
    }

    // -------------------------------------------------------------------------
    // SOAP / XML
    // -------------------------------------------------------------------------

    /**
     * Construir un SOAP Envelope completo.
     *
     * El resultado es el string XML listo para enviar, incluyendo:
     *   - Declaración XML
     *   - soap:Envelope con namespaces estándar
     *   - soap:Body con el tag raíz del método
     *   - (Opcional) nodo <Autenticacion> o equivalente con las credenciales dentro del body
     *
     * @param array  $payload         Array PHP con el payload ya mapeado (de build())
     * @param array  $soapConfig      Configuración del envelope:
     *   - 'soap_envelope_tag'   (string)  Tag raíz del método, ej: 'GenerarGuia'
     *   - 'soap_namespace'      (string)  xmlns del tag raíz, ej: 'http://www.caexlogistics.com/ServiceBus'
     *   - 'soap_item_tag'       (string)  Tag para ítems de arrays numéricos, default 'item'
     *   - 'soap_auth_in_body'   (bool)    Si las credenciales van dentro del body
     *   - 'soap_auth_tag'       (string)  Tag del nodo de auth en el body, default 'Autenticacion'
     *   - 'soap_auth_login_tag' (string)  Sub-tag del usuario, default 'Login'
     *   - 'soap_auth_pass_tag'  (string)  Sub-tag del password, default 'Password'
     * @param array  $credentials     ['userName' => ..., 'password' => ...]  (si soap_auth_in_body)
     * @return string                 SOAP Envelope XML completo
     */
    public static function soapEnvelope(array $payload, array $soapConfig, array $credentials = []): string
    {
        $methodTag  = $soapConfig['soap_envelope_tag']   ?? 'Request';
        $xmlns      = $soapConfig['soap_namespace']       ?? '';
        $itemTag    = $soapConfig['soap_item_tag']        ?? 'item';
        $authInBody = !empty($soapConfig['soap_auth_in_body']);
        $authTag    = $soapConfig['soap_auth_tag']        ?? 'Autenticacion';
        $loginTag   = $soapConfig['soap_auth_login_tag']  ?? 'Login';
        $passTag    = $soapConfig['soap_auth_pass_tag']   ?? 'Password';

        // Construir el XML del body usando SimpleXML
        $xmlnsAttr = $xmlns ? ' xmlns="' . $xmlns . '"' : '';
        $bodyXml   = new SimpleXMLElement('<' . $methodTag . $xmlnsAttr . '/>');

        // Inyectar credenciales en el body si esta configurado
        if ($authInBody && !empty($credentials)) {
            $authNode = $bodyXml->addChild($authTag);
            $authNode->addChild($loginTag,  htmlspecialchars($credentials['userName'] ?? '', ENT_XML1));
            $authNode->addChild($passTag,   htmlspecialchars($credentials['password'] ?? '', ENT_XML1));
        }

        // Inyectar el payload mapeado
        self::arrayToXmlRecursivo($payload, $bodyXml, $itemTag);

        // Extraer el XML (sin declaracion XML del body interno, la ponemos en el envelope)
        $dom = dom_import_simplexml($bodyXml)->ownerDocument;
        $dom->formatOutput = true;
        $bodyXmlStr = $dom->saveXML($dom->documentElement);

        // Construir el envelope completo
        $q = '?';
        $envelope  = '<' . $q . 'xml version="1.0" encoding="utf-8"' . $q . '>' . "\n";
        $envelope .= '<soap:Envelope'
            . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
            . ' xmlns:xsd="http://www.w3.org/2001/XMLSchema"'
            . ' xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">' . "\n";
        $envelope .= '  <soap:Body>' . "\n";
        $envelope .= '    ' . trim($bodyXmlStr) . "\n";
        $envelope .= '  </soap:Body>' . "\n";
        $envelope .= '</soap:Envelope>';

        return $envelope;
    }

    /**
     * Convertir un array PHP a XML simple.
     * Para SOAP, usar soapEnvelope() que envuelve este resultado correctamente.
     *
     * @param array  $data      Array a convertir
     * @param string $rootTag   Etiqueta raíz del XML
     * @param string $itemTag   Etiqueta para elementos de arrays numéricos (default 'item')
     * @return string XML con declaración
     */
    public static function arrayToXml(array $data, string $rootTag = 'root', string $itemTag = 'item'): string
    {
        $xml = new SimpleXMLElement("<{$rootTag}/>");
        self::arrayToXmlRecursivo($data, $xml, $itemTag);
        $dom = dom_import_simplexml($xml)->ownerDocument;
        $dom->formatOutput = true;
        return $dom->saveXML();
    }

    // -------------------------------------------------------------------------
    // Privados
    // -------------------------------------------------------------------------

    /**
     * Determina si una ruta contiene la notación de arreglo "[].".
     */
    private static function esRutaDeArreglo(string $path): bool
    {
        return strpos($path, '[].') !== false || substr($path, -2) === '[]';
    }

    /**
     * Resolver el valor de un campo simple del $pedido.
     */
    private static function resolverValorSimple(array $pedido, array $m)
    {
        $internalKey = $m['internal_key'];
        $valor       = $pedido[$internalKey] ?? $m['default_value'] ?? null;

        if ($valor === null || $valor === '') {
            if ((int)$m['is_required']) {
                throw new Exception(
                    "PayloadBuilder: Campo requerido '{$m['field_path']}' (clave interna: '{$internalKey}') no tiene valor."
                );
            }
            $valor = $m['default_value'];
        }

        return self::castear($valor, $m['field_type'] ?? 'string', $m['transform_rule'] ?? null);
    }

    /**
     * Procesar mapeos de arreglo iterando sobre $pedido['productos'].
     * Agrupa por prefijo de arreglo (antes del []).
     */
    private static function resolverArreglos(array $salida, array $pedido, array $arrayMap): array
    {
        // Agrupar por nombre del arreglo externo (ej "contains" de "contains[].name")
        $grupos = [];
        foreach ($arrayMap as $m) {
            $partes      = explode('[]', $m['field_path'], 2);
            $arrayName   = trim($partes[0], '.');
            $subPath     = ltrim($partes[1] ?? '', '.');
            $grupos[$arrayName][] = ['sub_path' => $subPath, 'mapping' => $m];
        }

        $productos = $pedido['productos'] ?? [];

        foreach ($grupos as $arrayName => $campos) {
            $itemsResultado = [];

            // ── Caso especial: _total_units sin sub_path ──────────────────────
            // Cuando internal_key = '_total_units' y no hay sub_path,
            // genera N elementos VACIOS repetidos (ej: <Pieza/> x cantidad total).
            // Permite el patron CAEX/SOAP de bultos sin contenido.
            $esTotalUnits = count($campos) === 1
                && ($campos[0]['mapping']['internal_key'] ?? '') === '_total_units'
                && ($campos[0]['sub_path'] ?? '') === '';

            if ($esTotalUnits) {
                $n = (int)($pedido['_total_units'] ?? 1);
                for ($i = 0; $i < $n; $i++) {
                    $itemsResultado[] = [];
                }
                self::setDotPath($salida, $arrayName, $itemsResultado);
                continue; // Siguiente grupo, no pasar por la logica de productos
            }
            // ─────────────────────────────────────────────────────────────────

            foreach ($productos as $prod) {
                // Calcular cantidad neta
                $prod['cantidad_neta'] = max(0, (int)($prod['cantidad'] ?? 0) - (int)($prod['cantidad_devuelta'] ?? 0));
                // Campo virtual formateado (ej. "2x URO UP Forte")
                $prod['nombre_con_cantidad'] = $prod['cantidad_neta'] . 'x ' . ($prod['producto_nombre'] ?? '');

                // Omitir ítems con cantidad neta 0
                if ($prod['cantidad_neta'] <= 0 && in_array('cantidad_neta', array_column($campos, 'sub_path'))) {
                    continue;
                }

                $item = [];
                foreach ($campos as $campo) {
                    $subPath     = $campo['sub_path'];
                    $m           = $campo['mapping'];
                    $internalKey = ltrim(str_replace('productos[].', '', $m['internal_key']), 'productos[].');
                    $valor       = $prod[$internalKey] ?? $m['default_value'] ?? null;
                    $valor       = self::castear($valor, $m['field_type'] ?? 'string', $m['transform_rule'] ?? null);

                    if ($subPath !== '') {
                        self::setDotPath($item, $subPath, $valor);
                    } else {
                        $item = $valor;
                    }
                }
                $itemsResultado[] = $item;
            }

            // Fallback de seguridad: si no hay productos, meter un ítem vacío
            if (empty($itemsResultado)) {
                $itemsResultado[] = ['name' => 'Envio', 'quantity' => 1];
            }

            self::setDotPath($salida, $arrayName, $itemsResultado);
        }

        return $salida;
    }

    /**
     * Insertar un valor en un array siguiendo dot-notation.
     * Ejemplo: setDotPath($arr, "shipment_destination.address", "Calle 5") →
     *   $arr['shipment_destination']['address'] = "Calle 5"
     */
    private static function setDotPath(array &$arr, string $path, $valor): void
    {
        $keys = explode('.', $path);
        $ref  = &$arr;
        foreach ($keys as $key) {
            if (!isset($ref[$key]) || !is_array($ref[$key])) {
                $ref[$key] = [];
            }
            $ref = &$ref[$key];
        }
        $ref = $valor;
    }

    /**
     * Castear y transformar un valor según el tipo y la regla de transformación.
     *
     * @param mixed  $valor
     * @param string $type          string | int | float | boolean | array
     * @param string|null $rule     to_int, to_float, to_bool, limit:N, upper, lower
     * @return mixed
     */
    private static function castear($valor, string $type, ?string $rule)
    {
        // Primero aplicar transform_rule si existe
        if ($rule) {
            if ($rule === 'to_int')   return (int)$valor;
            if ($rule === 'to_float') return (float)$valor;
            if ($rule === 'to_bool')  return (bool)$valor;
            if ($rule === 'upper')    return strtoupper((string)$valor);
            if ($rule === 'lower')    return strtolower((string)$valor);
            if (strpos($rule, 'limit:') === 0) {
                $n = (int)substr($rule, 6);
                return substr((string)$valor, 0, $n);
            }
        }

        // Luego aplicar el type del campo
        switch ($type) {
            case 'int':     return (int)$valor;
            case 'float':   return (float)$valor;
            case 'boolean': return (bool)$valor;
            case 'array':   return is_array($valor) ? $valor : [];
            default:        return (string)($valor ?? '');
        }
    }

    /**
     * Recursivo: poblar un SimpleXMLElement desde un array PHP.
     * Cuando la clave es numérica (array secuencial), usa $itemTag como nombre del nodo.
     *
     * @param array            $data
     * @param SimpleXMLElement $xml
     * @param string           $itemTag  Etiqueta para elementos de arrays numéricos
     */
    private static function arrayToXmlRecursivo(array $data, SimpleXMLElement &$xml, string $itemTag = 'item'): void
    {
        foreach ($data as $key => $val) {
            // Llave numérica → usar $itemTag en lugar de 'item' genérico
            $tag = is_numeric($key)
                ? $itemTag
                : preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string)$key);

            if (is_array($val)) {
                $child = $xml->addChild($tag);
                self::arrayToXmlRecursivo($val, $child, $itemTag);
            } else {
                $xml->addChild($tag, htmlspecialchars((string)$val, ENT_XML1));
            }
        }
    }

    /**
     * Buscar el código de poblado CAEX a partir del nombre del municipio del pedido.
     * Usa búsqueda normalizada (sin acentos, minúsculas) contra la tabla caex_poblados.
     * Si hay varios resultados, intenta afinar con el departamento.
     *
     * @param string $municipio  p.ej. "Guatemala" o "Mixco"
     * @param string $depto      p.ej. "Guatemala"
     * @return string            Código CAEX (ej. "1065") o vacío si no se encuentra
     */
    private static function buscarCodigoCaexPoblado(string $municipio, string $depto): string
    {
        if (empty($municipio)) return '';

        try {
            // Normalizar el término de búsqueda
            $normalizado = mb_strtolower(trim($municipio), 'UTF-8');
            $normalizado = strtr($normalizado, [
                'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u',
                'ä'=>'a','ë'=>'e','ï'=>'i','ö'=>'o','ü'=>'u',
                'ñ'=>'n','ç'=>'c',
            ]);

            $db = (new Conexion())->conectar();

            // Búsqueda exacta primero
            $stmt = $db->prepare(
                "SELECT codigo, nombre, nombre_normalizado FROM caex_poblados WHERE nombre_normalizado = :q LIMIT 5"
            );
            $stmt->execute([':q' => $normalizado]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Si no hay exacto, intentar LIKE (municipio contenido en nombre_normalizado)
            if (empty($rows)) {
                $stmt = $db->prepare(
                    "SELECT codigo, nombre, nombre_normalizado FROM caex_poblados WHERE nombre_normalizado LIKE :q LIMIT 5"
                );
                $stmt->execute([':q' => '%' . $normalizado . '%']);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            if (empty($rows)) return '';

            // Si hay un solo resultado, usarlo directamente
            if (count($rows) === 1) return $rows[0]['codigo'];

            // Si hay varios, intentar afinar con el departamento
            if (!empty($depto)) {
                $deptoNorm = mb_strtolower(trim($depto), 'UTF-8');
                $deptoNorm = strtr($deptoNorm, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n']);
                foreach ($rows as $r) {
                    if (strpos($r['nombre_normalizado'], $deptoNorm) !== false) {
                        return $r['codigo'];
                    }
                }
            }

            // Usar el primer resultado
            return $rows[0]['codigo'];

        } catch (Exception $e) {
            error_log('PayloadBuilderService::buscarCodigoCaexPoblado error: ' . $e->getMessage());
            return '';
        }
    }
}
