<?php
/**
 * ThermalLabelService - Generador de Etiquetas Térmicas (80 mm / 3nStar)
 *
 * Genera documentos PDF continuos o multipágina calibrados exactamente para
 * impresoras térmicas de 80 mm (3 pulgadas) marca 3nStar y compatibles.
 *
 * Características:
 * - 100% Vectorial, alto contraste térmico (sin imágenes pesadas).
 * - Código de barras Code 128 de alta legibilidad.
 * - Código QR de rastreo y entrega.
 * - Soporte para generación individual y masiva en un solo flujo continuo.
 *
 * @author RutaEx Latam
 */

require_once __DIR__ . '/../libs/PDF_Code128.php';
require_once __DIR__ . '/../vendor/autoload.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class ThermalLabelService
{
    /**
     * Ancho estándar en milímetros para rollo térmico de 80 mm
     */
    const LABEL_WIDTH_MM = 80;

    /**
     * Alto estándar por etiqueta térmica (145 mm)
     */
    const LABEL_HEIGHT_MM = 145;

    /**
     * Margen lateral en mm
     */
    const MARGIN_LEFT_MM = 4;
    const MARGIN_RIGHT_MM = 4;
    const MARGIN_TOP_MM = 4;

    /**
     * Convierte una medida en la unidad indicada ('mm', 'cm', 'in', 'px') a milímetros (mm).
     *
     * @param float $value Valor numérico
     * @param string $unit Unidad ('mm', 'cm', 'in', 'px')
     * @return float Valor en milímetros
     */
    public static function convertToMm(float $value, string $unit = 'mm'): float
    {
        $u = strtolower(trim($unit));
        switch ($u) {
            case 'cm':
                return $value * 10.0;
            case 'in':
            case 'inch':
            case 'pulgadas':
                return $value * 25.4;
            case 'px':
                // Estándar CSS / pantalla 96 DPI (1 in = 96 px = 25.4 mm)
                return $value * (25.4 / 96.0);
            case 'mm':
            default:
                return $value;
        }
    }

    /**
     * Resuelve y valida las opciones de dimensión de etiqueta enviadas por el usuario.
     *
     * @param array $options ['width' => float, 'height' => float, 'unit' => string, 'fit' => string]
     * @return array ['width_mm' => float, 'height_mm' => float, 'fit' => string]
     */
    public static function resolveDimensions(array $options = []): array
    {
        $unit = strtolower(trim((string)($options['unit'] ?? 'mm')));
        if (!in_array($unit, ['mm', 'cm', 'in', 'px'], true)) {
            $unit = 'mm';
        }

        $rawW = isset($options['width']) && is_numeric($options['width']) ? (float)$options['width'] : 0.0;
        $rawH = isset($options['height']) && is_numeric($options['height']) ? (float)$options['height'] : 0.0;

        $widthMm  = ($rawW > 0) ? self::convertToMm($rawW, $unit) : (float)self::LABEL_WIDTH_MM;
        $heightMm = ($rawH > 0) ? self::convertToMm($rawH, $unit) : (float)self::LABEL_HEIGHT_MM;

        // Límites de seguridad para impresoras térmicas y de etiquetas (25mm a 500mm)
        $widthMm  = max(25.0, min(500.0, round($widthMm, 2)));
        $heightMm = max(25.0, min(500.0, round($heightMm, 2)));

        $fit = strtolower(trim((string)($options['fit'] ?? 'contain')));
        if (!in_array($fit, ['contain', 'fill', 'adapt'], true)) {
            $fit = 'contain';
        }

        return [
            'width_mm'  => $widthMm,
            'height_mm' => $heightMm,
            'fit'       => $fit,
        ];
    }

    /**
     * Determina si un pedido corresponde al formato de etiqueta C807 (El Salvador).
     */
    private static function esPedidoC807(array $p): bool
    {
        $idCliente = (int)($p['id_cliente'] ?? 0);
        $idPais    = (int)($p['id_pais'] ?? 0);
        $paisIso   = strtoupper(trim($p['pais_iso'] ?? ''));
        $courier   = strtolower(trim($p['courier_service'] ?? ''));

        return !empty($p['c807_guia'])
            || ($idCliente === 42)
            || ($idPais === 7)
            || ($paisIso === 'SLV')
            || (strpos($courier, 'c807') !== false);
    }

    /**
     * Calcula el lienzo virtual (canvasW x canvasH) con la misma proporción de la página destino
     * para que el diseño se adapte al 100% del ancho y alto sin deformar fuentes ni códigos QR.
     */
    private static function resolveAdaptiveCanvas(array $p, float $targetW, float $targetH, string $fitMode): array
    {
        $esC807 = self::esPedidoC807($p);
        $baseW  = 80.0;
        $baseH  = $esC807 ? 130.5 : 136.0;

        if (!$esC807) {
            $numProd = !empty($p['productos']) && is_array($p['productos']) ? count($p['productos']) : 1;
            $dirLen  = mb_strlen(trim((string)($p['direccion'] ?? '')), 'UTF-8');
            $extraH  = max(0, $numProd - 1) * 4.8 + max(0, ceil($dirLen / 42) - 2) * 3.4;
            $baseH   = max(126.0, 124.0 + $extraH);
        }

        // Si el usuario eligió explícitamente "fill" (estirar directo), usamos el lienzo base y escalamos X/Y por separado
        if ($fitMode === 'fill') {
            return [
                'canvas_w' => $baseW,
                'canvas_h' => $baseH,
                'scale_x'  => $targetW / $baseW,
                'scale_y'  => $targetH / $baseH,
                'offset_x' => 0.0,
                'offset_y' => 0.0,
            ];
        }

        // Modo adaptativo ("contain" / "adapt"): el layout interno adopta la relación de aspecto exacta (targetW : targetH)
        // para ocupar el 100% de la etiqueta sin márgenes vacíos a los lados ni abajo, manteniendo escala 1:1 en QR y textos.
        $aspect = $targetW / $targetH;
        $baseAspect = $baseW / $baseH;

        if ($aspect >= $baseAspect) {
            // Página más ancha o cuadrada (ej. 4x6 in, 4x4 in, 10x15 cm, 6x4 in)
            $canvasH = $baseH;
            $canvasW = round($baseH * $aspect, 2);
        } else {
            // Página más angosta/alta (ej. 80x145 mm, 58x100 mm, 2.25x4 in)
            $canvasW = $baseW;
            $canvasH = round($baseW / $aspect, 2);
        }

        $scale = $targetW / $canvasW;

        return [
            'canvas_w' => $canvasW,
            'canvas_h' => $canvasH,
            'scale_x'  => $scale,
            'scale_y'  => $scale,
            'offset_x' => 0.0,
            'offset_y' => 0.0,
        ];
    }

    /**
     * Genera el PDF con las etiquetas térmicas para los pedidos proporcionados
     *
     * @param array $pedidos Array con los datos completos de los pedidos
     * @param string $dest 'I' (Inline navegador), 'D' (Descarga), 'S' (String binario)
     * @param array $options Opciones de tamaño ['width', 'height', 'unit' ('mm'|'cm'|'in'|'px'), 'fit' ('contain'|'fill')]
     * @return string|void
     */
    public static function generarPDF(array $pedidos, string $dest = 'I', array $options = [])
    {
        $dims = self::resolveDimensions($options);
        $targetW = $dims['width_mm'];
        $targetH = $dims['height_mm'];
        $fitMode = $dims['fit'];

        $orientation = ($targetW > $targetH) ? 'L' : 'P';

        // Instanciar FPDF con el tamaño físico exacto solicitado para la impresora
        $pdf = new PDF_Code128($orientation, 'mm', [$targetW, $targetH]);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(self::MARGIN_LEFT_MM, self::MARGIN_TOP_MM, self::MARGIN_RIGHT_MM);

        $totalPedidos = count($pedidos);

        foreach ($pedidos as $idx => $p) {
            $pdf->AddPage($orientation, [$targetW, $targetH]);

            $layout = self::resolveAdaptiveCanvas($p, $targetW, $targetH, $fitMode);
            $scaleX  = $layout['scale_x'];
            $scaleY  = $layout['scale_y'];
            $offsetX = $layout['offset_x'];
            $offsetY = $layout['offset_y'];
            $canvasW = $layout['canvas_w'];
            $canvasH = $layout['canvas_h'];

            $needsScale = (abs($scaleX - 1.0) > 0.001 || abs($scaleY - 1.0) > 0.001 || abs($offsetX) > 0.01 || abs($offsetY) > 0.01);

            if ($needsScale) {
                $pdf->startPageScale($scaleX, $scaleY, $offsetX, $offsetY);
            }
            self::renderEtiqueta($pdf, $p, $idx + 1, $totalPedidos, $canvasW, $canvasH);
            if ($needsScale) {
                $pdf->stopPageScale();
            }
        }

        $filename = 'Etiquetas_RutaEx_' . date('Ymd_His') . '.pdf';
        return $pdf->Output($dest, $filename);
    }

    /**
     * Renderiza una etiqueta individual adaptada dinámicamente al lienzo ($canvasW x $canvasH)
     */
    private static function renderEtiqueta(PDF_Code128 $pdf, array $p, int $indice = 1, int $total = 1, float $canvasW = 80.0, float $canvasH = 145.0)
    {
        if (self::esPedidoC807($p)) {
            self::renderEtiquetaC807($pdf, $p, $indice, $total, $canvasW, $canvasH);
            return;
        }

        $marginX = 3.5;
        $marginY = 3.5;
        $usableW = max(50.0, $canvasW - ($marginX * 2.0));
        $usableH = max(80.0, $canvasH - ($marginY * 2.0));
        $x0      = $marginX;
        $y       = $marginY;

        $wRatio   = $usableW / 72.0;
        $charMult = max(1.0, $wRatio);

        // Cargar productos temprano para calcular distribución vertical exacta
        if (empty($p['productos']) && !empty($p['id'])) {
            try {
                require_once __DIR__ . '/../modelo/conexion.php';
                $db = (new Conexion())->conectar();
                $stmtP = $db->prepare("
                    SELECT pp.cantidad, pr.nombre
                    FROM pedidos_productos pp
                    INNER JOIN productos pr ON pr.id = pp.id_producto
                    WHERE pp.id_pedido = :id
                    ORDER BY pp.id_producto ASC
                ");
                $stmtP->execute([':id' => (int)$p['id']]);
                $p['productos'] = $stmtP->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $p['productos'] = [];
            }
        }

        $productos = $p['productos'] ?? [];
        $numProd   = count($productos);
        $direccionTexto = trim($p['direccion'] ?? '');
        $charsPerLineDir = max(32, (int)round(38 * $charMult));
        $dirLines = max(1, (int)ceil(mb_strlen($direccionTexto, 'UTF-8') / $charsPerLineDir));
        $prodH = ($numProd > 0) ? ($numProd * (($numProd === 1) ? 5.7 : (($numProd === 2) ? 5.3 : 4.7))) : 5.5;

        // Estimación de altura de contenido base para repartir el espacio vertical sobrante de forma uniforme
        $estContentH = 11.0 + 18.5 + 12.5 + (8.0 + ($dirLines * 3.4) + 3.6 + $prodH) + 19.0 + 21.0 + 7.5;
        $extraSpace  = max(0.0, $usableH - ($estContentH + 24.0));
        $divPadBefore = 1.8 + ($extraSpace / 12.0);
        $divPadAfter  = 2.2 + ($extraSpace / 12.0);

        // Resetear estilos y colores por defecto para evitar arrastre de estados entre páginas
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetFillColor(0, 0, 0);
        $pdf->SetLineWidth(0.2);

        // ── 1. ENCABEZADO CORPORATIVO: LOGO "RutaEx | Latam" ─────────────────
        $pdf->SetFont('Arial', 'B', 13);
        $wRuta  = $pdf->GetStringWidth('Ruta');
        $wEx    = $pdf->GetStringWidth('Ex');
        $pdf->SetFont('Arial', '', 11);
        $wPipe  = $pdf->GetStringWidth(' | ');
        $wLatam = $pdf->GetStringWidth('Latam');
        $totalLogoW = $wRuta + $wEx + $wPipe + $wLatam;

        $logoX = $x0 + (($usableW - $totalLogoW) / 2);
        
        $pdf->SetXY($logoX, $y);
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Write(5.5, 'Ruta');

        $pdf->SetFont('Arial', 'B', 13);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Write(5.5, 'Ex');

        $pdf->SetFont('Arial', '', 11);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->Write(5.5, ' | ');

        $pdf->SetFont('Arial', '', 13);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Write(5.5, 'Latam');

        $pdf->SetTextColor(0, 0, 0);
        $y += 6.5;

        $pdf->SetXY($x0, $y);
        $pdf->SetFont('Arial', '', 7.5);
        $pdf->Cell($usableW, 3.5, self::utf8('COMPROBANTE DE ENTREGA Y GUÍA'), 0, 1, 'C');
        $y += 3.5 + $divPadBefore;

        // Línea divisoria punteada
        self::dibujarLineaDivisoria($pdf, $x0, $y, $usableW);
        $y += $divPadAfter;

        // ── 2. CÓDIGO DE BARRAS (Code 128) + NÚMERO DE ORDEN ─────────────────
        $numOrden = !empty($p['numero_orden']) ? (string)$p['numero_orden'] : 'ORD-' . $p['id'];
        
        // Dibujar barras Code 128 adaptado al ancho disponible
        $bcW = min($usableW - 4.0, max(64.0, round($usableW * 0.84, 1)));
        $bcX = $x0 + (($usableW - $bcW) / 2);
        $pdf->Code128($bcX, $y, $numOrden, $bcW, 10);
        $y += 11;

        // Número de orden legible en negrita
        $pdf->SetXY($x0, $y);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($usableW, 4.5, $numOrden, 0, 1, 'C');
        $y += 4.5;

        // Subtítulo de tracking si existe
        if (!empty($p['numero_traking']) && $p['numero_traking'] !== $numOrden) {
            $pdf->SetXY($x0, $y);
            $pdf->SetFont('Arial', '', 7);
            $pdf->Cell($usableW, 3, 'Tracking: ' . $p['numero_traking'], 0, 1, 'C');
            $y += 3.2;
        } elseif (!empty($p['c807_guia'])) {
            $pdf->SetXY($x0, $y);
            $pdf->SetFont('Arial', 'B', 7.5);
            $pdf->Cell($usableW, 3, self::utf8('Guía C807: ' . $p['c807_guia']), 0, 1, 'C');
            $y += 3.2;
        } elseif (((int)($p['id_cliente'] ?? 0)) === 42) {
            $pdf->SetXY($x0, $y);
            $pdf->SetFont('Arial', '', 7);
            $pdf->Cell($usableW, 3, 'Ref: EXLATA-' . $numOrden, 0, 1, 'C');
            $y += 3.2;
        }

        $y += $divPadBefore;
        self::dibujarLineaDivisoria($pdf, $x0, $y, $usableW);
        $y += $divPadAfter;

        // ── 3. REMITENTE Y DESTINATARIO ──────────────────────────────────────
        $remitente = !empty($p['remitente_nombre']) ? $p['remitente_nombre'] : (!empty($p['proveedor_nombre']) ? $p['proveedor_nombre'] : 'RutaEx Cliente');
        $destinatario = !empty($p['destinatario']) ? $p['destinatario'] : 'Consignatario Final';
        $telefono = !empty($p['telefono']) ? $p['telefono'] : 'N/D';

        // Remitente
        $pdf->SetXY($x0, $y);
        $pdf->SetFont('Arial', 'B', 7.5);
        $pdf->Cell(20, 3.8, 'REMITENTE:', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 7.5);
        $pdf->Cell($usableW - 20, 3.8, self::utf8(self::truncar($remitente, (int)round(35 * $charMult))), 0, 1, 'L');
        $y += 4;

        // Destinatario
        $pdf->SetXY($x0, $y);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(25, 4, 'DESTINATARIO: ', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell($usableW - 25, 4, self::utf8(self::truncar($destinatario, (int)round(30 * $charMult))), 0, 1, 'L');
        $y += 4.5;

        // Teléfono
        $pdf->SetXY($x0, $y);
        $pdf->SetFont('Arial', 'B', 7.5);
        $pdf->Cell(20, 3.8, self::utf8('TELÉFONO: '), 0, 0, 'L');
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell($usableW - 20, 3.8, $telefono, 0, 1, 'L');
        $y += 3.8 + $divPadBefore;

        self::dibujarLineaDivisoria($pdf, $x0, $y, $usableW);
        $y += $divPadAfter;

        // ── 4. DIRECCIÓN COMPLETA Y PRODUCTOS ASIGNADOS ──────────────────────
        $pdf->SetXY($x0, $y);
        $pdf->SetFont('Arial', 'B', 7.5);
        $pdf->Cell($usableW, 3.5, self::utf8('DIRECCIÓN DE ENTREGA:'), 0, 1, 'L');
        $y += 3.8;
        
        // Ubicación geográfica complementaria
        $geoParts = [];
        if (!empty($p['departamento_nombre'])) $geoParts[] = $p['departamento_nombre'];
        if (!empty($p['municipio_nombre']))    $geoParts[] = $p['municipio_nombre'];
        if (!empty($p['barrio_nombre']))       $geoParts[] = $p['barrio_nombre'];
        if (!empty($p['codigo_postal']))       $geoParts[] = 'CP: ' . $p['codigo_postal'];

        if (!empty($geoParts)) {
            $pdf->SetXY($x0, $y);
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->Cell($usableW, 3.5, self::utf8(implode(' | ', $geoParts)), 0, 1, 'L');
            $y += 3.8;
        }

        // Dirección texto principal
        $pdf->SetXY($x0, $y);
        $pdf->SetFont('Arial', '', 7.5);
        $pdf->MultiCell($usableW, 3.4, self::utf8($direccionTexto), 0, 'L');
        $y = $pdf->GetY() + 1.5;

        // ── 4.1 PRODUCTOS Y CANTIDADES ASIGNADAS ─────────────────────────────
        $pdf->SetXY($x0, $y);
        $pdf->SetFont('Arial', 'B', 6.5);
        $pdf->SetTextColor(70, 70, 70);
        $pdf->Cell($usableW, 3.2, self::utf8('PRODUCTOS ASIGNADOS:'), 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);
        $y += 3.6;

        if (!empty($productos)) {
            // Ajustar dinámicamente el tamaño según cantidad de ítems para máxima legibilidad
            if ($numProd === 1) {
                $baseFontCant = 12.5;
                $baseFontProd = 11.5;
                $lineH = 5.2;
            } elseif ($numProd === 2) {
                $baseFontCant = 11.5;
                $baseFontProd = 10.5;
                $lineH = 4.8;
            } else {
                $baseFontCant = 10;
                $baseFontProd = 9;
                $lineH = 4.2;
            }

            foreach ($productos as $prod) {
                $cant = intval($prod['cantidad'] ?? 1);
                $nombreProd = trim($prod['nombre'] ?? 'Producto');
                $cantText = $cant . 'x';

                $pdf->SetXY($x0 + 1, $y);
                $pdf->SetFont('Arial', 'B', $baseFontCant);
                $wCant = $pdf->GetStringWidth($cantText . ' ');
                $pdf->Cell($wCant, $lineH, $cantText . ' ', 0, 0, 'L');

                $fontProd = $baseFontProd;
                $pdf->SetFont('Arial', 'B', $fontProd);
                $maxWProd = $usableW - $wCant - 2;

                // Reducir gradualmente la fuente si el nombre es extenso para evitar desbordes
                while ($fontProd > 7.5 && $pdf->GetStringWidth(self::utf8($nombreProd)) > $maxWProd) {
                    $fontProd -= 0.5;
                    $pdf->SetFont('Arial', 'B', $fontProd);
                }

                $pdf->Cell($maxWProd, $lineH, self::utf8(self::truncar($nombreProd, (int)round(45 * $charMult))), 0, 1, 'L');
                $y += $lineH + 0.5;
            }
        } elseif (!empty($p['observaciones_combo'])) {
            $comboText = trim($p['observaciones_combo']);
            $pdf->SetXY($x0 + 1, $y);
            $fontProd = 10.5;
            $pdf->SetFont('Arial', 'B', $fontProd);
            $maxW = $usableW - 2;
            while ($fontProd > 7.5 && $pdf->GetStringWidth(self::utf8($comboText)) > $maxW) {
                $fontProd -= 0.5;
                $pdf->SetFont('Arial', 'B', $fontProd);
            }
            $pdf->Cell($maxW, 5.0, self::utf8(self::truncar($comboText, (int)round(45 * $charMult))), 0, 1, 'L');
            $y += 5.5;
        } else {
            $pdf->SetXY($x0 + 1, $y);
            $pdf->SetFont('Arial', 'B', 12.0);
            $pdf->Cell(8, 5.0, '1x ', 0, 0, 'L');
            $pdf->SetFont('Arial', 'B', 10.5);
            $pdf->Cell($usableW - 10, 5.0, self::utf8('Paquete estándar'), 0, 1, 'L');
            $y += 5.5;
        }

        $y += $divPadBefore;
        self::dibujarLineaDivisoria($pdf, $x0, $y, $usableW);
        $y += $divPadAfter;

        // ── 5. FECHA DE ENTREGA Y MONTO A COBRAR (COD) ────────────────────────
        $fechaEntrega = !empty($p['fecha_entrega']) ? date('d/m/Y', strtotime($p['fecha_entrega'])) : date('d/m/Y');
        
        $pdf->SetXY($x0, $y);
        $pdf->SetFont('Arial', 'B', 7.5);
        $pdf->Cell(25, 3.8, 'FECHA ENTREGA:', 0, 0, 'L');
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell($usableW - 25, 3.8, $fechaEntrega, 0, 1, 'L');
        $y += 4.5;

        // Moneda y Monto por País (Código ISO: CRC, USD, NIO, GTQ, COP, MXN, HNL, etc.)
        $monedaCod = strtoupper(trim($p['moneda_codigo'] ?? ''));
        $idPais = (int)($p['id_pais'] ?? 0);
        if (empty($monedaCod) || in_array($monedaCod, ['NI', 'NIC'])) {
            $monedaCod = ($idPais === 1) ? 'NIO' : (($idPais === 2) ? 'CRC' : (($idPais === 6 || $idPais === 10) ? 'GTQ' : (($idPais === 3) ? 'COP' : 'CRC')));
        } elseif (in_array($monedaCod, ['CR', 'CRI'])) {
            $monedaCod = 'CRC';
        } elseif (in_array($monedaCod, ['GUAT', 'GUATL', 'GT'])) {
            $monedaCod = 'GTQ';
        } elseif (in_array($monedaCod, ['CO', 'COL'])) {
            $monedaCod = 'COP';
        } elseif (in_array($monedaCod, ['SLV', 'SV', 'PAN', 'PA', 'EC'])) {
            $monedaCod = 'USD';
        } elseif (in_array($monedaCod, ['MX', 'MEX'])) {
            $monedaCod = 'MXN';
        } elseif (in_array($monedaCod, ['HND', 'HN'])) {
            $monedaCod = 'HNL';
        } elseif (in_array($monedaCod, ['URY', 'UY'])) {
            $monedaCod = 'UYU';
        } elseif (in_array($monedaCod, ['ARS', 'AR'])) {
            $monedaCod = 'ARS';
        }
        if (empty($monedaCod)) $monedaCod = 'CRC';

        $totalCobrar = floatval($p['precio_total_local'] ?? ($p['precio_local'] ?? 0));

        $pdf->SetXY($x0, $y);
        $pdf->SetFont('Arial', 'B', 7.5);
        $pdf->Cell($usableW, 3.5, self::utf8('MONTO A COBRAR (PAGO CONTRA ENTREGA):'), 0, 1, 'L');
        $y += 4;

        // Recuadro destacado para el Monto
        $pdf->SetXY($x0, $y);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->Rect($x0, $y, $usableW, 8.5, 'DF');
        $pdf->SetFillColor(0, 0, 0); // Restaurar inmediatamente a negro

        $pdf->SetXY($x0, $y + 1);
        $pdf->SetFont('Arial', 'B', 12);
        
        if ($totalCobrar > 0) {
            $montoTexto = $monedaCod . ' ' . number_format($totalCobrar, 2, '.', ',');
        } else {
            $montoTexto = self::utf8('PAGADO / SIN COBRO (' . $monedaCod . ' 0.00)');
        }
        $pdf->Cell($usableW, 6.5, $montoTexto, 0, 1, 'C');
        $y += 8.5 + $divPadBefore;

        self::dibujarLineaDivisoria($pdf, $x0, $y, $usableW);
        $y += $divPadAfter;

        // ── 6. CÓDIGO QR Y RASTREO ───────────────────────────────────────────
        $qrSize = 20; // 20x20 mm
        $qrX = $x0 + 2;
        $qrY = $y;

        // Determinar contenido del QR:
        $idCliente = (int)($p['id_cliente'] ?? 0);
        $esClienteC807 = ($idCliente === 42) || !empty($p['c807_guia']);

        if ($esClienteC807) {
            // Texto plano para pistolas y escáneres de bodega (C807 / RutaEx)
            $qrData = !empty($p['c807_guia']) ? (string)$p['c807_guia'] : ('EXLATA-' . $numOrden);
            $textoGuiaVisible = $qrData;
        } else {
            $qrData = 'https://rutaex.com/';
            $textoGuiaVisible = null;
        }

        // Generar QR temporal con texto plano
        $tempQrFile = self::generarImagenQR($qrData);

        if ($tempQrFile && file_exists($tempQrFile)) {
            $pdf->Image($tempQrFile, $qrX, $qrY, $qrSize, $qrSize, 'PNG');
            @unlink($tempQrFile);
        }

        // Texto junto al QR
        $infoX = $qrX + $qrSize + 3;
        $infoW = $usableW - ($qrSize + 5);
        
        $pdf->SetXY($infoX, $qrY + 1.2);
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->Cell($infoW, 3.2, self::utf8('ESCANEA PARA'), 0, 1, 'L');

        $pdf->SetXY($infoX, $qrY + 4.4);
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->Cell($infoW, 3.2, self::utf8('RASTREAR PEDIDO'), 0, 1, 'L');

        if ($textoGuiaVisible) {
            $pdf->SetXY($infoX, $qrY + 8.0);
            $pdf->SetFont('Arial', 'B', 6.2);
            $pdf->Cell($infoW, 3, self::utf8('GUIA: ' . self::truncar($textoGuiaVisible, (int)round(18 * $charMult))), 0, 1, 'L');
        }

        $pdf->SetXY($infoX, $qrY + 11.5);
        $pdf->SetFont('Arial', 'I', 6.5);
        $pdf->Cell($infoW, 3, self::utf8('Etiqueta ' . $indice . ' de ' . $total), 0, 1, 'L');

        $y += $qrSize + $divPadBefore;

        // ── 7. PIE DE PÁGINA INSTITUCIONAL ───────────────────────────────────
        self::dibujarLineaDivisoria($pdf, $x0, $y, $usableW);
        $y += 2;

        $pdf->SetXY($x0, $y);
        $pdf->SetFont('Arial', 'B', 7.5);
        $pdf->Cell($usableW, 3.5, '* GRACIAS POR SU COMPRA *', 0, 1, 'C');
        $y += 3.8;

        $pdf->SetXY($x0, $y);
        $pdf->SetFont('Arial', '', 7);
        $pdf->Cell($usableW, 3.2, 'https://rutaex.com/', 0, 1, 'C');
    }

    /**
     * Dibuja una línea punteada/discontinua térmica para separación nítida
     */
    private static function dibujarLineaDivisoria(PDF_Code128 $pdf, float $x, float $y, float $w)
    {
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.2);
        
        // Simular línea discontinua con pequeños guiones
        $dashLen = 1.5;
        $gapLen = 1.0;
        $curX = $x;
        
        while ($curX < ($x + $w)) {
            $drawTo = min($curX + $dashLen, $x + $w);
            $pdf->Line($curX, $y, $drawTo, $y);
            $curX += $dashLen + $gapLen;
        }
    }

    /**
     * Obtiene un directorio temporal con permisos de escritura garantizados para Apache y CLI
     */
    private static function obtenerDirectorioTemporal(): string
    {
        $candidatos = [
            '/Applications/XAMPP/xamppfiles/temp',
            '/private/tmp',
            '/tmp',
            sys_get_temp_dir(),
        ];

        foreach ($candidatos as $dir) {
            if (!empty($dir) && is_dir($dir) && is_writable($dir)) {
                return rtrim($dir, '/\\');
            }
        }

        $local = __DIR__ . '/../temp';
        if (!is_dir($local)) {
            @mkdir($local, 0777, true);
        }
        return is_writable($local) ? $local : sys_get_temp_dir();
    }

    /**
     * Genera un archivo temporal PNG con el código QR usando chillerlan/php-qrcode
     */
    private static function generarImagenQR(string $data): ?string
    {
        try {
            $options = new QROptions([
                'version'      => QRCode::VERSION_AUTO,
                'outputType'   => QRCode::OUTPUT_IMAGE_PNG,
                'eccLevel'     => QRCode::ECC_M,
                'scale'        => 8,
                'imageBase64'  => false,
                'quietzoneSize'=> 2,
            ]);

            $qrcode = new QRCode($options);
            $rawImage = $qrcode->render($data);

            $tempDir = self::obtenerDirectorioTemporal();
            $tempPath = $tempDir . '/qr_3nstar_' . bin2hex(random_bytes(8)) . '.png';
            if (file_put_contents($tempPath, $rawImage) === false) {
                error_log("ThermalLabelService::generarImagenQR: no se pudo escribir en {$tempPath}");
                return null;
            }

            return $tempPath;
        } catch (\Throwable $e) {
            error_log('Error generando QR térmica: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Trunca un texto a longitud máxima para evitar desbordes térmicos
     */
    private static function truncar(string $texto, int $max = 35): string
    {
        if (mb_strlen($texto, 'UTF-8') <= $max) {
            return $texto;
        }
        return mb_substr($texto, 0, $max - 3, 'UTF-8') . '...';
    }

    /**
     * Cache de códigos de departamento C807
     */
    private static $c807DeptosCache = null;

    /**
     * Resuelve el código de 3 letras de C807 para el departamento (ej. SLV, AHU, etc.)
     */
    private static function getC807DeptoCodigo(array $p): string
    {
        if (self::$c807DeptosCache === null) {
            self::$c807DeptosCache = [
                'sansalvador'  => 'SLV',
                'ahuachapan'   => 'AHU',
                'cabanas'      => 'CAB',
                'chalatenango' => 'CHA',
                'cuscatlan'    => 'CUS',
                'lalibertad'   => 'LIB',
                'lapaz'        => 'PAZ',
                'launion'      => 'UNI',
                'morazan'      => 'MOR',
                'sanmiguel'    => 'MIG',
                'sanvicente'   => 'VIC',
                'santaana'     => 'ANA',
                'sonsonate'    => 'SON',
                'usulutan'     => 'USU',
            ];
            try {
                require_once __DIR__ . '/../modelo/conexion.php';
                $db = (new Conexion())->conectar();
                $rows = $db->query("SELECT nombre, codigo FROM c807_departamentos WHERE codigo IS NOT NULL AND codigo != ''")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $r) {
                    $norm = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $r['nombre'])));
                    self::$c807DeptosCache[$norm] = strtoupper(trim($r['codigo']));
                }
            } catch (\Throwable $e) {}
        }

        $depNombre = trim($p['departamento_nombre'] ?? $p['departmentName'] ?? '');
        $norm = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $depNombre)));
        return self::$c807DeptosCache[$norm] ?? 'SLV';
    }

    private static $c807EstacionCache = null;

    /**
     * Resuelve el código de estación/Hub (ej. HAHU, PLL, HTOR) según el municipio/distrito del pedido
     */
    private static function getC807EstacionCodigo(array $p): string
    {
        if (self::$c807EstacionCache === null) {
            self::$c807EstacionCache = [];
            try {
                require_once __DIR__ . '/../modelo/conexion.php';
                $db = (new Conexion())->conectar();
                $rows = $db->query("
                    SELECT m.nombre, m.codigo_estacion, d.nombre AS dep_nombre
                    FROM c807_municipios m
                    INNER JOIN c807_departamentos d ON d.id = m.c807_departamento_id
                    WHERE m.codigo_estacion IS NOT NULL AND m.codigo_estacion != ''
                ")->fetchAll(PDO::FETCH_ASSOC);

                foreach ($rows as $r) {
                    $normMun = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $r['nombre'])));
                    $normDep = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $r['dep_nombre'])));
                    self::$c807EstacionCache[$normDep . '|' . $normMun] = strtoupper(trim($r['codigo_estacion']));
                    self::$c807EstacionCache[$normMun] = strtoupper(trim($r['codigo_estacion']));
                }
            } catch (\Throwable $e) {}
        }

        $depNombre = trim($p['departamento_nombre'] ?? $p['departmentName'] ?? '');
        $munNombre = trim($p['municipio_nombre'] ?? $p['municipalitiesName'] ?? '');
        $normDep = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $depNombre)));
        $normMun = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $munNombre)));

        return self::$c807EstacionCache[$normDep . '|' . $normMun] 
            ?? self::$c807EstacionCache[$normMun] 
            ?? 'PLL';
    }

    /**
     * Renderiza una etiqueta individual adaptada al formato de C807 Express con marca Rutaex-latam
     */
    private static function renderEtiquetaC807(PDF_Code128 $pdf, array $p, int $indice = 1, int $total = 1, float $canvasW = 80.0, float $canvasH = 130.5)
    {
        $marginX = 3.0;
        $marginY = 3.0;
        $usableW = max(50.0, $canvasW - ($marginX * 2.0));
        $usableH = max(80.0, $canvasH - ($marginY * 2.0));
        $x0 = $marginX;
        $y0 = $marginY;

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetFillColor(0, 0, 0);
        $pdf->SetLineWidth(0.25);

        // Distribución horizontal adaptativa
        $colLeftW  = round(max(24.0, min(34.0, $usableW * 0.31)), 2);
        $colRightW = round($usableW - $colLeftW, 2);
        $charMult  = max(1.0, $colRightW / 48.0);

        // Distribución vertical adaptativa (base: 75 + 9 + 34 + 6.5 = 124.5)
        $vScale = $usableH / 124.5;
        $midH   = round(9.0 * min(1.15, $vScale), 2);
        $footH  = round(6.5 * min(1.15, $vScale), 2);
        $remH   = $usableH - $midH - $footH;
        $topH   = round($remH * (75.0 / 109.0), 2);
        $botH   = round($remH - $topH, 2);
        $kTop   = $topH / 75.0;

        // Datos del pedido
        $numOrden      = !empty($p['numero_orden']) ? (string)$p['numero_orden'] : 'ORD-' . ($p['id'] ?? 0);
        $numGuia       = !empty($p['c807_guia']) ? (string)$p['c807_guia'] : (!empty($p['numero_traking']) ? (string)$p['numero_traking'] : ('EXLATA-' . $numOrden));
        $remitente     = !empty($p['remitente_nombre']) ? $p['remitente_nombre'] : (!empty($p['proveedor_nombre']) ? $p['proveedor_nombre'] : 'RutaEx Cliente');
        $remitenteTel  = !empty($p['remitente_telefono']) ? $p['remitente_telefono'] : (!empty($p['telefono_remitente']) ? $p['telefono_remitente'] : '22222222');
        $destinatario  = !empty($p['destinatario']) ? $p['destinatario'] : 'Consignatario Final';
        $telefono      = !empty($p['telefono']) ? $p['telefono'] : 'N/D';
        $contacto      = !empty($p['contacto']) ? $p['contacto'] : '';
        $direccion     = trim($p['direccion'] ?? '');

        // Referencia
        $refPartes = array_filter([
            trim($p['Location'] ?? ''),
            trim($p['betweenStreets'] ?? ''),
            trim($p['zona'] ?? ''),
        ], fn($s) => $s !== '');
        $referencia = implode(' - ', $refPartes);

        // Cargar productos si no vienen en el array
        if (empty($p['productos']) && !empty($p['id'])) {
            try {
                require_once __DIR__ . '/../modelo/conexion.php';
                $db = (new Conexion())->conectar();
                $stmtP = $db->prepare("
                    SELECT pp.cantidad, pr.nombre
                    FROM pedidos_productos pp
                    INNER JOIN productos pr ON pr.id = pp.id_producto
                    WHERE pp.id_pedido = :id
                    ORDER BY pp.id_producto ASC
                ");
                $stmtP->execute([':id' => (int)$p['id']]);
                $p['productos'] = $stmtP->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                $p['productos'] = [];
            }
        }

        $itemsArr = [];
        if (!empty($p['productos'])) {
            foreach ($p['productos'] as $prod) {
                $cant = intval($prod['cantidad'] ?? 1);
                $nom  = trim($prod['nombre'] ?? 'Item');
                $itemsArr[] = "{$cant}x {$nom}";
            }
        } elseif (!empty($p['observaciones_combo'])) {
            $itemsArr[] = trim($p['observaciones_combo']);
        } else {
            $itemsArr[] = 'Paquete de mercadería';
        }
        $contenido = implode('; ', $itemsArr);

        // Parámetros C807
        $estacion      = self::getC807EstacionCodigo($p);
        $codigoDepto   = self::getC807DeptoCodigo($p);
        $distrito      = strtoupper(trim($p['municipio_nombre'] ?? $p['municipalitiesName'] ?? 'SAN SALVADOR'));
        $totalCobrar   = floatval($p['precio_total_local'] ?? ($p['precio_local'] ?? 0));
        $tipoServicio  = !empty($p['tipo_servicio']) ? strtoupper(trim($p['tipo_servicio'])) : (($totalCobrar > 0) ? 'CCE' : 'SER');
        $diasCobertura = 'L M X J V S';
        $pago          = ($tipoServicio === 'CCE') ? ('Cobro ($' . number_format($totalCobrar, 2, '.', ',') . ')') : 'Crédito';
        
        $pesoVal       = floatval($p['peso'] ?? 0);
        if ($pesoVal <= 0) $pesoVal = 1.80;
        $pesoUnidad    = !empty($p['unidad_peso']) ? strtoupper($p['unidad_peso']) : 'LB';
        $pesoTexto     = number_format($pesoVal, 2, '.', ',') . ' ' . $pesoUnidad;
        $pesoMaxTexto  = '30.00 LB';
        $tipoEntrega   = 'NRML';
        $especiales    = trim($p['comentario'] ?? '');

        $bultosTotal   = max(1, intval($p['bultos'] ?? 1));
        $bultosTexto   = "{$indice} de {$bultosTotal}";

        // Generar QR (el mismo para superior e inferior, codificando el número de guía)
        $tempQr = self::generarImagenQR($numGuia);

        // Alturas proporcionales de las 7 celdas de la columna izquierda
        $hLogo = round(11.0 * $kTop, 2);
        $hQr1  = round(24.0 * $kTop, 2);
        $hEst  = round(8.0 * $kTop, 2);
        $hDep  = round(8.0 * $kTop, 2);
        $hDis  = round(10.0 * $kTop, 2);
        $hSrv  = round(8.0 * $kTop, 2);
        $hDia  = round($topH - ($hLogo + $hQr1 + $hEst + $hDep + $hDis + $hSrv), 2);

        $yL = $y0;

        // ── 1. COLUMNA IZQUIERDA ─────────────────────────────────────────
        // 1.1 Logo "Rutaex-latam"
        $pdf->Rect($x0, $yL, $colLeftW, $hLogo);
        $pdf->SetXY($x0, $yL + ($hLogo * 0.18));
        $pdf->SetFont('Arial', 'B', 8.5);
        $pdf->Cell($colLeftW, 4, 'RutaEx', 0, 1, 'C');
        $pdf->SetXY($x0, $yL + ($hLogo * 0.53));
        $pdf->SetFont('Arial', '', 6.5);
        $pdf->Cell($colLeftW, 3, '- latam -', 0, 1, 'C');
        $yL += $hLogo;

        // 1.2 QR Superior
        $pdf->Rect($x0, $yL, $colLeftW, $hQr1);
        if ($tempQr && file_exists($tempQr)) {
            $qr1Size = min($colLeftW - 3.6, $hQr1 - 3.6);
            $qr1X = $x0 + ($colLeftW - $qr1Size) / 2.0;
            $qr1Y = $yL + ($hQr1 - $qr1Size) / 2.0;
            $pdf->Image($tempQr, $qr1X, $qr1Y, $qr1Size, $qr1Size, 'PNG');
        }
        $yL += $hQr1;

        // 1.3 Estación
        $pdf->Rect($x0, $yL, $colLeftW, $hEst);
        $pdf->SetXY($x0, $yL + (($hEst - 5.5) / 2.0));
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell($colLeftW, 5.5, $estacion, 0, 1, 'C');
        $yL += $hEst;

        // 1.4 Código Departamento
        $pdf->Rect($x0, $yL, $colLeftW, $hDep);
        $pdf->SetXY($x0, $yL + (($hDep - 5.5) / 2.0));
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell($colLeftW, 5.5, $codigoDepto, 0, 1, 'C');
        $yL += $hDep;

        // 1.5 Distrito
        $pdf->Rect($x0, $yL, $colLeftW, $hDis);
        $pdf->SetXY($x0 + 0.5, $yL + 1.2);
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->MultiCell($colLeftW - 1, 3.2, self::utf8($distrito), 0, 'C');
        $yL += $hDis;

        // 1.6 Tipo de Servicio
        $pdf->Rect($x0, $yL, $colLeftW, $hSrv);
        $pdf->SetXY($x0, $yL + (($hSrv - 5.5) / 2.0));
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell($colLeftW, 5.5, $tipoServicio, 0, 1, 'C');
        $yL += $hSrv;

        // 1.7 Días Cobertura
        $pdf->Rect($x0, $yL, $colLeftW, $hDia);
        $pdf->SetXY($x0, $yL + (($hDia - 4.0) / 2.0));
        $pdf->SetFont('Arial', 'B', 6.8);
        $pdf->Cell($colLeftW, 4, $diasCobertura, 0, 1, 'C');

        // ── 2. COLUMNA DERECHA ───────────────────────────────────────────
        $xR = $x0 + $colLeftW;
        $pdf->Rect($xR, $y0, $colRightW, $topH);

        // 2.1 Remitente
        $pdf->Line($xR, $y0 + $hLogo, $xR + $colRightW, $y0 + $hLogo);
        $pdf->SetXY($xR + 1.5, $y0 + ($hLogo * 0.16));
        $fontRem = (mb_strlen($remitente) > (int)round(30 * $charMult)) ? 6.2 : 7.2;
        $pdf->SetFont('Arial', 'B', $fontRem);
        $pdf->Cell($colRightW - 3, 3.5, self::utf8(self::truncar($remitente, (int)round(38 * $charMult))), 0, 1, 'L');
        $pdf->SetXY($xR + 1.5, $y0 + ($hLogo * 0.52));
        $pdf->SetFont('Arial', '', 6.8);
        $pdf->Cell(13, 3.5, self::utf8('Teléfono: '), 0, 0, 'L');
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->Cell($colRightW - 16, 3.5, $remitenteTel, 0, 1, 'L');

        // 2.2 Bloque Destino
        $yDest = $y0 + $hLogo + 1.2;
        $pdf->SetXY($xR + 1.5, $yDest);
        $pdf->SetFont('Arial', 'B', 7.8);
        $pdf->Cell($colRightW - 3, 3.2, 'Destino:', 0, 1, 'L');

        $pdf->SetXY($xR + 1.5, $yDest + 3.6);
        $pdf->SetFont('Arial', 'B', 6.2);
        $pdf->Cell(12, 3, 'Nombre:', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 6.2);
        $pdf->Cell($colRightW - 15, 3, self::utf8(self::truncar($destinatario, (int)round(30 * $charMult))), 0, 1, 'L');

        $pdf->SetXY($xR + 1.5, $yDest + 6.8);
        $pdf->SetFont('Arial', 'B', 6.2);
        $pdf->Cell(12, 3, self::utf8('Teléfono:'), 0, 0, 'L');
        $pdf->SetFont('Arial', 'B', 6.5);
        $pdf->Cell($colRightW - 15, 3, $telefono, 0, 1, 'L');

        $pdf->SetXY($xR + 1.5, $yDest + 10.0);
        $pdf->SetFont('Arial', 'B', 6.2);
        $pdf->Cell(12, 3, 'Contacto:', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 6.2);
        $pdf->Cell($colRightW - 15, 3, self::utf8(self::truncar($contacto, (int)round(30 * $charMult))), 0, 1, 'L');

        $pdf->SetXY($xR + 1.5, $yDest + 13.2);
        $pdf->SetFont('Arial', 'B', 6.2);
        $pdf->Cell($colRightW - 3, 2.8, self::utf8('Dirección:'), 0, 1, 'L');
        $pdf->SetXY($xR + 1.5, $yDest + 16.0);
        $pdf->SetFont('Arial', '', 5.8);
        $pdf->MultiCell($colRightW - 3, 2.6, self::utf8(self::truncar($direccion, (int)round(115 * $charMult))), 0, 'L');

        $curY = max($pdf->GetY() + 0.5, $y0 + (40.5 * $kTop));
        $pdf->SetXY($xR + 1.5, $curY);
        $pdf->SetFont('Arial', 'B', 6.2);
        $pdf->Cell($colRightW - 3, 2.8, 'Referencia:', 0, 1, 'L');
        $curY = $pdf->GetY();
        $pdf->SetXY($xR + 1.5, $curY);
        $pdf->SetFont('Arial', '', 5.8);
        $pdf->MultiCell($colRightW - 3, 2.5, self::utf8(self::truncar($referencia, (int)round(70 * $charMult))), 0, 'L');

        $curY = max($pdf->GetY() + 0.5, $y0 + (51.5 * $kTop));
        $pdf->SetXY($xR + 1.5, $curY);
        $pdf->SetFont('Arial', 'B', 6.2);
        $pdf->Cell($colRightW - 3, 2.8, 'Contenido:', 0, 1, 'L');
        $curY = $pdf->GetY();
        $pdf->SetXY($xR + 1.5, $curY);
        $pdf->SetFont('Arial', '', 5.4);
        $pdf->MultiCell($colRightW - 3, 2.4, self::utf8(self::truncar($contenido, (int)round(125 * $charMult))), 0, 'L');

        $curY = max($pdf->GetY() + 0.5, $y0 + $topH - 4.8);
        $pdf->SetXY($xR + 1.5, $curY);
        $pdf->SetFont('Arial', 'B', 6.5);
        $pdf->Cell(14, 3.2, 'No. Orden:', 0, 0, 'L');
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->Cell($colRightW - 17, 3.2, $numOrden, 0, 1, 'L');

        // ── 3. FRANJA CENTRAL: NÚMERO DE GUÍA ────────────────────────────
        $yMid = $y0 + $topH;
        $pdf->Rect($x0, $yMid, $usableW, $midH);

        // Reservar espacio a la derecha para bultos (15 mm) y dejar margen de separación
        $maxWGuia = $usableW - 18.0;
        $fontGuia = min(13.5, 11.5 * max(1.0, min(1.2, $usableW / 72.0)));
        $pdf->SetFont('Arial', 'B', $fontGuia);
        while ($fontGuia > 7.0 && $pdf->GetStringWidth($numGuia) > $maxWGuia) {
            $fontGuia -= 0.3;
            $pdf->SetFont('Arial', 'B', $fontGuia);
        }

        $yMidText = $yMid + (($midH - 6.0) / 2.0);
        $pdf->SetXY($x0 + 1.5, $yMidText);
        $pdf->Cell($maxWGuia, 6, $numGuia, 0, 0, 'L');

        // Bultos alineado a la derecha con espacio garantizado
        $pdf->SetXY($x0 + $usableW - 15.0, $yMidText);
        $pdf->SetFont('Arial', 'B', 8.5);
        $pdf->Cell(13.5, 6, $bultosTexto, 0, 1, 'R');

        // ── 4. BLOQUE INFERIOR ───────────────────────────────────────────
        $yBot = $yMid + $midH;
        $wBotRight = round(max(25.0, min(36.0, $usableW * 0.33)), 2);
        $wBotLeft  = round($usableW - $wBotRight, 2);
        $botCharMult = max(1.0, $wBotLeft / 47.0);
        $kBot = $botH / 34.0;

        // 4.1 Sub-bloque datos comerciales y peso
        $pdf->Rect($x0, $yBot, $wBotLeft, $botH);

        $pdf->SetXY($x0 + 1.5, $yBot + (2.0 * $kBot));
        $pdf->SetFont('Arial', 'B', 7.5);
        $pdf->Cell(11, 3.8, 'PAGO:', 0, 0, 'L');
        $pdf->SetFont('Arial', 'B', 7.5);
        $pdf->Cell($wBotLeft - 13, 3.8, self::utf8($pago), 0, 1, 'L');

        $pdf->SetXY($x0 + 1.5, $yBot + (6.5 * $kBot));
        $pdf->SetFont('Arial', 'B', 7.5);
        $pdf->Cell(11, 3.8, 'Peso:', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 7.5);
        $pdf->Cell($wBotLeft - 13, 3.8, $pesoTexto, 0, 1, 'L');

        $pdf->SetXY($x0 + 1.5, $yBot + (11.0 * $kBot));
        $pdf->SetFont('Arial', 'B', 7.5);
        $pdf->Cell(20, 3.8, self::utf8('Peso Máximo:'), 0, 0, 'L');
        $pdf->SetFont('Arial', '', 7.5);
        $pdf->Cell($wBotLeft - 22, 3.8, $pesoMaxTexto, 0, 1, 'L');

        $pdf->SetXY($x0 + 1.5, $yBot + (15.5 * $kBot));
        $pdf->SetFont('Arial', 'B', 7.2);
        $pdf->Cell(23, 3.8, 'Tipo de entrega:', 0, 0, 'L');
        $pdf->SetFont('Arial', 'B', 7.5);
        $pdf->Cell($wBotLeft - 25, 3.8, $tipoEntrega, 0, 1, 'L');

        // 4.1.5 Especiales: control estricto de ancho para no tocar el QR
        $pdf->SetXY($x0 + 1.5, $yBot + (20.2 * $kBot));
        $pdf->SetFont('Arial', 'B', 6.8);
        $lblW = $pdf->GetStringWidth('Especiales: ');
        $pdf->Cell($lblW, 3.2, 'Especiales: ', 0, 0, 'L');

        $maxWEsp = ($wBotLeft - 3) - $lblW;
        $txtEsp = trim($especiales);

        $pdf->SetFont('Arial', '', 6.0);
        if ($pdf->GetStringWidth(self::utf8($txtEsp)) <= $maxWEsp) {
            $pdf->Cell($maxWEsp, 3.2, self::utf8($txtEsp), 0, 1, 'L');
        } else {
            $pdf->Ln(3.2);
            $pdf->SetX($x0 + 1.5);
            $pdf->SetFont('Arial', '', 5.5);
            $pdf->MultiCell($wBotLeft - 3, 2.5, self::utf8(self::truncar($txtEsp, (int)round(75 * $botCharMult))), 0, 'L');
        }

        // 4.2 Sub-bloque QR Inferior
        $pdf->Rect($x0 + $wBotLeft, $yBot, $wBotRight, $botH);
        if ($tempQr && file_exists($tempQr)) {
            $qr2Size = min($wBotRight - 3.0, $botH - 4.0);
            $qr2X = $x0 + $wBotLeft + ($wBotRight - $qr2Size) / 2.0;
            $qr2Y = $yBot + ($botH - $qr2Size) / 2.0;
            $pdf->Image($tempQr, $qr2X, $qr2Y, $qr2Size, $qr2Size, 'PNG');
            @unlink($tempQr);
        }

        // ── 5. PIE DE PÁGINA ─────────────────────────────────────────────
        $yFoot = $yBot + $botH;
        $pdf->Rect($x0, $yFoot, $usableW, $footH);
        $pdf->SetXY($x0, $yFoot + (($footH - 4.0) / 2.0));
        $pdf->SetFont('Arial', 'B', 7.5);
        $pdf->Cell($usableW, 4, 'https://rutaex.com/', 0, 1, 'C');
    }

    /**
     * Convierte string UTF-8 a ISO-8859-1 compatible con FPDF de forma segura
     */
    private static function utf8(string $texto): string
    {
        return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
    }
}

