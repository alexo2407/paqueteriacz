<?php
/**
 * CAEXProvider
 *
 * Conector específico para la API SOAP/XML de CAEX.
 */

require_once __DIR__ . '/BaseProvider.php';

class CAEXProvider extends BaseProvider
{
    /**
     * Autenticación con CAEX.
     * Retorna las credenciales para incluirlas en el SOAP Body.
     */
    public function authenticate()
    {
        $login = $this->credentials['userName'] ?? '';
        $pass  = $this->credentials['password'] ?? '';

        if (empty($login) || empty($pass)) {
            throw new Exception("CAEX auth: Login y Password requeridos en credenciales.");
        }

        return [
            'login'    => $login,
            'password' => $pass
        ];
    }

    /**
     * Crear orden de envío en CAEX (SOAP).
     */
    public function createOrder(array $pedido, array $productos, array $authData)
    {
        $url = $this->baseUrl . ($this->config['order_endpoint'] ?? '/wsCAEXLogisticsSB/wsCAEXLogisticsSB.asmx');
        $xmlPayload = $this->mapearCampos($pedido, $productos, $authData);

        $headers = [
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: "http://www.caexlogistics.com/ServiceBus/GenerarGuia"',
            'Content-Length: ' . strlen($xmlPayload)
        ];

        $response = $this->httpRequest('POST', $url, $headers, $xmlPayload, 30);

        if ($response['error']) {
            throw new Exception("Error de conexión con CAEX (SOAP): " . $response['error']);
        }

        $httpStatus = $response['http_status'];
        $body = $response['body'];

        $success = false;
        $externalOrderId = null;
        $errorMsg = 'Error desconocido al procesar XML de CAEX';

        if ($httpStatus === 200 && !empty($body)) {
            try {
                // Registrar namespaces para buscar elementos en el XML
                $xml = new SimpleXMLElement($body);
                $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
                $xml->registerXPathNamespace('ns', 'http://www.caexlogistics.com/ServiceBus');

                // Buscar resultado dentro del cuerpo de la respuesta SOAP
                $resultNode = $xml->xpath('//ns:GenerarGuiaResult');
                if (!empty($resultNode)) {
                    // Intentar extraer el ID de la guía
                    // Mapeo flexible de tags usuales en CAEX (GuiaID, CodigoGuia, NumeroGuia, etc.)
                    $externalOrderId = (string)$resultNode[0]->GuiaID ?? (string)$resultNode[0]->CodigoGuia ?? (string)$resultNode[0]->NumeroGuia ?? null;
                    if (empty($externalOrderId)) {
                        // Intentar buscar de manera recursiva si no está en el primer nivel del result
                        $xmlResponse = $resultNode[0];
                        if (isset($xmlResponse->Guia)) {
                            $externalOrderId = (string)$xmlResponse->Guia;
                        } elseif (isset($xmlResponse->Codigo)) {
                            $externalOrderId = (string)$xmlResponse->Codigo;
                        }
                    }
                    $success = !empty($externalOrderId);
                }
                
                if (!$success) {
                    // Buscar si hay SOAP Fault
                    $faultNode = $xml->xpath('//soap:Fault');
                    if (!empty($faultNode)) {
                        $errorMsg = (string)$faultNode[0]->faultstring ?? 'SOAP Fault';
                    } else {
                        // Si no hay Fault, pero no pudimos extraer el ID, mostrar la respuesta
                        $errorMsg = "No se pudo extraer el ID de guía de la respuesta: " . substr($body, 0, 500);
                    }
                }
            } catch (Exception $ex) {
                $errorMsg = "Error parseando XML de respuesta: " . $ex->getMessage();
            }
        } else {
            // Intentar extraer error de SOAP Fault si el status no es 200 (SOAP suele responder con 500 en fallos)
            if (!empty($body)) {
                try {
                    $xml = new SimpleXMLElement($body);
                    $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
                    $faultNode = $xml->xpath('//soap:Fault');
                    if (!empty($faultNode)) {
                        $errorMsg = (string)$faultNode[0]->faultstring ?? 'SOAP Fault';
                    } else {
                        $errorMsg = "HTTP Status " . $httpStatus;
                    }
                } catch (Exception $ex) {
                    $errorMsg = "HTTP Status " . $httpStatus;
                }
            } else {
                $errorMsg = "HTTP Status " . $httpStatus;
            }
        }

        $result = [
            'success'           => $success,
            'external_order_id' => $externalOrderId,
            'response'          => ['raw_xml' => $body],
            'http_status'       => $httpStatus,
        ];

        if (!$result['success']) {
            throw new Exception("CAEX GenerarGuia falló: " . $errorMsg, (int)$httpStatus);
        }

        return $result;
    }

    /**
     * Mapear campos internos al SOAP XML Envelope para GenerarGuia.
     */
    public function mapearCampos(array $pedido, array $productos, array $authData)
    {
        // 1. Datos del Remitente (estáticos o configurables)
        $remitenteNombre = htmlspecialchars($this->config['remitente_nombre'] ?? 'RutaEx Latam', ENT_XML1);
        $remitenteDir    = htmlspecialchars($this->config['remitente_direccion'] ?? 'Oficina Central', ENT_XML1);
        $remitenteTel    = htmlspecialchars($this->config['remitente_telefono'] ?? '22000000', ENT_XML1);

        // 2. Datos del Destinatario
        $destNombre = htmlspecialchars($pedido['destinatario'] ?? '', ENT_XML1);
        $destDir    = htmlspecialchars($pedido['direccion'] ?? '', ENT_XML1);
        $destTel    = htmlspecialchars($pedido['telefono'] ?? '', ENT_XML1);
        $destNit    = htmlspecialchars($pedido['nit'] ?? 'CF', ENT_XML1); // Consumidor Final por defecto

        // 3. Monto COD y cargos
        $montoCod = (float)($pedido['precio_total_local'] ?? 0);
        
        // Determinar si es cobro contra entrega a partir de la configuración
        $paymentMethodId = (int)($this->config['payment_method_id'] ?? 34); 
        $isCod = ($paymentMethodId === 34);
        if (!$isCod) {
            $montoCod = 0.00;
        }

        $fechaRecoleccion = date('Y-m-d\TH:i:s');

        // 4. Mapear bultos/piezas
        $piezasXml = '';
        $numPiezas = 0;
        foreach ($productos as $p) {
            $cantidad = max(0, (int)($p['cantidad'] ?? 0) - (int)($p['cantidad_devuelta'] ?? 0));
            if ($cantidad <= 0) continue;
            for ($i = 0; $i < $cantidad; $i++) {
                $piezasXml .= '          <Pieza />' . "\n";
                $numPiezas++;
            }
        }
        
        // Garantizar al menos una pieza de seguridad
        if ($numPiezas === 0) {
            $piezasXml = '          <Pieza />' . "\n";
        }

        // Construir el Envelope completo
        $xml = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <GenerarGuia xmlns="http://www.caexlogistics.com/ServiceBus">
      <Autenticacion>
        <Login>' . htmlspecialchars($authData['login'], ENT_XML1) . '</Login>
        <Password>' . htmlspecialchars($authData['password'], ENT_XML1) . '</Password>
      </Autenticacion>
      <ListaRecolecciones>
        <DatosRecoleccion>
          <RecoleccionID>' . htmlspecialchars($pedido['numero_orden'], ENT_XML1) . '</RecoleccionID>
          <RemitenteNombre>' . $remitenteNombre . '</RemitenteNombre>
          <RemitenteDireccion>' . $remitenteDir . '</RemitenteDireccion>
          <RemitenteTelefono>' . $remitenteTel . '</RemitenteTelefono>
          <DestinatarioNombre>' . $destNombre . '</DestinatarioNombre>
          <DestinatarioDireccion>' . $destDir . '</DestinatarioDireccion>
          <DestinatarioTelefono>' . $destTel . '</DestinatarioTelefono>
          <DestinatarioContacto>' . $destNombre . '</DestinatarioContacto>
          <DestinatarioNIT>' . $destNit . '</DestinatarioNIT>
          <ReferenciaCliente1>' . htmlspecialchars($pedido['numero_orden'], ENT_XML1) . '</ReferenciaCliente1>
          <ReferenciaCliente2></ReferenciaCliente2>
          <CodigoPobladoDestino>' . htmlspecialchars($pedido['codigo_postal'] ?? $pedido['postalCode'] ?? '', ENT_XML1) . '</CodigoPobladoDestino>
          <CodigoPobladoOrigen>' . htmlspecialchars($this->config['codigo_poblado_origen'] ?? '0101', ENT_XML1) . '</CodigoPobladoOrigen>
          <TipoServicio>' . htmlspecialchars($this->config['tipo_servicio'] ?? 'Estandard', ENT_XML1) . '</TipoServicio>
          <MontoCOD>' . number_format($montoCod, 2, '.', '') . '</MontoCOD>
          <FormatoImpresion>' . htmlspecialchars($this->config['formato_impresion'] ?? 'PDF', ENT_XML1) . '</FormatoImpresion>
          <CodigoCredito>' . htmlspecialchars($this->config['codigo_credito'] ?? '', ENT_XML1) . '</CodigoCredito>
          <MontoAsegurado>0.00</MontoAsegurado>
          <Observaciones>' . htmlspecialchars($pedido['comentario'] ?? '', ENT_XML1) . '</Observaciones>
          <CodigoReferencia>0</CodigoReferencia>
          <FechaRecoleccion>' . $fechaRecoleccion . '</FechaRecoleccion>
          <TipoEntrega>' . (int)($this->config['tipo_entrega'] ?? 1) . '</TipoEntrega>
          <TokenDireccion></TokenDireccion>
          <Piezas>
' . $piezasXml . '          </Piezas>
        </DatosRecoleccion>
      </ListaRecolecciones>
    </GenerarGuia>
  </soap:Body>
</soap:Envelope>';

        return $xml;
    }
}
