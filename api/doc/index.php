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
            background-color: #f9fafb;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            color: #212529 !important;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

       

        header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 0;
            position: relative;
            overflow: hidden;
        }

        header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="50" r="2" fill="white" opacity="0.1"/></svg>');
            opacity: 0.3;
        }

        header .container {
            position: relative;
            z-index: 1;
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
            transition: all 0.3s ease;
        }

        .section-container:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .section-title {
            color: #667eea;
            font-weight: bold;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #667eea;
            display: inline-block;
        }

        /* Nav tabs styling */
        .nav-tabs {
            border-bottom: 2px solid #e5e7eb;
        }

        .nav-tabs .nav-link {
            color: rgba(255, 255, 255, 0.75);
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border: none;
            transition: all 0.3s ease;
            position: relative;
            font-size: 0.95rem;
        }

        .nav-tabs .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: white;
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .nav-tabs .nav-link:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
        }

        .nav-tabs .nav-link.active {
            color: white;
            background: rgba(255, 255, 255, 0.15);
            border: none;
            font-weight: 700;
        }

        .nav-tabs .nav-link.active::after {
            transform: scaleX(1);
        }

        .navbar-brand {
            color: white !important;
            text-decoration: none;
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            transition: all  0.3s ease;
        }

        .navbar-brand:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
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

        /* Language Toggle Button */
        .lang-toggle {
            position: relative;
            display: inline-flex;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 2rem;
            padding: 0.25rem;
            gap: 0.25rem;
        }

        .lang-toggle button {
            padding: 0.4rem 1rem;
            border: none;
            background: transparent;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
            border-radius: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 1;
        }

        .lang-toggle button.active {
            background: white;
            color: #667eea;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        .lang-toggle button:hover:not(.active) {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Hide inactive language content */
        [data-lang]:not([data-lang="en"]) {
            display: none;
        }

        body.lang-es [data-lang="en"] {
            display: none;
        }

        body.lang-es [data-lang="es"] {
            display: block;
        }

        body.lang-es [data-lang="es"].d-inline {
            display: inline !important;
        }

        body.lang-es [data-lang="es"].table {
            display: table !important;
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
        <div class="container d-flex justify-content-between align-items-center">
            <a class="navbar-brand" href="#">← <span data-lang="en">Home</span><span data-lang="es">Inicio</span></a>
            
            <!-- Language Toggle -->
            <div class="lang-toggle">
                <button id="lang-en" class="active" onclick="setLanguage('en')">🇬🇧 English</button>
                <button id="lang-es" onclick="setLanguage('es')">🇪🇸 Español</button>
            </div>
        </div>
        <div class="container text-center">
            <h1 data-lang="en">Logistics API Documentation</h1>
            <h1 data-lang="es">Documentación API de Logística</h1>
            <p class="lead" data-lang="en">Practical guide to consume the local API of the package delivery system</p>
            <p class="lead" data-lang="es">Guía práctica para consumir la API local del sistema de paquetería</p>
            <p>
                <a class="btn btn-outline-primary btn-sm" href="./crmdoc.php" target="_blank" data-lang="en">📋 CRM API Docs</a>
                <a class="btn btn-outline-primary btn-sm" href="./crmdoc.php" target="_blank" data-lang="es">📋 Docs API CRM</a>
            </p>
        </div>
        <div>
            
        </div>
    <div class="container mt-5" style="color: #212529;">
        <!-- Nav tabs -->
        <ul class="nav nav-tabs mb-4" id="apiTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
                    🚀 General
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="auth-tab" data-bs-toggle="tab" data-bs-target="#auth" type="button" role="tab">
                    🔐 Authentication
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="geo-tab" data-bs-toggle="tab" data-bs-target="#geo" type="button" role="tab">
                    🌍 Geographic Data
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="products-tab" data-bs-toggle="tab" data-bs-target="#products" type="button" role="tab">
                    🏷️ Products
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders" type="button" role="tab">
                    📦 Orders
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="client-tab" data-bs-toggle="tab" data-bs-target="#client" type="button" role="tab">
                    📱 Client App
                </button>
            </li>
        </ul>

        <!-- Tab content -->
        <div class="tab-content" id="apiTabsContent">
            <!-- Tab: General -->
            <div class="tab-pane fade show active" id="general" role="tabpanel">
        <div class="section-container" style="color: #212529;">
            <h2 class="section-title" data-lang="en">Quick Reference</h2>
            <h2 class="section-title" data-lang="es">Referencia Rápida</h2>
            <p data-lang="en">This documentation describes the most used endpoints for integration: <strong>Authentication</strong>, <strong>Products</strong>, <strong>Orders (Pedidos)</strong>, and <strong>Logistics Workers</strong>. Examples show request shape, response shape and common errors.</p>
            <p data-lang="es">Esta documentación describe los endpoints más utilizados para integración: <strong>Autenticación</strong>, <strong>Productos</strong>, <strong>Órdenes (Pedidos)</strong> y <strong>Workers de Logística</strong>. Los ejemplos muestran formato de petición, respuesta y errores comunes.</p>
        </div>

        <div class="section-container" style="color: #212529;">
            <h2 class="section-title" data-lang="en">Pagination</h2>
            <h2 class="section-title" data-lang="es">Paginación</h2>
            <p data-lang="en">General listing endpoints (Orders, Products) support pagination via query parameters.</p>
            <p data-lang="es">Los endpoints de listado general (Pedidos, Productos) soportan paginación mediante parámetros GET.</p>
            
            <h4 data-lang="en">Parameters (GET)</h4>
            <h4 data-lang="es">Parámetros (GET)</h4>
            <ul style="color: #212529;">
                <li><code>page</code> (int): Page number (default: 1).</li>
                <li><code>limit</code> (int): Items per page (default: 20 or 50).</li>
            </ul>

            <h4 data-lang="en">Response Structure</h4>
            <h4 data-lang="es">Estructura de Respuesta</h4>
            <p data-lang="en">When pagination is active, the response includes a <code>pagination</code> object with metadata.</p>
            <p data-lang="es">Cuando la paginación está activa, la respuesta incluye un objeto <code>pagination</code> con metadatos.</p>
            
            <pre class="code-block line-numbers"><code class="language-json">{
    "success": true,
    "data": [ ... ],
    "pagination": {
        "total": 150,
        "page": 1,
        "limit": 20,
        "total_pages": 8
    }
}</code></pre>
        </div>

        <div class="section-container" id="quickstart">
            <h2 class="section-title" data-lang="en">Quickstart</h2>
            <h2 class="section-title" data-lang="es">Inicio Rápido</h2>
            <p data-lang="en">Minimal steps to call the API successfully:</p>
            <p data-lang="es">Pasos mínimos para llamar la API exitosamente:</p>
            <ol style="color: #212529;" data-lang="en">
                <li>Obtain a JWT token: <code>POST /api/auth/login</code> with <code>{ "email", "password" }</code>.</li>
                <li>Take the token from the login response at <code>response.data.token</code> (important: the token is inside <code>data.token</code>, not at the top level).</li>
                <li>Call protected endpoints adding header: <code>Authorization: Bearer &lt;token&gt;</code>.</li>
                <li>When creating orders, provide required address fields and a unique <code>numero_orden</code>, and ensure the product has enough stock.</li>
            </ol>
            <ol style="color: #212529;" data-lang="es">
                <li>Obtener un token JWT: <code>POST /api/auth/login</code> con <code>{ "email", "password" }</code>.</li>
                <li>Tomar el token de la respuesta en <code>response.data.token</code> (importante: el token está dentro de <code>data.token</code>, no en el nivel superior).</li>
                <li>Llamar endpoints protegidos agregando el header: <code>Authorization: Bearer &lt;token&gt;</code>.</li>
                <li>Al crear órdenes, proporcionar los campos de dirección requeridos y un <code>numero_orden</code> único, y asegurar que el producto tenga stock suficiente.</li>
            </ol>

            <h4 data-lang="en">Example: get token (curl)</h4>
            <h4 data-lang="es">Ejemplo: obtener token (curl)</h4>
            <div class="code-block">curl -s -X POST "{API_BASE_URL}/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"<REPLACE_WITH_PASSWORD>"}'</div>

            <h4 data-lang="en">Login response (important)</h4>
            <h4 data-lang="es">Respuesta login (importante)</h4>
            <pre class="code-block line-numbers"><code class="language-json">{
    "success": true,
    "message": "Login exitoso",
    "data": { "token": "&lt;JWT_TOKEN&gt;" }
}
</code></pre>

            <p data-lang="en">Note: always use the token value located at <code>data.token</code> when setting the <code>Authorization</code> header.</p>
            <p data-lang="es">Nota: siempre usa el valor del token ubicado en <code>data.token</code> al configurar el header <code>Authorization</code>.</p>
        </div>
            </div>
            <!-- End Tab: General -->

            <!-- Tab: Authentication -->
            <div class="tab-pane fade" id="auth" role="tabpanel">
        <div class="section-container" style="color: #212529;">
            <h2 class="section-title" data-lang="en">Authentication (Login)</h2>
            <h2 class="section-title" data-lang="es">Autenticación (Login)</h2>
            <p data-lang="en">Obtain a JWT token. NOTE: the HTTP response envelope is <code>{ success, message, data: { token } }</code>.</p>
            <p data-lang="es">Obtener un token JWT. NOTA: el sobre de respuesta HTTP es <code>{ success, message, data: { token } }</code>.</p>

            <h4 data-lang="en">Endpoint</h4>
            <h4 data-lang="es">Endpoint</h4>
            <div class="code-block"><span class="badge-endpoint">POST</span> /api/auth/login</div>

            <h4 data-lang="en">Request body (JSON)</h4>
            <h4 data-lang="es">Cuerpo de la petición (JSON)</h4>
            <table class="table table-sm table-bordered" data-lang="en">
                <thead class="table-light"><tr><th>Field</th><th>Type</th><th>Required</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><code>email</code></td><td>string (email)</td><td>yes</td><td>User email</td></tr>
                    <tr><td><code>password</code></td><td>string</td><td>yes</td><td>User password</td></tr>
                </tbody>
            </table>
            <table class="table table-sm table-bordered" data-lang="es">
                <thead class="table-light"><tr><th>Campo</th><th>Tipo</th><th>Requerido</th><th>Descripción</th></tr></thead>
                <tbody>
                    <tr><td><code>email</code></td><td>string (email)</td><td>sí</td><td>Email del usuario</td></tr>
                    <tr><td><code>password</code></td><td>string</td><td>sí</td><td>Contraseña del usuario</td></tr>
                </tbody>
            </table>

            <h4 data-lang="en">Example request</h4>
            <h4 data-lang="es">Petición de ejemplo</h4>
            <pre class="code-block line-numbers"><code class="language-json">{
    "email": "admin@example.com",
    "password": "123456"
}</code></pre>

            <h4 data-lang="en">Success response (200)</h4>
            <h4 data-lang="es">Respuesta exitosa (200)</h4>
            <pre class="code-block line-numbers"><code class="language-json">{
    "success": true,
    "message": "Login exitoso",
    "data": { "token": "&lt;JWT_TOKEN&gt;" }
}</code></pre>

            <h4 data-lang="en">Usage</h4>
            <h4 data-lang="es">Uso</h4>
            <div class="code-block">Authorization: Bearer &lt;JWT_TOKEN from response.data.token&gt;</div>
            <p style="color:#6c757d;font-size:0.9rem;" data-lang="en">Security note: never embed real credentials or long-lived tokens in public documentation or examples. Use placeholders and environment variables when running commands.</p>
            <p style="color:#6c757d;font-size:0.9rem;" data-lang="es">Nota de seguridad: nunca incluyas credenciales reales o tokens de larga duración en documentación pública o ejemplos. Usa placeholders y variables de entorno al ejecutar comandos.</p>
        </div>
            </div>
            <!-- End Tab: Authentication -->

            <!-- Tab: Geographic Data -->
            <div class="tab-pane fade" id="geo" role="tabpanel">
        <div class="section-container">
            <h2 class="section-title" data-lang="en">Geographic & Reference Data (GeoInfo)</h2>
            <h2 class="section-title" data-lang="es">Datos Geográficos y de Referencia (GeoInfo)</h2>
            <p data-lang="en">Endpoint to retrieve reference lists used by the front-end selects: countries, departments, municipalities, neighborhoods and currencies.</p>
            <p data-lang="es">Endpoint para obtener listas de referencia usadas por los selectores del front-end: países, departamentos, municipios, barrios y monedas.</p>

            <h4 data-lang="en">Endpoint</h4>
            <h4 data-lang="es">Endpoint</h4>
            <div class="code-block"><span class="badge-endpoint">GET</span> /api/geoinfo/listar</div>

            <p data-lang="en">Returns an object <code>data</code> containing arrays for <code>paises</code> (countries), <code>departamentos</code> (departments), <code>municipios</code> (municipalities), <code>barrios</code> (neighborhoods) and <code>monedas</code> (currencies). Useful to initialize forms and dependent selects.</p>
            <p data-lang="es">Retorna un objeto <code>data</code> con arrays para <code>paises</code> (países), <code>departamentos</code>, <code>municipios</code>, <code>barrios</code> y <code>monedas</code>. Útil para inicializar formularios y selectores dependientes.</p>

            <h4 data-lang="en">Response example</h4>
            <h4 data-lang="es">Ejemplo de respuesta</h4>
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
            <h2 class="section-title" data-lang="en">Geographic Data Management (CRUD)</h2>
            <h2 class="section-title" data-lang="es">Gestión de Datos Geográficos (CRUD)</h2>
            <p data-lang="en">Endpoints to manage Paises, Departamentos, Municipios, and Barrios. All endpoints support GET, POST, PUT, DELETE.</p>
            <p data-lang="es">Endpoints para gestionar Países, Departamentos, Municipios y Barrios. Todos soportan GET, POST, PUT, DELETE.</p>

            <h4 data-lang="en">Countries (Paises)</h4>
            <h4 data-lang="es">Países</h4>
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
            </div>
            <!-- End Tab: Geographic Data -->

            <!-- Tab: Products -->
            <div class="tab-pane fade" id="products" role="tabpanel">
        <div class="section-container">
            <h2 class="section-title" data-lang="en">Products (CRUD)</h2>
            <h2 class="section-title" data-lang="es">Productos (CRUD)</h2>
            <p data-lang="en">Manage products. Mutating endpoints require a valid <code>Authorization</code> header.</p>
            <p data-lang="es">Gestionar productos. Los endpoints de modificación requieren un header <code>Authorization</code> válido.</p>

            <h4 data-lang="en">List products</h4>
            <h4 data-lang="es">Listar productos</h4>
            <div class="code-block"><span class="badge-endpoint">GET</span> /api/productos/listar</div>
            <p data-lang="en">Returns a list of products with aggregated stock (field <code>stock_total</code>).</p>
            <p data-lang="es">Retorna una lista de productos con stock agregado (campo <code>stock_total</code>).</p>
            <p data-lang="en">Optional query parameter: <code>include_stock=1</code> — when set, the response includes a <code>stock_entries</code> array for each product with recent stock movements (fields: <code>id</code>, <code>id_producto</code>, <code>id_usuario</code>, <code>cantidad</code>, <code>updated_at</code>).</p>
            <p data-lang="es">Parámetro opcional: <code>include_stock=1</code> — cuando se activa, la respuesta incluye un array <code>stock_entries</code> por producto con movimientos recientes (campos: <code>id</code>, <code>id_producto</code>, <code>id_usuario</code>, <code>cantidad</code>, <code>updated_at</code>).</p>
            <h4 data-lang="en">Response (200)</h4>
            <h4 data-lang="es">Respuesta (200)</h4>
            <pre class="code-block line-numbers"><code class="language-json">{
    "success": true,
    "data": [
        { "id": 1, "nombre": "Matcha Slim", "precio_usd": "25.00", "stock_total": 2 },
        { "id": 2, "nombre": "Protein Shake", "precio_usd": "40.00", "stock_total": 60 }
    ]
}</code></pre>

            <h5 data-lang="en">Response with include_stock=1 (example)</h5>
            <h5 data-lang="es">Respuesta con include_stock=1 (ejemplo)</h5>
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

            <h4 data-lang="en">Create product</h4>
            <h4 data-lang="es">Crear producto</h4>
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
            <h4 data-lang="en">Example create request</h4>
            <h4 data-lang="es">Petición de creación ejemplo</h4>
            <pre class="code-block line-numbers"><code class="language-json">{
    "nombre": "Producto X",
    "descripcion": "Descripción opcional",
    "precio_usd": 9.5,
    "stock": 12
}</code></pre>
            <h4 data-lang="en">Success response</h4>
            <h4 data-lang="es">Respuesta exitosa</h4>
            <pre class="code-block line-numbers"><code class="language-json">{
    "success": true,
    "message": "Producto creado correctamente.",
    "data": { "id": 42, "stock_inserted": 99 }
}</code></pre>
        </div>
            </div>
            <!-- End Tab: Products -->

            <!-- Tab: Orders -->
            <div class="tab-pane fade" id="orders" role="tabpanel">
        <div class="section-container">


            <h4 data-lang="en">Request fields (common)</h4>
            <h4 data-lang="es">Campos de petición (comunes)</h4>
            <p>Below are the main fields related to the <code>pedidos</code> table and how to pass them in the JSON payload when creating an order. Fields generated by the server (like <code>id</code> and <code>fecha_ingreso</code>) should not be supplied.</p>
            <table class="table table-sm table-bordered">
                <thead class="table-light"><tr><th>Field</th><th>Type</th><th>Required</th><th>Description / how to provide it</th></tr></thead>
                <tbody>
                    <tr><td><code>id</code></td><td>integer</td><td>server</td><td>Primary key generated by the server — do not provide on create.</td></tr>
                    <tr><td><code>fecha_ingreso</code></td><td>datetime</td><td>server</td><td>Insertion timestamp set by the server — do not provide on create.</td></tr>
                    <tr><td><code>numero_orden</code></td><td>integer</td><td>yes</td><td>Unique order number (your system should ensure uniqueness).</td></tr>
                    <tr><td><code>destinatario</code></td><td>string</td><td>yes</td><td>Recipient name.</td></tr>
                    <tr><td><code>telefono</code></td><td>string</td><td>yes</td><td>Phone number for the recipient.</td></tr>
                    
                    <!-- Pricing Fields -->
                    <tr><td><code>precio_total_local</code></td><td>number</td><td>no</td><td>Total price in local currency. Required override if <code>es_combo=1</code>.</td></tr>
                    <tr><td><code>precio_total_usd</code></td><td>number</td><td>no</td><td>Total price in USD. Required override if <code>es_combo=1</code>.</td></tr>
                    <tr><td><code>tasa_conversion_usd</code></td><td>number</td><td>no</td><td>Exchange rate.</td></tr>
                    <tr><td><code>es_combo</code></td><td>integer</td><td>no</td><td><strong>1</strong> = Combo (fixed price), <strong>0</strong> = Unitary (calculated). <br>If <strong>0</strong> (or omitted), the system <strong>calculates totals automatically</strong> by summing <code>product price × quantity</code>.</td></tr>

                    <tr><td><code>id_pais</code></td><td>integer</td><td>recommended</td><td>Country id — use the numeric <code>id</code> from <code>/api/geoinfo/listar</code> → <code>paises</code>.</td></tr>
                    <tr><td><code>id_departamento</code></td><td>integer</td><td>recommended</td><td>Department id — use the numeric <code>id</code> from <code>/api/geoinfo/listar</code> → <code>departamentos</code>.</td></tr>
                    <tr><td><code>id_municipio</code></td><td>integer</td><td>recommended</td><td>Municipality id — use the numeric <code>id</code> from <code>/api/geoinfo/listar</code> → <code>municipios</code>.</td></tr>
                    <tr><td><code>id_barrio</code></td><td>integer</td><td>no</td><td>Neighborhood id — optional; get the numeric <code>id</code> from <code>/api/geoinfo/listar</code> → <code>barrios</code> if available.</td></tr>
                    <tr><td><code>direccion</code></td><td>string</td><td>no</td><td>Full address.</td></tr>
                    <tr><td><code>zona</code></td><td>string</td><td>no</td><td>Optional zone/neighborhood descriptor (free text).</td></tr>
                    <tr><td><code>comentario</code></td><td>string</td><td>no</td><td>Optional comments about the order.</td></tr>
                    <tr><td><code>coordenadas</code></td><td>string</td><td>yes</td><td>Latitude and longitude as <code>"lat,long"</code> (or provide numeric <code>latitud</code> and <code>longitud</code> fields).</td></tr>
                    <tr><td><code>estado</code></td><td>integer</td><td>recommended</td><td>Status id referencing <code>estados</code>.</td></tr>
                    <tr><td><code>moneda</code></td><td>integer</td><td>recommended</td><td>FK to <code>monedas.id</code>. Use the numeric <code>id</code> from <code>/api/geoinfo/listar</code> → <code>monedas</code>.</td></tr>
                    <tr><td><code>vendedor</code></td><td>integer</td><td>optional</td><td>FK to <code>usuarios.id</code> for seller/repartidor. Use numeric user IDs.</td></tr>
                    <tr><td><code>proveedor</code></td><td>integer</td><td>optional</td><td>FK to <code>usuarios.id</code> for provider — provide a numeric user id.</td></tr>
                    <tr><td><code>productos</code></td><td>array</td><td>no</td><td>Array of items: each item { <code>producto_id</code>: integer, <code>cantidad</code>: integer }. For single-product requests you may use top-level <code>producto_id</code> + <code>cantidad</code>.</td></tr>
                </tbody>
            </table>

            <div style="margin-top: 1rem; border-bottom: 1px solid #dee2e6; padding-bottom: 2rem; margin-bottom: 2rem; background-color: #f8f9fa; padding: 1.5rem; border-radius: 8px;">
                <h4 data-lang="en">🤖 Automated Background Processing (Recommended)</h4>
                <h4 data-lang="es">🤖 Procesamiento Automático en Segundo Plano (Recomendado)</h4>
                <p data-lang="en">When you create orders, the system triggers background tasks (Worker) to validate addresses, update tracking status, and more. This ensures your API requests return quickly without waiting for external validations.</p>
                <p data-lang="es">Cuando creas órdenes, el sistema dispara tareas en segundo plano (Worker) para validar direcciones, actualizar estados de tracking y más. Esto asegura respuestas rápidas.</p>
                
                <h6 data-lang="en">Automatic Actions:</h6>
                <h6 data-lang="es">Acciones Automáticas:</h6>
                <ul class="d-inline-block text-start mb-0">
                    <li data-lang="en">Address validation & normalization</li>
                    <li data-lang="es">Validación y normalización de direcciones</li>
                    <li data-lang="en">Status sync with external providers</li>
                    <li data-lang="es">Sincronización de estados con proveedores externos</li>
                    <li data-lang="en"><strong>Combo Logic:</strong> Validates <code>es_combo</code> orders, ensuring the fixed total price is respected over individual item prices.</li>
                    <li data-lang="es"><strong>Lógica de Combos:</strong> Valida pedidos con <code>es_combo</code>, asegurando que se respete el precio total fijo sobre los precios individuales.</li>
                </ul>

                <hr style="margin: 1.5rem 0;">

                <h4 data-lang="en">📦 Bulk Import with Automatic Validation (Async)</h4>
                <h4 data-lang="es">📦 Importación Masiva con Validación Automática (Asíncrono)</h4>
                <p data-lang="en">Import multiple orders in a single request. Use <code>auto_enqueue=true</code> to validate addresses asynchronously via the Worker. Supports <strong>Combos</strong> within the batch.</p>
                <p data-lang="es">Importa múltiples órdenes en una sola petición. Usa <code>auto_enqueue=true</code> para validar direcciones asíncronamente vía Worker. Soporta <strong>Combos</strong> dentro del lote.</p>

                <div class="code-block"><span class="badge-endpoint badge-post">POST</span> /api/pedidos/multiple?auto_enqueue=true</div>
                
                <h5 data-lang="en">Request Example</h5>
                <h5 data-lang="es">Ejemplo de Petición</h5>
                <pre class="code-block line-numbers"><code class="language-json">{
    "pedidos": [
        {
            "numero_orden": 1001,
            "destinatario": "Customer One",
            "telefono": "12345678",
            "productos": [{ "producto_id": 1, "cantidad": 2 }],
            "coordenadas": "-34.500000,-58.400000",
            "direccion": "Street 1 #123",
            "id_pais": 1,
            "id_departamento": 2
        },
        {
            "numero_orden": 1003,
            "destinatario": "Combo Example",
            "telefono": "55555555",
            "es_combo": 1,
            "productos": [
                { "producto_id": 10, "cantidad": 1 },
                { "producto_id": 11, "cantidad": 1 }
            ],
            "precio_total_local": 500.00,
            "precio_total_usd": 15.00,
            "tasa_conversion_usd": 33.33,
            "moneda": 1,
            "coordenadas": "-34.600000,-58.500000",
            "direccion": "Combo St."
        }
    ]
}</code></pre>

                <h5 data-lang="en">Response (Async Mode)</h5>
                <h5 data-lang="es">Respuesta (Modo Asíncrono)</h5>
                <pre class="code-block line-numbers"><code class="language-json">{
    "results": [
        { 
            "numero_orden": 1001, 
            "success": true, 
            "id_pedido": 201, 
            "job_queued": true 
        },
        { 
            "numero_orden": 1002, 
            "success": true, 
            "id_pedido": 202, 
            "job_queued": true 
        }
    ]
}</code></pre>
            </div>
            
            <h2 class="section-title" data-lang="en">Single Order Creation (Synchronous)</h2>
            <h2 class="section-title" data-lang="es">Creación de Orden Individual (Síncrono)</h2>
            <p data-lang="en">Endpoints to create one order at a time. Coordinates are stored as POINT.</p>
            <p data-lang="es">Endpoints para crear una orden a la vez. Las coordenadas se guardan como POINT.</p>

            <h4 data-lang="en">Search order by numero_orden</h4>
            <h4 data-lang="es">Buscar orden por numero_orden</h4>
            <div class="code-block"><span class="badge-endpoint">GET</span> /api/pedidos/buscar?numero_orden=&lt;NUMBER&gt;</div>
            <p data-lang="en">Requires Authorization header: <code>Authorization: Bearer &lt;token&gt;</code>. Returns the order data (latitud/longitud as numbers) when found.</p>
            <p data-lang="es">Requiere header de autorización: <code>Authorization: Bearer &lt;token&gt;</code>. Retorna los datos de la orden (latitud/longitud como números) cuando se encuentra.</p>
            <h5 data-lang="en">Example (curl)</h5>
            <h5 data-lang="es">Ejemplo (curl)</h5>
            <div class="code-block">curl -s "{API_BASE_URL}/api/pedidos/buscar?numero_orden=90001" \
  -H "Authorization: Bearer &lt;JWT_TOKEN&gt;"</div>
                        <h5 data-lang="en">Success response (200)</h5>
                        <h5 data-lang="es">Respuesta exitosa (200)</h5>
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

            <h4 data-lang="en">Create order</h4>
            <h4 data-lang="es">Crear orden</h4>
            <div class="code-block"><span class="badge-endpoint">POST</span> /api/pedidos/crear</div>

            <h4 data-lang="en">Important notes</h4>
            <h4 data-lang="es">Notas importantes</h4>
            <ul style="color: #212529;">
                <li>The API response envelope is <code>{ success, message, data }</code>.</li>
                <li>Fields <code>id_moneda</code>, <code>id_vendedor</code> and <code>id_proveedor</code> are stored in <code>pedidos</code> and have foreign key constraints — they must reference existing rows.</li>
                <li>Products are stored in <code>pedidos_productos</code> (pivot). The API accepts the simple format using top-level <code>producto</code> or <code>producto_id</code> plus <code>cantidad</code>. Internally the model supports creating an order with multiple items (see <code>crearPedidoConProductos</code> in the model).</li>
                <li>Stock validation: the system checks stock (via DB triggers and application checks). If stock is insufficient the request will fail with an error message.</li>
            </ul>



                        <h4 data-lang="en">1. Simple Order (Single Product)</h4>
                        <h4 data-lang="es">1. Orden Simple (Un Producto)</h4>
                        <p data-lang="en">Standard order with one product. You can provide <code>producto_id</code> and <code>cantidad</code> at the root level.</p>
                        <p data-lang="es">Orden estándar con un producto. Puedes indicar <code>producto_id</code> y <code>cantidad</code> en el nivel raíz.</p>

                        <pre class="code-block line-numbers"><code class="language-json">{
    "numero_orden": 10001,
    "destinatario": "Juan Perez",
    "telefono": "88888888",
    "producto_id": 1,
    "cantidad": 1,
    "coordenadas": "-34.603722,-58.381592",
    "direccion": "Av. Principal 123",
    "id_pais": 1,
    "id_departamento": 1,
    "id_municipio": 1,
    "precio_local": 100.00,
    "moneda": 1
}</code></pre>

                        <h4 data-lang="en">2. Multi-product Order</h4>
                        <h4 data-lang="es">2. Orden con Múltiples Productos</h4>
                        <p data-lang="en">To include multiple items, use the <code>productos</code> array. Each item must have <code>producto_id</code> and <code>cantidad</code>.</p>
                        <p data-lang="es">Para incluir varios ítems, usa el array <code>productos</code>. Cada ítem debe tener <code>producto_id</code> y <code>cantidad</code>.</p>

                        <pre class="code-block line-numbers"><code class="language-json">{
    "numero_orden": 10002,
    "destinatario": "Maria Gonzalez",
    "telefono": "88889999",
    "coordenadas": "-34.603722,-58.381592",
    "direccion": "Calle 456",
    "productos": [
        { "producto_id": 1, "cantidad": 2 },
        { "producto_id": 5, "cantidad": 1 }
    ],
    "id_pais": 1,
    "id_departamento": 1,
    "moneda": 1
}</code></pre>

                        <h4 data-lang="en">3. Combo Order</h4>
                        <h4 data-lang="es">3. Orden Tipo Combo</h4>
                        <p data-lang="en">Combos have a fixed total price that overrides individual product prices. Set <code>es_combo: 1</code> and provide <code>precio_total_local</code>.</p>
                        <p data-lang="es">Los combos tienen un precio total fijo que sobrescribe los precios individuales. Envía <code>es_combo: 1</code> y <code>precio_total_local</code>.</p>

                        <pre class="code-block line-numbers"><code class="language-json">{
    "numero_orden": 10003,
    "destinatario": "Combo Client",
    "telefono": "88887777",
    "es_combo": 1,
    "productos": [
        { "producto_id": 10, "cantidad": 1 },
        { "producto_id": 11, "cantidad": 1 }
    ],
    "precio_total_local": 500.00,
    "precio_total_usd": 15.00,
    "tasa_conversion_usd": 33.33,
    "moneda": 1,
    "coordenadas": "-34.603722,-58.381592",
    "direccion": "Combo St.",
    "id_pais": 1,
    "id_departamento": 1
}</code></pre>


                        <h4>Usage rules / Quick tips</h4>
                                    <ul style="color: #212529;">
                                        <li>Always use numeric identifiers for geographic fields (<code>id_pais</code>, <code>id_departamento</code>, <code>id_municipio</code>, <code>id_barrio</code>). Obtain these ids from <code>/api/geoinfo/listar</code>.</li>
                                        <li>Do not send server-managed fields such as <code>id</code> or <code>fecha_ingreso</code> — the server sets them.</li>
                                        <li>You can send a single-product order using <code>producto_id</code> + <code>cantidad</code>, or multiple products using the <code>productos</code> array (each item: <code>{ producto_id, cantidad }</code>).</li>
                                        <li>If you omit <code>id_proveedor</code>, the API may use the authenticated user (from the token) when applicable.</li>
                                        <li>Coordinate format: <code>"lat,long"</code> or provide numeric <code>latitud</code> and <code>longitud</code> fields.</li>
                                        <li>Price fields (<code>precio_total_local</code>, <code>precio_total_usd</code>) are optional but must be numeric if provided.</li>
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
    "message": "Error inserting order: Cannot add or update a child row: a foreign key constraint fails (...)"
}</code></pre>


        </div>

        <div class="section-container">
            <h2 class="section-title" data-lang="en">Troubleshooting & tips</h2>
            <h2 class="section-title" data-lang="es">Solución de problemas y consejos</h2>
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
            <!-- End Tab: Orders -->

            <!-- Tab: Client App -->
            <div class="tab-pane fade" id="client" role="tabpanel">
        <div class="section-container" style="color: #212529;">
            <h2 class="section-title" data-lang="en">Client App</h2>
            <h2 class="section-title" data-lang="es">App de Clientes</h2>
            <p data-lang="en">Endpoints designed for the client mobile/web application. Requires a valid JWT token from a user with the <strong>Cliente</strong> role.</p>
            <p data-lang="es">Endpoints diseñados para la aplicación móvil/web de clientes. Requiere un token JWT válido de un usuario con el rol <strong>Cliente</strong>.</p>

            <h4 data-lang="en">List Assigned Orders</h4>
            <h4 data-lang="es">Listar Pedidos Asignados</h4>
            <div class="code-block"><span class="badge-endpoint">GET</span> /api/cliente/pedidos</div>
            <p data-lang="en">Returns a list of orders connected to the authenticated client.</p>
            <p data-lang="es">Retorna la lista de pedidos conectados al cliente autenticado.</p>
            
            <h5 data-lang="en">Response (200)</h5>
            <h5 data-lang="es">Respuesta (200)</h5>
            <pre class="code-block line-numbers"><code class="language-json">{
    "success": true,
    "data": [
        {
            "id": 105,
            "numero_orden": "90100",
            "destinatario": "Mi Tienda",
            "fecha_ingreso": "2025-01-24 10:00:00",
            "estado": "En Ruta",
            "total_usd": 45.50
        }
    ]
}</code></pre>

            <hr>

            <h4 data-lang="en">Update Order Status</h4>
            <h4 data-lang="es">Actualizar Estado de Pedido</h4>
            <div class="code-block"><span class="badge-endpoint">POST</span> /api/cliente/cambiar_estado</div>
            <p data-lang="en">Allows the client to update the status of one of their orders (e.g., to confirm reception).</p>
            <p data-lang="es">Permite al cliente actualizar el estado de uno de sus pedidos (ej. confirmar recepción).</p>

            <h5 data-lang="en">Payload</h5>
            <h5 data-lang="es">Payload</h5>
            <pre class="code-block line-numbers"><code class="language-json">{
    "id_pedido": 105,
    "estado": 4, 
    "observaciones": "Recibido en portería"
}</code></pre>
            <p style="font-size: 0.9em; color: gray;">Note: "estado": 4 usually maps to "Entregado" (Delivered) in standard configuration.</p>

            <h5 data-lang="en">Success Response (200)</h5>
            <h5 data-lang="es">Respuesta Exitosa (200)</h5>
            <pre class="code-block line-numbers"><code class="language-json">{
    "success": true,
    "message": "Estado actualizado correctamente"
}</code></pre>
        </div>
            </div>
            <!-- End Tab: Client App -->
        </div>
        <!-- End Tab Content -->
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

    <!-- Language Toggle Script -->
    <script>
        function setLanguage(lang) {
            // Update body class
            document.body.className = 'lang-' + lang;
            
            // Update button states
            document.getElementById('lang-en').classList.toggle('active', lang === 'en');
            document.getElementById('lang-es').classList.toggle('active', lang === 'es');
            
            // Save preference
            localStorage.setItem('logistics-docs-lang', lang);
        }

        // Load saved language preference on page load
        document.addEventListener('DOMContentLoaded', function() {
            const savedLang = localStorage.getItem('logistics-docs-lang') || 'en';
            setLanguage(savedLang);
        });
    </script>
</body>

</html>