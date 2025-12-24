#!/bin/bash
# Script para probar el API de creación de pedidos

echo "=== Probando API de Creación de Pedidos ==="
echo ""

# Primero obtener un token de autenticación
echo "1. Obteniendo token de autenticación..."
LOGIN_RESPONSE=$(curl -s -X POST http://localhost/paqueteriacz/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "admin123"
  }')

echo "Respuesta login: $LOGIN_RESPONSE"
TOKEN=$(echo $LOGIN_RESPONSE | grep -o '"token":"[^"]*' | cut -d'"' -f4)

if [ -z "$TOKEN" ]; then
  echo "❌ No se pudo obtener el token. Probando sin autenticación..."
  echo ""
  echo "2. Creando pedido SIN autenticación..."
  
  curl -X POST http://localhost/paqueteriacz/api/pedidos/crear \
    -H "Content-Type: application/json" \
    -d @ejemplo_pedido.json \
    -w "\n\nHTTP Status: %{http_code}\n"
else
  echo "✓ Token obtenido: ${TOKEN:0:20}..."
  echo ""
  echo "2. Creando pedido CON autenticación..."
  
  curl -X POST http://localhost/paqueteriacz/api/pedidos/crear \
    -H "Content-Type: application/json" \
    -H "Authorization: Bearer $TOKEN" \
    -d @ejemplo_pedido.json \
    -w "\n\nHTTP Status: %{http_code}\n"
fi

echo ""
echo "=== Fin de la prueba ==="
