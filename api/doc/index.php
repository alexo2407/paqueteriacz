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

        <!-- Products: detailed -->
        <div class="section-container">
            <h2 class="section-title">Products (CRUD)</h2>
            <p>Manage products. Mutating endpoints require a valid <code>Authorization</code> header.</p>

            <h4>List products</h4>
            <div class="code-block"><span class="badge-endpoint">GET</span> /api/productos/listar</div>
            <p>Returns a list of products with aggregated stock (field <code>stock_total</code>).</p>
            <h4>Response (200)</h4>
            <div class="code-block">{
    "success": true,
    "data": [
        { "id": 1, "nombre": "Matcha Slim", "precio_usd": "25.00", "stock_total": 2 },
        { "id": 2, "nombre": "Protein Shake", "precio_usd": "40.00", "stock_total": 60 }
    ]
}</div>

            <h4>Create product</h4>
            <div class="code-block"><span class="badge-endpoint">POST</span> /api/productos/crear</div>
            <table class="table table-sm table-bordered">
                <thead class="table-light"><tr><th>Field</th><th>Type</th><th>Required</th><th>Notes</th></tr></thead>
                <tbody>
                    <tr><td><code>nombre</code></td><td>string</td><td>yes</td><td>Unique-ish name used by lookup functions</td></tr>
                    <tr><td><code>descripcion</code></td><td>string</td><td>no</td><td>Optional</td></tr>
                    <tr><td><code>precio_usd</code></td><td>number</td><td>no</td><td>Decimal, stored as string in responses</td></tr>
                </tbody>
            </table>
            <h4>Example create request</h4>
            <div class="code-block">{
    "nombre": "Producto X",
    "descripcion": "Descripción opcional",
    "precio_usd": 9.5
}</div>
            <h4>Success response</h4>
            <div class="code-block">{
    "success": true,
    "message": "Producto creado correctamente.",
    "id": 42
}</div>
        </div>

        <!-- Orders / Pedidos: detailed -->
        <div class="section-container">
            <h2 class="section-title">Orders (Pedidos)</h2>
            <p>Endpoint to create and list orders. The server expects coordinates in <code>"lat,long"</code> format (stored as POINT).</p>

            <h4>Create order</h4>
            <div class="code-block"><span class="badge-endpoint">POST</span> /api/pedidos/crear</div>

            <h4>Important notes</h4>
            <ul style="color: #212529;">
                <li>The API response envelope is <code>{ success, message, data }</code>.</li>
                <li>Fields <code>id_moneda</code>, <code>id_vendedor</code> and <code>id_proveedor</code> are stored in <code>pedidos</code> and have foreign key constraints — they must reference existing rows.</li>
                <li>Products are stored in <code>pedidos_productos</code> (pivot). If you provide <code>producto</code> (string) the system will try to resolve or create it; if you provide <code>producto_id</code> it will use that id.</li>
                <li>Stock validation: the system checks stock (via DB triggers and application checks). If stock is insufficient the request will fail with an error message.</li>
            </ul>

            <h4>Request fields (common)</h4>
            <table class="table table-sm table-bordered">
                <thead class="table-light"><tr><th>Field</th><th>Type</th><th>Required</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><code>numero_orden</code></td><td>integer</td><td>yes</td><td>Unique order number</td></tr>
                    <tr><td><code>destinatario</code></td><td>string</td><td>yes</td><td>Recipient name</td></tr>
                    <tr><td><code>telefono</code></td><td>string</td><td>yes</td><td>Phone number</td></tr>
                    <tr><td><code>coordenadas</code></td><td>string</td><td>yes</td><td>Latitude and longitude as <code>"lat,long"</code></td></tr>
                    <tr><td><code>direccion</code></td><td>string</td><td>no</td><td>Full address</td></tr>
                    <tr><td><code>producto</code></td><td>string</td><td>yes (or use producto_id)</td><td>Product name to resolve or create</td></tr>
                    <tr><td><code>producto_id</code></td><td>integer</td><td>yes (or use producto)</td><td>Prefer this when you already know the id</td></tr>
                    <tr><td><code>cantidad</code></td><td>integer</td><td>yes</td><td>Quantity requested</td></tr>
                    <tr><td><code>id_moneda</code></td><td>integer</td><td>recommended</td><td>FK to <code>monedas.id</code></td></tr>
                    <tr><td><code>id_vendedor</code></td><td>integer</td><td>recommended</td><td>FK to <code>usuarios.id</code> (assigned seller)</td></tr>
                    <tr><td><code>id_proveedor</code></td><td>integer</td><td>recommended</td><td>FK to <code>usuarios.id</code> (supplier)</td></tr>
                </tbody>
            </table>

            <h4>Example create request (minimal)</h4>
            <div class="code-block">{
    "numero_orden": 90001,
    "destinatario": "Cliente Prueba",
    "telefono": "0999999999",
    "producto": "Producto X",
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