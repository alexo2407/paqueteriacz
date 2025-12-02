## Ejemplo de JSON para crear un pedido

### Endpoint
```
POST http://localhost/paqueteriacz/api/pedidos/crear
```

### Headers requeridos
```
Content-Type: application/json
Authorization: Bearer {tu_token_jwt}
```

### Cuerpo del request (JSON)

```json
{
  "numero_orden": 99010,
  "destinatario": "Juan Pérez",
  "telefono": "88887777",
  "producto_id": 21,
  "cantidad": 1,
  "coordenadas": "-0.180653,-78.467838",
  "direccion": "Av. Principal #123, Edificio Central",
  "zona": "Centro",
  "precio_local": 250.50,
  "id_moneda": 1,
  "id_vendedor": 1,
  "id_proveedor": 1,
  "id_pais": 1,
  "id_departamento": 1,
  "id_municipio": 1,
  "id_barrio": 1,
  "comentario": "Entregar en horario de oficina"
}
```

### Campos opcionales adicionales

```json
{
  "numero_orden": 99011,
  "destinatario": "María González",
  "telefono": "99998888",
  
  // Opción 1: Un solo producto
  "producto_id": 21,
  "cantidad": 2,
  
  // Opción 2: Múltiples productos (alternativa a producto_id + cantidad)
  "productos": [
    {
      "producto_id": 21,
      "cantidad": 2,
      "cantidad_devuelta": 0
    },
    {
      "producto_id": 18,
      "cantidad": 1,
      "cantidad_devuelta": 0
    }
  ],
  
  // Coordenadas (opción 1: como string)
  "coordenadas": "-0.180653,-78.467838",
  
  // Coordenadas (opción 2: separadas)
  "latitud": -0.180653,
  "longitud": -78.467838,
  
  "direccion": "Calle Principal #456",
  "zona": "Norte",
  "precio_local": 500.00,
  "precio_usd": 13.58,
  "id_moneda": 1,
  "id_vendedor": 1,
  "id_proveedor": 1,
  "id_estado": 1,
  "id_pais": 1,
  "id_departamento": 1,
  "id_municipio": 1,
  "id_barrio": 1,
  "comentario": "Llamar antes de entregar"
}
```

### Respuesta exitosa (200)

```json
{
  "success": true,
  "message": "Pedido creado correctamente.",
  "data": 99010
}
```

### Respuestas de error

**Stock insuficiente (200 con success: false)**
```json
{
  "success": false,
  "message": "Stock insuficiente para el producto ID 18. Disponible: 0, Solicitado: 1."
}
```

**Número de orden duplicado (200 con success: false)**
```json
{
  "success": false,
  "message": "El número de orden ya existe en la base de datos."
}
```

**Sin autenticación (401)**
```json
{
  "success": false,
  "message": "Token requerido"
}
```

**Datos inválidos (400)**
```json
{
  "success": false,
  "message": "Datos inválidos o vacíos"
}
```

### Notas importantes

1. **Autenticación**: El endpoint requiere un token JWT válido en el header `Authorization: Bearer {token}`
2. **Stock**: El sistema valida que haya stock disponible antes de crear el pedido
3. **Número de orden**: Debe ser único en el sistema
4. **Producto**: Usar `producto_id` (no nombre del producto)
5. **Coordenadas**: Se pueden enviar como string "lat,long" o como campos separados
6. **Productos múltiples**: Usar el array `productos` en lugar de `producto_id` + `cantidad`

### Cómo obtener un token

```bash
curl -X POST http://localhost/paqueteriacz/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "tu_email@example.com",
    "password": "tu_password"
  }'
```

### Ejemplo completo con curl

```bash
# 1. Obtener token
TOKEN=$(curl -s -X POST http://localhost/paqueteriacz/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"admin123"}' \
  | grep -o '"token":"[^"]*' | cut -d'"' -f4)

# 2. Crear pedido
curl -X POST http://localhost/paqueteriacz/api/pedidos/crear \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "numero_orden": 99010,
    "destinatario": "Juan Pérez",
    "telefono": "88887777",
    "producto_id": 21,
    "cantidad": 1,
    "coordenadas": "-0.180653,-78.467838",
    "direccion": "Av. Principal #123",
    "id_moneda": 1,
    "id_vendedor": 1,
    "id_proveedor": 1,
    "id_pais": 1,
    "id_departamento": 1,
    "id_municipio": 1
  }'
```
