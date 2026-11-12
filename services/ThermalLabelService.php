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
     * Ajusta dinámicamente el tamaño de fuente para que un texto quepa en el ancho disponible ($maxW),
     * partiendo de un tamaño ideal grande ($idealSize) y bajando hasta $minSize si es necesario.
     */
    private static function fitFontSize(PDF_Code128 $pdf, string $family, string $style, float $idealSize, float $minSize, string $isoText, float $maxW): float
    {
        $size = round($idealSize, 2);
        $pdf->SetFont($family, $style, $size);
        while ($size > $minSize && $pdf->GetStringWidth($isoText) > $maxW) {
            $size = max($minSize, round($size - 0.3, 2));
            $pdf->SetFont($family, $style, $size);
        }
        return $size;
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
        $vRatio   = $usableH / 129.0;
        $fScale   = max(0.95, min(1.38, sqrt($wRatio * max(1.0, $vRatio * 0.95))));
        $charMult = max(1.0, $wRatio / max(1.0, $fScale * 0.88));

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
        $charsPerLineDir = max(28, (int)round(34 * $charMult));
        $dirLines = max(1, (int)ceil(mb_strlen($direccionTexto, 'UTF-8') / $charsPerLineDir));
        $prodH = ($numProd > 0) ? ($numProd * (($numProd === 1) ? 6.0 : (($numProd === 2) ? 5.5 : 4.9))) : 5.8;

        // Estimación de altura de contenido base para repartir el espacio vertical sobrante de forma uniforme
        $estContentH = (12.0 + 19.5 + 14.0 + (8.8 + ($dirLines * 3.8) + 4.0 + $prodH) + 20.0 + 21.5 + 8.5) * min(1.12, $fScale);
        $extraSpace  = max(0.0, $usableH - ($estContentH + 18.0));
        $divPadBefore = 1.5 + ($extraSpace / 12.0);
        $divPadAfter  = 1.9 + ($extraSpace / 12.0);

        // Resetear estilos y colores por defecto para evitar arrastre de estados entre páginas
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetFillColor(0, 0, 0);
        $pdf->SetLineWidth(0.22);

        // ── 1. ENCABEZADO CORPORATIVO: LOGO "RutaEx | Latam" ─────────────────
        $fLogoMain = round(14.5 * $fScale, 1);
        $fLogoPipe = round(12.0 * $fScale, 1);
        $pdf->SetFont('Arial', 'B', $fLogoMain);
        $wRuta  = $pdf->GetStringWidth('Ruta');
        $wEx    = $pdf->GetStringWidth('Ex');
        $pdf->SetFont('Arial', '', $fLogoPipe);
        $wPipe  = $pdf->GetStringWidth(' | ');
        $pdf->SetFont('Arial', '', $fLogoMain);
        $wLatam = $pdf->GetStringWidth('Latam');
        $totalLogoW = $wRuta + $wEx + $wPipe + $wLatam;

        $logoX = $x0 + (($usableW - $totalLogoW) / 2);

        $pdf->SetXY($logoX, $y);
        $pdf->SetFont('Arial', 'B', $fLogoMain);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Write(5.8, 'Ruta');
        $pdf->Write(5.8, 'Ex');

        $pdf->SetFont('Arial', '', $fLogoPipe);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->Write(5.8, ' | ');

        $pdf->SetFont('Arial', '', $fLogoMain);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Write(5.8, 'Latam');

        $pdf->SetTextColor(0, 0, 0);
        $y += 6.6 * min(1.15, $fScale);

        $pdf->SetXY($x0, $y);
        $pdf->SetFont('Arial', 'B', round(8.5 * $fScale, 1));
        $pdf->Cell($usableW, 3.8, self::utf8('COMPROBANTE DE ENTREGA Y GUÍA'), 0, 1, 'C');
        $y += 3.8 + $divPadBefore;

        // Línea divisoria punteada
        self::dibujarLineaDivisoria($pdf, $x0, $y, $usableW);
        $y += $divPadAfter;

        // ── 2. CÓDIGO DE BARRAS (Code 128) + NÚMERO DE ORDEN ─────────────────
        $numOrden = !empty($p['numero_orden']) ? (string)$p['numero_orden'] : 'ORD-' . $p['id'];

        // Dibujar barras Code 128 adaptado al ancho disponible
        $bcW = min($usableW - 4.0, max(64.0, round($usableW * 0.85, 1)));
        $bcX = $x0 + (($usableW - $bcW) / 2);
        $pdf->Code128($bcX, $y, $numOrden, $bcW, 10.5);
        $y += 11.2;

        // Número de orden legible en negrita
        $pdf->SetXY($x0, $y);
        $pdf->SetFont('Arial', 'B', round(11.5 * $fScale, 1));
        $pdf->Cell($usableW, 4.8, $numOrden, 0, 1, 'C');
        $y += 5.0;

        // Subtítulo de tracking si existe
        if (!empty($p['numero_traking']) && $p['numero_traking'] !== $numOrden) {
            $pdf->SetXY($x0, $y);
            $pdf->SetFont('Arial', 'B', round(8.5 * $fScale, 1));
            $pdf->Cell($usableW, 3.6, 'Tracking: ' . $p['numero_traking'], 0, 1, 'C');
            $y += 3.8;
        } elseif (!empty($p['c807_guia'])) {
            $pdf->SetXY($x0, $y);
            $pdf->SetFont('Arial', 'B', round(8.8 * $fScale, 1));
            $pdf->Cell($usableW, 3.6, self::utf8('Guía C807: ' . $p['c807_guia']), 0, 1, 'C');
            $y += 3.8;
        } elseif (((int)($p['id_cliente'] ?? 0)) === 42) {
            $pdf->SetXY($x0, $y);
            $pdf->SetFont('Arial', 'B', round(8.5 * $fScale, 1));
            $pdf->Cell($usableW, 3.6, 'Ref: EXLATA-' . $numOrden, 0, 1, 'C');
            $y += 3.8;
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
        $pdf->SetFont('Arial', 'B', round(8.8 * $fScale, 1));
        $wLblRem = $pdf->GetStringWidth('REMITENTE: ') + 1.0;
        $pdf->Cell($wLblRem, 4.2, 'REMITENTE: ', 0, 0, 'L');
        $remIso = self::utf8(self::truncar($remitente, (int)round(40 * $charMult)));
        self::fitFontSize($pdf, 'Arial', '', 9.0 * $fScale, 7.2, $remIso, $usableW - $wLblRem);
        $pdf->Cell($usableW - $wLblRem, 4.2, $remIso, 0, 1, 'L');
        $y += 4.5 * min(1.15, $fScale);

        // Destinatario
        $pdf->SetXY($x0, $y);
        $pdf->SetFont('Arial', 'B', round(9.2 * $fScale, 1));
        $wLblDest = $pdf->GetStringWidth('DESTINATARIO: ') + 1.0;
        $pdf->Cell($wLblDest, 4.4, 'DESTINATARIO: ', 0, 0, 'L');
        $destIso = self::utf8(self::truncar($destinatario, (int)round(36 * $charMult)));
        self::fitFontSize($pdf, 'Arial', 'B', 9.5 * $fScale, 7.5, $destIso, $usableW - $wLblDest);
        $pdf->Cell($usableW - $wLblDest, 4.4, $destIso, 0, 1, 'L');
        $y += 4.8 * min(1.15, $fScale);

        // Teléfono
        $pdf->SetXY($x0, $y);
        $pdf->SetFont('Arial', 'B', round(8.8 * $fScale, 1));
        $wLblTel = $pdf->GetStringWidth(self::utf8('TELÉFONO: ')) + 1.0;
        $pdf->Cell($wLblTel, 4.2, self::utf8('TELÉFONO: '), 0, 0, 'L');
        $pdf->SetFont('Arial', 'B', round(9.6 * $fScale, 1));
        $pdf->Cell($usableW - $wLblTel, 4.2, $telefono, 0, 1, 'L');
        $y += 4.2 + $divPadBefore;

        self::dibujarLineaDivisoria($pdf, $x0, $y, $usableW);
        $y += $divPadAfter;

        // ── 4. DIRECCIÓN COMPLETA Y PRODUCTOS ASIGNADOS ──────────────────────
        $pdf->SetXY($x0, $y);
        $pdf->SetFont('Arial', 'B', round(8.8 * $fScale, 1));
        $pdf->Cell($usableW, 3.8, self::utf8('DIRECCIÓN DE ENTREGA:'), 0, 1, 'L');
        $y += 4.2;

        // Ubicación geográfica complementaria
        $geoParts = [];
        if (!empty($p['departamento_nombre'])) $geoParts[] = $p['departamento_nombre'];
        if (!empty($p['municipio_nombre']))    $geoParts[] = $p['municipio_nombre'];
        if (!empty($p['barrio_nombre']))       $geoParts[] = $p['barrio_nombre'];
        if (!empty($p['codigo_postal']))       $geoParts[] = 'CP: ' . $p['codigo_postal'];

        if (!empty($geoParts)) {
            $pdf->SetXY($x0, $y);
            $geoIso = self::utf8(implode(' | ', $geoParts));
            self::fitFontSize($pdf, 'Arial', 'B', 8.5 * $fScale, 7.0, $geoIso, $usableW);
            $pdf->Cell($usableW, 3.8, $geoIso, 0, 1, 'L');
            $y += 4.1;
        }

        // Dirección texto principal
        $pdf->SetXY($x0, $y);
        $pdf->SetFont('Arial', '', round(8.8 * $fScale, 1));
        $pdf->MultiCell($usableW, 3.8 * min(1.12, $fScale), self::utf8($direccionTexto), 0, 'L');
        $y = $pdf->GetY() + 1.8;

        // ── 4.1 PRODUCTOS Y CANTIDADES ASIGNADAS ─────────────────────────────
        $pdf->SetXY($x0, $y);
        $pdf->SetFont('Arial', 'B', round(8.0 * $fScale, 1));
        $pdf->SetTextColor(50, 50, 50);
        $pdf->Cell($usableW, 3.6, self::utf8('PRODUCTOS ASIGNADOS:'), 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);
        $y += 4.0;

        if (!empty($productos)) {
            if ($numProd === 1) {
                $baseFontCant = 13.5 * $fScale;
                $baseFontProd = 12.5 * $fScale;
                $lineH = 5.5 * min(1.15, $fScale);
            } elseif ($numProd === 2) {
                $baseFontCant = 12.5 * $fScale;
                $baseFontProd = 11.5 * $fScale;
                $lineH = 5.0 * min(1.15, $fScale);
            } else {
                $baseFontCant = 11.0 * $fScale;
                $baseFontProd = 10.0 * $fScale;
                $lineH = 4.5 * min(1.15, $fScale);
            }

            foreach ($productos as $prod) {
                $cant = intval($prod['cantidad'] ?? 1);
                $nombreProd = trim($prod['nombre'] ?? 'Producto');
                $cantText = $cant . 'x';

                $pdf->SetXY($x0 + 1, $y);
                $pdf->SetFont('Arial', 'B', $baseFontCant);
                $wCant = $pdf->GetStringWidth($cantText . ' ');
                $pdf->Cell($wCant, $lineH, $cantText . ' ', 0, 0, 'L');

                $maxWProd = $usableW - $wCant - 2;
                $prodIso  = self::utf8(self::truncar($nombreProd, (int)round(48 * $charMult)));
                self::fitFontSize($pdf, 'Arial', 'B', $baseFontProd, 8.0, $prodIso, $maxWProd);

                $pdf->Cell($maxWProd, $lineH, $prodIso, 0, 1, 'L');
                $y += $lineH + 0.5;
            }
        } elseif (!empty($p['observaciones_combo'])) {
            $comboText = trim($p['observaciones_combo']);
            $pdf->SetXY($x0 + 1, $y);
            $maxW = $usableW - 2;
            $comboIso = self::utf8(self::truncar($comboText, (int)round(48 * $charMult)));
            self::fitFontSize($pdf, 'Arial', 'B', 11.5 * $fScale, 8.0, $comboIso, $maxW);
            $pdf->Cell($maxW, 5.2, $comboIso, 0, 1, 'L');
            $y += 5.6;
        } else {
            $pdf->SetXY($x0 + 1, $y);
            $pdf->SetFont('Arial', 'B', round(12.5 * $fScale, 1));
            $pdf->Cell(9, 5.2, '1x ', 0, 0, 'L');
            $pdf->SetFont('Arial', 'B', round(11.5 * $fScale, 1));
            $pdf->Cell($usableW - 11, 5.2, self::utf8('Paquete estándar'), 0, 1, 'L');
            $y += 5.6;
        }

        $y += $divPadBefore;
        self::dibujarLineaDivisoria($pdf, $x0, $y, $usableW);
        $y += $divPadAfter;

        // ── 5. FECHA DE ENTREGA Y MONTO A COBRAR (COD) ────────────────────────
        $fechaEntrega = !empty($p['fecha_entrega']) ? date('d/m/Y', strtotime($p['fecha_entrega'])) : date('d/m/Y');

        $pdf->SetXY($x0, $y);
        $pdf->SetFont('Arial', 'B', round(8.8 * $fScale, 1));
        $wLblFec = $pdf->GetStringWidth('FECHA ENTREGA: ') + 1.0;
        $pdf->Cell($wLblFec, 4.2, 'FECHA ENTREGA: ', 0, 0, 'L');
        $pdf->SetFont('Arial', 'B', round(9.4 * $fScale, 1));
        $pdf->Cell($usableW - $wLblFec, 4.2, $fechaEntrega, 0, 1, 'L');
        $y += 4.8;

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
        $pdf->SetFont('Arial', 'B', round(8.5 * $fScale, 1));
        $pdf->Cell($usableW, 3.8, self::utf8('MONTO A COBRAR (PAGO CONTRA ENTREGA):'), 0, 1, 'L');
        $y += 4.2;

        // Recuadro destacado para el Monto
        $boxMontoH = 9.0 * min(1.15, $fScale);
        $pdf->SetXY($x0, $y);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->Rect($x0, $y, $usableW, $boxMontoH, 'DF');
        $pdf->SetFillColor(0, 0, 0); // Restaurar inmediatamente a negro

        $pdf->SetXY($x0, $y + 1.1);
        if ($totalCobrar > 0) {
            $montoTexto = $monedaCod . ' ' . number_format($totalCobrar, 2, '.', ',');
        } else {
            $montoTexto = self::utf8('PAGADO / SIN COBRO (' . $monedaCod . ' 0.00)');
        }
        self::fitFontSize($pdf, 'Arial', 'B', 13.5 * $fScale, 9.5, $montoTexto, $usableW - 4);
        $pdf->Cell($usableW, $boxMontoH - 2.2, $montoTexto, 0, 1, 'C');
        $y += $boxMontoH + $divPadBefore;

        self::dibujarLineaDivisoria($pdf, $x0, $y, $usableW);
        $y += $divPadAfter;

        // ── 6. CÓDIGO QR Y RASTREO ───────────────────────────────────────────
        $qrSize = round(20.0 * min(1.18, $fScale), 1);
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
        $pdf->SetFont('Arial', 'B', round(8.5 * $fScale, 1));
        $pdf->Cell($infoW, 3.6, self::utf8('ESCANEA PARA'), 0, 1, 'L');

        $pdf->SetXY($infoX, $qrY + 5.0);
        $pdf->SetFont('Arial', 'B', round(8.5 * $fScale, 1));
        $pdf->Cell($infoW, 3.6, self::utf8('RASTREAR PEDIDO'), 0, 1, 'L');

        if ($textoGuiaVisible) {
            $pdf->SetXY($infoX, $qrY + 9.2);
            $guiaIso = self::utf8('GUIA: ' . self::truncar($textoGuiaVisible, (int)round(22 * $charMult)));
            self::fitFontSize($pdf, 'Arial', 'B', 7.8 * $fScale, 6.5, $guiaIso, $infoW);
            $pdf->Cell($infoW, 3.4, $guiaIso, 0, 1, 'L');
        }

        $pdf->SetXY($infoX, $qrY + 13.5);
        $pdf->SetFont('Arial', 'BI', round(7.8 * $fScale, 1));
        $pdf->Cell($infoW, 3.4, self::utf8('Etiqueta ' . $indice . ' de ' . $total), 0, 1, 'L');

        $y += $qrSize + $divPadBefore;

        // ── 7. PIE DE PÁGINA INSTITUCIONAL ───────────────────────────────────
        self::dibujarLineaDivisoria($pdf, $x0, $y, $usableW);
        $y += 2;

        $pdf->SetXY($x0, $y);
        $pdf->SetFont('Arial', 'B', round(8.8 * $fScale, 1));
        $pdf->Cell($usableW, 3.8, '* GRACIAS POR SU COMPRA *', 0, 1, 'C');
        $y += 4.2;

        $pdf->SetXY($x0, $y);
        $pdf->SetFont('Arial', 'B', round(8.2 * $fScale, 1));
        $pdf->Cell($usableW, 3.5, 'https://rutaex.com/', 0, 1, 'C');
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
        $pdf->SetLineWidth(0.28);

        // Distribución horizontal adaptativa
        $colLeftW  = round(max(24.0, min(34.0, $usableW * 0.31)), 2);
        $colRightW = round($usableW - $colLeftW, 2);
        $wRatioR   = $colRightW / 48.0;

        // Distribución vertical adaptativa (base: 75 + 9 + 34 + 6.5 = 124.5)
        $vScale = $usableH / 124.5;
        $midH   = round(9.2 * min(1.18, $vScale), 2);
        $footH  = round(6.8 * min(1.18, $vScale), 2);
        $remH   = $usableH - $midH - $footH;
        $topH   = round($remH * (75.0 / 109.0), 2);
        $botH   = round($remH - $topH, 2);
        $kTop   = $topH / 75.0;

        // Escala tipográfica inteligente según espacio disponible de la celda
        $fScale   = max(1.0, min(1.42, sqrt($wRatioR * max(1.0, $kTop * 0.96))));
        $fLeft    = max(1.0, min(1.30, sqrt(($colLeftW / 24.0) * max(1.0, $kTop * 0.96))));
        $charMult = max(1.0, $wRatioR / max(1.0, $fScale * 0.85));

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
        $pdf->SetXY($x0, $yL + ($hLogo * 0.14));
        $pdf->SetFont('Arial', 'B', round(10.2 * $fLeft, 1));
        $pdf->Cell($colLeftW, 4.2, 'RutaEx', 0, 1, 'C');
        $pdf->SetXY($x0, $yL + ($hLogo * 0.54));
        $pdf->SetFont('Arial', 'B', round(7.8 * $fLeft, 1));
        $pdf->Cell($colLeftW, 3.4, '- latam -', 0, 1, 'C');
        $yL += $hLogo;

        // 1.2 QR Superior
        $pdf->Rect($x0, $yL, $colLeftW, $hQr1);
        if ($tempQr && file_exists($tempQr)) {
            $qr1Size = min($colLeftW - 3.2, $hQr1 - 3.2);
            $qr1X = $x0 + ($colLeftW - $qr1Size) / 2.0;
            $qr1Y = $yL + ($hQr1 - $qr1Size) / 2.0;
            $pdf->Image($tempQr, $qr1X, $qr1Y, $qr1Size, $qr1Size, 'PNG');
        }
        $yL += $hQr1;

        // 1.3 Estación
        $pdf->Rect($x0, $yL, $colLeftW, $hEst);
        $pdf->SetXY($x0, $yL + (($hEst - 5.8) / 2.0));
        self::fitFontSize($pdf, 'Arial', 'B', 14.0 * $fLeft, 10.0, $estacion, $colLeftW - 2.0);
        $pdf->Cell($colLeftW, 5.8, $estacion, 0, 1, 'C');
        $yL += $hEst;

        // 1.4 Código Departamento
        $pdf->Rect($x0, $yL, $colLeftW, $hDep);
        $pdf->SetXY($x0, $yL + (($hDep - 5.8) / 2.0));
        self::fitFontSize($pdf, 'Arial', 'B', 14.0 * $fLeft, 10.0, $codigoDepto, $colLeftW - 2.0);
        $pdf->Cell($colLeftW, 5.8, $codigoDepto, 0, 1, 'C');
        $yL += $hDep;

        // 1.5 Distrito
        $pdf->Rect($x0, $yL, $colLeftW, $hDis);
        $distIso = self::utf8($distrito);
        $fDist = round(8.4 * $fLeft, 1);
        $pdf->SetFont('Arial', 'B', $fDist);
        if ($pdf->GetStringWidth($distIso) <= ($colLeftW - 1.5)) {
            $pdf->SetXY($x0, $yL + (($hDis - 4.2) / 2.0));
            $pdf->Cell($colLeftW, 4.2, $distIso, 0, 1, 'C');
        } else {
            $pdf->SetXY($x0 + 0.5, $yL + 1.4);
            $pdf->SetFont('Arial', 'B', round(7.6 * $fLeft, 1));
            $pdf->MultiCell($colLeftW - 1, 3.4 * min(1.15, $kTop), $distIso, 0, 'C');
        }
        $yL += $hDis;

        // 1.6 Tipo de Servicio
        $pdf->Rect($x0, $yL, $colLeftW, $hSrv);
        $pdf->SetXY($x0, $yL + (($hSrv - 5.8) / 2.0));
        self::fitFontSize($pdf, 'Arial', 'B', 14.0 * $fLeft, 10.0, $tipoServicio, $colLeftW - 2.0);
        $pdf->Cell($colLeftW, 5.8, $tipoServicio, 0, 1, 'C');
        $yL += $hSrv;

        // 1.7 Días Cobertura
        $pdf->Rect($x0, $yL, $colLeftW, $hDia);
        $pdf->SetXY($x0, $yL + (($hDia - 4.2) / 2.0));
        self::fitFontSize($pdf, 'Arial', 'B', 8.0 * $fLeft, 6.5, $diasCobertura, $colLeftW - 1.5);
        $pdf->Cell($colLeftW, 4.2, $diasCobertura, 0, 1, 'C');

        // ── 2. COLUMNA DERECHA ───────────────────────────────────────────
        $xR = $x0 + $colLeftW;
        $pdf->Rect($xR, $y0, $colRightW, $topH);

        // 2.1 Remitente
        $pdf->Line($xR, $y0 + $hLogo, $xR + $colRightW, $y0 + $hLogo);
        $pdf->SetXY($xR + 1.5, $y0 + ($hLogo * 0.12));
        $remIso = self::utf8(self::truncar($remitente, (int)round(42 * $charMult)));
        self::fitFontSize($pdf, 'Arial', 'B', 8.6 * $fScale, 6.8, $remIso, $colRightW - 3.0);
        $pdf->Cell($colRightW - 3, 3.8 * min(1.15, $kTop), $remIso, 0, 1, 'L');

        $pdf->SetXY($xR + 1.5, $y0 + ($hLogo * 0.52));
        $pdf->SetFont('Arial', 'B', round(7.8 * $fScale, 1));
        $wLblRemTel = $pdf->GetStringWidth(self::utf8('Teléfono: ')) + 0.8;
        $pdf->Cell($wLblRemTel, 3.6 * min(1.15, $kTop), self::utf8('Teléfono: '), 0, 0, 'L');
        $pdf->SetFont('Arial', 'B', round(8.5 * $fScale, 1));
        $pdf->Cell($colRightW - $wLblRemTel - 2.0, 3.6 * min(1.15, $kTop), $remitenteTel, 0, 1, 'L');

        // 2.2 Bloque Destino
        $stepDest = 3.85 * $kTop;
        $yDest = $y0 + $hLogo + (1.0 * $kTop);
        $pdf->SetXY($xR + 1.5, $yDest);
        $pdf->SetFont('Arial', 'B', round(9.0 * $fScale, 1));
        $pdf->Cell($colRightW - 3, 3.6 * min(1.15, $kTop), 'Destino:', 0, 1, 'L');

        // Nombre
        $yNom = $yDest + $stepDest;
        $pdf->SetXY($xR + 1.5, $yNom);
        $pdf->SetFont('Arial', 'B', round(7.6 * $fScale, 1));
        $wLblNom = $pdf->GetStringWidth('Nombre: ') + 0.8;
        $pdf->Cell($wLblNom, 3.4, 'Nombre: ', 0, 0, 'L');
        $nomIso = self::utf8(self::truncar($destinatario, (int)round(36 * $charMult)));
        self::fitFontSize($pdf, 'Arial', 'B', 7.8 * $fScale, 6.4, $nomIso, $colRightW - $wLblNom - 2.5);
        $pdf->Cell($colRightW - $wLblNom - 2.5, 3.4, $nomIso, 0, 1, 'L');

        // Teléfono
        $yTel = $yNom + ($stepDest * 0.92);
        $pdf->SetXY($xR + 1.5, $yTel);
        $pdf->SetFont('Arial', 'B', round(7.6 * $fScale, 1));
        $wLblTel = $pdf->GetStringWidth(self::utf8('Teléfono: ')) + 0.8;
        $pdf->Cell($wLblTel, 3.4, self::utf8('Teléfono: '), 0, 0, 'L');
        self::fitFontSize($pdf, 'Arial', 'B', 8.4 * $fScale, 6.8, $telefono, $colRightW - $wLblTel - 2.5);
        $pdf->Cell($colRightW - $wLblTel - 2.5, 3.4, $telefono, 0, 1, 'L');

        // Contacto
        $yCon = $yTel + ($stepDest * 0.92);
        $pdf->SetXY($xR + 1.5, $yCon);
        $pdf->SetFont('Arial', 'B', round(7.5 * $fScale, 1));
        $wLblCon = $pdf->GetStringWidth('Contacto: ') + 0.8;
        $pdf->Cell($wLblCon, 3.4, 'Contacto: ', 0, 0, 'L');
        $conIso = self::utf8(self::truncar($contacto, (int)round(34 * $charMult)));
        self::fitFontSize($pdf, 'Arial', '', 7.6 * $fScale, 6.2, $conIso, $colRightW - $wLblCon - 2.5);
        $pdf->Cell($colRightW - $wLblCon - 2.5, 3.4, $conIso, 0, 1, 'L');

        // Dirección
        $yDir = $yCon + ($stepDest * 0.92);
        $pdf->SetXY($xR + 1.5, $yDir);
        $pdf->SetFont('Arial', 'B', round(7.6 * $fScale, 1));
        $pdf->Cell($colRightW - 3, 3.2, self::utf8('Dirección:'), 0, 1, 'L');
        $pdf->SetXY($xR + 1.5, $yDir + (3.2 * min(1.12, $kTop)));
        $dirFont = (mb_strlen($direccion, 'UTF-8') > (int)round(95 * $charMult)) ? round(6.8 * $fScale, 1) : round(7.4 * $fScale, 1);
        $pdf->SetFont('Arial', '', $dirFont);
        $pdf->MultiCell($colRightW - 3, 3.0 * min(1.15, $kTop), self::utf8(self::truncar($direccion, (int)round(130 * $charMult))), 0, 'L');

        // Referencia
        $curY = max($pdf->GetY() + (0.8 * $kTop), $y0 + (41.5 * $kTop));
        $pdf->SetXY($xR + 1.5, $curY);
        $pdf->SetFont('Arial', 'B', round(7.6 * $fScale, 1));
        $pdf->Cell($colRightW - 3, 3.2, 'Referencia:', 0, 1, 'L');
        $curY = $pdf->GetY();
        $pdf->SetXY($xR + 1.5, $curY);
        $pdf->SetFont('Arial', '', round(7.2 * $fScale, 1));
        $pdf->MultiCell($colRightW - 3, 2.9 * min(1.15, $kTop), self::utf8(self::truncar($referencia, (int)round(75 * $charMult))), 0, 'L');

        // Contenido (destacado en negrita y mayor tamaño para lectura rápida en bodega/ruta)
        $curY = max($pdf->GetY() + (0.8 * $kTop), $y0 + (52.0 * $kTop));
        $pdf->SetXY($xR + 1.5, $curY);
        $pdf->SetFont('Arial', 'B', round(7.8 * $fScale, 1));
        $pdf->Cell($colRightW - 3, 3.2, 'Contenido:', 0, 1, 'L');
        $curY = $pdf->GetY();
        $pdf->SetXY($xR + 1.5, $curY);
        $contFont = (mb_strlen($contenido, 'UTF-8') > (int)round(55 * $charMult)) ? round(7.2 * $fScale, 1) : round(8.2 * $fScale, 1);
        $pdf->SetFont('Arial', 'B', $contFont);
        $pdf->MultiCell($colRightW - 3, 3.2 * min(1.15, $kTop), self::utf8(self::truncar($contenido, (int)round(130 * $charMult))), 0, 'L');

        // No. Orden al pie de la columna derecha
        $curY = max($pdf->GetY() + 0.5, $y0 + $topH - (5.2 * min(1.15, $kTop)));
        $pdf->SetXY($xR + 1.5, $curY);
        $pdf->SetFont('Arial', 'B', round(8.0 * $fScale, 1));
        $wLblOrd = $pdf->GetStringWidth('No. Orden: ') + 0.8;
        $pdf->Cell($wLblOrd, 3.8, 'No. Orden: ', 0, 0, 'L');
        self::fitFontSize($pdf, 'Arial', 'B', 9.2 * $fScale, 7.5, $numOrden, $colRightW - $wLblOrd - 2.5);
        $pdf->Cell($colRightW - $wLblOrd - 2.5, 3.8, $numOrden, 0, 1, 'L');

        // ── 3. FRANJA CENTRAL: NÚMERO DE GUÍA ────────────────────────────
        $yMid = $y0 + $topH;
        $pdf->Rect($x0, $yMid, $usableW, $midH);

        // Reservar espacio a la derecha para bultos y adaptar guía al ancho total
        $maxWGuia = $usableW - 19.0;
        self::fitFontSize($pdf, 'Arial', 'B', 13.5 * $fScale, 8.0, $numGuia, $maxWGuia);

        $yMidText = $yMid + (($midH - 6.0) / 2.0);
        $pdf->SetXY($x0 + 1.5, $yMidText);
        $pdf->Cell($maxWGuia, 6, $numGuia, 0, 0, 'L');

        // Bultos alineado a la derecha con espacio garantizado
        $pdf->SetXY($x0 + $usableW - 16.5, $yMidText);
        self::fitFontSize($pdf, 'Arial', 'B', 9.8 * $fScale, 8.0, $bultosTexto, 15.0);
        $pdf->Cell(15.0, 6, $bultosTexto, 0, 1, 'R');

        // ── 4. BLOQUE INFERIOR ───────────────────────────────────────────
        $yBot = $yMid + $midH;
        $wBotRight = round(max(25.0, min(36.0, $usableW * 0.33)), 2);
        $wBotLeft  = round($usableW - $wBotRight, 2);
        $wRatioBot = $wBotLeft / 47.0;
        $kBot      = $botH / 34.0;
        $fBot      = max(1.0, min(1.40, sqrt($wRatioBot * max(1.0, $kBot * 0.96))));
        $botCharMult = max(1.0, $wRatioBot / max(1.0, $fBot * 0.85));

        // 4.1 Sub-bloque datos comerciales y peso
        $pdf->Rect($x0, $yBot, $wBotLeft, $botH);

        $pdf->SetXY($x0 + 1.5, $yBot + (1.8 * $kBot));
        $pdf->SetFont('Arial', 'B', round(8.5 * $fBot, 1));
        $wLblPago = $pdf->GetStringWidth('PAGO: ') + 0.8;
        $pdf->Cell($wLblPago, 4.0, 'PAGO: ', 0, 0, 'L');
        $pagoIso = self::utf8($pago);
        self::fitFontSize($pdf, 'Arial', 'B', 9.0 * $fBot, 7.2, $pagoIso, $wBotLeft - $wLblPago - 2.5);
        $pdf->Cell($wBotLeft - $wLblPago - 2.5, 4.0, $pagoIso, 0, 1, 'L');

        $pdf->SetXY($x0 + 1.5, $yBot + (6.5 * $kBot));
        $pdf->SetFont('Arial', 'B', round(8.4 * $fBot, 1));
        $wLblPeso = $pdf->GetStringWidth('Peso: ') + 0.8;
        $pdf->Cell($wLblPeso, 4.0, 'Peso: ', 0, 0, 'L');
        $pdf->SetFont('Arial', 'B', round(8.4 * $fBot, 1));
        $pdf->Cell($wBotLeft - $wLblPeso - 2.5, 4.0, $pesoTexto, 0, 1, 'L');

        $pdf->SetXY($x0 + 1.5, $yBot + (11.2 * $kBot));
        $pdf->SetFont('Arial', 'B', round(8.4 * $fBot, 1));
        $wLblPMax = $pdf->GetStringWidth(self::utf8('Peso Máximo: ')) + 0.8;
        $pdf->Cell($wLblPMax, 4.0, self::utf8('Peso Máximo: '), 0, 0, 'L');
        $pdf->SetFont('Arial', '', round(8.4 * $fBot, 1));
        $pdf->Cell($wBotLeft - $wLblPMax - 2.5, 4.0, $pesoMaxTexto, 0, 1, 'L');

        $pdf->SetXY($x0 + 1.5, $yBot + (15.9 * $kBot));
        $pdf->SetFont('Arial', 'B', round(8.2 * $fBot, 1));
        $wLblTEnt = $pdf->GetStringWidth('Tipo de entrega: ') + 0.8;
        $pdf->Cell($wLblTEnt, 4.0, 'Tipo de entrega: ', 0, 0, 'L');
        $pdf->SetFont('Arial', 'B', round(8.6 * $fBot, 1));
        $pdf->Cell($wBotLeft - $wLblTEnt - 2.5, 4.0, $tipoEntrega, 0, 1, 'L');

        // 4.1.5 Especiales: control estricto de ancho para no tocar el QR
        $pdf->SetXY($x0 + 1.5, $yBot + (20.6 * $kBot));
        $pdf->SetFont('Arial', 'B', round(8.0 * $fBot, 1));
        $lblW = $pdf->GetStringWidth('Especiales: ') + 0.5;
        $pdf->Cell($lblW, 3.6, 'Especiales: ', 0, 0, 'L');

        $maxWEsp = ($wBotLeft - 3.0) - $lblW;
        $txtEsp = trim($especiales);
        $espIso = self::utf8($txtEsp);

        $pdf->SetFont('Arial', 'B', round(7.4 * $fBot, 1));
        if ($pdf->GetStringWidth($espIso) <= $maxWEsp) {
            $pdf->Cell($maxWEsp, 3.6, $espIso, 0, 1, 'L');
        } else {
            $pdf->Ln(3.6 * min(1.12, $kBot));
            $pdf->SetX($x0 + 1.5);
            $pdf->SetFont('Arial', 'B', round(7.0 * $fBot, 1));
            $pdf->MultiCell($wBotLeft - 3, 2.9 * min(1.15, $kBot), self::utf8(self::truncar($txtEsp, (int)round(80 * $botCharMult))), 0, 'L');
        }

        // 4.2 Sub-bloque QR Inferior
        $pdf->Rect($x0 + $wBotLeft, $yBot, $wBotRight, $botH);
        if ($tempQr && file_exists($tempQr)) {
            $qr2Size = min($wBotRight - 3.0, $botH - 3.6);
            $qr2X = $x0 + $wBotLeft + ($wBotRight - $qr2Size) / 2.0;
            $qr2Y = $yBot + ($botH - $qr2Size) / 2.0;
            $pdf->Image($tempQr, $qr2X, $qr2Y, $qr2Size, $qr2Size, 'PNG');
            @unlink($tempQr);
        }

        // ── 5. PIE DE PÁGINA ─────────────────────────────────────────────
        $yFoot = $yBot + $botH;
        $pdf->Rect($x0, $yFoot, $usableW, $footH);
        $pdf->SetXY($x0, $yFoot + (($footH - 4.2) / 2.0));
        $pdf->SetFont('Arial', 'B', round(8.8 * $fScale, 1));
        $pdf->Cell($usableW, 4.2, 'https://rutaex.com/', 0, 1, 'C');
    }

    /**
     * Convierte string UTF-8 a ISO-8859-1 compatible con FPDF de forma segura
     */
    private static function utf8(string $texto): string
    {
        return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
    }
}

