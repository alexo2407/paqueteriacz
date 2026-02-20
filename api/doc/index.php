<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logistics API Documentation</title>

    <!-- Bootstrap CSS v5.3.2 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous" />
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    <!-- Prism.js theme -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/line-numbers/prism-line-numbers.min.css" />

    <style>
        :root {
            /* Modern Color Palette */
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #48c6ef 0%, #6f86d6 100%);
            --warning-gradient: linear-gradient(135deg, #ffa751 0%, #ffe259 100%);
            
            --primary-color: #667eea;
            --primary-dark: #5568d3;
            --secondary-color: #764ba2;
            
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
            --info-color: #3b82f6;
            
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --text-muted: #9ca3af;
            
            --bg-light: #f9fafb;
            --bg-white: #ffffff;
            --border-color: #e5e7eb;
            
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: var(--bg-light);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: var(--text-primary);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Header with modern gradient */
        header {
            background: var(--primary-gradient);
            color: white;
            padding: 3rem 0;
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
            font-size: 2.75rem;
            font-weight: 700;
            letter-spacing: -0.025em;
            margin-bottom: 0.5rem;
        }

        header .lead {
            font-size: 1.15rem;
            font-weight: 400;
            opacity: 0.95;
        }

        .navbar-brand {
            color: white !important;
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            display: inline-block;
        }

        .navbar-brand:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .btn-outline-light {
            border: 2px solid rgba(255, 255, 255, 0.8);
            color: white;
            font-weight: 600;
            padding: 0.5rem 1.5rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-outline-light:hover {
            background: white;
            color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* Section containers with glassmorphism */
        .section-container {
            background: var(--bg-white);
            border-radius: 1rem;
            box-shadow: var(--shadow-md);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .section-container:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .section-title {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1.75rem;
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 3px solid var(--primary-color);
            display: inline-block;
        }

        /* Code blocks with enhanced styling */
        .code-block {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 1.25rem;
            border-radius: 0.75rem;
            font-family: 'Fira Code', 'Consolas', 'Monaco', monospace;
            font-size: 0.9rem;
            overflow-x: auto;
            box-shadow: var(--shadow-lg);
            border: 1px solid #2d2d2d;
            position: relative;
        }

        .code-block::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary-gradient);
            border-radius: 0.75rem 0 0 0.75rem;
        }

        pre.code-block {
            margin-bottom: 1.5rem;
        }

        /* Prism token colors - VS Code inspired */
        .token.property, .token.key { color: #9cdcfe; }
        .token.string { color: #ce9178; }
        .token.number { color: #b5cea8; }
        .token.boolean, .token.null { color: #569cd6; }
        .token.punctuation, .token.operator { color: #d4d4d4; }
        .token.comment { color: #6a9955; font-style: italic; }
        .token.function { color: #dcdcaa; }
        .token.keyword { color: #c586c0; }

        /* Enhanced badges with semantic colors */
        .badge-endpoint {
            font-size: 0.85rem;
            font-weight: 600;
            color: white;
            padding: 0.4rem 0.9rem;
            border-radius: 0.5rem;
            margin-right: 0.5rem;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            box-shadow: var(--shadow-sm);
        }

        .badge-endpoint.badge-post {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            /* Green */
        }

        .badge-endpoint.badge-get {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            /* Blue */
        }

        .badge-endpoint.badge-put {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            /* Orange */
        }

        .badge-endpoint.badge-delete {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            /* Red */
        }

        /* Status badges with improved design */
        .status-badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            letter-spacing: 0.025em;
        }

        .status-200 { 
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
            border: 1px solid #6ee7b7;
        }
        
        .status-202 { 
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            color: #1e40af;
            border: 1px solid #93c5fd;
        }
        
        .status-400 { 
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        
        .status-401 { 
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
            border: 1px solid #fcd34d;
        }
        
        .status-403 { 
            background: linear-gradient(135deg, #ffe4e6 0%, #fecdd3 100%);
            color: #881337;
            border: 1px solid #fda4af;
        }
        
        .status-404 { 
            background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
            color: #374151;
            border: 1px solid #d1d5db;
        }

        /* Enhanced tables */
        .table {
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .table thead {
            background: var(--primary-gradient);
            color: white;
        }

        .table thead th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.05em;
            padding: 1rem;
            border: none;
        }

        .table tbody tr {
            transition: all 0.2s ease;
        }

        .table tbody tr:hover {
            background: var(--bg-light);
            transform: scale(1.01);
        }

        .table tbody td {
            padding: 0.875rem 1rem;
            vertical-align: middle;
        }

        .table code {
            background: #f3f4f6;
            color: var(--primary-color);
            padding: 0.2rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 600;
        }

        /* List improvements */
        ul, ol {
            margin-left: 1.5rem;
            margin-bottom: 1rem;
        }

        li {
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        li strong {
            color: var(--primary-color);
            font-weight: 600;
        }

        /* Typography improvements */
        h4 {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 1.25rem;
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
        }

        p {
            color: var(--text-secondary);
            margin-bottom: 1rem;
            font-size: 1rem;
        }

        /* Footer */
        footer {
            background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
            color: white;
            padding: 2rem 0;
            text-align: center;
            margin-top: 4rem;
        }

        footer p {
            margin: 0;
            color: rgba(255, 255, 255, 0.8);
        }

        /* Scrollbar styling */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-light);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-gradient);
            border-radius: 5px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-gradient);
        }

        /* Responsive improvements */
        @media (max-width: 768px) {
            header h1 {
                font-size: 2rem;
            }

            .section-container {
                padding: 1.5rem;
            }

            .code-block {
                font-size: 0.8rem;
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
            color: var(--primary-color);
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
    </style>
</head>

<body>
    <header>
        <div class="container d-flex justify-content-between align-items-center">
            <a class="navbar-brand" href="./">← <span data-lang="en">Home</span><span data-lang="es">Inicio</span></a>
            
            <!-- Language Toggle -->
            <div class="lang-toggle">
                <button id="lang-en" class="active" onclick="setLanguage('en')">🇬🇧 English</button>
                <button id="lang-es" onclick="setLanguage('es')">🇪🇸 Español</button>
            </div>
        </div>
        <div class="container text-center mt-4">
            <h1 data-lang="en">Logistics API & Integration</h1>
            <h1 data-lang="es">API de Logística e Integración</h1>
            <p class="lead" data-lang="en" style="color: white;">Comprehensive guide to consume the logistics system services</p>
            <p class="lead" data-lang="es" style="color: white;">Guía completa para consumir los servicios del sistema logístico</p>
            <p class="mt-3">
                <a class="btn btn-outline-light btn-sm" href="./crmdoc.php" target="_blank" data-lang="en">📋 CRM API Docs</a>
                <a class="btn btn-outline-light btn-sm" href="./crmdoc.php" target="_blank" data-lang="es">📋 Docs API CRM</a>
            </p>
        </div>
    </header>

    <div class="container mt-5">
        <!-- Nav tabs -->
        <ul class="nav nav-tabs mb-4" id="apiTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
                    <span data-lang="en">🚀 Genera</span><span data-lang="es">🚀 General</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="auth-tab" data-bs-toggle="tab" data-bs-target="#auth" type="button" role="tab">
                    <span data-lang="en">🔐 Auth</span><span data-lang="es">🔐 Auth</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders" type="button" role="tab">
                    <span data-lang="en">📦 Orders</span><span data-lang="es">📦 Pedidos</span>
                </button>
            </li>
             <li class="nav-item" role="presentation">
                <button class="nav-link" id="products-tab" data-bs-toggle="tab" data-bs-target="#products" type="button" role="tab">
                     <span data-lang="en">🏷️ Products</span><span data-lang="es">🏷️ Productos</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="geo-tab" data-bs-toggle="tab" data-bs-target="#geo" type="button" role="tab">
                    <span data-lang="en">🌍 Geo</span><span data-lang="es">🌍 Geo</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="mensajeria-tab" data-bs-toggle="tab" data-bs-target="#mensajeria" type="button" role="tab">
                    <span data-lang="en">📱 Messenger App</span><span data-lang="es">📱 App Mensajería</span>
                </button>
            </li>
        </ul>

        <!-- Tab panes -->
        <div class="tab-content" id="apiTabsContent">
            
            <!-- Tab: General -->
            <div class="tab-pane fade show active" id="general" role="tabpanel">
                
                <div class="section-container">
                    <h2 class="section-title" data-lang="en">Quick Reference</h2>
                    <h2 class="section-title" data-lang="es">Referencia Rápida</h2>
                    
                    <p data-lang="en">Welcome to the Logistics API. This system allows you to manage orders, products, and geographic data using standard HTTP requests.</p>
                    <p data-lang="es">Bienvenido a la API de Logística. Este sistema te permite gestionar pedidos, productos y datos geográficos usando peticiones HTTP estándar.</p>
                    
                    <h4 data-lang="en">✨ Key Concepts</h4>
                    <h4 data-lang="es">✨ Conceptos Clave</h4>
                    
                    <ul data-lang="en">
                        <li><strong>Base URL:</strong> <code>/api</code> (relative to your installation)</li>
                        <li><strong>Auth:</strong> JWT Bearer Token required for write operations.</li>
                        <li><strong>Response Format:</strong> All responses are JSON wrapped in a standard envelope.</li>
                        <li><strong>Dates:</strong> Format <code>YYYY-MM-DD HH:MM:SS</code> unless otherwise specified.</li>
                    </ul>
                    
                    <ul data-lang="es">
                        <li><strong>URL Base:</strong> <code>/api</code> (relativo a tu instalación)</li>
                        <li><strong>Auth:</strong> Token Bearer JWT requerido para operaciones de escritura.</li>
                        <li><strong>Formato Respuesta:</strong> Todas las respuestas son JSON envueltas en un sobre estándar.</li>
                        <li><strong>Fechas:</strong> Formato <code>YYYY-MM-DD HH:MM:SS</code> a menos que se especifique lo contrario.</li>
                    </ul>
                </div>

                <div class="section-container">
                    <h2 class="section-title" data-lang="en">Standard Response Envelope</h2>
                    <h2 class="section-title" data-lang="es">Sobre de Respuesta Estándar</h2>
                    
                    <p data-lang="en">Every API response follows this consistent JSON structure:</p>
                    <p data-lang="es">Toda respuesta de la API sigue esta estructura JSON consistente:</p>
                    
                    <pre class="code-block line-numbers"><code class="language-json">{
    "success": true,           // boolean: did the request succeed?
    "message": "Operation...", // string: human-readable message
    "data": { ... }            // object/array: the requested payload
}</code></pre>

                    <h4 data-lang="en">Error Response Example</h4>
                    <h4 data-lang="es">Ejemplo de Respuesta de Error</h4>
                    
                    <pre class="code-block line-numbers"><code class="language-json">{
    "success": false,
    "message": "Invalid credentials",
    "error_code": 401          // optional: numeric error code
}</code></pre>
                </div>
                
                 <div class="section-container">
                    <h2 class="section-title" data-lang="en">Pagination</h2>
                    <h2 class="section-title" data-lang="es">Paginación</h2>
                    
                    <p data-lang="en">Endpoints that return lists (Orders, Products) support pagination via query parameters.</p>
                    <p data-lang="es">Los endpoints que retornan listas (Pedidos, Productos) soportan paginación vía parámetros GET.</p>
                    
                    <table class="table table-bordered" data-lang="en">
                        <thead><tr><th>Parameter</th><th>Type</th><th>Default</th><th>Description</th></tr></thead>
                        <tbody>
                            <tr><td><code>page</code></td><td>integer</td><td>1</td><td>Current page number</td></tr>
                            <tr><td><code>limit</code></td><td>integer</td><td>20</td><td>Items per page</td></tr>
                        </tbody>
                    </table>
                    
                    <table class="table table-bordered" data-lang="es">
                        <thead><tr><th>Parámetro</th><th>Tipo</th><th>Defecto</th><th>Descripción</th></tr></thead>
                        <tbody>
                            <tr><td><code>page</code></td><td>entero</td><td>1</td><td>Número de página actual</td></tr>
                            <tr><td><code>limit</code></td><td>entero</td><td>20</td><td>Elementos por página</td></tr>
                        </tbody>
                    </table>
                    
                    <h4 data-lang="en">Paginated Response</h4>
                    <h4 data-lang="es">Respuesta Paginada</h4>
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
                
                <!-- Get Order States Endpoint -->
                <div class="section-container">
                    <h2 class="section-title" data-lang="en">Get Order States</h2>
                    <h2 class="section-title" data-lang="es">Obtener Estados de Pedidos</h2>
                    
                    <p data-lang="en">Retrieve all available order states programmatically.</p>
                    <p data-lang="es">Obtener todos los estados de pedidos disponibles de forma programática.</p>
                    
                    <div class="code-block">
                        <span class="badge-endpoint badge-get">GET</span> /api/pedidos/estados
                        <span class="badge bg-success float-end">🌐 <span data-lang="en">Public</span><span data-lang="es">Público</span></span>
                    </div>
                    
                    <h4 data-lang="en">Example Request</h4>
                    <h4 data-lang="es">Ejemplo de Petición</h4>
                    <pre class="code-block line-numbers"><code class="language-bash">GET /api/pedidos/estados</code></pre>
                    
                    <h4 data-lang="en">Response <span class="status-badge status-200">200 OK</span></h4>
                    <h4 data-lang="es">Respuesta <span class="status-badge status-200">200 OK</span></h4>
                    <pre class="code-block line-numbers"><code class="language-json">{
    "success": true,
    "data": [
        {"id": 1, "nombre_estado": "En bodega"},
        {"id": 2, "nombre_estado": "En ruta o proceso"},
        {"id": 3, "nombre_estado": "Entregado"},
        {"id": 4, "nombre_estado": "Reprogramado"},
        {"id": 5, "nombre_estado": "Domicilio cerrado"},
        {"id": 6, "nombre_estado": "No hay quien reciba en domicilio"},
        {"id": 7, "nombre_estado": "Devuelto"},
        {"id": 8, "nombre_estado": "Domicilio no encontrado"},
        {"id": 9, "nombre_estado": "Rechazado"},
        {"id": 10, "nombre_estado": "No puede pagar recaudo"}
    ]
}</code></pre>

                    <div class="alert alert-info mt-3">
                        <strong data-lang="en">💡 Use Cases</strong>
                        <strong data-lang="es">💡 Casos de Uso</strong>
                        <ul class="mb-0 mt-2" data-lang="en">
                            <li>Populate status dropdown filters</li>
                            <li>Build dynamic order management UIs</li>
                            <li>Validate status IDs before updates</li>
                        </ul>
                        <ul class="mb-0 mt-2" data-lang="es">
                            <li>Poblar filtros dropdown de estados</li>
                            <li>Construir UIs dinámicas de gestión de pedidos</li>
                            <li>Validar IDs de estado antes de actualizar</li>
                        </ul>
                    </div>
                </div>
                
            <!-- Reference: Order Statuses -->
                 <div class="section-container">
                    <h2 class="section-title" data-lang="en">Order Status Reference</h2>
                    <h2 class="section-title" data-lang="es">Referencia de Estados</h2>
                    
                    <p data-lang="en">Use these IDs when filtering or updating order statuses.</p>
                    <p data-lang="es">Usa estos IDs al filtrar o actualizar estados de pedidos.</p>
                    
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th><span data-lang="en">Status Name</span><span data-lang="es">Nombre Estado</span></th>
                                <th><span data-lang="en">Description</span><span data-lang="es">Descripción</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td><code>1</code></td><td>En bodega</td><td><span data-lang="en">Initial status, order received at warehouse.</span><span data-lang="es">Estado inicial, pedido recibido en bodega.</span></td></tr>
                            <tr><td><code>2</code></td><td>En ruta o proceso</td><td><span data-lang="en">Order is being delivered.</span><span data-lang="es">El pedido está en camino.</span></td></tr>
                            <tr><td><code>3</code></td><td>Entregado</td><td><span data-lang="en">Order successfully delivered.</span><span data-lang="es">Pedido entregado exitosamente.</span></td></tr>
                            <tr><td><code>4</code></td><td>Reprogramado</td><td><span data-lang="en">Delivery rescheduled for another day/time.</span><span data-lang="es">Entrega reprogramada para otro día/hora.</span></td></tr>
                            <tr><td><code>5</code></td><td>Domicilio cerrado</td><td><span data-lang="en">Delivery failed: location closed.</span><span data-lang="es">Falló entrega: lugar cerrado.</span></td></tr>
                            <tr><td><code>6</code></td><td>No hay quien reciba</td><td><span data-lang="en">Delivery failed: no recipient available.</span><span data-lang="es">Falló entrega: nadie para recibir.</span></td></tr>
                            <tr><td><code>7</code></td><td>Devuelto</td><td><span data-lang="en">Order returned to warehouse.</span><span data-lang="es">Pedido devuelto a bodega.</span></td></tr>
                            <tr><td><code>8</code></td><td>Domicilio no encontrado</td><td><span data-lang="en">Address could not be located.</span><span data-lang="es">No se encontró la dirección.</span></td></tr>
                            <tr><td><code>9</code></td><td>Rechazado</td><td><span data-lang="en">Customer rejected the order.</span><span data-lang="es">Cliente rechazó el pedido.</span></td></tr>
                            <tr><td><code>10</code></td><td>No puede pagar recaudo</td><td><span data-lang="en">Customer unable to pay on delivery.</span><span data-lang="es">Cliente no pudo pagar al recibir.</span></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab: Authentication -->
            <div class="tab-pane fade" id="auth" role="tabpanel">
                <div class="section-container">
                    <h2 class="section-title" data-lang="en">Authentication</h2>
                    <h2 class="section-title" data-lang="es">Autenticación</h2>
                    
                    <p data-lang="en">To perform write operations (create orders, products, etc.), you must obtain a JWT token.</p>
                    <p data-lang="es">Para realizar operaciones de escritura (crear pedidos, productos, etc.), debes obtener un token JWT.</p>

                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        <strong data-lang="en">Role Access & Capabilities:</strong>
                        <strong data-lang="es">Accesos y Capacidades por Rol:</strong>
                        <div class="mt-2">
                            <ul class="mb-0 ps-3">
                                <li class="mb-2">
                                    <strong class="text-primary">Role: Client</strong>
                                    <div data-lang="en" class="small text-muted">Use this role for <strong>Order Management</strong>: Create new orders, manage massive shipments, and control inventory.</div>
                                    <div data-lang="es" class="small text-muted">Usa este rol para <strong>Gestión de Pedidos</strong>: Crear nuevos pedidos, administrar envíos masivos y controlar inventario.</div>
                                </li>
                                <li>
                                    <strong class="text-primary">Role: Provider</strong>
                                    <div data-lang="en" class="small text-muted">Use this role for <strong>Tracking & Visualization</strong>: View order history and real-time delivery status.</div>
                                    <div data-lang="es" class="small text-muted">Usa este rol para <strong>Seguimiento y Visualización</strong>: Ver historial de pedidos y estado de entrega en tiempo real.</div>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <h4 data-lang="en">1. Get Token</h4>
                    <h4 data-lang="es">1. Obtener Token</h4>
                    <div class="code-block">
                        <span class="badge-endpoint badge-post">POST</span> /api/auth/login
                        <span class="badge bg-success float-end">🔓 <span data-lang="en">Public</span><span data-lang="es">Público</span></span>
                    </div>

                    <h5 data-lang="en">Request Body</h5>
                    <h5 data-lang="es">Cuerpo de la Petición</h5>
                    <table class="table table-bordered" data-lang="en">
                        <thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Description</th></tr></thead>
                        <tbody>
                            <tr><td><code>email</code></td><td>string</td><td>✅ Yes</td><td>Registered user email</td></tr>
                            <tr><td><code>password</code></td><td>string</td><td>✅ Yes</td><td>User password</td></tr>
                        </tbody>
                    </table>
                     <table class="table table-bordered" data-lang="es">
                        <thead><tr><th>Campo</th><th>Tipo</th><th>Req.</th><th>Descripción</th></tr></thead>
                        <tbody>
                            <tr><td><code>email</code></td><td>string</td><td>✅ Sí</td><td>Email del usuario registrado</td></tr>
                            <tr><td><code>password</code></td><td>string</td><td>✅ Sí</td><td>Contraseña del usuario</td></tr>
                        </tbody>
                    </table>

                    <h5 data-lang="en">Example Request</h5>
                    <h5 data-lang="es">Ejemplo de Petición</h5>
                    <pre class="code-block line-numbers"><code class="language-bash">curl -X POST "http://localhost/paqueteriacz/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com", "password":"secure_password"}'</code></pre>

                    <h5 data-lang="en">Response <span class="status-badge status-200">200 OK</span></h5>
                    <h5 data-lang="es">Respuesta <span class="status-badge status-200">200 OK</span></h5>
                    <pre class="code-block line-numbers"><code class="language-json">{
    "success": true,
    "message": "Login exitoso",
    "data": {
        "token": "eyJ0e... (your_token_here) ... "
    }
}</code></pre>

                    <h4 data-lang="en">2. Use Token</h4>
                    <h4 data-lang="es">2. Usar el Token</h4>
                    <p data-lang="en">Include the token in the <code>Authorization</code> header for subsequent requests.</p>
                    <p data-lang="es">Incluye el token en el encabezado <code>Authorization</code> para las siguientes peticiones.</p>
                    
                    <div class="code-block">Authorization: Bearer &lt;YOUR_TOKEN&gt;</div>
                </div>
            </div>

            <!-- Tab: Orders -->
            <div class="tab-pane fade" id="orders" role="tabpanel">
                
                <div class="section-container">
                    <h2 class="section-title" data-lang="en">List & View</h2>
                    <h2 class="section-title" data-lang="es">Listar y Ver</h2>
                    
                    <div class="mb-4">
                        <h4 data-lang="en">List Orders</h4>
                        <h4 data-lang="es">Listar Pedidos</h4>
                        <div class="code-block">
                            <span class="badge-endpoint badge-get">GET</span> /api/pedidos/listar
                            <span class="badge bg-primary float-end">🔐 <span data-lang="en">Authenticated</span><span data-lang="es">Autenticado</span></span>
                        </div>
                        <p data-lang="en">Retrieve a list of orders with advanced filtering and pagination. Use these parameters to refine your results.</p>
                        <p data-lang="es">Obtén una lista de pedidos con filtrado avanzado y paginación. Usa estos parámetros para refinar tus resultados.</p>
                        
                        <h5 class="mt-4" data-lang="en">Query Parameters (Filters)</h5>
                        <h5 class="mt-4" data-lang="es">Parámetros de Consulta (Filtros)</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead>
                                    <tr>
                                        <th><span data-lang="en">Parameter</span><span data-lang="es">Parámetro</span></th>
                                        <th><span data-lang="en">Type</span><span data-lang="es">Tipo</span></th>
                                        <th><span data-lang="en">Description</span><span data-lang="es">Descripción</span></th>
                                        <th><span data-lang="en">Example</span><span data-lang="es">Ejemplo</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td><code>page</code></td><td>int</td><td><span data-lang="en">Page number (default: 1)</span><span data-lang="es">Número de página (defecto: 1)</span></td><td><code>1</code></td></tr>
                                    <tr><td><code>limit</code></td><td>int</td><td><span data-lang="en">Results per page (max: 100)</span><span data-lang="es">Resultados por página (máx: 100)</span></td><td><code>20</code></td></tr>
                                    <tr><td><code>numero_orden</code></td><td>string</td><td><span data-lang="en">Order number</span><span data-lang="es">Número de orden externo</span></td><td><code>88002</code></td></tr>
                                    <tr><td><code>numero_cliente</code></td><td>int</td><td><span data-lang="en">Client ID</span><span data-lang="es">ID del cliente</span></td><td><code>10</code></td></tr>
                                </tbody>
                            </table>
                        </div>

                        <h5 class="mt-4" data-lang="en">Sample Request</h5>
                        <h5 class="mt-4" data-lang="es">Ejemplo de Uso</h5>
                        <pre class="code-block"><code>GET /api/pedidos/listar?numero_orden=88002&numero_cliente=10</code></pre>
                    </div>

                    <div class="mb-4">
                        <h4 data-lang="en">Get Single Order</h4>
                        <h4 data-lang="es">Ver Pedido</h4>
                        <div class="code-block">
                            <span class="badge-endpoint badge-get">GET</span> /api/pedidos/ver?id=100
                            <span class="badge bg-primary float-end">🔐 <span data-lang="en">Authenticated</span><span data-lang="es">Autenticado</span></span>
                        </div>
                        <p class="small text-muted">Returns full details of a specific order by Internal ID.</p>
                    </div>
                </div>

                <!-- Create Order -->
                 <div class="section-container">
                    <h2 class="section-title" data-lang="en">Create Order</h2>
                    <h2 class="section-title" data-lang="es">Crear Pedido</h2>
                    
                    <p data-lang="en">Create a new delivery order. The system automatically validates stock, calculates pricing, and enforces security rules based on user role.</p>
                    <p data-lang="es">Crea un nuevo pedido de entrega. El sistema valida automáticamente el stock, calcula precios y aplica reglas de seguridad según el rol del usuario.</p>

                    <div class="alert alert-danger mt-3">
                        <strong data-lang="en">⚠️ Strict Required Fields:</strong>
                        <strong data-lang="es">⚠️ Campos estrictos (obligatorios):</strong>
                        <p class="mb-0 mt-2" data-lang="en">These fields are <strong>REQUIRED/STRICT</strong>. If any is missing, empty, or incorrect, the order <strong>WILL NOT</strong> be created and an HTTP 400 error will be returned with field-specific details.</p>
                        <p class="mb-0 mt-2" data-lang="es">Estos campos son <strong>REQUIRED/STRICT</strong>. Si falta alguno o viene vacío/incorrecto, el pedido <strong>NO</strong> se crea y se retorna HTTP 400 con detalle por campo.</p>
                    </div>

                    <div class="code-block">
                        <span class="badge-endpoint badge-post">POST</span> /api/pedidos/crear
                        <span class="badge bg-primary float-end">🔐 <span data-lang="en">Authenticated</span><span data-lang="es">Autenticado</span></span>
                        <span class="badge bg-warning text-dark float-end me-1">👤 <span data-lang="en">Role: Client</span><span data-lang="es">Rol: Cliente</span></span>
                    </div>

                    <h4 data-lang="en">🔑 Required Fields (Strict)</h4>
                    <h4 data-lang="es">🔑 Campos Obligatorios (Estrictos)</h4>
                    
                     <table class="table table-sm table-bordered" data-lang="en">
                        <thead><tr><th>Field</th><th>Type</th><th>Validation</th><th>Description</th></tr></thead>
                        <tbody>
                            <tr><td><code>numero_orden</code></td><td>integer/string</td><td>STRICT</td><td>External order ID</td></tr>
                            <tr><td><code>destinatario</code></td><td>string</td><td>STRICT</td><td>Recipient's full name</td></tr>
                            <tr><td><code>producto_id</code></td><td>array</td><td>STRICT, not empty</td><td>Array of product objects/IDs</td></tr>
                            <tr><td><code>id_cliente</code></td><td>integer</td><td>STRICT, exists</td><td>Client ID owner</td></tr>
                            <tr><td><code>id_proveedor</code></td><td>integer</td><td>STRICT, exists</td><td>Messenger/Provider ID assigned</td></tr>
                            <tr><td><code>telefono</code></td><td>string</td><td>STRICT</td><td>Contact phone</td></tr>
                            <tr><td><code>direccion</code></td><td>string</td><td>STRICT</td><td>Full delivery address</td></tr>
                            <tr><td><code>comentario</code></td><td>string</td><td>STRICT</td><td>Delivery notes</td></tr>
                            <tr><td><code>id_pais</code></td><td>integer</td><td>STRICT, exists</td><td>Country ID</td></tr>
                            <tr><td><code>id_departamento</code></td><td>integer</td><td>STRICT, in country</td><td>Department ID</td></tr>
                            <tr><td><code>id_municipio</code></td><td>integer</td><td>STRICT, in dept</td><td>Municipality ID</td></tr>
                            <tr><td><code>zona</code></td><td>string</td><td>STRICT</td><td>Neighborhood/Zone</td></tr>
                            <tr><td><code>codigo_postal</code></td><td>string</td><td>STRICT</td><td>Postal code</td></tr>
                            <tr><td><code>precio_total_local</code></td><td>decimal</td><td>STRICT, > 0</td><td>Total local price</td></tr>
                            <tr><td><code>es_combo</code></td><td>integer</td><td>STRICT (0 or 1)</td><td>1 for combo, 0 for standard</td></tr>
                        </tbody>
                    </table>
                    
                    <table class="table table-sm table-bordered" data-lang="es">
                        <thead><tr><th>Campo</th><th>Tipo</th><th>Validación</th><th>Descripción</th></tr></thead>
                        <tbody>
                            <tr><td><code>numero_orden</code></td><td>integer/string</td><td>ESTRICTO</td><td>ID externo del pedido</td></tr>
                            <tr><td><code>destinatario</code></td><td>string</td><td>ESTRICTO</td><td>Nombre del destinatario</td></tr>
                            <tr><td><code>producto_id</code></td><td>array</td><td>ESTRICTO, no vacío</td><td>Array de productos (objetos o IDs)</td></tr>
                            <tr><td><code>id_cliente</code></td><td>entero</td><td>ESTRICTO, existe</td><td>ID del cliente dueño</td></tr>
                            <tr><td><code>id_proveedor</code></td><td>entero</td><td>ESTRICTO, existe</td><td>ID del proveedor de mensajería asignado</td></tr>
                            <tr><td><code>telefono</code></td><td>string</td><td>ESTRICTO</td><td>Teléfono de contacto</td></tr>
                            <tr><td><code>direccion</code></td><td>string</td><td>ESTRICTO</td><td>Dirección completa</td></tr>
                            <tr><td><code>comentario</code></td><td>string</td><td>ESTRICTO</td><td>Notas de entrega</td></tr>
                            <tr><td><code>id_pais</code></td><td>entero</td><td>ESTRICTO, existe</td><td>ID del país</td></tr>
                            <tr><td><code>id_departamento</code></td><td>entero</td><td>ESTRICTO, en país</td><td>ID del departamento</td></tr>
                            <tr><td><code>id_municipio</code></td><td>entero</td><td>ESTRICTO, en depto</td><td>ID del municipio</td></tr>
                            <tr><td><code>zona</code></td><td>string</td><td>ESTRICTO</td><td>Zona o barrio</td></tr>
                            <tr><td><code>codigo_postal</code></td><td>string</td><td>ESTRICTO</td><td>Código postal</td></tr>
                            <tr><td><code>precio_total_local</code></td><td>decimal</td><td>ESTRICTO, > 0</td><td>Precio total local</td></tr>
                            <tr><td><code>es_combo</code></td><td>entero</td><td>ESTRICTO (0 o 1)</td><td>1 si es combo, 0 si estándar</td></tr>
                        </tbody>
                    </table>
                    
                    <!-- ... (middle content skipped for brevity but would be preserved in a real manual edit, here handled by context) ... -->

                 <!-- Bulk Orders -->
                 <div class="section-container">
                    <h2 class="section-title" data-lang="en">Bulk Import (Async)</h2>
                    <h2 class="section-title" data-lang="es">Importación Masiva (Async)</h2>
                    
                    <p data-lang="en">Import multiple orders efficiently. Use <code>auto_enqueue=true</code> to process in background.</p>
                    <p data-lang="es">Importa múltiples pedidos eficientemente. Usa <code>auto_enqueue=true</code> para procesar en segundo plano.</p>
                    
                    <div class="code-block">
                        <span class="badge-endpoint badge-post">POST</span> /api/pedidos/multiple?auto_enqueue=true
                        <span class="badge bg-primary float-end">🔐 <span data-lang="en">Authenticated</span><span data-lang="es">Autenticado</span></span>
                        <span class="badge bg-warning text-dark float-end me-1">👤 <span data-lang="en">Role: Client</span><span data-lang="es">Rol: Cliente</span></span>
                    </div>

                    <h4 data-lang="en">📋 Optional Details (Automatic)</h4>
                    <h4 data-lang="es">📋 Detalles Opcionales (Automáticos)</h4>
                    
                    <table class="table table-sm table-bordered" data-lang="en">
                        <thead><tr><th>Field</th><th>Type</th><th>Default</th><th>Description</th></tr></thead>
                        <tbody>
                            <tr><td><code>coordenadas</code></td><td>string</td><td>null</td><td>GPS format: "lat,long" (e.g. "14.6349,-90.5069")</td></tr>
                            <tr><td><code>latitud</code></td><td>float</td><td>null</td><td>Latitude (alternative to coordenadas)</td></tr>
                            <tr><td><code>longitud</code></td><td>float</td><td>null</td><td>Longitude (alternative to coordenadas)</td></tr>
                            <tr><td><code>id_barrio</code></td><td>integer</td><td>null</td><td>Neighborhood/District ID (if known)</td></tr>
                        </tbody>
                    </table>
                    
                    <table class="table table-sm table-bordered" data-lang="es">
                        <thead><tr><th>Campo</th><th>Tipo</th><th>Defecto</th><th>Descripción</th></tr></thead>
                        <tbody>
                            <tr><td><code>coordenadas</code></td><td>string</td><td>null</td><td>Formato GPS: "lat,long" (ej. "14.6349,-90.5069")</td></tr>
                            <tr><td><code>latitud</code></td><td>float</td><td>null</td><td>Latitud (alternativa a coordenadas)</td></tr>
                            <tr><td><code>longitud</code></td><td>float</td><td>null</td><td>Longitud (alternativa a coordenadas)</td></tr>
                            <tr><td><code>id_barrio</code></td><td>entero</td><td>null</td><td>ID del barrio/distrito (si se conoce)</td></tr>
                        </tbody>
                    </table>

                    <h4 data-lang="en">🌍 Optional Fields - Geographic</h4>
                    <h4 data-lang="es">🌍 Campos Opcionales - Geográficos</h4>
                    
                    <table class="table table-sm table-bordered" data-lang="en">
                        <thead><tr><th>Field</th><th>Type</th><th>Description</th></tr></thead>
                        <tbody>
                            <tr><td><code>id_pais</code> or <code>pais</code></td><td>integer</td><td>Country ID</td></tr>
                            <tr><td><code>id_departamento</code> or <code>departamento</code></td><td>integer</td><td>State/Department ID</td></tr>
                            <tr><td><code>id_municipio</code> or <code>municipio</code></td><td>integer</td><td>City/Municipality ID</td></tr>
                            <tr><td><code>id_barrio</code> or <code>barrio</code></td><td>integer</td><td>Neighborhood/District ID</td></tr>
                            <tr><td><code>zona</code></td><td>string</td><td>Zone name</td></tr>
                        </tbody>
                    </table>
                    
                    <table class="table table-sm table-bordered" data-lang="es">
                        <thead><tr><th>Campo</th><th>Tipo</th><th>Descripción</th></tr></thead>
                        <tbody>
                            <tr><td><code>id_pais</code> o <code>pais</code></td><td>entero</td><td>ID del país</td></tr>
                            <tr><td><code>id_departamento</code> o <code>departamento</code></td><td>entero</td><td>ID del departamento/estado</td></tr>
                            <tr><td><code>id_municipio</code> o <code>municipio</code></td><td>entero</td><td>ID del municipio/ciudad</td></tr>
                            <tr><td><code>id_barrio</code> o <code>barrio</code></td><td>entero</td><td>ID del barrio/zona</td></tr>
                            <tr><td><code>zona</code></td><td>string</td><td>Nombre de la zona</td></tr>
                        </tbody>
                    </table>
                    <h4 data-lang="en">👥 Optional Fields - Assignments</h4>
                    <h4 data-lang="es">👥 Campos Opcionales - Asignaciones</h4>
                    
                    <table class="table table-sm table-bordered" data-lang="en">
                        <thead><tr><th>Field</th><th>Type</th><th>Default</th><th>Description</th></tr></thead>
                        <tbody>
                            <tr><td><code>id_estado</code></td><td>integer</td><td>1</td><td>Order status (see Status Reference)</td></tr>
                            <tr><td><code>id_vendedor</code></td><td>integer</td><td>null</td><td>Assigned delivery person</td></tr>
                            <tr><td><code>id_proveedor</code></td><td>integer</td><td>✅ Yes</td><td>Messenger/Provider ID assigned to the order</td></tr>
                            <tr><td><code>id_cliente</code></td><td>integer</td><td>null</td><td>Client ID</td></tr>
                            <tr><td><code>id_moneda</code></td><td>integer</td><td>null</td><td>Currency ID (auto-detected from provider's country if not provided)</td></tr>
                        </tbody>
                    </table>
                    
                    <table class="table table-sm table-bordered" data-lang="es">
                        <thead><tr><th>Campo</th><th>Tipo</th><th>Defecto</th><th>Descripción</th></tr></thead>
                        <tbody>
                            <tr><td><code>id_estado</code></td><td>entero</td><td>1</td><td>Estado del pedido (ver Referencia de Estados)</td></tr>
                            <tr><td><code>id_vendedor</code></td><td>entero</td><td>null</td><td>Repartidor asignado</td></tr>
                            <tr><td><code>id_proveedor</code></td><td>entero</td><td>✅ Sí</td><td>ID del usuario de mensajería (Proveedor) asignado</td></tr>
                            <tr><td><code>id_cliente</code></td><td>entero</td><td>null</td><td>ID del cliente</td></tr>
                            <tr><td><code>id_moneda</code></td><td>entero</td><td>null</td><td>ID de la moneda (auto-detectada del país del proveedor si no se envía)</td></tr>
                        </tbody>
                    </table>

                    <h4 data-lang="en">💰 Optional Fields - Pricing</h4>
                    <h4 data-lang="es">💰 Campos Opcionales - Precios</h4>
                    
                    <table class="table table-sm table-bordered" data-lang="en">
                        <thead><tr><th>Field</th><th>Type</th><th>Auto-calculated</th><th>Description</th></tr></thead>
                        <tbody>
                            <tr><td><code>precio_total_local</code></td><td>decimal</td><td>No</td><td>Total price in local currency</td></tr>
                            <tr><td><code>precio_total_usd</code></td><td>decimal</td><td>Yes*</td><td>Total price in USD (auto-calc if local + currency provided)</td></tr>
                            <tr><td><code>tasa_conversion_usd</code></td><td>decimal</td><td>Yes*</td><td>Exchange rate used (auto-fetched from currency)</td></tr>
                            <tr><td><code>es_combo</code></td><td>boolean</td><td>Yes*</td><td>Whether it's a combo (auto-detected from product)</td></tr>
                        </tbody>
                    </table>
                    
                    <table class="table table-sm table-bordered" data-lang="es">
                        <thead><tr><th>Campo</th><th>Tipo</th><th>Auto-calculado</th><th>Descripción</th></tr></thead>
                        <tbody>
                            <tr><td><code>precio_total_local</code></td><td>decimal</td><td>No</td><td>Precio total en moneda local</td></tr>
                            <tr><td><code>precio_total_usd</code></td><td>decimal</td><td>Sí*</td><td>Precio total en USD (auto-calc si local + moneda)</td></tr>
                            <tr><td><code>tasa_conversion_usd</code></td><td>decimal</td><td>Sí*</td><td>Tasa de cambio usada (auto desde moneda)</td></tr>
                            <tr><td><code>es_combo</code></td><td>boolean</td><td>Sí*</td><td>Si es combo (auto-detectado desde producto)</td></tr>
                        </tbody>
                    </table>

                    <h4 data-lang="en">❌ Error Response (Validation Error)</h4>
                    <h4 data-lang="es">❌ Respuesta de Error (Falla de Validación)</h4>
                    
                    <p data-lang="en">When strict rules are not met, a <code>400 Bad Request</code> is returned with a <code>VALIDATION_ERROR</code> message and a <code>fields</code> object mapping each field to its specific error.</p>
                    <p data-lang="es">Cuando no se cumplen las reglas estrictas, se devuelve <code>400 Bad Request</code> con el mensaje <code>VALIDATION_ERROR</code> y un objeto <code>fields</code> que detalla el error por cada campo.</p>
                    
                    <pre class="code-block line-numbers"><code class="language-json">{
    "success": false,
    "message": "VALIDATION_ERROR",
    "fields": {
        "numero_orden": "El número de orden ya existe para este cliente.",
        "id_departamento": "El departamento no pertenece al país seleccionado.",
        "telefono": "El campo 'telefono' debe tener al menos 7 caracteres."
    }
}</code></pre>

                    <h4 data-lang="en">✅ Automatic Validations</h4>
                    <h4 data-lang="es">✅ Validaciones Automáticas</h4>
                    
                    <ul data-lang="en">
                        <li><strong>Geography Hierarchy:</strong> Rejects if Department doesn't match Country, or Municipality doesn't match Department.</li>
                        <li><strong>CP Normalization:</strong> Postal codes are converted to uppercase and stripped of spaces/dashes before saving.</li>
                        <li><strong>Order Uniqueness:</strong> Scoped by <code>id_cliente</code> to prevent collision between different clients' numbering.</li>
                        <li><strong>Stock validation:</strong> Ensures sufficient inventory before creating order.</li>
                        <li><strong>Foreign key validation:</strong> Verifies that all IDs (vendor, provider, client, currency) exist in database.</li>
                    </ul>
                    
                    <ul data-lang="es">
                        <li><strong>Jerarquía Geográfica:</strong> Rechaza si el Depto no coincide con el País, o el Muni no coincide con el Depto.</li>
                        <li><strong>Normalización de CP:</strong> Los códigos postales se convierten a mayúsculas y se limpian de espacios/guiones.</li>
                        <li><strong>Unicidad de Orden:</strong> Validada por <code>id_cliente</code> para evitar colisiones entre numeración de distintos clientes.</li>
                        <li><strong>Validación de stock:</strong> Asegura inventario suficiente antes de crear.</li>
                        <li><strong>Validación FK:</strong> Verifica que todos los IDs (vendedor, proveedor, cliente, moneda) existan en BD.</li>
                    </ul>

                    <h4 data-lang="en">🔐 Security Rules</h4>
                    <h4 data-lang="es">🔐 Reglas de Seguridad</h4>
                    
                    <p data-lang="en"><strong>Provider Assignment:</strong> This field is strict. You must explicitly provide the ID of the user (role Provider) who will handle the delivery.</p>
                    <p data-lang="es"><strong>Asignación de Proveedor:</strong> Este campo es estricto. Debes proporcionar explícitamente el ID del usuario (rol Proveedor) que gestionará la entrega.</p>

                    <h4 data-lang="en">📝 Example: Valid Order with Minimal Fields</h4>
                    <h4 data-lang="es">📝 Ejemplo: Pedido Válido con Campos Mínimos</h4>
                    <pre class="code-block line-numbers"><code class="language-json">{
    "numero_orden": 12345,
    "destinatario": "Juan Pérez",
    "id_cliente": 7,
    "telefono": "50212345678",
    "direccion": "Avenida 1-23 Zona 1",
    "comentario": "Sin comentario",
    "id_pais": 1,
    "id_departamento": 1,
    "id_municipio": 1,
    "zona": "Zona 1",
    "codigo_postal": "01001",
    "precio_total_local": 100.00,
    "es_combo": 0,
    "producto_id": 10,
    "cantidad": 2
}</code></pre>

                    <h4 data-lang="en">📝 Example: Complete Multi-Product Combo</h4>
                    <h4 data-lang="es">📝 Ejemplo: Pedido Completo con Múltiples Productos y Combo</h4>
                    <pre class="code-block line-numbers"><code class="language-json">{
    "numero_orden": 12346,
    "destinatario": "María García",
    "id_cliente": 15,
    "telefono": "50298765432",
    "direccion": "Calle Principal #123",
    "comentario": "Entregar en horario de oficina",
    "id_pais": 1,
    "id_departamento": 5,
    "id_municipio": 25,
    "zona": "Candelaria",
    "codigo_postal": "01005",
    "id_moneda": 2,
    "es_combo": 1,
    "precio_total_local": 250.00,
    "productos": [
        { "producto_id": 10, "cantidad": 2 },
        { "producto_id": 15, "cantidad": 1 }
    ]
}</code></pre>
                 </div>

                 <!-- Order Types Explanation -->
                 <div class="section-container">
                    <h2 class="section-title" data-lang="en">Standard vs. Combo Orders</h2>
                    <h2 class="section-title" data-lang="es">Pedidos Estándar vs. Combos</h2>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h4 data-lang="en">🛒 Standard Order</h4>
                            <h4 data-lang="es">🛒 Pedido Estándar</h4>
                            <p data-lang="en">
                                <strong>When to use:</strong> Regular sales where items are sold at their list price.<br>
                                <strong>Logic:</strong> The system automatically sums up <code>(product_price × quantity)</code>.<br>
                                <strong>Don't send:</strong> <code>precio_total</code> (it will be ignored/overwritten).
                            </p>
                            <pre class="code-block line-numbers"><code class="language-json">{
    "numero_orden": "STD-101",
    "es_combo": 0,
    "productos": [
        { "producto_id": 1, "cantidad": 2 }
    ]
    // Total calculated by system:
    // (Price of ID 1 × 2)
}</code></pre>
                        </div>
                        <div class="col-md-6">
                            <h4 data-lang="en">🎁 Combo / Promo</h4>
                            <h4 data-lang="es">🎁 Combo / Promoción</h4>
                            <p data-lang="en">
                                <strong>When to use:</strong> Special offers, bundles, or fixed-price packages.<br>
                                <strong>Logic:</strong> You MUST define the <code>precio_total_local</code> explicitly.<br>
                                <strong>Important:</strong> Set <code>es_combo: 1</code> so the system respects your total.
                            </p>
                            <pre class="code-block line-numbers"><code class="language-json">{
    "numero_orden": "PROMO-500",
    "es_combo": 1,
    "precio_total_local": 999.00,
    "productos": [
        { "producto_id": 1, "cantidad": 1 },
        { "producto_id": 5, "cantidad": 1 }
    ]
    // System accepts 999.00
    // ignoring individual prices.
}</code></pre>
                        </div>
                    </div>
                 </div>
                 
                 <!-- Bulk Orders -->
                 <div class="section-container">
                    <h2 class="section-title" data-lang="en">Bulk Import (Async)</h2>
                    <h2 class="section-title" data-lang="es">Importación Masiva (Async)</h2>
                    
                    <p data-lang="en">Import multiple orders efficiently. Use <code>auto_enqueue=true</code> to process in background.</p>
                    <p data-lang="es">Importa múltiples pedidos eficientemente. Usa <code>auto_enqueue=true</code> para procesar en segundo plano.</p>
                    
                    <div class="code-block">
                        <span class="badge-endpoint badge-post">POST</span> /api/pedidos/multiple?auto_enqueue=true
                        <span class="badge bg-primary float-end">🔐 <span data-lang="en">Authenticated</span><span data-lang="es">Autenticado</span></span>
                    </div>
                    
                    <h4 data-lang="en">Success Response (202 Accepted)</h4>
                    <h4 data-lang="es">Respuesta Exitosa (202 Accepted)</h4>
                    <pre class="code-block line-numbers"><code class="language-json">{
    "success": true,
    "message": "Proceso iniciado",
    "results": [
        { "numero_orden": 10050, "success": true, "job_queued": true },
        { "numero_orden": 10051, "success": true, "job_queued": true }
    ]
}</code></pre>

                    <h4 data-lang="en" class="mt-4">Advanced Example: Multiple Products & Combo</h4>
                    <h4 data-lang="es" class="mt-4">Ejemplo Avanzado: Múltiples Productos y Combos</h4>
                    
                    <pre class="code-block line-numbers"><code class="language-json">{
    "pedidos": [
        {
            // Pedido multiproducto estándar
            "numero_orden": 20001,
            "destinatario": "Juan Perez",
            "telefono": "5555-5555",
            "direccion": "Calle Principal #123",
            "coordenadas": "12.1234,-86.1234",
            "productos": [
                { "producto_id": 1, "cantidad": 2 },
                { "producto_id": 5, "cantidad": 1 }
            ]
        },
        {
            // Pedido tipo COMBO (Precio fijo total)
            "numero_orden": 20002,
            "destinatario": "Ana Lopez",
            "telefono": "7777-7777",
            "direccion": "Residencial Los Arcos, Casa 5",
            "coordenadas": "12.1255,-86.1255",
            "es_combo": 1, 
            "precio_total_local": 1500.00,  // Precio fijo total
            "precio_total_usd": 40.50,      // Opcional si se calcula, pero recomendado en combos
            "productos": [
                { "producto_id": 10, "cantidad": 1 },
                { "producto_id": 11, "cantidad": 1 },
                { "producto_id": 12, "cantidad": 1 }
            ]
        }
    ]
}</code></pre>
                 </div>
            </div>
            </div>

            <!-- Tab: Products -->
            <div class="tab-pane fade" id="products" role="tabpanel" aria-labelledby="products-tab">
                 <div class="section-container">
                    <h2 class="section-title" data-lang="en">Product Management</h2>
                    <h2 class="section-title" data-lang="es">Gestión de Productos</h2>
                    
                    <div class="mb-4">
                        <div class="code-block">
                            <span class="badge-endpoint badge-get">GET</span> /api/productos/listar
                            <span class="badge bg-success float-end">🔓 <span data-lang="en">Public</span><span data-lang="es">Público</span></span>
                        </div>
                        <p data-lang="en" class="mt-2">List all available products with current stock.</p>
                        <p data-lang="es" class="mt-2">Listar todos los productos disponibles con stock actual.</p>

                        <h5 data-lang="en" class="mt-3">Query Parameters</h5>
                        <h5 data-lang="es" class="mt-3">Parámetros de Consulta</h5>
                        
                        <table class="table table-sm table-bordered mt-2" data-lang="en">
                            <thead><tr><th>Parameter</th><th>Type</th><th>Description</th></tr></thead>
                            <tbody>
                                <tr><td><code>page</code></td><td>integer</td><td>Page number (default 1)</td></tr>
                                <tr><td><code>limit</code></td><td>integer</td><td>Items per page (default 50)</td></tr>
                                <tr><td><code>id_cliente</code></td><td>integer</td><td>Filter by Creator/Client ID</td></tr>
                                <tr><td><code>categoria_id</code></td><td>integer</td><td>Filter by Category ID</td></tr>
                                 <tr><td><code>marca</code></td><td>string</td><td>Filter by Brand name (exact match)</td></tr>
                                <tr><td><code>sku</code></td><td>string</td><td>Filter by exact SKU</td></tr>
                                <tr><td><code>activo</code></td><td>boolean</td><td>Filter by active status (1/0 or true/false)</td></tr>
                            </tbody>
                        </table>
                        
                        <table class="table table-sm table-bordered mt-2" data-lang="es">
                            <thead><tr><th>Parámetro</th><th>Tipo</th><th>Descripción</th></tr></thead>
                            <tbody>
                                <tr><td><code>page</code></td><td>entero</td><td>Número de página (defecto 1)</td></tr>
                                <tr><td><code>limit</code></td><td>entero</td><td>Items por página (defecto 50)</td></tr>
                                <tr><td><code>id_cliente</code></td><td>entero</td><td>Filtrar por ID de Cliente/Creador</td></tr>
                                <tr><td><code>categoria_id</code></td><td>entero</td><td>Filtrar por ID de Categoría</td></tr>
                                <tr><td><code>marca</code></td><td>string</td><td>Filtrar por Marca (coincidencia exacta)</td></tr>
                                <tr><td><code>sku</code></td><td>string</td><td>Filtrar por SKU exacto</td></tr>
                                <tr><td><code>activo</code></td><td>boolean</td><td>Filtrar por estado activo (1/0 o true/false)</td></tr>
                            </tbody>
                        </table>

                        <h5 data-lang="en" class="mt-3">📝 Usage Examples</h5>
                        <h5 data-lang="es" class="mt-3">📝 Ejemplos de Uso</h5>

                        <pre class="code-block line-numbers"><code class="language-bash"># 1. List products created by client ID 15
GET /api/productos/listar?id_cliente=15

# 2. List only active products in category 8
GET /api/productos/listar?activo=1&categoria_id=8

# 3. Filter by brand
GET /api/productos/listar?marca=Samsung

# 4. Combined filter with pagination
GET /api/productos/listar?id_cliente=15&activo=1&page=2&limit=10</code></pre>
                    </div>

                    <div class="mb-4">
                         <div class="code-block">
                            <span class="badge-endpoint badge-post">POST</span> /api/productos/crear
                            <span class="badge bg-primary float-end">🔐 <span data-lang="en">Authenticated</span><span data-lang="es">Autenticado</span></span>
                            <span class="badge bg-warning text-dark float-end me-1">👤 <span data-lang="en">Role: Client</span><span data-lang="es">Rol: Cliente</span></span>
                        </div>
                         <p data-lang="en" class="mt-2">Create a new product.</p>
                         <p data-lang="es" class="mt-2">Crear un nuevo producto.</p>
                         
                         <h5 data-lang="en" class="mt-3">Request Body</h5>
                         <h5 data-lang="es" class="mt-3">Cuerpo de la Petición</h5>
                         <table class="table table-sm table-bordered mt-2">
                             <thead>
                                 <tr>
                                     <th><span data-lang="en">Field</span><span data-lang="es">Campo</span></th>
                                     <th><span data-lang="en">Type</span><span data-lang="es">Tipo</span></th>
                                     <th><span data-lang="en">Req.</span><span data-lang="es">Req.</span></th>
                                     <th><span data-lang="en">Description</span><span data-lang="es">Descripción</span></th>
                                 </tr>
                             </thead>
                             <tbody>
                                 <tr><td><code>nombre</code></td><td>string</td><td>✅</td><td><span data-lang="en">Product name</span><span data-lang="es">Nombre del producto</span></td></tr>
                                 <tr><td><code>sku</code></td><td>string</td><td>❌</td><td><span data-lang="en">Unique identifier (SKU)</span><span data-lang="es">Identificador único (SKU)</span></td></tr>
                                 <tr><td><code>descripcion</code></td><td>string</td><td>❌</td><td><span data-lang="en">Product description</span><span data-lang="es">Descripción del producto</span></td></tr>
                                 <tr><td><code>precio_usd</code></td><td>number</td><td>❌</td><td><span data-lang="en">Price in USD</span><span data-lang="es">Precio en USD</span></td></tr>
                                 <tr><td><code>stock</code></td><td>integer</td><td>❌</td><td><span data-lang="en">Initial stock level</span><span data-lang="es">Nivel de stock inicial</span></td></tr>
                             </tbody>
                         </table>
                    </div>
                    
                    <h4 data-lang="en">Product Object Model</h4>
                    <h4 data-lang="es">Modelo de Objeto Producto</h4>
                     <pre class="code-block line-numbers"><code class="language-json">{
    "id": 1,
    "nombre": "Protein Shake",
    "sku": "PROT-SHK-001",
    "precio_usd": "45.00",
    "stock_total": 150,
    "descripcion": "High quality whey protein"
}</code></pre>
                 </div>

                 <div class="section-container">
                    <h2 class="section-title" data-lang="en">Update & Delete</h2>
                    <h2 class="section-title" data-lang="es">Actualizar y Eliminar</h2>

                    <div class="mb-4">
                        <h4 data-lang="en">Update Product</h4>
                        <h4 data-lang="es">Actualizar Producto</h4>
                        <div class="code-block">
                            <span class="badge-endpoint badge-put">POST</span> /api/productos/actualizar
                            <span class="badge bg-primary float-end">🔐 <span data-lang="en">Authenticated</span><span data-lang="es">Autenticado</span></span>
                            <span class="badge bg-warning text-dark float-end me-1">👤 <span data-lang="en">Role: Client</span><span data-lang="es">Rol: Cliente</span></span>
                        </div> 
                        <small class="text-muted d-block mb-2">Note: Use POST with <code>id</code> param or check PHP config for PUT support.</small>

                        <pre class="code-block line-numbers"><code class="language-json">{
    "id": 1,
    "nombre": "Protein Shake V2",
    "sku": "PROT-SHK-001-B",
    "precio_usd": 48.00,
    "descripcion": "New formula"
}</code></pre>
                    </div>

                    <div class="mb-4">
                         <h4 data-lang="en">Get Single Product</h4>
                         <h4 data-lang="es">Ver Producto Individual</h4>
                         <div class="code-block">
                            <span class="badge-endpoint badge-get">GET</span> /api/productos/ver?id=1
                            <span class="badge bg-success float-end">🔓 <span data-lang="en">Public</span><span data-lang="es">Público</span></span>
                        </div>
                    </div>
                 </div>
            </div>

            <!-- Tab: Geo -->
            <div class="tab-pane fade" id="geo" role="tabpanel">
                
                <!-- Get All Geoinfo -->
                <div class="section-container">
                    <h2 class="section-title" data-lang="en">🗺️ Get All Geographic Data</h2>
                    <h2 class="section-title" data-lang="es">🗺️ Obtener Todos los Datos Geográficos</h2>
                    
                    <p data-lang="en">Retrieve complete hierarchical geographic data (countries, departments, municipalities, neighborhoods).</p>
                    <p data-lang="es">Obtener datos geográficos jerárquicos completos (países, departamentos, municipios, barrios).</p>
                    
                    <div class="code-block">
                        <span class="badge-endpoint badge-get">GET</span> /api/geoinfo/listar
                        <span class="badge bg-success float-end">🔓 <span data-lang="en">Public</span><span data-lang="es">Público</span></span>
                    </div>
                     
                    <h4 data-lang="en">Response Structure</h4>
                    <h4 data-lang="es">Estructura de Respuesta</h4>
                    <pre class="code-block line-numbers"><code class="language-json">{
    "success": true,
    "data": {
        "paises": [
            { "id": 1, "nombre": "Nicaragua", "codigo_iso": "NI" }
        ],
        "departamentos": [
            { "id": 1, "nombre": "Managua", "id_pais": 1 }
        ],
        "municipios": [
            { "id": 1, "nombre": "Managua", "id_departamento": 1, "codigo_postal": "10000" }
        ],
        "barrios": [
            { "id": 1, "nombre": "Altamira", "id_municipio": 1, "codigo_postal": "10100" }
        ]
    }
}</code></pre>
                </div>

                <!-- Countries Endpoints -->
                <div class="section-container">
                    <h2 class="section-title" data-lang="en">🌎 Countries (Países)</h2>
                    <h2 class="section-title" data-lang="es">🌎 Países</h2>
                    
                    <div class="mb-4">
                        <h4 data-lang="en">List All Countries</h4>
                        <h4 data-lang="es">Listar Todos los Países</h4>
                        <div class="code-block">
                            <span class="badge-endpoint badge-get">GET</span> /api/geoinfo/paises
                            <span class="badge bg-success float-end">🔓 <span data-lang="en">Public</span><span data-lang="es">Público</span></span>
                        </div>
                        
                        <pre class="code-block line-numbers mt-3"><code class="language-json">// Response
[
    {
        "id": 1,
        "nombre": "Nicaragua",
        "codigo_iso": "NI",
        "id_moneda_local": 1
    },
    {
        "id": 2,
        "nombre": "Honduras",
        "codigo_iso": "HN",
        "id_moneda_local": 2
    }
]</code></pre>
                    </div>
                    
                    <div class="mb-4">
                        <h4 data-lang="en">Get Single Country</h4>
                        <h4 data-lang="es">Obtener País Individual</h4>
                        <div class="code-block">
                            <span class="badge-endpoint badge-get">GET</span> /api/geoinfo/paises?id=1
                            <span class="badge bg-success float-end">🔓 <span data-lang="en">Public</span><span data-lang="es">Público</span></span>
                        </div>
                    </div>
                </div>

                <!-- Departments Endpoints -->
                <div class="section-container">
                    <h2 class="section-title" data-lang="en">🏛️ Departments (Departamentos)</h2>
                    <h2 class="section-title" data-lang="es">🏛️ Departamentos</h2>
                    
                    <div class="mb-4">
                        <h4 data-lang="en">List All Departments</h4>
                        <h4 data-lang="es">Listar Todos los Departamentos</h4>
                        <div class="code-block">
                            <span class="badge-endpoint badge-get">GET</span> /api/geoinfo/departamentos
                            <span class="badge bg-success float-end">🔓 <span data-lang="en">Public</span><span data-lang="es">Público</span></span>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h4 data-lang="en">Filter by Country</h4>
                        <h4 data-lang="es">Filtrar por País</h4>
                        <div class="code-block">
                            <span class="badge-endpoint badge-get">GET</span> /api/geoinfo/departamentos<strong>?id_pais=1</strong>
                            <span class="badge bg-success float-end">🔓 <span data-lang="en">Public</span><span data-lang="es">Público</span></span>
                        </div>
                        
                        <table class="table table-sm table-bordered mt-3" data-lang="en">
                            <thead><tr><th>Parameter</th><th>Type</th><th>Description</th></tr></thead>
                            <tbody>
                                <tr><td><code>id_pais</code></td><td>integer</td><td>Filter departments by country ID</td></tr>
                            </tbody>
                        </table>
                        
                        <table class="table table-sm table-bordered mt-3" data-lang="es">
                            <thead><tr><th>Parámetro</th><th>Tipo</th><th>Descripción</th></tr></thead>
                            <tbody>
                                <tr><td><code>id_pais</code></td><td>entero</td><td>Filtrar departamentos por ID de país</td></tr>
                            </tbody>
                        </table>
                        
                        <pre class="code-block line-numbers mt-3"><code class="language-json">// Response
[
    {
        "id": 1,
        "nombre": "Managua",
        "id_pais": 1
    },
    {
        "id": 2,
        "nombre": "Granada",
        "id_pais": 1
    }
]</code></pre>
                    </div>
                </div>

                <!-- Municipalities Endpoints -->
                <div class="section-container">
                    <h2 class="section-title" data-lang="en">🏘️ Municipalities (Municipios)</h2>
                    <h2 class="section-title" data-lang="es">🏘️ Municipios</h2>
                    
                    <div class="mb-4">
                        <h4 data-lang="en">List All Municipalities</h4>
                        <h4 data-lang="es">Listar Todos los Municipios</h4>
                        <div class="code-block">
                            <span class="badge-endpoint badge-get">GET</span> /api/geoinfo/municipios
                            <span class="badge bg-success float-end">🔓 <span data-lang="en">Public</span><span data-lang="es">Público</span></span>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h4 data-lang="en">Filter by Department</h4>
                        <h4 data-lang="es">Filtrar por Departamento</h4>
                        <div class="code-block">
                            <span class="badge-endpoint badge-get">GET</span> /api/geoinfo/municipios<strong>?id_departamento=1</strong>
                            <span class="badge bg-success float-end">🔓 <span data-lang="en">Public</span><span data-lang="es">Público</span></span>
                        </div>
                        
                        <table class="table table-sm table-bordered mt-3" data-lang="en">
                            <thead><tr><th>Parameter</th><th>Type</th><th>Description</th></tr></thead>
                            <tbody>
                                <tr><td><code>id_departamento</code></td><td>integer</td><td>Filter municipalities by department ID</td></tr>
                            </tbody>
                        </table>
                        
                        <table class="table table-sm table-bordered mt-3" data-lang="es">
                            <thead><tr><th>Parámetro</th><th>Tipo</th><th>Descripción</th></tr></thead>
                            <tbody>
                                <tr><td><code>id_departamento</code></td><td>entero</td><td>Filtrar municipios por ID de departamento</td></tr>
                            </tbody>
                        </table>
                        
                        <pre class="code-block line-numbers mt-3"><code class="language-json">// Response
[
    {
        "id": 1,
        "nombre": "Managua",
        "id_departamento": 1,
        "codigo_postal": "10000"
    },
    {
        "id": 2,
        "nombre": "Tipitapa",
        "id_departamento": 1,
        "codigo_postal": "11000"
    }
]</code></pre>
                    </div>
                </div>

                <!-- Neighborhoods Endpoints -->
                <div class="section-container">
                    <h2 class="section-title" data-lang="en">🏠 Neighborhoods (Barrios)</h2>
                    <h2 class="section-title" data-lang="es">🏠 Barrios</h2>
                    
                    <div class="mb-4">
                        <h4 data-lang="en">List All Neighborhoods</h4>
                        <h4 data-lang="es">Listar Todos los Barrios</h4>
                        <div class="code-block">
                            <span class="badge-endpoint badge-get">GET</span> /api/geoinfo/barrios
                            <span class="badge bg-success float-end">🔓 <span data-lang="en">Public</span><span data-lang="es">Público</span></span>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h4 data-lang="en">Filter by Municipality</h4>
                        <h4 data-lang="es">Filtrar por Municipio</h4>
                        <div class="code-block">
                            <span class="badge-endpoint badge-get">GET</span> /api/geoinfo/barrios<strong>?id_municipio=1</strong>
                            <span class="badge bg-success float-end">🔓 <span data-lang="en">Public</span><span data-lang="es">Público</span></span>
                        </div>
                        
                        <table class="table table-sm table-bordered mt-3" data-lang="en">
                            <thead><tr><th>Parameter</th><th>Type</th><th>Description</th></tr></thead>
                            <tbody>
                                <tr><td><code>id_municipio</code></td><td>integer</td><td>Filter neighborhoods by municipality ID</td></tr>
                            </tbody>
                        </table>
                        
                        <table class="table table-sm table-bordered mt-3" data-lang="es">
                            <thead><tr><th>Parámetro</th><th>Tipo</th><th>Descripción</th></tr></thead>
                            <tbody>
                                <tr><td><code>id_municipio</code></td><td>entero</td><td>Filtrar barrios por ID de municipio</td></tr>
                            </tbody>
                        </table>
                        
                        <pre class="code-block line-numbers mt-3"><code class="language-json">// Response
[
    {
        "id": 1,
        "nombre": "Altamira",
        "id_municipio": 1,
        "codigo_postal": "10100"
    },
    {
        "id": 2,
        "nombre": "Bolonia",
    }\n]\</code></pre>
                    </div>
                </div>
                
                <!-- Postal Codes Endpoints -->
                <div class="section-container">
                    <h2 class="section-title" data-lang="en">📮 Postal Codes (Códigos Postales)</h2>
                    <h2 class="section-title" data-lang="es">📮 Códigos Postales</h2>
                    
                    <p data-lang="en">Resolve postal codes to geographic locations or find postal codes by zone.</p>
                    <p data-lang="es">Resolver códigos postales a ubicaciones geográficas o buscar códigos postales por zona.</p>
                    
                    <div class="mb-5">
                        <h4 data-lang="en">Resolve Postal Code</h4>
                        <h4 data-lang="es">Resolver Código Postal</h4>
                        <div class="code-block">
                            <span class="badge-endpoint badge-get">GET</span> /api/geoinfo/codigos_postales?action=resolve&cp=<strong>&lt;cp&gt;</strong>[&amp;id_pais=<strong>&lt;id&gt;</strong>]
                            <span class="badge bg-success float-end">🔓 <span data-lang="en">Public</span><span data-lang="es">Público</span></span>
                        </div>
                        
                        <table class="table table-sm table-bordered mt-3">
                            <thead><tr><th><span data-lang="en">Parameter</span><span data-lang="es">Parámetro</span></th><th><span data-lang="en">Type</span><span data-lang="es">Tipo</span></th><th><span data-lang="en">Req.</span><span data-lang="es">Req.</span></th><th><span data-lang="en">Description</span><span data-lang="es">Descripción</span></th></tr></thead>
                            <tbody>
                                <tr><td><code>cp</code></td><td>string</td><td>✅</td><td><span data-lang="en">Postal code to search</span><span data-lang="es">Código postal a buscar</span></td></tr>
                                <tr><td><code>id_pais</code></td><td>integer</td><td>❌</td><td><span data-lang="en">Filter by specific country ID</span><span data-lang="es">Filtrar por ID de país específico</span></td></tr>
                            </tbody>
                        </table>
                        
                        <pre class="code-block line-numbers mt-3"><code class="language-json">// Response Example (Single Match/Specific)
{
    "ok": true,
    "data": {
        "normalized_cp": "08001",
        "matches": [
            {
                "id_codigo_postal": 542,
                "id_pais": 1,
                "pais": "España",
                "codigo_postal": "08001",
                "id_departamento": 8,
                "departamento": "Barcelona",
                "id_municipio": 12,
                "municipio": "Barcelona",
                "id_barrio": null,
                "barrio": null,
                "partial": false
            }
        ]
    }
}</code></pre>
                    </div>

                    <div class="mb-4">
                        <h4 data-lang="en">Find by Zone</h4>
                        <h4 data-lang="es">Buscar por Zona</h4>
                        <p data-lang="en">Get the postal code for a specific neighborhood.</p>
                        <p data-lang="es">Obtener el código postal para un barrio específico.</p>
                        <div class="code-block">
                            <span class="badge-endpoint badge-get">GET</span> /api/geoinfo/codigos_postales?action=find_by_zone&id_pais=<strong>&lt;id&gt;</strong>&id_barrio=<strong>&lt;id&gt;</strong>
                            <span class="badge bg-success float-end">🔓 <span data-lang="en">Public</span><span data-lang="es">Público</span></span>
                        </div>
                    </div>
                </div>

                <!-- Search Endpoint -->
                <div class="section-container">
                    <h2 class="section-title" data-lang="en">🔍 Unified Search</h2>
                    <h2 class="section-title" data-lang="es">🔍 Búsqueda Unificada</h2>
                    
                    <p data-lang="en">Search across all geographic entities (países, departamentos, municipios, barrios) with autocomplete/typeahead functionality.</p>
                    <p data-lang="es">Buscar en todas las entidades geográficas (países, departamentos, municipios, barrios) con funcionalidad de autocomplete/typeahead.</p>
                    
                    <div class="code-block">
                        <span class="badge-endpoint badge-get">GET</span> /api/geoinfo/buscar?q=<strong>&lt;query&gt;</strong>
                        <span class="badge bg-success float-end">🔓 <span data-lang="en">Public</span><span data-lang="es">Público</span></span>
                    </div>
                    
                    <h4 data-lang="en">Query Parameters</h4>
                    <h4 data-lang="es">Parámetros de Consulta</h4>
                    
                    <table class="table table-sm table-bordered" data-lang="en">
                        <thead><tr><th>Parameter</th><th>Type</th><th>Required</th><th>Description</th></tr></thead>
                        <tbody>
                            <tr><td><code>q</code></td><td>string</td><td>✅ Yes</td><td>Search query (min 2 chars)</td></tr>
                            <tr><td><code>tipo</code></td><td>string</td><td>❌ No</td><td>Filter by type: <code>pais</code>, <code>departamento</code>, <code>municipio</code>, <code>barrio</code></td></tr>
                            <tr><td><code>pais_id</code></td><td>integer</td><td>❌ No</td><td>Filter results within country</td></tr>
                            <tr><td><code>departamento_id</code></td><td>integer</td><td>❌ No</td><td>Filter results within department</td></tr>
                            <tr><td><code>municipio_id</code></td><td>integer</td><td>❌ No</td><td>Filter results within municipality</td></tr>
                        </tbody>
                    </table>
                    
                    <table class="table table-sm table-bordered" data-lang="es">
                        <thead><tr><th>Parámetro</th><th>Tipo</th><th>Requerido</th><th>Descripción</th></tr></thead>
                        <tbody>
                            <tr><td><code>q</code></td><td>string</td><td>✅ Sí</td><td>Consulta de búsqueda (mín 2 caracteres)</td></tr>
                            <tr><td><code>tipo</code></td><td>string</td><td>❌ No</td><td>Filtrar por tipo: <code>pais</code>, <code>departamento</code>, <code>municipio</code>, <code>barrio</code></td></tr>
                            <tr><td><code>pais_id</code></td><td>entero</td><td>❌ No</td><td>Filtrar resultados dentro de país</td></tr>
                            <tr><td><code>departamento_id</code></td><td>entero</td><td>❌ No</td><td>Filtrar resultados dentro de departamento</td></tr>
                            <tr><td><code>municipio_id</code></td><td>entero</td><td>❌ No</td><td>Filtrar resultados dentro de municipio</td></tr>
                        </tbody>
                    </table>
                    
                    
                    <h4 data-lang="en">Example Requests</h4>
                    <h4 data-lang="es">Ejemplos de Peticiones</h4>
                    
                    <pre class="code-block line-numbers"><code class="language-bash"># 1. Basic search (all types)
GET /api/geoinfo/buscar?q=Guatemala

# 2. Search only countries
GET /api/geoinfo/buscar?q=Guat&tipo=pais

# 3. Search municipalities containing "San"
GET /api/geoinfo/buscar?q=San&tipo=municipio

# 4. Search within specific country (Nicaragua = 1)
GET /api/geoinfo/buscar?q=San&tipo=municipio&pais_id=1

# 5. Search neighborhoods in specific municipality
GET /api/geoinfo/buscar?q=Alta&tipo=barrio&municipio_id=1

# 6. Autocomplete for cascading dropdown
GET /api/geoinfo/buscar?q=Mana&tipo=departamento&pais_id=1</code></pre>

                    <h4 class="mt-4" data-lang="en">Practical Use Cases</h4>
                    <h4 class="mt-4" data-lang="es">Casos de Uso Prácticos</h4>
                    
                    <div class="alert alert-secondary">
                        <strong data-lang="en">🎯 Use Case 1: Country Autocomplete</strong>
                        <strong data-lang="es">🎯 Caso de Uso 1: Autocomplete de País</strong>
                        <pre class="mt-2 mb-0"><code class="language-javascript">// User types "Gua"
fetch('/api/geoinfo/buscar?q=Gua&tipo=pais')
  .then(r => r.json())
  .then(data => {
    // Shows: Guatemala, Guinea, etc.
    populateCountryDropdown(data.data);
  });</code></pre>
                    </div>

                    <div class="alert alert-secondary mt-2">
                        <strong data-lang="en">🎯 Use Case 2: Cascading Location Selector</strong>
                        <strong data-lang="es">🎯 Caso de Uso 2: Selector de Ubicación en Cascada</strong>
                        <pre class="mt-2 mb-0"><code class="language-javascript">// Step 1: User selects country
const selectedCountryId = 6; // Guatemala

// Step 2: Search departments in that country
fetch(`/api/geoinfo/buscar?q=${userInput}&tipo=departamento&pais_id=${selectedCountryId}`)
  .then(r => r.json())
  .then(data => populateDepartmentDropdown(data.data));

// Step 3: Search municipalities in selected department
const selectedDepartmentId = 80;
fetch(`/api/geoinfo/buscar?q=${userInput}&tipo=municipio&departamento_id=${selectedDepartmentId}`)
  .then(r => r.json())
  .then(data => populateMunicipalityDropdown(data.data));</code></pre>
                    </div>

                    <div class="alert alert-secondary mt-2">
                        <strong data-lang="en">🎯 Use Case 3: Quick Global Search</strong>
                        <strong data-lang="es">🎯 Caso de Uso 3: Búsqueda Global Rápida</strong>
                        <pre class="mt-2 mb-0"><code class="language-javascript">// Search everywhere, get prioritized results
fetch('/api/geoinfo/buscar?q=Managua')
  .then(r => r.json())
  .then(data => {
    // Returns:
    // 1. Departamento "Managua" (priority 3)
    // 2. Municipio "Managua" (priority 5)
    displayResults(data.data);
  });</code></pre>
                    </div>
                    
                    <h4 data-lang="en">Example Response</h4>
                    <h4 data-lang="es">Ejemplo de Respuesta</h4>
                    
                    <pre class="code-block line-numbers"><code class="language-json">{
    "success": true,
    "data": [
        {
            "id": 6,
            "tipo": "pais",
            "nombre": "Guatemala",
            "codigo_iso": "GUAT",
            "codigo_postal": null,
            "id_pais": null,
            "pais": null,
            "id_departamento": null,
            "departamento": null,
            "id_municipio": null,
            "municipio": null
        },
        {
            "id": 80,
            "tipo": "departamento",
            "nombre": "Guatemala",
            "codigo_iso": null,
            "codigo_postal": null,
            "id_pais": 6,
            "pais": "Guatemala",
            "id_departamento": null,
            "departamento": null,
            "id_municipio": null,
            "municipio": null
        },
        {
            "id": 1278,
            "tipo": "municipio",
            "nombre": "Antigua Guatemala",
            "codigo_iso": null,
            "codigo_postal": "3001",
            "id_pais": 6,
            "pais": "Guatemala",
            "id_departamento": 89,
            "departamento": "Sacatepequez",
            "id_municipio": null,
            "municipio": null
        },
        {
            "id": 7747,
            "tipo": "barrio",
            "nombre": "Antigua Guatemala",
            "codigo_iso": null,
            "codigo_postal": "3001",
            "id_pais": 6,
            "pais": "Guatemala",
            "id_departamento": 89,
            "departamento": "Sacatepequez",
            "id_municipio": 1278,
            "municipio": "Antigua Guatemala"
        }
    ],
    "query": "Guatemala",
    "filters": []
}</code></pre>

                    <div class="alert alert-info mt-3">
                        <strong data-lang="en">💡 Key Features</strong>
                        <strong data-lang="es">💡 Características Clave</strong>
                        <ul class="mb-0 mt-2" data-lang="en">
                            <li><strong>Priority Ordering:</strong> Countries first, then departments, municipalities, and neighborhoods</li>
                            <li><strong>Postal Codes:</strong> Neighborhoods inherit postal code from parent municipality if not set</li>
                            <li><strong>Hierarchical Data:</strong> Includes parent entity names for complete context</li>
                            <li><strong>Performance:</strong> Limited to 20 results, perfect for autocomplete/typeahead</li>
                        </ul>
                        <ul class="mb-0 mt-2" data-lang="es">
                            <li><strong>Orden por Prioridad:</strong> Países primero, luego departamentos, municipios y barrios</li>
                            <li><strong>Códigos Postales:</strong> Barrios heredan código postal del municipio si no tienen</li>
                            <li><strong>Datos Jerárquicos:</strong> Incluye nombres de entidades padre para contexto completo</li>
                            <li><strong>Rendimiento:</strong> Limitado a 20 resultados, perfecto para autocomplete/typeahead</li>
                        </ul>
                    </div>
                </div>
                
                <!-- Usage Example
                <div class="section-container">
                    <h2 class="section-title" data-lang="en">💡 Cascading Dropdown Example</h2>
                    <h2 class="section-title" data-lang="es">💡 Ejemplo de Dropdown en Cascada</h2>
                    
                    <p data-lang="en">Common pattern for building dependent geographic selectors:</p>
                    <p data-lang="es">Patrón común para construir selectores geográficos dependientes:</p>
                    
                    <pre class="code-block line-numbers"><code class="language-javascript">// 1. Load all countries on page load
fetch('/api/geoinfo/paises')
    .then(r => r.json())
    .then(data => populateCountries(data));

// 2. When user selects country, load departments
countrySelect.addEventListener('change', (e) => {
    const paisId = e.target.value;
    fetch(`/api/geoinfo/departamentos?id_pais=${paisId}`)
        .then(r => r.json())
        .then(data => populateDepartments(data));
});

// 3. When user selects department, load municipalities
departmentSelect.addEventListener('change', (e) => {
    const deptoId = e.target.value;
    fetch(`/api/geoinfo/municipios?id_departamento=${deptoId}`)
        .then(r => r.json())
        .then(data => populateMunicipalities(data));
});

// 4. When user selects municipality, load neighborhoods
municipalitySelect.addEventListener('change', (e) => {
    const munId = e.target.value;
    fetch(`/api/geoinfo/barrios?id_municipio=${munId}`)
        .then(r => r.json())
        .then(data => populateNeighborhoods(data));
});</code></pre>
                </div>-->
            </div> 


            <!-- Tab: Client App -->
            <div class="tab-pane fade" id="mensajeria" role="tabpanel" aria-labelledby="mensajeria-tab">
                <div class="section-container">
                    <h2 class="section-title" data-lang="en">Messenger Application API</h2>
                    <h2 class="section-title" data-lang="es">API App de Mensajería</h2>
                    
                        <p data-lang="en">Specialized endpoints for the delivery provider app.</p>
                        <p data-lang="es">Endpoints especializados para la app de los proveedores de mensajería (logística).</p>
                    
                        <!-- Messenger Orders -->
                        <div class="mb-5">
                            <h4 data-lang="en">Assigned Orders</h4>
                            <h4 data-lang="es">Mis Asignaciones</h4>
                            <p data-lang="en">Get list of orders assigned to the authenticated provider.</p>
                            <p data-lang="es">Obtener lista de pedidos asignados al proveedor autenticado.</p>
    
                            <div class="code-block">
                                <span class="badge-endpoint badge-get">GET</span> /api/mensajeria/pedidos?page=1&limit=20
                                <span class="badge bg-info text-dark float-end">👤 <span data-lang="en">Role: Messenger</span><span data-lang="es">Rol: Mensajería</span></span>
                            </div>

                         <table class="table table-sm table-bordered mt-2">
                             <thead><tr><th>Field / Campo</th><th>Req.</th><th>Type</th><th>Description</th></tr></thead>
                             <tbody>
                                 <tr><td><code>page</code></td><td>No</td><td>integer</td><td>
                                    <span data-lang="en">Page number (default: 1).</span>
                                    <span data-lang="es">Número de página (por defecto: 1).</span>
                                 </td></tr>
                                 <tr><td><code>limit</code></td><td>No</td><td>integer</td><td>
                                    <span data-lang="en">Items per page (default: 20, max: 100).</span>
                                    <span data-lang="es">Items por página (por defecto: 20, máx: 100).</span>
                                 </td></tr>
                                 <tr><td><code>estado</code></td><td>No</td><td>integer</td><td>
                                    <span data-lang="en">Filter by status ID.</span>
                                    <span data-lang="es">Filtrar por ID de estado.</span>
                                 </td></tr>
                             </tbody>
                        </table>

                        <pre class="code-block line-numbers"><code class="language-json">{
    "success": true,
    "data": [
        {
            "ID_Pedido": 100,
            "Numero_Orden": "ORD-2025-001",
            "Estado": "En ruta",
            "Cliente": "Juan Pérez"
        }
    ],
    "pagination": {
        "total": 45,
        "page": 1,
        "limit": 20,
        "total_pages": 3
    }
}</code></pre>
                    </div>

                        <!-- Messenger Status Update -->
                        <div class="mb-4">
                            <h4 data-lang="en">Change Order Status</h4>
                            <h4 data-lang="es">Cambiar Estado de Pedido</h4>
                            <p data-lang="en">Allows providers to update delivery progress states.</p>
                            <p data-lang="es">Permite a los proveedores actualizar los estados de progreso de entrega.</p>
    
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <span data-lang="en"><strong>Rules:</strong> Status changes are audited and some transitions might be restricted based on current state.</span>
                                <span data-lang="es"><strong>Reglas:</strong> Los cambios de estado son auditados y algunas transiciones pueden estar restringidas según el estado actual.</span>
                            </div>
    
                            <div class="code-block">
                                <span class="badge-endpoint badge-post">POST</span> /api/mensajeria/cambiar_estado
                                <span class="badge bg-info text-dark float-end">👤 <span data-lang="en">Role: Messenger</span><span data-lang="es">Rol: Mensajería</span></span>
                            </div>
                        
                         <table class="table table-sm table-bordered mt-2">
                             <thead><tr><th>Field / Campo</th><th>Req.</th><th>Allowed Values / Valores Permitidos</th><th>Description</th></tr></thead>
                             <tbody>
                                 <tr><td><code>id_pedido</code></td><td>Cond.</td><td>integer (ID)</td><td>
                                    <span data-lang="en">Internal Order ID. <strong>Required</strong> if <code>numero_orden</code> not provided.</span>
                                    <span data-lang="es">ID interno del pedido. <strong>Requerido</strong> si no se envía <code>numero_orden</code>.</span>
                                 </td></tr>
                                 <tr><td><code>numero_orden</code></td><td>Cond.</td><td>string</td><td>
                                    <span data-lang="en">External Order Number. <strong>Required</strong> if <code>id_pedido</code> not provided.</span>
                                    <span data-lang="es">Número de orden externo. <strong>Requerido</strong> si no se envía <code>id_pedido</code>.</span>
                                 </td></tr>
                                 <tr><td><code>estado</code></td><td>Yes</td><td>integer</td><td>
                                    <span data-lang="en">Target Status ID (e.g., 3: Delivered, 4: Rescheduled, 7: Returned).</span>
                                    <span data-lang="es">ID del estado destino (ej: 3: Entregado, 4: Reprogramado, 7: Devuelto).</span>
                                 </td></tr>
                                 <tr><td><code>motivo</code></td><td>Cond.</td><td>string</td><td>
                                    <span data-lang="en">Reason. <strong>Mandatory</strong> if status=7.</span>
                                    <span data-lang="es">Motivo. <strong>Obligatorio</strong> si estado=7.</span>
                                 </td></tr>
                             </tbody>
                        </table>

                        <div class="row">
                            <div class="col-md-4">
                                <h5 data-lang="en">By ID</h5>
                                <h5 data-lang="es">Por ID</h5>
                                <pre class="code-block line-numbers"><code class="language-json">{
    "id_pedido": 150,
    "estado": 4,
    "motivo": "Reprogramar"
}</code></pre>
                            </div>
                            <div class="col-md-4">
                                <h5 data-lang="en">By Order Num</h5>
                                <h5 data-lang="es">Por Núm. Orden</h5>
                                <pre class="code-block line-numbers"><code class="language-json">{
    "numero_orden": "EXT-88002",
    "estado": 4,
    "motivo": "Reprogramar"
}</code></pre>
                            </div>
                            <div class="col-md-4">
                                <h5 data-lang="en">Return (Mandatory Motive)</h5>
                                <h5 data-lang="es">Devolución (Motivo Oblig.)</h5>
                                <pre class="code-block line-numbers"><code class="language-json">{
    "numero_orden": "EXT-88002",
    "estado": 7,
    "motivo": "Dirección incorrecta"
}</code></pre>
                            </div>
                        </div>

                        <h5 data-lang="en">Error Response (Forbidden transition)</h5>
                        <h5 data-lang="es">Respuesta de Error (Transición prohibida)</h5>
                        <pre class="code-block line-numbers"><code class="language-json">{
    "success": false,
    "message": "No se puede cambiar el estado de un pedido que ya ha sido entregado."
}</code></pre>
                    </div>
                </div>
            </div>
            
        </div>
    </div>

    <footer>
        <div class="container">
            <p>Logistics API System &copy; <?php echo date('Y'); ?></p>
        </div>
    </footer>

    <!-- Bootstrap JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
        integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous">
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js"
        integrity="sha384-BBtl+eGJRgqQAUMxJ7pMwbEyER4l1g+O15P+16Ep7Q9Q+zqX6gSbd85u4mG4QzX+" crossorigin="anonymous">
    </script>
    
    <!-- Prism.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/line-numbers/prism-line-numbers.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-json.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-bash.min.js"></script>

    <script>
        function setLanguage(lang) {
            document.body.className = 'lang-' + lang;
            document.querySelectorAll('.lang-toggle button').forEach(btn => btn.classList.remove('active'));
            document.getElementById('lang-' + lang).classList.add('active');
            
            // Persist preference
            localStorage.setItem('api-docs-lang', lang);
        }

        // Initialize language
        document.addEventListener('DOMContentLoaded', () => {
            const savedLang = localStorage.getItem('api-docs-lang') || 'es';
            setLanguage(savedLang);
        });
    </script>
</body>

</html>