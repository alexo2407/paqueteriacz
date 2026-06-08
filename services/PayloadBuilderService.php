<?php
/**
 * PayloadBuilderService
 *
 * Motor dinámico que construye el payload (JSON/XML) de una petición a proveedor
 * externo a partir de un array de reglas de mapeo almacenadas en BD.
 *
 * Soporta:
 *   - Dot-notation para rutas anidadas:  "shipment_destination.address"
 *   - Arreglos de productos con []:      "contains[].name"
 *   - Type casting:                      string, int, float, boolean
 *   - Transform rules:                   to_int, to_float, to_bool, limit:N
 *   - Valores por defecto                si el campo interno está vacío
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
            // Productos (arreglo — usar prefijo productos[])
            ['key' => 'productos[].producto_nombre', 'label' => 'Productos → Nombre'],
            ['key' => 'productos[].sku',             'label' => 'Productos → SKU'],
            ['key' => 'productos[].cantidad',        'label' => 'Productos → Cantidad (bruta)'],
            ['key' => 'productos[].cantidad_neta',   'label' => 'Productos → Cantidad (neta, -devueltos)'],
            ['key' => 'productos[].precio_unitario_usd', 'label' => 'Productos → Precio Unitario USD'],
        ];
    }

    /**
     * Construir el payload final a partir del $pedido y las reglas de mapeo.
     *
     * @param array $pedido  Array completo del pedido (incluye $pedido['productos'])
     * @param array $mapeos  Array de mapeos desde ForwardingModel::obtenerMapeosDeProveedor()
     *                       Cada elemento: [field_path, field_type, is_required,
     *                                       default_value, internal_key, transform_rule]
     * @return array         Array PHP estructurado listo para json_encode / ArrayToXml
     * @throws Exception     Si un campo requerido no tiene valor
     */
    public static function build(array $pedido, array $mapeos): array
    {
        $salida = [];

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

        // 2. Resolver campos de arreglo (productos)
        if (!empty($arrayMap)) {
            $salida = self::resolverArreglos($salida, $pedido, $arrayMap);
        }

        return $salida;
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

            foreach ($productos as $prod) {
                // Calcular cantidad neta (útil si se mapea como productos[].cantidad_neta)
                $prod['cantidad_neta'] = max(0, (int)($prod['cantidad'] ?? 0) - (int)($prod['cantidad_devuelta'] ?? 0));

                // Omitir ítems con cantidad neta 0
                if ($prod['cantidad_neta'] <= 0 && in_array('cantidad_neta', array_column($campos, 'sub_path'))) {
                    continue;
                }

                $item = [];
                foreach ($campos as $campo) {
                    $subPath = $campo['sub_path'];
                    $m       = $campo['mapping'];
                    $internalKey = ltrim(str_replace('productos[].', '', $m['internal_key']), 'productos[].');
                    // Soporte simple: buscar la clave en el producto
                    $valor = $prod[$internalKey] ?? $m['default_value'] ?? null;
                    $valor = self::castear($valor, $m['field_type'] ?? 'string', $m['transform_rule'] ?? null);

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
                $itemsResultado[] = ['name' => 'Envío', 'quantity' => 1];
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
     * Convertir un array PHP a XML simple (sin namespaces).
     * Para SOAP el DynamicProvider envuelve este resultado en el Envelope.
     *
     * @param array  $data      Array a convertir
     * @param string $rootTag   Etiqueta raíz del XML
     * @return string XML
     */
    public static function arrayToXml(array $data, string $rootTag = 'root'): string
    {
        $xml = new SimpleXMLElement("<{$rootTag}/>");
        self::arrayToXmlRecursivo($data, $xml);
        $dom = dom_import_simplexml($xml)->ownerDocument;
        $dom->formatOutput = true;
        return $dom->saveXML();
    }

    private static function arrayToXmlRecursivo(array $data, SimpleXMLElement &$xml): void
    {
        foreach ($data as $key => $val) {
            // Llave numérica (arreglo secuencial): usar "item" como etiqueta
            $tag = is_numeric($key) ? 'item' : preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string)$key);
            if (is_array($val)) {
                $child = $xml->addChild($tag);
                self::arrayToXmlRecursivo($val, $child);
            } else {
                $xml->addChild($tag, htmlspecialchars((string)$val, ENT_XML1));
            }
        }
    }
}
