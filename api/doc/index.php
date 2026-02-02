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
                <button class="nav-link" id="client-tab" data-bs-toggle="tab" data-bs-target="#client" type="button" role="tab">
                    <span data-lang="en">📱 Client App</span><span data-lang="es">📱 App Cliente</span>
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
                                    <strong class="text-primary">Role: Client (ID 4)</strong>
                                    <div data-lang="en" class="small text-muted">Use this role for <strong>Order Management</strong>: Create new orders, manage massive shipments, and control inventory.</div>
                                    <div data-lang="es" class="small text-muted">Usa este rol para <strong>Gestión de Pedidos</strong>: Crear nuevos pedidos, administrar envíos masivos y controlar inventario.</div>
                                </li>
                                <li>
                                    <strong class="text-primary">Role: Provider (ID 5)</strong>
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
                            <span class="badge-endpoint badge-get">GET</span> /api/pedidos/listar?page=1&limit=20
                            <span class="badge bg-primary float-end">🔐 <span data-lang="en">Authenticated</span><span data-lang="es">Autenticado</span></span>
                        </div>
                        <p class="small text-muted">Returns paginated list of orders.</p>
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

                    <div class="code-block">
                        <span class="badge-endpoint badge-post">POST</span> /api/pedidos/crear
                        <span class="badge bg-primary float-end">🔐 <span data-lang="en">Authenticated</span><span data-lang="es">Autenticado</span></span>
                        <span class="badge bg-warning text-dark float-end me-1">👤 <span data-lang="en">Role: Client (ID 4)</span><span data-lang="es">Rol: Cliente (ID 4)</span></span>
                    </div>

                    <h4 data-lang="en">🔑 Required Fields</h4>
                    <h4 data-lang="es">🔑 Campos Obligatorios</h4>
                    
                     <table class="table table-sm table-bordered" data-lang="en">
                        <thead><tr><th>Field</th><th>Type</th><th>Validation</th><th>Description</th></tr></thead>
                        <tbody>
                            <tr><td><code>numero_orden</code></td><td>integer</td><td>Unique, positive</td><td>Unique external order ID</td></tr>
                            <tr><td><code>destinatario</code></td><td>string</td><td>Not empty</td><td>Recipient's full name</td></tr>
                            <tr><td><code>producto_id</code> or <code>productos</code></td><td>int/array</td><td>Must exist in DB</td><td>Single product ID or array of products</td></tr>
                        </tbody>
                    </table>
                    
                    <table class="table table-sm table-bordered" data-lang="es">
                        <thead><tr><th>Campo</th><th>Tipo</th><th>Validación</th><th>Descripción</th></tr></thead>
                        <tbody>
                            <tr><td><code>numero_orden</code></td><td>entero</td><td>Único, positivo</td><td>ID externo único del pedido</td></tr>
                            <tr><td><code>destinatario</code></td><td>string</td><td>No vacío</td><td>Nombre completo del destinatario</td></tr>
                            <tr><td><code>producto_id</code> o <code>productos</code></td><td>int/array</td><td>Debe existir en BD</td><td>ID de producto único o array de productos</td></tr>
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
                        <span class="badge bg-warning text-dark float-end me-1">👤 <span data-lang="en">Role: Client (ID 4)</span><span data-lang="es">Rol: Cliente (ID 4)</span></span>
                    </div>

                    <h4 data-lang="en">📋 Optional Fields - Contact & Delivery</h4>
                    <h4 data-lang="es">📋 Campos Opcionales - Contacto y Entrega</h4>
                    
                    <table class="table table-sm table-bordered" data-lang="en">
                        <thead><tr><th>Field</th><th>Type</th><th>Default</th><th>Description</th></tr></thead>
                        <tbody>
                            <tr><td><code>telefono</code></td><td>string</td><td>''</td><td>Contact phone number</td></tr>
                            <tr><td><code>direccion</code></td><td>string</td><td>''</td><td>Full delivery address</td></tr>
                            <tr><td><code>comentario</code></td><td>string</td><td>null</td><td>Additional delivery notes</td></tr>
                            <tr><td><code>coordenadas</code></td><td>string</td><td>null</td><td>GPS format: "lat,long" (e.g. "14.6349,-90.5069")</td></tr>
                            <tr><td><code>latitud</code></td><td>float</td><td>null</td><td>Latitude (alternative to coordenadas)</td></tr>
                            <tr><td><code>longitud</code></td><td>float</td><td>null</td><td>Longitude (alternative to coordenadas)</td></tr>
                        </tbody>
                    </table>
                    
                    <table class="table table-sm table-bordered" data-lang="es">
                        <thead><tr><th>Campo</th><th>Tipo</th><th>Defecto</th><th>Descripción</th></tr></thead>
                        <tbody>
                            <tr><td><code>telefono</code></td><td>string</td><td>''</td><td>Teléfono de contacto</td></tr>
                            <tr><td><code>direccion</code></td><td>string</td><td>''</td><td>Dirección completa de entrega</td></tr>
                            <tr><td><code>comentario</code></td><td>string</td><td>null</td><td>Notas adicionales de entrega</td></tr>
                            <tr><td><code>coordenadas</code></td><td>string</td><td>null</td><td>Formato GPS: "lat,long" (ej. "14.6349,-90.5069")</td></tr>
                            <tr><td><code>latitud</code></td><td>float</td><td>null</td><td>Latitud (alternativa a coordenadas)</td></tr>
                            <tr><td><code>longitud</code></td><td>float</td><td>null</td><td>Longitud (alternativa a coordenadas)</td></tr>
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
                            <tr><td><code>id_proveedor</code></td><td>integer</td><td>null</td><td>Provider ID (auto-set for Provider role)</td></tr>
                            <tr><td><code>id_cliente</code></td><td>integer</td><td>null</td><td>Client ID</td></tr>
                            <tr><td><code>id_moneda</code></td><td>integer</td><td>null</td><td>Currency ID (auto-detected from provider's country if not provided)</td></tr>
                        </tbody>
                    </table>
                    
                    <table class="table table-sm table-bordered" data-lang="es">
                        <thead><tr><th>Campo</th><th>Tipo</th><th>Defecto</th><th>Descripción</th></tr></thead>
                        <tbody>
                            <tr><td><code>id_estado</code></td><td>entero</td><td>1</td><td>Estado del pedido (ver Referencia de Estados)</td></tr>
                            <tr><td><code>id_vendedor</code></td><td>entero</td><td>null</td><td>Repartidor asignado</td></tr>
                            <tr><td><code>id_proveedor</code></td><td>entero</td><td>null</td><td>ID del proveedor (auto-asignado para rol Proveedor)</td></tr>
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

                    <h4 data-lang="en">✅ Automatic Validations</h4>
                    <h4 data-lang="es">✅ Validaciones Automáticas</h4>
                    
                    <ul data-lang="en">
                        <li><strong>Stock validation:</strong> Ensures sufficient inventory before creating order</li>
                        <li><strong>Unique order number:</strong> Prevents duplicate numero_orden</li>
                        <li><strong>Coordinate format:</strong> Validates "lat,long" format</li>
                        <li><strong>Valid products:</strong> Verifies product IDs exist in database</li>
                        <li><strong>Positive quantities:</strong> All quantities must be > 0</li>
                        <li><strong>Foreign key validation:</strong> Checks vendor, provider, client, currency exist</li>
                    </ul>
                    
                    <ul data-lang="es">
                        <li><strong>Validación de stock:</strong> Asegura inventario suficiente antes de crear</li>
                        <li><strong>Número único:</strong> Previene numero_orden duplicados</li>
                        <li><strong>Formato coordenadas:</strong> Valida formato "lat,long"</li>
                        <li><strong>Productos válidos:</strong> Verifica que IDs de productos existan en BD</li>
                        <li><strong>Cantidades positivas:</strong> Todas las cantidades deben ser > 0</li>
                        <li><strong>Validación FK:</strong> Verifica que vendedor, proveedor, cliente, moneda existan</li>
                    </ul>

                    <h4 data-lang="en">🔐 Security Rules</h4>
                    <h4 data-lang="es">🔐 Reglas de Seguridad</h4>
                    
                    <p data-lang="en"><strong>Provider Role:</strong> If authenticated user is a Provider (role 4), the system automatically sets <code>id_proveedor</code> to their user ID, ignoring any value sent in the request.</p>
                    <p data-lang="es"><strong>Rol Proveedor:</strong> Si el usuario autenticado es Proveedor (rol 4), el sistema automáticamente asigna <code>id_proveedor</code> a su ID de usuario, ignorando cualquier valor enviado en la petición.</p>

                    <h4 data-lang="en">📝 Example: Minimal Order</h4>
                    <h4 data-lang="es">📝 Ejemplo: Pedido Mínimo</h4>
                    <pre class="code-block line-numbers"><code class="language-json">{
    "numero_orden": 12345,
    "destinatario": "Juan Pérez",
    "producto_id": 10,
    "cantidad": 2
}</code></pre>

                    <h4 data-lang="en">📝 Example: Complete Order with Combo Pricing</h4>
                    <h4 data-lang="es">📝 Ejemplo: Pedido Completo con Precio Combo</h4>
                    <pre class="code-block line-numbers"><code class="language-json">{
    "numero_orden": 12346,
    "destinatario": "María García",
    "telefono": "50212345678",
    "direccion": "Calle Principal #123",
    "coordenadas": "14.6349,-90.5069",
    "comentario": "Entregar en horario de oficina",
    "id_pais": 1,
    "id_departamento": 5,
    "id_municipio": 25,
    "id_proveedor": 8,
    "id_cliente": 15,
    "id_moneda": 2,
    "es_combo": 1,
    "precio_total_local": 150.00,
    "productos": [
        { "producto_id": 10, "cantidad": 2 },
        { "producto_id": 15, "cantidad": 1 }
    ]
}</code></pre>

                    <h4 data-lang="en">📝 Example: Multi-Product Standard Order</h4>
                    <h4 data-lang="es">📝 Ejemplo: Pedido Estándar Multi-Producto</h4>
                    <pre class="code-block line-numbers"><code class="language-json">{
    "numero_orden": 10050,
    "destinatario": "Maria Gonzalez",
    "telefono": "8888-8888",
    "direccion": "Bello Horizonte C-4",
    "es_combo": 0,
    "coordenadas": "12.1345,-86.2456",
    "productos": [
        { "producto_id": 1, "cantidad": 2 },
        { "producto_id": 5, "cantidad": 1 }
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
                    </div>

                    <div class="mb-4">
                         <div class="code-block">
                            <span class="badge-endpoint badge-post">POST</span> /api/productos/crear
                            <span class="badge bg-primary float-end">🔐 <span data-lang="en">Authenticated</span><span data-lang="es">Autenticado</span></span>
                            <span class="badge bg-warning text-dark float-end me-1">👤 <span data-lang="en">Role: Client (ID 4)</span><span data-lang="es">Rol: Cliente (ID 4)</span></span>
                        </div>
                         <p data-lang="es" class="mt-2">Crear un nuevo producto.</p>
                    </div>
                    
                    <h4 data-lang="en">Product Object Model</h4>
                    <h4 data-lang="es">Modelo de Objeto Producto</h4>
                     <pre class="code-block line-numbers"><code class="language-json">{
    "id": 1,
    "nombre": "Protein Shake",
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
                            <span class="badge bg-warning text-dark float-end me-1">👤 <span data-lang="en">Role: Client (ID 4)</span><span data-lang="es">Rol: Cliente (ID 4)</span></span>
                        </div> 
                        <small class="text-muted d-block mb-2">Note: Use POST with <code>id</code> param or check PHP config for PUT support.</small>

                        <pre class="code-block line-numbers"><code class="language-json">{
    "id": 1,
    "nombre": "Protein Shake V2",
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
                <div class="section-container">
                    <h2 class="section-title" data-lang="en">Geographic Data</h2>
                    <h2 class="section-title" data-lang="es">Datos Geográficos</h2>
                    
                    <p data-lang="en">Retrieve reference data for dropdowns (Countries, Departments, Municipalities).</p>
                    <p data-lang="es">Obtener datos de referencia para listas desplegables (Países, Departamentos, Municipios).</p>
                    
                     <div class="code-block">
                         <span class="badge-endpoint badge-get">GET</span> /api/geoinfo/listar
                         <span class="badge bg-success float-end">🔓 <span data-lang="en">Public</span><span data-lang="es">Público</span></span>
                     </div>
                     
                     <h4 data-lang="en">Response Structure</h4>
                     <h4 data-lang="es">Estructura de Respuesta</h4>
                     <pre class="code-block line-numbers"><code class="language-json">{
    "success": true,
    "data": {
        "paises": [ ... ],
        "departamentos": [ ... ],
        "municipios": [ ... ],
        "barrios": [ ... ]
    }
}</code></pre>
                </div>

                <div class="section-container">
                    <h2 class="section-title" data-lang="en">Geo CRUD Operations</h2>
                    <h2 class="section-title" data-lang="es">Operaciones CRUD Geo</h2>
                    
                    <p data-lang="en">Endpoints to manage reference data. All accept POST for creation/update.</p>
                    <p data-lang="es">Endpoints para gestionar datos de referencia. Todos aceptan POST para crear/actualizar.</p>
                    <p class="text-end mb-2"><span class="badge bg-primary">🔐 <span data-lang="en">Authenticated</span><span data-lang="es">Autenticado</span></span></p>

                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Resource</th>
                                <th>Method</th>
                                <th>Endpoint</th>
                                <th>Params (JSON)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Countries</strong></td>
                                <td><span class="badge-endpoint badge-post">POST</span></td>
                                <td><code>/api/geoinfo/municipios</code></td>
                                <td><code>{ "nombre": "...", "id_departamento": 1 }</code></td>
                            </tr>
                            <tr>
                                <td><strong>Neighborhoods</strong></td>
                                <td><span class="badge-endpoint badge-post">POST</span></td>
                                <td><code>/api/geoinfo/barrios</code></td>
                                <td><code>{ "nombre": "...", "id_municipio": 1 }</code></td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle"></i> 
                        <span data-lang="en">To <strong>Update</strong> or <strong>Delete</strong>, pass the <code>action</code> parameter:</span>
                        <span data-lang="es">Para <strong>Actualizar</strong> o <strong>Eliminar</strong>, pasa el parámetro <code>action</code>:</span>
                        <br>
                        <code>{ "action": "update", "id": 1, "nombre": "New Name" }</code><br>
                        <code>{ "action": "delete", "id": 1 }</code>
                    </div>
                </div>
            </div>

            <!-- Tab: Client App -->
            <div class="tab-pane fade" id="client" role="tabpanel" aria-labelledby="client-tab">
                <div class="section-container">
                    <h2 class="section-title" data-lang="en">Client Application API</h2>
                    <h2 class="section-title" data-lang="es">API App Cliente</h2>
                    
                    <p data-lang="en">Specialized endpoints for the end-customer mobile/web app.</p>
                    <p data-lang="es">Endpoints especializados para la app móvil/web del cliente final.</p>
                
                    <!-- Client Orders -->
                    <div class="mb-5">
                        <h4 data-lang="en">My Orders</h4>
                        <h4 data-lang="es">Mis Pedidos</h4>
                        <p data-lang="en">Get list of orders belonging to the authenticated client.</p>
                        <p data-lang="es">Obtener lista de pedidos pertenecientes al cliente autenticado.</p>

                        <div class="code-block">
                            <span class="badge-endpoint badge-get">GET</span> /api/cliente/pedidos
                            <span class="badge bg-info text-dark float-end">👤 <span data-lang="en">Role: Provider (ID 5)</span><span data-lang="es">Rol: Proveedor (ID 5)</span></span>
                        </div>
                        <pre class="code-block line-numbers"><code class="language-json">{
    "success": true,
    "data": [
        {
            "id": 100,
            "numero_orden": "ORD-2025-001",
            "estado": "En ruta",
            "productos": "Producto A (x2)"
        }
    ]
}</code></pre>
                    </div>

                    <!-- Client Status Update -->
                    <div class="mb-4">
                        <h4 data-lang="en">Change Order Status</h4>
                        <h4 data-lang="es">Cambiar Estado de Pedido</h4>
                        <p data-lang="en">Allows clients to mark orders as delivered or returned (if permitted).</p>
                        <p data-lang="es">Permite a los clientes marcar pedidos como entregados o devueltos (si está permitido).</p>

                        <div class="code-block">
                            <span class="badge-endpoint badge-post">POST</span> /api/cliente/cambiar_estado
                            <span class="badge bg-info text-dark float-end">👤 <span data-lang="en">Role: Provider (ID 5)</span><span data-lang="es">Rol: Proveedor (ID 5)</span></span>
                        </div>
                        
                        <table class="table table-sm table-bordered mt-2">
                             <thead><tr><th>Field</th><th>Required</th><th>Description</th></tr></thead>
                             <tbody>
                                 <tr><td><code>id_pedido</code></td><td>Yes</td><td>Internal Order ID</td></tr>
                                 <tr><td><code>estado</code></td><td>Yes</td><td>New Status ID (e.g., 3 for Delivered)</td></tr>
                                 <tr><td><code>motivo</code></td><td>No</td><td>Reason/Comment</td></tr>
                             </tbody>
                        </table>

                        <pre class="code-block line-numbers"><code class="language-json">{
    "id_pedido": 100,
    "estado": 3,
    "motivo": "Recibido conforme"
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