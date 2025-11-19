<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logistics API Documentation</title>

    <!-- Bootstrap CSS v5.2.1 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous" />

    <style>
        body {
            background-color: #ffffffff;
            font-family: 'Roboto', sans-serif;
            color: #212529 !important; /* ensure readable dark text (Bootstrap body color) */
        }

       

        header {
            background: linear-gradient(to right, #007bff, #6610f2);
            color: white;
            padding: 30px 0;
        }

        header h1 {
            font-size: 2.5rem;
            font-weight: bold;
        }

        .section-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 30px;
        }

        .section-title {
            color: #007bff;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .code-block {
            background: #ebeaeaff;
            border-left: 4px solid #007bff;
            padding: 15px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 0.95rem;
        }

        footer {
            background-color: #343a40;
            color: white;
            padding: 15px 0;
            text-align: center;
        }

        .badge-endpoint {
            font-size: 0.85rem;
            color: white;
            background: #6610f2;
            padding: 5px 10px;
            border-radius: 5px;
            margin-right: 5px;
        }
    </style>
</head>

<body>
    <header>
        <div class="container">
            <a class="navbar-brand" href="https://cruzvalle.website/">Home</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

        </div>
        <div class="container text-center">
            <h1>Logistics API Documentation</h1>
            <p class="lead">Guía práctica para consumir la API local del sistema de paquetería</p>
            <p>
                <a class="btn btn-outline-primary btn-sm" href="./paqueteria_api.yaml" target="_blank">Ver OpenAPI (YAML)</a>
            </p>
        </div>
        <div>
            
        </div>
    <div class="container mt-5" style="color: #212529;">
        <!-- Section: Documentation Overview -->
        <div class="section-container" style="color: #212529;">
            <h2 class="section-title">Quick Reference</h2>
            <p>This documentation describes the most used endpoints for integration: <strong>Authentication</strong>, <strong>Products</strong> and <strong>Orders (Pedidos)</strong>. Examples show request shape, response shape and common errors.</p>
            <p>OpenAPI (machine-readable): <a href="./paqueteria_api.yaml" target="_blank">paqueteria_api.yaml</a>.</p>
        </div>

        <!-- Table of contents / Quickstart -->
        <div class="section-container" id="quickstart">
            <h2 class="section-title">Quickstart</h2>
            <p>Minimal steps to call the API successfully:</p>
            <ol style="color: #212529;">
                <li>Obtain a JWT token: <code>POST /api/auth/login</code> with <code>{ "email", "password" }</code>.</li>
                <li>Take the token from the login response at <code>response.data.token</code> (important: the token is inside <code>data.token</code>, not at the top level).</li>
                <li>Call protected endpoints adding header: <code>Authorization: Bearer &lt;token&gt;</code>.</li>
                <li>When creating orders, provide required address fields and a unique <code>numero_orden</code>, and ensure the product has enough stock.</li>
            </ol>

            <h4>Example: get token (curl)</h4>
            <div class="code-block">curl -s -X POST "http://localhost/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"123456"}'</div>

            <h4>Login response (important)</h4>
            <div class="code-block">{
    "success": true,
    "message": "Login exitoso",
    "data": { "token": "&lt;JWT_TOKEN&gt;" }
}
            </div>

            <p>Note: always use the token value located at <code>data.token</code> when setting the <code>Authorization</code> header.</p>
        </div>

        <!-- Authentication (detailed table + examples) -->
        <div class="section-container" style="color: #212529;">
            <h2 class="section-title">Authentication (Login)</h2>
            <p>Obtain a JWT token. NOTE: the HTTP response envelope is <code>{ success, message, data: { token } }</code>.</p>

            <h4>Endpoint</h4>
            <div class="code-block"><span class="badge-endpoint">POST</span> /api/auth/login</div>

            <h4>Request body (JSON)</h4>
            <table class="table table-sm table-bordered">
                <thead class="table-light"><tr><th>Field</th><th>Type</th><th>Required</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><code>email</code></td><td>string (email)</td><td>yes</td><td>User email</td></tr>
                    <tr><td><code>password</code></td><td>string</td><td>yes</td><td>User password</td></tr>
                </tbody>
            </table>

            <h4>Example request</h4>
            <div class="code-block">{
    "email": "admin@example.com",
    "password": "123456"
}</div>

            <h4>Success response (200)</h4>
            <div class="code-block">{
    "success": true,
    "message": "Login exitoso",
    "data": { "token": "&lt;JWT_TOKEN&gt;" }
}</div>

            <h4>Usage</h4>
            <div class="code-block">Authorization: Bearer &lt;JWT_TOKEN from response.data.token&gt;</div>
        </div>

        <!-- Geographic & Reference Data (GeoInfo) -->
        <div class="section-container">
            <h2 class="section-title">Geographic & Reference Data (GeoInfo)</h2>
            <p>Endpoint to retrieve reference lists used by the front-end selects: countries, departments, municipalities, neighborhoods and currencies.</p>

            <h4>Endpoint</h4>
            <div class="code-block"><span class="badge-endpoint">GET</span> /api/geoinfo/listar</div>

            <p>Returns an object <code>data</code> containing arrays for <code>paises</code> (countries), <code>departamentos</code> (departments), <code>municipios</code> (municipalities), <code>barrios</code> (neighborhoods) and <code>monedas</code> (currencies). Useful to initialize forms and dependent selects.</p>

            <h4>Response example</h4>
            <div class="code-block">{
    "success": true,
    "message": "GeoInfo listed",
    "data": {
        "paises": [{ "id": 1, "nombre": "Nicaragua", "codigo_iso": "NI" }],
        "departamentos": [{ "id": 1, "nombre": "Managua", "id_pais": 1 }],
        "municipios": [{ "id": 1, "nombre": "Managua", "id_departamento": 1 }],
        "barrios": [{ "id": 1, "nombre": "Altamira", "id_municipio": 1 }],
        "monedas": [{ "id":1, "codigo":"USD", "nombre":"US Dollar", "tasa_usd":"1.0000" }]
    }
}</div>

        </div>

        <!-- Products: detailed -->
        <div class="section-container">
            <h2 class="section-title">Products (CRUD)</h2>
            <p>Manage products. Mutating endpoints require a valid <code>Authorization</code> header.</p>

            <h4>List products</h4>
            <div class="code-block"><span class="badge-endpoint">GET</span> /api/productos/listar</div>
            <p>Returns a list of products with aggregated stock (field <code>stock_total</code>).</p>
            <p>Optional query parameter: <code>include_stock=1</code> — when set, the response includes a <code>stock_entries</code> array for each product with recent stock movements (fields: <code>id</code>, <code>id_producto</code>, <code>id_usuario</code>, <code>cantidad</code>, <code>updated_at</code>).</p>
            <h4>Response (200)</h4>
            <div class="code-block">{
    "success": true,
    "data": [
        { "id": 1, "nombre": "Matcha Slim", "precio_usd": "25.00", "stock_total": 2 },
        { "id": 2, "nombre": "Protein Shake", "precio_usd": "40.00", "stock_total": 60 }
    ]
}</div>

            <h5>Response with include_stock=1 (example)</h5>
            <div class="code-block">{
    "success": true,
    "data": [
        {
            "id": 2,
            "nombre": "Protein Shake",
            "precio_usd": "40.00",
            "stock_total": "48",
            "stock_entries": [
                { "id": 28, "id_producto": 2, "id_usuario": 5, "cantidad": -11, "updated_at": "2025-11-19 11:57:38" },
                { "id": 9,  "id_producto": 2, "id_usuario": 1, "cantidad": 29,  "updated_at": "2025-10-31 12:56:52" }
            ]
        }
    ]
}
</div>

            <h4>Create product</h4>
            <div class="code-block"><span class="badge-endpoint">POST</span> /api/productos/crear</div>
            <table class="table table-sm table-bordered">
                <thead class="table-light"><tr><th>Field</th><th>Type</th><th>Required</th><th>Notes</th></tr></thead>
                <tbody>
                    <tr><td><code>nombre</code></td><td>string</td><td>yes</td><td>Unique-ish name used by lookup functions</td></tr>
                    <tr><td><code>descripcion</code></td><td>string</td><td>no</td><td>Optional</td></tr>
                    <tr><td><code>precio_usd</code></td><td>number</td><td>no</td><td>Decimal, stored as string in responses</td></tr>
                    <tr><td><code>stock</code></td><td>integer</td><td>no</td><td>Optional initial stock quantity — when provided the API inserts a stock movement for the authenticated user (or uses FALLBACK_USER_FOR_STOCK if configured).</td></tr>
                </tbody>
            </table>
            <h4>Example create request</h4>
            <div class="code-block">{
    "nombre": "Producto X",
    "descripcion": "Descripción opcional",
    "precio_usd": 9.5,
    "stock": 12
}</div>
            <h4>Success response</h4>
            <div class="code-block">{
    "success": true,
    "message": "Producto creado correctamente.",
    "data": { "id": 42, "stock_inserted": 99 }
}</div>
        </div>

        <!-- Orders / Pedidos: detailed -->
        <div class="section-container">
            <h2 class="section-title">Orders (Pedidos)</h2>
            <p>Endpoints to create, search and list orders. The server stores coordinates as a POINT; for API requests provide coordinates as <code>"lat,long"</code> or as numeric <code>latitud</code> and <code>longitud</code> fields.</p>

            <h4>Search order by numero_orden</h4>
            <div class="code-block"><span class="badge-endpoint">GET</span> /api/pedidos/buscar?numero_orden=&lt;NUMBER&gt;</div>
            <p>Requires Authorization header: <code>Authorization: Bearer &lt;token&gt;</code>. Returns the order data (latitud/longitud as numbers) when found.</p>
            <h5>Example (curl)</h5>
            <div class="code-block">curl -s "http://localhost/api/pedidos/buscar?numero_orden=90001" \
  -H "Authorization: Bearer &lt;JWT_TOKEN&gt;"</div>
            <h5>Success response (200)</h5>
            <div class="code-block">{
  "success": true,
  "message": "Pedido encontrado",
  "data": {
    "numero_orden": "90001",
    "destinatario": "Cliente Prueba",
    "telefono": "0999999999",
    "pais": "EC",
    "latitud": -0.180653,
    "longitud": -78.467838,
    "nombre_estado": "Pendiente"
  }
}</div>

            <hr />

            <h4>Create order</h4>
            <div class="code-block"><span class="badge-endpoint">POST</span> /api/pedidos/crear</div>

            <h4>Important notes</h4>
            <ul style="color: #212529;">
                <li>The API response envelope is <code>{ success, message, data }</code>.</li>
                <li>Fields <code>id_moneda</code>, <code>id_vendedor</code> and <code>id_proveedor</code> are stored in <code>pedidos</code> and have foreign key constraints — they must reference existing rows.</li>
                <li>Products are stored in <code>pedidos_productos</code> (pivot). The API accepts the simple format using top-level <code>producto</code> or <code>producto_id</code> plus <code>cantidad</code>. Internally the model supports creating an order with multiple items (see <code>crearPedidoConProductos</code> in the model).</li>
                <li>Stock validation: the system checks stock (via DB triggers and application checks). If stock is insufficient the request will fail with an error message.</li>
            </ul>

            <h4>Request fields (common)</h4>
            <table class="table table-sm table-bordered">
                <thead class="table-light"><tr><th>Field</th><th>Type</th><th>Required</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><code>numero_orden</code></td><td>integer</td><td>yes</td><td>Unique order number</td></tr>
                    <tr><td><code>destinatario</code></td><td>string</td><td>yes</td><td>Recipient name</td></tr>
                    <tr><td><code>telefono</code></td><td>string</td><td>yes</td><td>Phone number</td></tr>
                    <tr><td><code>coordenadas</code></td><td>string</td><td>yes</td><td>Latitude and longitude as <code>"lat,long"</code> (or provide <code>latitud</code> and <code>longitud</code> separately)</td></tr>
                    <tr><td><code>direccion</code></td><td>string</td><td>no</td><td>Full address</td></tr>
                    <tr><td><code>producto_id</code></td><td>integer</td><td>yes</td><td>Product id (preferred). Use the numeric <code>id</code> from <code>/api/productos/listar</code>. The API expects existing product IDs so stock checks work.</td></tr>
                    <tr><td><code>producto</code></td><td>string</td><td>no (deprecated)</td><td>Deprecated: providing product by name is no longer supported in the API — use <code>producto_id</code>.</td></tr>
                    <tr><td><code>cantidad</code></td><td>integer</td><td>yes</td><td>Quantity requested (for the single-product payload)</td></tr>
                    <tr><td><code>productos</code></td><td>array</td><td>no</td><td>Advanced: array of items { "producto_id": int, "cantidad": int } — internal model supports multiple items, see note below</td></tr>
                    <tr><td><code>id_moneda</code></td><td>integer</td><td>recommended</td><td>FK to <code>monedas.id</code>. Use the <code>id</code> value from <code>/api/geoinfo/listar</code> → <code>monedas</code>.</td></tr>
                    <tr><td><code>id_vendedor</code></td><td>integer</td><td>optional</td><td>FK to <code>usuarios.id</code> for seller/repartidor. Use IDs from your users administration or <code>/api/usuarios/listar</code> if available.</td></tr>
                    <tr><td><code>id_proveedor</code></td><td>integer</td><td>optional</td><td>FK to <code>usuarios.id</code> for provider. When calling the API you can omit this and the authenticated token's user will be used. To provide explicitly, use a numeric id from your users list.</td></tr>
                    <tr><td><code>pais</code></td><td>string</td><td>recommended</td><td>Country identifier - use the <code>id</code> from <code>/api/geoinfo/listar</code> → <code>paises</code> (numeric) or the country code string depending on your frontend mapping. Prefer numeric ids where the field expects an integer.</td></tr>
                    <tr><td><code>departamento</code></td><td>integer|string</td><td>recommended</td><td>Department id (numeric) or name. For numeric ids, use <code>/api/geoinfo/listar</code> → <code>departamentos</code>.</td></tr>
                    <tr><td><code>municipio</code></td><td>integer|string</td><td>recommended</td><td>Municipality id (numeric) or name. For numeric ids, use <code>/api/geoinfo/listar</code> → <code>municipios</code>.</td></tr>
                </tbody>
            </table>

                        <h4>Example create request (single product)</h4>
            <div class="code-block">{
  "numero_orden": 90001,
  "destinatario": "Cliente Prueba",
  "telefono": "0999999999",
    "producto_id": 12,
    "cantidad": 1,
  "coordenadas": "-0.180653,-78.467838",
  "direccion": "Calle Falsa 123",
  "id_moneda": 1,
  "id_vendedor": 5,
  "id_proveedor": 6,
  "pais": "EC",
  "departamento": "Pichincha",
  "municipio": "Quito"
}</div>

                        <h4>Example create request (multiple products)</h4>
                        <p>Provide product IDs in the <code>productos</code> array. Each item must include <code>producto_id</code> (integer) and <code>cantidad</code> (int).</p>
            <div class="code-block">{
  "numero_orden": 90002,
  "destinatario": "Cliente Prueba",
  "telefono": "0999999999",
    "productos": [
        { "producto_id": 12, "cantidad": 2 },
        { "producto_id": 13, "cantidad": 1 }
    ],
  "coordenadas": "-0.180653,-78.467838",
  "direccion": "Calle Falsa 123",
  "id_moneda": 1
}</div>

                                                <h4>Ejemplo que funciona (payload real)</h4>
                                                <p>Ejemplo de JSON que se ha probado y funciona con el endpoint <code>/api/pedidos/crear</code> — usa <code>productos</code> con <code>producto_id</code> (aquí el producto 2: "Protein Shake").</p>
                        <div class="code-block">{
    "numero_orden": 1700385600,
    "destinatario": "Proveedor Prueba",
    "telefono": "0999999999",
    "productos": [
        { "producto_id": 2, "cantidad": 1 }
    ],
    "coordenadas": "-0.180653,-78.467838",
    "direccion": "Calle Falsa 123",
    "id_moneda": 1,
    "pais": "EC",
    "departamento": "Pichincha",
    "municipio": "Quito",
    "comentario": "Pedido de prueba via Postman"
}</div>

            <h4>Example (curl) - create order</h4>
            <div class="code-block">curl -s -X POST "http://localhost/api/pedidos/crear" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer &lt;JWT_TOKEN&gt;" \
  -d '{ "numero_orden": 90001, "destinatario": "Cliente Prueba", "telefono": "0999999999", "producto": "Producto X", "cantidad": 1, "coordenadas": "-0.180653,-78.467838" }'</div>

            <h4>Possible successful response</h4>
            <div class="code-block">{
    "success": true,
    "message": "Pedido creado correctamente.",
    "data": 15
}</div>

            <h4>Examples of error responses</h4>
            <div class="code-block">{
    "success": false,
    "message": "Error al insertar el pedido: Stock insuficiente para el producto ID 11. Disponible: 0, requerido: 1"
}

{
    "success": false,
    "message": "Error al insertar el pedido: Cannot add or update a child row: a foreign key constraint fails (...)"
}</div>
        </div>

        <!-- Troubleshooting / tips -->
        <div class="section-container">
            <h2 class="section-title">Troubleshooting & tips</h2>
            <ul style="color: #212529;">
                <li>If you get FK errors when creating orders, check that <code>id_moneda</code>, <code>id_vendedor</code> and <code>id_proveedor</code> exist in their respective tables.</li>
                <li>To create a product and give it stock (dev): create product via <code>/api/productos/crear</code>, then use the stock UI or insert into <code>stock</code> table.</li>
                <li>Coordinates must be provided; the API will reject requests missing valid coordinates.</li>
                <li>Address fields required: the API validates <code>pais</code>, <code>departamento</code> and <code>municipio</code>. If any are missing you will receive a validation error listing the missing fields.</li>
                <li><strong>numero_orden</strong> must be unique. If you get <em>"El número de orden ya existe"</em>, use a different number.</li>
                <li>If you receive <em>"Stock insuficiente"</em> for a product, either increase stock for that product (via stock creation) or reduce the requested <code>cantidad</code>.</li>
            </ul>
        </div>
    </div>

    <footer>
        <p>&copy; 2025 Logistics API - All Rights Reserved</p>
    </footer>
    <!-- Bootstrap JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
        integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r"
        crossorigin="anonymous"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js"
        integrity="sha384-BBtl+eGJRgqQAUMxJ7pMwbEyER4l1g+O15P+16Ep7Q9Q+zqX6gSbd85u4mG4QzX+"
        crossorigin="anonymous"></script>
</body>

</html>