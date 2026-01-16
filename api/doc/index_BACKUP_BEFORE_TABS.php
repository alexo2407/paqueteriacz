<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logistics API Documentation</title>

    <!-- Bootstrap CSS v5.2.1 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous" />
    <!-- Editor-like font -->
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    <!-- Prism.js theme (editor-like dark theme) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css" />
    <!-- Prism line numbers plugin CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/line-numbers/prism-line-numbers.min.css" />

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
            background: #2d2d2d; /* dark editor background */
            color: #dcdcdc;
            padding: 14px;
            border-radius: 8px;
            font-family: 'Fira Code', ui-monospace, SFMono-Regular, Menlo, Monaco, 'Roboto Mono', monospace;
            font-size: 0.95rem;
            overflow: auto;
            box-shadow: 0 6px 18px rgba(0,0,0,0.25);
            border-left: 4px solid rgba(255,255,255,0.03);
        }

        /* Prism token colors tweaks to look more like a code editor */
        .token.property, .token.key { color: #9cdcfe; }
        .token.string { color: #ce9178; }
        .token.number { color: #b5cea8; }
        .token.boolean, .token.null { color: #569cd6; }
        .token.punctuation, .token.operator { color: #d4d4d4; }
        .token.comment { color: #6a9955; font-style: italic; }

        /* Print-friendly styles */
        @media print {
            .code-block {
                background: #ffffff !important;
                color: #000000 !important;
                box-shadow: none !important;
                border-left: 4px solid #e0e0e0 !important;
                font-size: 0.85rem !important;
                overflow: visible !important;
                white-space: pre-wrap !important;
                word-break: break-word !important;
            }
            /* Make line numbers visible and subtle when printing */
            .line-numbers .line-numbers-rows {
                color: #6c757d !important;
            }
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
            <a class="navbar-brand" href="/">Home</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

        </div>
        <div class="container text-center">
            <h1>Logistics API Documentation</h1>
            <p class="lead">Guía práctica para consumir la API local del sistema de paquetería</p>
            <p>
                <a class="btn btn-outline-primary btn-sm" href="./App_api.yaml" target="_blank">Ver OpenAPI (YAML)</a>
            </p>
        </div>
        <div>
            
        </div>
    <div class="container mt-5" style="color: #212529;">
        <!-- Section: Documentation Overview -->
        <div class="section-container" style="color: #212529;">
            <h2 class="section-title">Quick Reference</h2>
            <p>This documentation describes the most used endpoints for integration: <strong>Authentication</strong>, <strong>Products</strong> and <strong>Orders (Pedidos)</strong>. Examples show request shape, response shape and common errors.</p>
            <p>OpenAPI (machine-readable): <a href="./App_api.yaml" target="_blank">App_api.yaml</a>.</p>
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
            <div class="code-block">curl -s -X POST "{API_BASE_URL}/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"<REPLACE_WITH_PASSWORD>"}'</div>

            <h4>Login response (important)</h4>
            <pre class="code-block line-numbers"><code class="language-json">{
    "success": true,
    "message": "Login exitoso",
    "data": { "token": "&lt;JWT_TOKEN&gt;" }
}
</code></pre>

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
            <pre class="code-block line-numbers"><code class="language-json">{
    "email": "admin@example.com",
    "password": "123456"
}</code></pre>

            <h4>Success response (200)</h4>
            <pre class="code-block line-numbers"><code class="language-json">{
    "success": true,
    "message": "Login exitoso",
    "data": { "token": "&lt;JWT_TOKEN&gt;" }
}</code></pre>

            <h4>Usage</h4>
            <div class="code-block">Authorization: Bearer &lt;JWT_TOKEN from response.data.token&gt;</div>
            <p style="color:#6c757d;font-size:0.9rem;">Security note: never embed real credentials or long-lived tokens in public documentation or examples. Use placeholders and environment variables when running commands.</p>
        </div>

        <!-- Geographic & Reference Data (GeoInfo) -->
        <div class="section-container">
            <h2 class="section-title">Geographic & Reference Data (GeoInfo)</h2>
            <p>Endpoint to retrieve reference lists used by the front-end selects: countries, departments, municipalities, neighborhoods and currencies.</p>

            <h4>Endpoint</h4>
            <div class="code-block"><span class="badge-endpoint">GET</span> /api/geoinfo/listar</div>

            <p>Returns an object <code>data</code> containing arrays for <code>paises</code> (countries), <code>departamentos</code> (departments), <code>municipios</code> (municipalities), <code>barrios</code> (neighborhoods) and <code>monedas</code> (currencies). Useful to initialize forms and dependent selects.</p>

            <h4>Response example</h4>
            <pre class="code-block line-numbers"><code class="language-json">{
    "success": true,
    "message": "GeoInfo listed",
    "data": {
        "paises": [{ "id": 1, "nombre": "Nicaragua", "codigo_iso": "NI" }],
        "departamentos": [{ "id": 1, "nombre": "Managua", "id_pais": 1 }],
        "municipios": [{ "id": 1, "nombre": "Managua", "id_departamento": 1 }],
        "barrios": [{ "id": 1, "nombre": "Altamira", "id_municipio": 1 }],
        "monedas": [{ "id":1, "codigo":"USD", "nombre":"US Dollar", "tasa_usd":"1.0000" }]
    }
}</code></pre>

        </div>

        <!-- Geographic Data Management (CRUD) -->
        <div class="section-container">
            <h2 class="section-title">Geographic Data Management (CRUD)</h2>
            <p>Endpoints to manage Paises, Departamentos, Municipios, and Barrios. All endpoints support GET, POST, PUT, DELETE.</p>

            <h4>Paises</h4>
            <div class="code-block"><span class="badge-endpoint">GET</span> /api/geoinfo/paises?id={id} (optional)</div>
            <div class="code-block"><span class="badge-endpoint">POST</span> /api/geoinfo/paises</div>
            <div class="code-block"><span class="badge-endpoint">PUT</span> /api/geoinfo/paises?id={id}</div>
            <div class="code-block"><span class="badge-endpoint">DELETE</span> /api/geoinfo/paises?id={id}</div>
            
            <h5>Payload (POST/PUT)</h5>
            <pre class="code-block line-numbers"><code class="language-json">{
    "nombre": "Nombre del Pais",
    "codigo_iso": "NP"
}</code></pre>

            <hr>

            <h4>Departamentos</h4>
            <div class="code-block"><span class="badge-endpoint">GET</span> /api/geoinfo/departamentos?id={id} (optional)</div>
            <div class="code-block"><span class="badge-endpoint">POST</span> /api/geoinfo/departamentos</div>
            <div class="code-block"><span class="badge-endpoint">PUT</span> /api/geoinfo/departamentos?id={id}</div>
            <div class="code-block"><span class="badge-endpoint">DELETE</span> /api/geoinfo/departamentos?id={id}</div>

            <h5>Payload (POST/PUT)</h5>
            <pre class="code-block line-numbers"><code class="language-json">{
    "nombre": "Nombre del Departamento",
    "id_pais": 1
}</code></pre>

            <hr>

            <h4>Municipios</h4>
            <div class="code-block"><span class="badge-endpoint">GET</span> /api/geoinfo/municipios?id={id} (optional)</div>
            <div class="code-block"><span class="badge-endpoint">POST</span> /api/geoinfo/municipios</div>
            <div class="code-block"><span class="badge-endpoint">PUT</span> /api/geoinfo/municipios?id={id}</div>
            <div class="code-block"><span class="badge-endpoint">DELETE</span> /api/geoinfo/municipios?id={id}</div>

            <h5>Payload (POST/PUT)</h5>
            <pre class="code-block line-numbers"><code class="language-json">{
    "nombre": "Nombre del Municipio",
    "id_departamento": 1
}</code></pre>

            <hr>

            <h4>Barrios</h4>
            <div class="code-block"><span class="badge-endpoint">GET</span> /api/geoinfo/barrios?id={id} (optional)</div>
            <div class="code-block"><span class="badge-endpoint">POST</span> /api/geoinfo/barrios</div>
            <div class="code-block"><span class="badge-endpoint">PUT</span> /api/geoinfo/barrios?id={id}</div>
            <div class="code-block"><span class="badge-endpoint">DELETE</span> /api/geoinfo/barrios?id={id}</div>

            <h5>Payload (POST/PUT)</h5>
            <pre class="code-block line-numbers"><code class="language-json">{
    "nombre": "Nombre del Barrio",
    "id_municipio": 1
}</code></pre>

            <hr>

            <h4>Monedas</h4>
            <div class="code-block"><span class="badge-endpoint">GET</span> /api/monedas/listar</div>
            <div class="code-block"><span class="badge-endpoint">GET</span> /api/monedas/ver?id={id}</div>
            <div class="code-block"><span class="badge-endpoint">POST</span> /api/monedas/crear</div>
            <div class="code-block"><span class="badge-endpoint">POST</span> /api/monedas/actualizar?id={id}</div>
            <div class="code-block"><span class="badge-endpoint">DELETE</span> /api/monedas/eliminar?id={id}</div>

            <h5>Payload (POST/PUT)</h5>
            <pre class="code-block line-numbers"><code class="language-json">{
    "codigo": "USD",
    "nombre": "Dólar Estadounidense",
    "tasa_usd": 1.0
}</code></pre>
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
            <pre class="code-block line-numbers"><code class="language-json">{
    "success": true,
    "data": [
        { "id": 1, "nombre": "Matcha Slim", "precio_usd": "25.00", "stock_total": 2 },
        { "id": 2, "nombre": "Protein Shake", "precio_usd": "40.00", "stock_total": 60 }
    ]
}</code></pre>

            <h5>Response with include_stock=1 (example)</h5>
            <pre class="code-block line-numbers"><code class="language-json">{
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
</code></pre>

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
            <pre class="code-block line-numbers"><code class="language-json">{
    "nombre": "Producto X",
    "descripcion": "Descripción opcional",
    "precio_usd": 9.5,
    "stock": 12
}</code></pre>
            <h4>Success response</h4>
            <pre class="code-block line-numbers"><code class="language-json">{
    "success": true,
    "message": "Producto creado correctamente.",
    "data": { "id": 42, "stock_inserted": 99 }
}</code></pre>
        </div>

        <!-- Orders: detailed -->
        <div class="section-container">
            <h2 class="section-title">Orders</h2>
            <p>Endpoints to create, search and list orders. The server stores coordinates as a POINT; for API requests provide coordinates as <code>"lat,long"</code> or as numeric <code>latitud</code> and <code>longitud</code> fields.</p>

            <h4>Search order by numero_orden</h4>
            <div class="code-block"><span class="badge-endpoint">GET</span> /api/pedidos/buscar?numero_orden=&lt;NUMBER&gt;</div>
            <p>Requires Authorization header: <code>Authorization: Bearer &lt;token&gt;</code>. Returns the order data (latitud/longitud as numbers) when found.</p>
            <h5>Example (curl)</h5>
            <div class="code-block">curl -s "{API_BASE_URL}/api/pedidos/buscar?numero_orden=90001" \
  -H "Authorization: Bearer &lt;JWT_TOKEN&gt;"</div>
                        <h5>Success response (200)</h5>
                        <pre class="code-block line-numbers"><code class="language-json">{
    "success": true,
    "message": "Order found",
    "data": {
        "numero_orden": "90001",
        "destinatario": "Test Customer",
        "telefono": "0999999999",
        "id_pais": 3,
        "latitud": -0.180653,
        "longitud": -78.467838,
        "nombre_estado": "Pending"
    }
}</code></pre>

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
            <p>Below are the main fields related to the <code>pedidos</code> table and how to pass them in the JSON payload when creating an order. Fields generated by the server (like <code>id</code> and <code>fecha_ingreso</code>) should not be supplied.</p>
            <table class="table table-sm table-bordered">
                <thead class="table-light"><tr><th>Field</th><th>Type</th><th>Required</th><th>Description / how to provide it</th></tr></thead>
                <tbody>
                    <tr><td><code>id</code></td><td>integer</td><td>server</td><td>Primary key generated by the server — do not provide on create.</td></tr>
                    <tr><td><code>fecha_ingreso</code></td><td>datetime</td><td>server</td><td>Insertion timestamp set by the server — do not provide on create.</td></tr>
                    <tr><td><code>numero_orden</code></td><td>integer</td><td>yes</td><td>Unique order number (your system should ensure uniqueness).</td></tr>
                    <tr><td><code>destinatario</code></td><td>string</td><td>yes</td><td>Recipient name.</td></tr>
                    <tr><td><code>telefono</code></td><td>string</td><td>yes</td><td>Phone number for the recipient.</td></tr>
                    <tr><td><code>precio_local</code></td><td>number</td><td>no</td><td>Local currency price (optional). If provided, include as decimal (e.g., 120.50).</td></tr>
                    <tr><td><code>precio_usd</code></td><td>number</td><td>no</td><td>Price in USD (optional).</td></tr>
                    <tr><td><code>id_pais</code></td><td>integer</td><td>recommended</td><td>Country id — use the numeric <code>id</code> from <code>/api/geoinfo/listar</code> → <code>paises</code>.</td></tr>
                    <tr><td><code>id_departamento</code></td><td>integer</td><td>recommended</td><td>Department id — use the numeric <code>id</code> from <code>/api/geoinfo/listar</code> → <code>departamentos</code>.</td></tr>
                    <tr><td><code>id_municipio</code></td><td>integer</td><td>recommended</td><td>Municipality id — use the numeric <code>id</code> from <code>/api/geoinfo/listar</code> → <code>municipios</code>.</td></tr>
                    <tr><td><code>id_barrio</code></td><td>integer</td><td>no</td><td>Neighborhood id — optional; get the numeric <code>id</code> from <code>/api/geoinfo/listar</code> → <code>barrios</code> if available.</td></tr>
                    <tr><td><code>direccion</code></td><td>string</td><td>no</td><td>Full address.</td></tr>
                    <tr><td><code>zona</code></td><td>string</td><td>no</td><td>Optional zone/neighborhood descriptor (free text).</td></tr>
                    <tr><td><code>comentario</code></td><td>string</td><td>no</td><td>Optional comments about the order.</td></tr>
                    <tr><td><code>coordenadas</code></td><td>string</td><td>yes</td><td>Latitude and longitude as <code>"lat,long"</code> (or provide numeric <code>latitud</code> and <code>longitud</code> fields).</td></tr>
                    <tr><td><code>id_estado</code></td><td>integer</td><td>recommended</td><td>Status id referencing <code>estados</code> (if your system uses it). Use valid <code>id</code> from your statuses table.</td></tr>
                    <tr><td><code>id_moneda</code></td><td>integer</td><td>recommended</td><td>FK to <code>monedas.id</code>. Use the numeric <code>id</code> from <code>/api/geoinfo/listar</code> → <code>monedas</code>.</td></tr>
                    <tr><td><code>id_vendedor</code></td><td>integer</td><td>optional</td><td>FK to <code>usuarios.id</code> for seller/repartidor. Use numeric user IDs from your users administration or <code>/api/usuarios/listar</code> when available.</td></tr>
                    <tr><td><code>id_proveedor</code></td><td>integer</td><td>optional</td><td>FK to <code>usuarios.id</code> for provider — provide a numeric user id; if omitted the authenticated user may be used.</td></tr>
                    <tr><td><code>productos</code></td><td>array</td><td>no</td><td>Array of items: each item { <code>producto_id</code>: integer, <code>cantidad</code>: integer }. For single-product requests you may use top-level <code>producto_id</code> + <code>cantidad</code>.</td></tr>
                </tbody>
            </table>

                        <h4>Example create request (single product)</h4>
                        <pre class="code-block line-numbers"><code class="language-json">{
    "numero_orden": 90001,
    "destinatario": "Cliente Prueba",
    "telefono": "0999999999",
    "producto_id": 12,
    "cantidad": 1,
    "coordenadas": "-0.180653,-78.467838",
    "direccion": "Calle Falsa 123",
    "zona": "Zona A",
    "precio_local": 120.50,
    "precio_usd": 30.12,
    "id_moneda": 1,
    "id_vendedor": 5,
    "id_proveedor": 6,
    "id_pais": 3,
    "id_departamento": 5,
    "id_municipio": 12,
    "id_barrio": 7,
    "comentario": "Entrega en horario de oficina"
}</code></pre>

            <h4>Example create request (multiple products)</h4>
            <p>Provide product IDs in the <code>productos</code> array. Each item must include <code>producto_id</code> (integer) and <code>cantidad</code> (int).</p>
        <pre class="code-block line-numbers"><code class="language-json">{
    "numero_orden": 90002,
    "destinatario": "Cliente Prueba",
    "telefono": "0999999999",
    "productos": [
        { "producto_id": 12, "cantidad": 2 },
        { "producto_id": 13, "cantidad": 1 }
    ],
    "coordenadas": "-0.180653,-78.467838",
    "direccion": "Calle Falsa 123",
    "id_moneda": 1,
    "id_pais": 3,
    "id_departamento": 5,
    "id_municipio": 12
}</code></pre>

                                                <h4>Working example (real payload)</h4>
                                                                                                <p>Example JSON that has been tested with the <code>/api/pedidos/crear</code> endpoint — it uses the <code>productos</code> array with <code>producto_id</code> (product 2: "Protein Shake").</p>
                                                <pre class="code-block line-numbers"><code class="language-json">{
                            "numero_orden": 1700385600,
                            "destinatario": "Proveedor Prueba",
                            "telefono": "0999999999",
                            "productos": [
                                { "producto_id": 2, "cantidad": 1 }
                            ],
                            "coordenadas": "-0.180653,-78.467838",
                            "direccion": "Calle Falsa 123",
                            "zona": "Centro",
                            "id_moneda": 1,
                            "pais": 3,
                            "departamento": 5,
                            "id_municipio": 12,
                            "id_barrio": 7,
                            "comentario": "Pedido de prueba via Postman"
                        }</code></pre>

            <h4>Example (curl) - create order</h4>
            <div class="code-block">curl -s -X POST "{API_BASE_URL}/api/pedidos/crear" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer &lt;JWT_TOKEN&gt;" \
  -d '{ "numero_orden": 90001, "destinatario": "Cliente Prueba", "telefono": "0999999999", "producto_id": 12, "cantidad": 1, "coordenadas": "-0.180653,-78.467838", "id_municipio": 12 }'</div>

                        <h4>Usage rules / Quick tips</h4>
                                    <ul style="color: #212529;">
                                        <li>Always use numeric identifiers for geographic fields (<code>id_pais</code>, <code>id_departamento</code>, <code>id_municipio</code>, <code>id_barrio</code>). Obtain these ids from <code>/api/geoinfo/listar</code>.</li>
                                        <li>Do not send server-managed fields such as <code>id</code> or <code>fecha_ingreso</code> — the server sets them.</li>
                                        <li>You can send a single-product order using <code>producto_id</code> + <code>cantidad</code>, or multiple products using the <code>productos</code> array (each item: <code>{ producto_id, cantidad }</code>).</li>
                                        <li>If you omit <code>id_proveedor</code>, the API may use the authenticated user (from the token) when applicable.</li>
                                        <li>Coordinate format: <code>"lat,long"</code> or provide numeric <code>latitud</code> and <code>longitud</code> fields.</li>
                                        <li>Price fields (<code>precio_local</code>, <code>precio_usd</code>) are optional but must be numeric if provided.</li>
                                        <li><code>numero_orden</code> must be unique; duplicate numbers will return an error.</li>
                                        <li>The system validates stock before creating the order — if stock is insufficient the request will fail.</li>
                                    </ul>

            <h4>Possible successful response</h4>
            <pre class="code-block line-numbers"><code class="language-json">{
    "success": true,
    "message": "Order created successfully.",
    "data": 1700385600
}</code></pre>

            <h4>Examples of error responses</h4>
            <pre class="code-block line-numbers"><code class="language-json">{
    "success": false,
    "message": "Error inserting order: Insufficient stock for product ID 11. Available: 0, required: 1"
}

{
    "success": false,
    "message": "Error inserting order: Cannot add or update a child row: a foreign key constraint fails (...)"
}</code></pre>
        </div>

        <!-- Bulk orders documentation (ENGLISH) -->
        <div class="section-container">
            <h2 class="section-title">Bulk Orders (Import multiple orders)</h2>
            <p>Creates multiple orders in a single request from a JSON payload. This endpoint is intended for integrations and batch imports (for example, converting CSV to JSON before sending). The endpoint accepts a top-level JSON object with a <code>pedidos</code> array. Each element must follow the same order structure used by <code>/api/pedidos/crear</code>.</p>

            <h4>Endpoint</h4>
            <div class="code-block"><span class="badge-endpoint">POST</span> /api/pedidos/multiple</div>

            <h4>Request body (JSON)</h4>
            <p>Send a JSON object with a <code>pedidos</code> array. Each order must include <code>numero_orden</code>, recipient details and at least one product in the <code>productos</code> array. Coordinates may be provided as a string <code>"lat,long"</code> or as numeric fields <code>latitud</code> and <code>longitud</code>.</p>

            <h4>Example request (batch of 5 orders)</h4>
            <pre class="code-block line-numbers"><code class="language-json">{
    "pedidos": [
        {
            "numero_orden": 1001,
            "destinatario": "Customer One",
            "telefono": "12345678",
            "productos": [
                { "producto_id": 1, "cantidad": 2 }
            ],
            "coordenadas": "-34.500000,-58.400000",
            "direccion": "Street 1 #123",
            "id_pais": 1,
            "id_departamento": 2
        },
        {
            "numero_orden": 1002,
            "destinatario": "Customer Two",
            "telefono": "87654321",
            "productos": [
                { "producto_id": 2, "cantidad": 1 },
                { "producto_id": 3, "cantidad": 1 }
            ],
            "latitud": -34.600000,
            "longitud": -58.500000,
            "direccion": "Evergreen Ave 742",
            "id_pais": 1,
            "id_departamento": 2
        },
        {
            "numero_orden": 1003,
            "destinatario": "Customer Three",
            "telefono": "55512345",
            "productos": [
                { "producto_id": 1, "cantidad": 1 }
            ],
            "coordenadas": "-34.510000,-58.410000",
            "direccion": "Street 3 #45",
            "id_pais": 1,
            "id_departamento": 2
        },
        {
            "numero_orden": 1004,
            "destinatario": "Customer Four",
            "telefono": "60070080",
            "productos": [
                { "producto_id": 4, "cantidad": 1 }
            ],
            "latitud": -34.520000,
            "longitud": -58.420000,
            "direccion": "Boulevard 9",
            "id_pais": 1,
            "id_departamento": 2
        },
        {
            "numero_orden": 1005,
            "destinatario": "Customer Five",
            "telefono": "70080090",
            "productos": [
                { "producto_id": 2, "cantidad": 2 }
            ],
            "coordenadas": "-34.530000,-58.430000",
            "direccion": "Route 5",
            "id_pais": 1,
            "id_departamento": 2
        }
    ]
}</code></pre>

            <h4>Response format</h4>
            <p>The endpoint returns a JSON object with a <code>results</code> array. Each item corresponds to one submitted order and contains <code>numero_orden</code>, <code>success</code> (boolean) and either <code>id_pedido</code> when the insertion succeeded or <code>error</code> with a message describing why that particular order failed. Processing continues for remaining orders even if some fail.</p>

            <h4>Example response (success + errors)</h4>
            <pre class="code-block line-numbers"><code class="language-json">{
    "results": [
        { "numero_orden": 1001, "success": true, "id_pedido": 201 },
        { "numero_orden": 1002, "success": false, "error": "Insufficient stock for product ID 2" },
        { "numero_orden": 1003, "success": true, "id_pedido": 203 },
        { "numero_orden": 1004, "success": true, "id_pedido": 204 },
        { "numero_orden": 1005, "success": true, "id_pedido": 205 }
    ]
}</code></pre>

            <h4>Example (curl)</h4>
            <div class="code-block">curl -s -X POST "{API_BASE_URL}/api/pedidos/multiple" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer &lt;JWT_TOKEN&gt;" \
  -d '@pedidos_batch.json'
            </div>

            <p>Save the example payload to <code>pedidos_batch.json</code> locally and use the curl command above. The server returns HTTP 200 with the per-order <code>results</code> array when the top-level JSON is valid. If the JSON is malformed the API returns HTTP 400.</p>
        </div>
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

        <!-- Logistics Workers System Documentation -->
        <div class="section-container">
            <h2 class="section-title">🤖 Logistics Workers System</h2>
            <p>Asynchronous background job processing system for logistics operations. Jobs are queued and processed by CLI workers without blocking API requests.</p>

            <h4>✨ Key Features</h4>
            <ul style="color: #212529;">
                <li><strong>Asynchronous Processing</strong> — Jobs processed in background</li>
                <li><strong>Automatic Retries</strong> — Exponential backoff (1m, 5m, 15m, 1h, 6h)</li>
                <li><strong>4 Job Types</strong> — Generate guides, update tracking, validate addresses, send notifications</li>
                <li><strong>Concurrent Processing</strong> — SKIP LOCKED support for parallel execution</li>
                <li><strong>Queue Metrics</strong> — Real-time statistics and monitoring</li>
            </ul>
        </div>

        <div class="section-container">
            <h2 class="section-title">Job Types</h2>
            
            <h4>1. Generate Shipping Guide (<code>generar_guia</code>)</h4>
            <p>Generates shipping label/guide number for an order.</p>
            <pre class="code-block line-numbers"><code class="language-json">{
    "job_type": "generar_guia",
    "pedido_id": 89
}</code></pre>

            <h4>2. Update Tracking (<code>actualizar_tracking</code>)</h4>
            <p>Queries carrier API to update tracking status.</p>
            <pre class="code-block line-numbers"><code class="language-json">{
    "job_type": "actualizar_tracking",
    "pedido_id": 89,
    "payload": {
        "paqueteria": "fedex"
    }
}</code></pre>

            <h4>3. Validate Address (<code>validar_direccion</code>)</h4>
            <p>Validates and normalizes delivery address using geocoding.</p>
            <pre class="code-block line-numbers"><code class="language-json">{
    "job_type": "validar_direccion",
    "pedido_id": 89
}</code></pre>

            <h4>4. Send Notification (<code>notificar_estado</code>)</h4>
            <p>Sends email/SMS notification about status changes.</p>
            <pre class="code-block line-numbers"><code class="language-json">{
    "job_type": "notificar_estado",
    "pedido_id": 89,
    "payload": {
        "estado_anterior": "pendiente",
        "estado_nuevo": "en_transito"
    }
}</code></pre>
        </div>

        <div class="section-container">
            <h2 class="section-title">Worker CLI Usage</h2>
            
            <h4>Run Worker Once (Cron Mode)</h4>
            <div class="code-block">php cli/logistics_worker.php --once</div>
            <p>Processes all pending jobs once and exits. Ideal for cron scheduling.</p>

            <h4>Run Worker Loop (Daemon Mode)</h4>
            <div class="code-block">php cli/logistics_worker.php --loop</div>
            <p>Runs continuously, checking for new jobs every 3 seconds. Ideal for systemd/supervisor.</p>

            <h4>Cron Configuration</h4>
            <pre class="code-block"># Run every minute
* * * * * cd /path/to/paqueteriacz && php cli/logistics_worker.php --once >> logs/logistics_worker.log 2>&1</pre>

            <h4>Systemd Service (CentOS 7 / Linux)</h4>
            <pre class="code-block">[Unit]
Description=Logistics Worker
After=mariadb.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/path/to/paqueteriacz
ExecStart=/usr/bin/php /path/to/paqueteriacz/cli/logistics_worker.php --loop
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target</pre>
        </div>

        <div class="section-container">
            <h2 class="section-title">Programmatic Usage (PHP)</h2>
            
            <h4>Enqueue Job</h4>
            <pre class="code-block line-numbers"><code class="language-php">&lt;?php
require_once 'services/LogisticsQueueService.php';

// Enqueue single job
$result = LogisticsQueueService::queue('generar_guia', $pedidoId);

// Enqueue with payload
$result = LogisticsQueueService::queue('notificar_estado', $pedidoId, [
    'estado_anterior' =&gt; 'pendiente',
    'estado_nuevo' =&gt; 'en_transito'
]);

if ($result['success']) {
    echo "Job ID: {$result['id']} enqueued\n";
}</code></pre>

            <h4>Check Queue Metrics</h4>
            <pre class="code-block line-numbers"><code class="language-php">&lt;?php
$metrics = LogisticsQueueService::obtenerMetricas();

// By status
foreach ($metrics['by_status'] as $stat) {
    echo "{$stat['status']}: {$stat['count']}\n";
}

// By job type
foreach ($metrics['by_job_type'] as $job) {
    echo "{$job['job_type']}: {$job['count']}\n";
}</code></pre>

            <h4>Retry Failed Job</h4>
            <pre class="code-block line-numbers"><code class="language-php">&lt;?php
LogisticsQueueService::resetear($jobId);</code></pre>
        </div>

        <div class="section-container">
            <h2 class="section-title">Queue States & Retry Schedule</h2>
            
            <table class="table table-sm table-bordered">
                <thead class="table-light"><tr><th>State</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><code>pending</code></td><td>Waiting to be processed</td></tr>
                    <tr><td><code>processing</code></td><td>Currently being processed</td></tr>
                    <tr><td><code>completed</code></td><td>Successfully finished</td></tr>
                    <tr><td><code>failed</code></td><td>Failed after max retries (5)</td></tr>
                </tbody>
            </table>

            <h4>Retry Schedule (Exponential Backoff)</h4>
            <table class="table table-sm table-bordered">
                <thead class="table-light"><tr><th>Attempt</th><th>Retry After</th></tr></thead>
                <tbody>
                    <tr><td>1</td><td>1 minute</td></tr>
                    <tr><td>2</td><td>5 minutes</td></tr>
                    <tr><td>3</td><td>15 minutes</td></tr>
                    <tr><td>4</td><td>1 hour</td></tr>
                    <tr><td>5</td><td>6 hours</td></tr>
                    <tr><td>6+</td><td>Marked as failed</td></tr>
                </tbody>
            </table>
        </div>

        <div class="section-container">
            <h2 class="section-title">Complete Documentation</h2>
            <p>For detailed worker documentation including deployment instructions, troubleshooting, and extending with new processors, see:</p>
            <div class="code-block">cli/README_LOGISTICS_WORKER.md</div>
            
            <h4>Related Files</h4>
            <ul style="color: #212529;">
                <li><code>database/migrations/create_logistics_queue.sql</code> — Database migration</li>
                <li><code>services/LogisticsQueueService.php</code> — Queue management service</li>
                <li><code>cli/logistics_worker.php</code> — Worker CLI</li>
                <li><code>cli/processors/</code> — Job processors directory</li>
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
    <!-- Prism.js for JSON syntax highlighting -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-json.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-php.min.js"></script>
    <!-- Prism line numbers plugin -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/line-numbers/prism-line-numbers.min.js"></script>
</body>

</html>