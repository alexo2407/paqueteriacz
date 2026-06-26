<?php
require_once 'c:/xampp/htdocs/paqueteriacz/services/providers/LogisProProvider.php';

$baseUrl = 'https://apigateway.logispro.app';
$credentials = [
    'userName' => 'rutaexmex.api',
    'password' => 'muaYI51j9J*2'
];
$config = [
    'auth_endpoint'  => '/api/AccountApi',
    'order_endpoint' => '/api/Orders/OrderAndOrderDetail'
];

$provider = new LogisProProvider($baseUrl, $credentials, $config);

try {
    echo "Authenticating...\n";
    $authData = $provider->authenticate();
    echo "Auth Success: CustomersId = " . $authData['customersId'] . "\n";
    
    $pedido = [
        'id' => 2606,
        'numero_orden' => '42123',
        'destinatario' => 'Prueba Rechazado via api 2',
        'direccion' => 'Zona 10 , avenida La reforma 9 55, edificio reforma 10',
        'telefono' => '50255719448',
        'comentario' => '14.60421913655462, -90.51523263267117 Edificio Reforma 10, al frente de Banrural, en el mismo edificio donde se encuentra La Tazona Coffee Shop ',
        'precio_total_local' => 673,
        'postalCode' => 1057
    ];
    
    $productos = [
        [
            'sku' => 'SUPL-655',
            'cantidad' => 49
        ]
    ];
    
    echo "Creating Order...\n";
    $result = $provider->createOrder($pedido, $productos, $authData);
    print_r($result);

} catch (Exception $e) {
    echo "CAUGHT EXCEPTION: " . $e->getMessage() . " (Code: " . $e->getCode() . ")\n";
    $lastResp = $provider->getLastResponse();
    echo "Last Response body: " . ($lastResp['body'] ?? 'null') . "\n";
    echo "Last Response HTTP: " . ($lastResp['http_status'] ?? 'null') . "\n";
}
