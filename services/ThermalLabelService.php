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
     * Genera el PDF con las etiquetas térmicas para los pedidos proporcionados
     *
     * @param array $pedidos Array con los datos completos de los pedidos
     * @param string $dest 'I' (Inline navegador), 'D' (Descarga), 'S' (String binario)
     * @return string|void
     */
    public static function generarPDF(array $pedidos, string $dest = 'I')
    {
        // Instanciar FPDF con tamaño personalizado [80mm x 145mm]
        $pdf = new PDF_Code128('P', 'mm', [self::LABEL_WIDTH_MM, self::LABEL_HEIGHT_MM]);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(self::MARGIN_LEFT_MM, self::MARGIN_TOP_MM, self::MARGIN_RIGHT_MM);

        $totalPedidos = count($pedidos);

        foreach ($pedidos as $idx => $p) {
            $pdf->AddPage('P', [self::LABEL_WIDTH_MM, self::LABEL_HEIGHT_MM]);
            self::renderEtiqueta($pdf, $p, $idx + 1, $totalPedidos);
        }

        $filename = 'Etiquetas_RutaEx_' . date('Ymd_His') . '.pdf';
        return $pdf->Output($dest, $filename);
    }

    /**
     * Renderiza una etiqueta individual de 80mm en la página activa de FPDF
     */
    private static function renderEtiqueta(PDF_Code128 $pdf, array $p, int $indice = 1, int $total = 1)
    {
        $usableW = self::LABEL_WIDTH_MM - (self::MARGIN_LEFT_MM + self::MARGIN_RIGHT_MM); // 72 mm
        $x0 = self::MARGIN_LEFT_MM;
        $y = self::MARGIN_TOP_MM;

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
        $y += 4.5;

        // Línea divisoria punteada
        self::dibujarLineaDivisoria($pdf, $x0, $y, $usableW);
        $y += 2.5;

        // ── 2. CÓDIGO DE BARRAS (Code 128) + NÚMERO DE ORDEN ─────────────────
        $numOrden = !empty($p['numero_orden']) ? (string)$p['numero_orden'] : 'ORD-' . $p['id'];
        
        // Dibujar barras Code 128 (alto: 10 mm, ancho útil: 66 mm centrado)
        $bcW = 66;
        $bcX = $x0 + (($usableW - $bcW) / 2);
        $pdf->Code128($bcX, $y, $numOrden, $bcW, 10);
        $y += 11;

        // Número de orden legible en negrita
        $pdf->SetXY($x0, $y);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($usableW, 4.5, $numOrden, 0, 1, 'C');
        $y += 5.5;

        // Subtítulo de tracking si existe
        if (!empty($p['numero_traking']) && $p['numero_traking'] !== $numOrden) {
            $pdf->SetXY($x0, $y);
            $pdf->SetFont('Arial', '', 7);
            $pdf->Cell($usableW, 3, 'Tracking: ' . $p['numero_traking'], 0, 1, 'C');
            $y += 3.5;
        }

        self::dibujarLineaDivisoria($pdf, $x0, $y, $usableW);
        $y += 2.5;

        // ── 3. REMITENTE Y DESTINATARIO ──────────────────────────────────────
        $remitente = !empty($p['remitente_nombre']) ? $p['remitente_nombre'] : (!empty($p['proveedor_nombre']) ? $p['proveedor_nombre'] : 'RutaEx Cliente');
        $destinatario = !empty($p['destinatario']) ? $p['destinatario'] : 'Consignatario Final';
        $telefono = !empty($p['telefono']) ? $p['telefono'] : 'N/D';

        // Remitente
        $pdf->SetXY($x0, $y);
        $pdf->SetFont('Arial', 'B', 7.5);
        $pdf->Cell(20, 3.8, 'REMITENTE:', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 7.5);
        $pdf->Cell($usableW - 20, 3.8, self::utf8(self::truncar($remitente, 35)), 0, 1, 'L');
        $y += 4;

        // Destinatario
        $pdf->SetXY($x0, $y);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(22, 4, 'DESTINATARIO:', 0, 0, 'L');
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell($usableW - 22, 4, self::utf8(self::truncar($destinatario, 32)), 0, 1, 'L');
        $y += 4.5;

        // Teléfono
        $pdf->SetXY($x0, $y);
        $pdf->SetFont('Arial', 'B', 7.5);
        $pdf->Cell(18, 3.8, self::utf8('TELÉFONO:'), 0, 0, 'L');
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell($usableW - 18, 3.8, $telefono, 0, 1, 'L');
        $y += 4.5;

        self::dibujarLineaDivisoria($pdf, $x0, $y, $usableW);
        $y += 2.5;

        // ── 4. DIRECCIÓN COMPLETA Y REFERENCIAS (Ref 1 y Ref 2) ──────────────
        $pdf->SetXY($x0, $y);
        $pdf->SetFont('Arial', 'B', 7.5);
        $pdf->Cell($usableW, 3.5, self::utf8('DIRECCIÓN DE ENTREGA:'), 0, 1, 'L');
        $y += 3.8;

        // Construir dirección legible
        $direccionTexto = trim($p['direccion'] ?? '');
        
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
        $y = $pdf->GetY() + 1;

        // Ref 1: Entre Calles
        $ref1 = !empty($p['betweenStreets']) ? trim($p['betweenStreets']) : '';
        if ($ref1 !== '') {
            $pdf->SetXY($x0, $y);
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->Cell(12, 3.3, 'Ref 1:', 0, 0, 'L');
            $pdf->SetFont('Arial', '', 7);
            $pdf->Cell($usableW - 12, 3.3, self::utf8(self::truncar($ref1, 40)), 0, 1, 'L');
            $y += 3.5;
        }

        // Ref 2: Comentarios / Ubicación
        $ref2 = !empty($p['comentario']) ? trim($p['comentario']) : (!empty($p['Location']) ? trim($p['Location']) : '');
        if ($ref2 !== '' && $ref2 !== 'Sin comentarios') {
            $pdf->SetXY($x0, $y);
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->Cell(12, 3.3, 'Ref 2:', 0, 0, 'L');
            $pdf->SetFont('Arial', '', 7);
            $pdf->Cell($usableW - 12, 3.3, self::utf8(self::truncar($ref2, 40)), 0, 1, 'L');
            $y += 3.5;
        }

        self::dibujarLineaDivisoria($pdf, $x0, $y, $usableW);
        $y += 2.5;

        // ── 5. FECHA DE ENTREGA Y MONTO A COBRAR (COD) ────────────────────────
        $fechaEntrega = !empty($p['fecha_entrega']) ? date('d/m/Y', strtotime($p['fecha_entrega'])) : date('d/m/Y');
        
        $pdf->SetXY($x0, $y);
        $pdf->SetFont('Arial', 'B', 7.5);
        $pdf->Cell(25, 3.8, 'FECHA ENTREGA:', 0, 0, 'L');
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell($usableW - 25, 3.8, $fechaEntrega, 0, 1, 'L');
        $y += 4.5;

        // Monto
        $monedaSimbolo = '₡';
        $monedaCod = strtoupper($p['moneda_codigo'] ?? 'CRC');
        if ($monedaCod === 'GTQ') $monedaSimbolo = 'Q';
        elseif ($monedaCod === 'USD') $monedaSimbolo = '$';

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

        $pdf->SetXY($x0, $y + 1);
        $pdf->SetFont('Arial', 'B', 12);
        
        if ($totalCobrar > 0) {
            $montoTexto = $monedaSimbolo . ' ' . number_format($totalCobrar, 2, '.', ',');
        } else {
            $montoTexto = self::utf8('PAGADO / SIN COBRO (₡ 0.00)');
        }
        $pdf->Cell($usableW, 6.5, $montoTexto, 0, 1, 'C');
        $y += 10.5;

        self::dibujarLineaDivisoria($pdf, $x0, $y, $usableW);
        $y += 2.5;

        // ── 6. CÓDIGO QR Y RASTREO ───────────────────────────────────────────
        $qrSize = 20; // 20x20 mm
        $qrX = $x0 + 2;
        $qrY = $y;

        // Generar QR temporal
        $baseUrl = defined('RUTA_URL') ? RUTA_URL : 'https://rutaexlatam.com/';
        $trackingUrl = rtrim($baseUrl, '/') . '/seguimiento/ver/' . ($p['id'] ?? 0);
        $tempQrFile = self::generarImagenQR($trackingUrl);

        if ($tempQrFile && file_exists($tempQrFile)) {
            $pdf->Image($tempQrFile, $qrX, $qrY, $qrSize, $qrSize, 'PNG');
            @unlink($tempQrFile);
        }

        // Texto informativo junto al QR
        $infoX = $qrX + $qrSize + 3;
        $infoW = $usableW - ($qrSize + 5);
        
        $pdf->SetXY($infoX, $qrY + 1);
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->Cell($infoW, 3.2, self::utf8('ESCANEE PARA RASTREO'), 0, 1, 'L');

        $pdf->SetXY($infoX, $qrY + 4.5);
        $pdf->SetFont('Arial', '', 6.5);
        $pdf->Cell($infoW, 3, self::utf8('• Seguimiento en tiempo real'), 0, 1, 'L');

        $pdf->SetXY($infoX, $qrY + 7.5);
        $pdf->SetFont('Arial', '', 6.5);
        $pdf->Cell($infoW, 3, self::utf8('• Soporte y confirmación'), 0, 1, 'L');

        $pdf->SetXY($infoX, $qrY + 11);
        $pdf->SetFont('Arial', 'I', 6);
        $pdf->Cell($infoW, 3, self::utf8('Etiqueta ' . $indice . ' de ' . $total), 0, 1, 'L');

        $y += $qrSize + 2.5;

        // ── 7. PIE DE PÁGINA INSTITUCIONAL ───────────────────────────────────
        self::dibujarLineaDivisoria($pdf, $x0, $y, $usableW);
        $y += 2;

        $pdf->SetXY($x0, $y);
        $pdf->SetFont('Arial', 'B', 7.5);
        $pdf->Cell($usableW, 3.5, '* GRACIAS POR SU COMPRA *', 0, 1, 'C');
        $y += 3.5;

        $pdf->SetXY($x0, $y);
        $pdf->SetFont('Arial', '', 6.5);
        $pdf->Cell($usableW, 3, 'www.rutaexlatam.com', 0, 1, 'C');
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

            $tempPath = tempnam(sys_get_temp_dir(), 'qr_3nstar_') . '.png';
            file_put_contents($tempPath, $rawImage);
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
     * Convierte string UTF-8 a ISO-8859-1 compatible con FPDF de forma segura
     */
    private static function utf8(string $texto): string
    {
        return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
    }
}

