<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Relay API Documentation</title>

    <!-- Bootstrap CSS v5.2.1 -->
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
        }

        .badge-endpoint.badge-get {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .badge-endpoint.badge-put {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .badge-endpoint.badge-delete {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
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

        /* Print styles */
        @media print {
            .code-block {
                background: #ffffff !important;
                color: #000000 !important;
                box-shadow: none !important;
                border: 1px solid #e5e7eb !important;
            }

            .section-container {
                box-shadow: none;
                border: 1px solid var(--border-color);
                page-break-inside: avoid;
            }
        }

        /* Accessibility improvements */
        .code-block:focus,
        .section-container:focus {
            outline: 3px solid var(--primary-color);
            outline-offset: 2px;
        }

        /* Loading animation for better UX */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .section-container {
            animation: fadeIn 0.5s ease-out;
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
            <a class="navbar-brand" href="./">← <span data-lang="en">Back to API Docs</span><span data-lang="es">Volver a Docs API</span></a>
            
            <!-- Language Toggle -->
            <div class="lang-toggle">
                <button id="lang-en" class="active" onclick="setLanguage('en')">🇬🇧 English</button>
                <button id="lang-es" onclick="setLanguage('es')">🇪🇸 Español</button>
            </div>
        </div>
        <div class="container text-center mt-4">
            <h1 data-lang="en">CRM Relay API Documentation</h1>
            <h1 data-lang="es">Documentación API CRM Relay</h1>
            <p class="lead" data-lang="en">Simple JWT-authenticated API for lead management</p>
            <p class="lead" data-lang="es">API simple con autenticación JWT para gestión de leads</p>
            <p class="mt-3">
                <a class="btn btn-outline-light btn-sm" href="../../docs/CRM_CURL_EXAMPLES.md" target="_blank" data-lang="en">📋 View cURL Examples</a>
                <a class="btn btn-outline-light btn-sm" href="../../docs/CRM_CURL_EXAMPLES.md" target="_blank" data-lang="es">📋 Ver Ejemplos cURL</a>
            </p>
        </div>
    </header>

    <div class="container mt-5">
        <!-- Quick Reference -->
        <div class="section-container">
            <h2 class="section-title" data-lang="en">Quick Reference</h2>
            <h2 class="section-title" data-lang="es">Referencia Rápida</h2>
            
            <p data-lang="en">The CRM API allows <strong>providers</strong> to create and view leads, while <strong>clients</strong> can update lead status. All endpoints use JWT authentication.</p>
            <p data-lang="es">La API CRM permite a los <strong>proveedores</strong> crear y ver leads, mientras que los <strong>clientes</strong> pueden actualizar el estado. Todos los endpoints usan autenticación JWT.</p>
            
            <h4 data-lang="en">✨ Key Features</h4>
            <h4 data-lang="es">✨ Características Principales</h4>
            
            <ul data-lang="en">
                <li><strong>202 Accepted</strong> — Immediate responses for lead creation</li>
                <li><strong>Idempotency</strong> — Unique <code>proveedor_lead_id</code> prevents duplicates</li>
                <li><strong>State Normalization</strong> — Auto-converts aliases ("Aprovado" → "APROBADO")</li>
                <li><strong>JWT Authentication</strong> — Secure Bearer token authentication</li>
                <li><strong>Role-Based Access</strong> — Providers, Clients, and Administrators</li>
                <li><strong>Filtering & Pagination</strong> — Easy data retrieval</li>
            </ul>
            
            <ul data-lang="es">
                <li><strong>202 Accepted</strong> — Respuestas inmediatas para creación de leads</li>
                <li><strong>Idempotencia</strong> — <code>proveedor_lead_id</code> único previene duplicados</li>
                <li><strong>Normalización de Estados</strong> — Convierte automáticamente aliases ("Aprovado" → "APROBADO")</li>
                <li><strong>Autenticación JWT</strong> — Autenticación segura con Bearer token</li>
                <li><strong>Acceso por Roles</strong> — Proveedores, Clientes y Administradores</li>
                <li><strong>Filtrado y Paginación</strong> — Recuperación fácil de datos</li>
            </ul>
        </div>

        <!-- Quickstart -->
        <div class="section-container">
            <h2 class="section-title" data-lang="en">Quickstart Guide</h2>
            <h2 class="section-title" data-lang="es">Guía de Inicio Rápido</h2>
            
            <p data-lang="en">Get started with the CRM Relay API in 5 simple steps:</p>
            <p data-lang="es">Comienza con la API CRM Relay en 5 pasos simples:</p>
            
            <ol data-lang="en">
                <li><strong>Authenticate</strong> — Obtain JWT via <code>POST /api/auth/login</code></li>
                <li><strong>Extract Token</strong> — Use <code>response.data.token</code> (nested inside <code>data</code>)</li>
                <li><strong>Set Header</strong> — Add <code>Authorization: Bearer &lt;token&gt;</code> to all requests</li>
                <li><strong>Submit Leads</strong> — Send to <code>POST /api/crm/leads</code> with required fields</li>
                <li><strong>Process Async</strong> — System responds 202 and queues for background processing</li>
            </ol>
            
            <ol data-lang="es">
                <li><strong>Autenticar</strong> — Obtén JWT via <code>POST /api/auth/login</code></li>
                <li><strong>Extraer Token</strong> — Usa <code>response.data.token</code> (anidado dentro de <code>data</code>)</li>
                <li><strong>Configurar Header</strong> — Agrega <code>Authorization: Bearer &lt;token&gt;</code> a todas las peticiones</li>
                <li><strong>Enviar Leads</strong> — Envía a <code>POST /api/crm/leads</code> con campos requeridos</li>
                <li><strong>Procesamiento Async</strong> — El sistema responde 202 y encola para procesamiento en segundo plano</li>
            </ol>

            <h4 data-lang="en">Example: Create Lead</h4>
            <h4 data-lang="es">Ejemplo: Crear Lead</h4>
            
            <pre class="code-block"><code class="language-bash">curl -X POST "http://localhost/paqueteriacz/api/crm/leads" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer &lt;TOKEN&gt;" \
  -d '{"lead":{"proveedor_lead_id":"PR-001","nombre":"Juan Pérez","telefono":"+50512345678","fecha_hora":"2025-01-15 10:00:00"}}'</code></pre>

            <h4 data-lang="en">Response (202 Accepted)</h4>
            <h4 data-lang="es">Respuesta (202 Accepted)</h4>
            <pre class="code-block line-numbers"><code class="language-json">{
    "success": true,
    "message": "Lead(s) aceptado(s) para procesamiento",
    "accepted": 1,
    "inbox_id": 123
}</code></pre>
        </div>

        <!-- Authentication -->
        <div class="section-container">
            <h2 class="section-title" data-lang="en">Authentication</h2>
            <h2 class="section-title" data-lang="es">Autenticación</h2>
            
            <p data-lang="en">All CRM endpoints require JWT authentication. Use the token from <code>POST /api/auth/login</code>.</p>
            <p data-lang="es">Todos los endpoints CRM requieren autenticación JWT. Usa el token de <code>POST /api/auth/login</code>.</p>

            <h4 data-lang="en">Step 1: Login to Get JWT Token</h4>
            <h4 data-lang="es">Paso 1: Login para Obtener Token JWT</h4>
            
            <p data-lang="en">Authenticate with your credentials to receive a JWT token:</p>
            <p data-lang="es">Autentícate con tus credenciales para recibir un token JWT:</p>

            <div class="code-block"><span class="badge-endpoint badge-post">POST</span> /api/auth/login</div>

            <h4 data-lang="en">Request Body</h4>
            <h4 data-lang="es">Cuerpo de la Petición</h4>
            <pre class="code-block line-numbers"><code class="language-json">{
    "email": "proveedor@example.com",
    "password": "your_secure_password"
}</code></pre>

            <h4>Response — Success <span class="status-badge status-200">200</span></h4>
            <pre class="code-block line-numbers"><code class="language-json">{
    "success": true,
    "message": "Login exitoso",
    "data": {
        "id": "123",
        "nombre": "Usuario Proveedor",
        "email": "proveedor@example.com",
        "rol": "Proveedor",
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpZCI6IjEyMyIsIm5vbWJyZSI6IlVzdWFyaW8gUHJvdmVlZG9yIiwiZW1haWwiOiJwcm92ZWVkb3JAZXhhbXBsZS5jb20iLCJyb2wiOiJQcm92ZWVkb3IiLCJleHAiOjE3MDQ4MTI0MDB9.signature_here"
    }
}</code></pre>

            <div class="alert alert-warning" data-lang="en">
                <strong>Important:</strong> The token is nested inside <code>data.token</code>, not at the root level. Extract it as: <code>response.data.token</code>
            </div>
            <div class="alert alert-warning" data-lang="es">
                <strong>Importante:</strong> El token está anidado dentro de <code>data.token</code>, no en el nivel raíz. Extráelo como: <code>response.data.token</code>
            </div>

            <h4>Response — Invalid Credentials <span class="status-badge status-401">401</span></h4>
            <pre class="code-block line-numbers"><code class="language-json">{
    "success": false,
    "message": "Credenciales inválidas"
}</code></pre>

            <h4 data-lang="en">Example: Full Login Flow</h4>
            <h4 data-lang="es">Ejemplo: Flujo Completo de Login</h4>
            <pre class="code-block line-numbers"><code class="language-bash"># Login and extract token
curl -X POST "http://localhost/paqueteriacz/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"proveedor@example.com","password":"your_password"}'

# Response contains token at data.token
# Use it in subsequent requests:
curl -X GET "http://localhost/paqueteriacz/api/crm/leads" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLC..."</code></pre>

            <h4 data-lang="en">Step 2: Use Token in API Requests</h4>
            <h4 data-lang="es">Paso 2: Usar Token en Peticiones API</h4>

            <h4 data-lang="en">Required Header</h4>
            <h4 data-lang="es">Encabezado Requerido</h4>
            <div class="code-block">Authorization: Bearer &lt;JWT_TOKEN&gt;</div>

            <h4 data-lang="en">Roles & Permissions</h4>
            <h4 data-lang="es">Roles y Permisos</h4>
            
            <table class="table table-sm table-bordered" data-lang="en">
                <thead><tr><th>Role</th><th>Permissions</th></tr></thead>
                <tbody>
                    <tr><td><code>Proveedor</code></td><td>Create leads, view own leads</td></tr>
                    <tr><td><code>Cliente</code></td><td>Update lead status (ownership required), view assigned leads</td></tr>
                    <tr><td><code>Administrador</code></td><td>Full access + system metrics</td></tr>
                </tbody>
            </table>
            
            <table class="table table-sm table-bordered" data-lang="es">
                <thead><tr><th>Rol</th><th>Permisos</th></tr></thead>
                <tbody>
                    <tr><td><code>Proveedor</code></td><td>Crear leads, ver propios leads</td></tr>
                    <tr><td><code>Cliente</code></td><td>Actualizar estado de lead (requiere propiedad), ver leads asignados</td></tr>
                    <tr><td><code>Administrador</code></td><td>Acceso completo + métricas del sistema</td></tr>
                </tbody>
            </table>
        </div>

        <!-- POST /api/crm/leads -->
        <div class="section-container">
            <h2 class="section-title" data-lang="en">Create Leads</h2>
            <h2 class="section-title" data-lang="es">Crear Leads</h2>
            
            <p data-lang="en">Submit individual leads or batches. Returns <span class="status-badge status-202">202 Accepted</span> immediately.</p>
            <p data-lang="es">Envía leads individuales o en lote. Retorna <span class="status-badge status-202">202 Accepted</span> inmediatamente.</p>

            <h4 data-lang="en">Endpoint</h4>
            <h4 data-lang="es">Endpoint</h4>
            <div class="code-block"><span class="badge-endpoint badge-post">POST</span> /api/crm/leads</div>

            <h4 data-lang="en">Allowed Roles</h4>
            <h4 data-lang="es">Roles Permitidos</h4>
            <p><code>Proveedor</code>, <code>Administrador</code></p>

            <h4 data-lang="en">Request — Individual Lead</h4>
            <h4 data-lang="es">Request — Lead Individual</h4>
            <pre class="code-block line-numbers"><code class="language-json">{
    "lead": {
        "proveedor_lead_id": "PR-12345",
        "nombre": "Juan Pérez",
        "telefono": "+50512345678",
        "producto": "Laptop Dell",
        "precio": 500.00,
        "fecha_hora": "2025-01-15 10:30:00",
        "cliente_id": 5
    }
}</code></pre>

            <h4 data-lang="en">Request — Batch</h4>
            <h4 data-lang="es">Request — Lote</h4>
            <pre class="code-block line-numbers"><code class="language-json">{
    "leads": [
        {"proveedor_lead_id":"PR-001","nombre":"Lead 1","telefono":"+50511111111","fecha_hora":"2025-01-15 10:00:00"},
        {"proveedor_lead_id":"PR-002","nombre":"Lead 2","telefono":"+50522222222","fecha_hora":"2025-01-15 10:05:00"}
    ]
}</code></pre>

            <h4 data-lang="en">Field Reference</h4>
            <h4 data-lang="es">Referencia de Campos</h4>
            
            <table class="table table-sm table-bordered" data-lang="en">
                <thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><code>proveedor_lead_id</code></td><td>string(120)</td><td>✅ Yes</td><td>Unique lead ID (per provider)</td></tr>
                    <tr><td><code>fecha_hora</code></td><td>datetime</td><td>✅ Yes</td><td>Lead timestamp (YYYY-MM-DD HH:MM:SS)</td></tr>
                    <tr><td><code>nombre</code></td><td>string(255)</td><td>No</td><td>Prospect name</td></tr>
                    <tr><td><code>telefono</code></td><td>string(30)</td><td>No</td><td>Phone number</td></tr>
                    <tr><td><code>producto</code></td><td>string(255)</td><td>No</td><td>Product of interest</td></tr>
                    <tr><td><code>precio</code></td><td>decimal(10,2)</td><td>No</td><td>Product price</td></tr>
                    <tr><td><code>cliente_id</code></td><td>integer</td><td>No</td><td>Client ID (auto-forward if has webhook)</td></tr>
                </tbody>
            </table>
            
            <table class="table table-sm table-bordered" data-lang="es">
                <thead><tr><th>Campo</th><th>Tipo</th><th>Requerido</th><th>Descripción</th></tr></thead>
                <tbody>
                    <tr><td><code>proveedor_lead_id</code></td><td>string(120)</td><td>✅ Sí</td><td>ID único de lead (por proveedor)</td></tr>
                    <tr><td><code>fecha_hora</code></td><td>datetime</td><td>✅ Sí</td><td>Fecha y hora del lead (YYYY-MM-DD HH:MM:SS)</td></tr>
                    <tr><td><code>nombre</code></td><td>string(255)</td><td>No</td><td>Nombre del prospecto</td></tr>
                    <tr><td><code>telefono</code></td><td>string(30)</td><td>No</td><td>Número de teléfono</td></tr>
                    <tr><td><code>producto</code></td><td>string(255)</td><td>No</td><td>Producto de interés</td></tr>
                    <tr><td><code>precio</code></td><td>decimal(10,2)</td><td>No</td><td>Precio del producto</td></tr>
                    <tr><td><code>cliente_id</code></td><td>integer</td><td>No</td><td>ID del cliente (auto-reenvío si tiene webhook)</td></tr>
                </tbody>
            </table>

            <h4>Response — Success <span class="status-badge status-202">202</span></h4>
            <pre class="code-block line-numbers"><code class="language-json">{
    "success": true,
    "message": "Lead(s) aceptado(s) para procesamiento",
    "accepted": 1,
    "inbox_id": 123
}</code></pre>

            <h4>Response — Duplicate (Idempotent) <span class="status-badge status-200">200</span></h4>
            <pre class="code-block line-numbers"><code class="language-json">{
    "success": true,
    "message": "Lead(s) ya procesado(s) previamente",
    "accepted": 1,
    "duplicated": true
}</code></pre>
        </div>

        <!-- POST /api/crm/leads/{id}/estado -->
        <div class="section-container">
            <h2 class="section-title" data-lang="en">Update Lead Status</h2>
            <h2 class="section-title" data-lang="es">Actualizar Estado de Lead</h2>
            
            <p data-lang="en">Update lead state with automatic normalization. Clients have <strong>full flexibility</strong> to change to any valid state.</p>
            <p data-lang="es">Actualiza el estado del lead con normalización automática. Clientes tienen <strong>total flexibilidad</strong> para cambiar a cualquier estado válido.</p>

            <h4 data-lang="en">Endpoint</h4>
            <h4 data-lang="es">Endpoint</h4>
            <div class="code-block"><span class="badge-endpoint badge-post">POST</span> /api/crm/leads/{id}/estado</div>

            <h4 data-lang="en">Allowed Roles</h4>
            <h4 data-lang="es">Roles Permitidos</h4>
            <p data-lang="en"><code>Cliente</code> (owner only), <code>Administrador</code></p>
            <p data-lang="es"><code>Cliente</code> (solo propietario), <code>Administrador</code></p>

            <h4 data-lang="en">Request Body</h4>
            <h4 data-lang="es">Cuerpo de la Petición</h4>
            <pre class="code-block line-numbers"><code class="language-json">{
    "estado": "Aprovado",
    "observaciones": "Cliente confirmó recepción"
}</code></pre>

            <h4 data-lang="en">Valid States & Aliases</h4>
            <h4 data-lang="es">Estados Válidos y Alias</h4>
            
            <table class="table table-sm table-bordered" data-lang="en">
                <thead><tr><th>Canonical State</th><th>Accepted Aliases</th></tr></thead>
                <tbody>
                    <tr><td><code>EN_ESPERA</code></td><td>ESPERA, PENDING, WAITING</td></tr>
                    <tr><td><code>APROBADO</code></td><td>APROVADO, APPROVED</td></tr>
                    <tr><td><code>CONFIRMADO</code></td><td>CONFIRMAR, CONFIRMED</td></tr>
                    <tr><td><code>EN_TRANSITO</code></td><td>EN TRANSITO, TRANSITO, TRANSIT</td></tr>
                    <tr><td><code>EN_BODEGA</code></td><td>EN BODEGA, BODEGA, WAREHOUSE</td></tr>
                    <tr><td><code>CANCELADO</code></td><td>CANCELAR, CANCELLED, CANCELED</td></tr>
                </tbody>
            </table>
            
            <table class="table table-sm table-bordered" data-lang="es">
                <thead><tr><th>Estado Canónico</th><th>Alias Aceptados</th></tr></thead>
                <tbody>
                    <tr><td><code>EN_ESPERA</code></td><td>ESPERA, PENDING, WAITING</td></tr>
                    <tr><td><code>APROBADO</code></td><td>APROVADO, APPROVED</td></tr>
                    <tr><td><code>CONFIRMADO</code></td><td>CONFIRMAR, CONFIRMED</td></tr>
                    <tr><td><code>EN_TRANSITO</code></td><td>EN TRANSITO, TRANSITO, TRANSIT</td></tr>
                    <tr><td><code>EN_BODEGA</code></td><td>EN BODEGA, BODEGA, WAREHOUSE</td></tr>
                    <tr><td><code>CANCELADO</code></td><td>CANCELAR, CANCELLED, CANCELED</td></tr>
                </tbody>
            </table>

            <h4 data-lang="en">State Descriptions</h4>
            <h4 data-lang="es">Descripción de Estados</h4>
            
            <table class="table table-sm table-bordered" data-lang="en">
                <thead><tr><th>State</th><th>Description</th><th>When to Use</th></tr></thead>
                <tbody>
                    <tr><td><code>EN_ESPERA</code></td><td>Waiting for approval</td><td>Initial state when order is created</td></tr>
                    <tr><td><code>APROBADO</code></td><td>Approved and validated</td><td>After reviewing and approving the order</td></tr>
                    <tr><td><code>CONFIRMADO</code></td><td>Confirmed with customer</td><td>Customer confirmed they want to proceed</td></tr>
                    <tr><td><code>EN_TRANSITO</code></td><td>Package on the way</td><td>Package shipped and being transported</td></tr>
                    <tr><td><code>EN_BODEGA</code></td><td>Package arrived at warehouse</td><td>Package received and stored</td></tr>
                    <tr><td><code>CANCELADO</code></td><td>Order cancelled</td><td>Order will not proceed for any reason</td></tr>
                </tbody>
            </table>
            
            <table class="table table-sm table-bordered" data-lang="es">
                <thead><tr><th>Estado</th><th>Descripción</th><th>Cuándo Usar</th></tr></thead>
                <tbody>
                    <tr><td><code>EN_ESPERA</code></td><td>Esperando aprobación</td><td>Estado inicial cuando se crea el pedido</td></tr>
                    <tr><td><code>APROBADO</code></td><td>Aprobado y validado</td><td>Después de revisar y aprobar el pedido</td></tr>
                    <tr><td><code>CONFIRMADO</code></td><td>Confirmado con cliente</td><td>Cliente confirmó que procede con el pedido</td></tr>
                    <tr><td><code>EN_TRANSITO</code></td><td>Paquete en camino</td><td>Paquete salió y está siendo transportado</td></tr>
                    <tr><td><code>EN_BODEGA</code></td><td>Paquete llegó a bodega</td><td>Paquete recibido y almacenado</td></tr>
                    <tr><td><code>CANCELADO</code></td><td>Pedido cancelado</td><td>Pedido no procede por cualquier razón</td></tr>
                </tbody>
            </table>
            
            <div class="alert alert-info" data-lang="en">
                <strong>Note:</strong> There are no transition restrictions. Clients can change from any state to any other valid state according to their business needs.
            </div>
            <div class="alert alert-info" data-lang="es">
                <strong>Nota:</strong> No hay restricciones de transición. Los clientes pueden cambiar de cualquier estado a cualquier otro estado válido según sus necesidades de negocio.
            </div>

            <h4>Response — Success <span class="status-badge status-200">200</span></h4>
            <pre class="code-block line-numbers"><code class="language-json">{
    "success": true,
    "message": "Estado actualizado a APROBADO",
    "estado_anterior": "EN_ESPERA",
    "estado_nuevo": "APROBADO"
}</code></pre>

            <h4>Response — Invalid State <span class="status-badge status-400">400</span></h4>
            <pre class="code-block line-numbers"><code class="language-json">{
    "success": false,
    "message": "Estado inválido: INVALID_STATE",
    "estados_validos": ["EN_ESPERA", "APROBADO", "CONFIRMADO", "EN_TRANSITO", "EN_BODEGA", "CANCELADO"]
}</code></pre>
        </div>

        <!-- GET /api/crm/leads -->
        <div class="section-container">
            <h2 class="section-title" data-lang="en">List Leads</h2>
            <h2 class="section-title" data-lang="es">Listar Leads</h2>
            
            <p data-lang="en">Retrieve leads with filtering and pagination. Automatic role-based scoping applies.</p>
            <p data-lang="es">Recupera leads con filtrado y paginación. Se aplica alcance automático basado en roles.</p>

            <h4 data-lang="en">Endpoint</h4>
            <h4 data-lang="es">Endpoint</h4>
            <div class="code-block"><span class="badge-endpoint badge-get">GET</span> /api/crm/leads</div>

            <h4 data-lang="en">Query Parameters</h4>
            <h4 data-lang="es">Parámetros de Consulta</h4>
            
            <table class="table table-sm table-bordered" data-lang="en">
                <thead><tr><th>Parameter</th><th>Type</th><th>Default</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><code>page</code></td><td>integer</td><td>1</td><td>Page number</td></tr>
                    <tr><td><code>limit</code></td><td>integer</td><td>50</td><td>Items per page (max 100)</td></tr>
                    <tr><td><code>estado</code></td><td>string</td><td>-</td><td>Filter by state</td></tr>
                    <tr><td><code>fecha_desde</code></td><td>date</td><td>-</td><td>From date (YYYY-MM-DD)</td></tr>
                    <tr><td><code>fecha_hasta</code></td><td>date</td><td>-</td><td>To date (YYYY-MM-DD)</td></tr>
                </tbody>
            </table>
            
            <table class="table table-sm table-bordered" data-lang="es">
                <thead><tr><th>Parámetro</th><th>Tipo</th><th>Por Defecto</th><th>Descripción</th></tr></thead>
                <tbody>
                    <tr><td><code>page</code></td><td>integer</td><td>1</td><td>Número de página</td></tr>
                    <tr><td><code>limit</code></td><td>integer</td><td>50</td><td>Items por página (máx 100)</td></tr>
                    <tr><td><code>estado</code></td><td>string</td><td>-</td><td>Filtrar por estado</td></tr>
                    <tr><td><code>fecha_desde</code></td><td>date</td><td>-</td><td>Desde fecha (YYYY-MM-DD)</td></tr>
                    <tr><td><code>fecha_hasta</code></td><td>date</td><td>-</td><td>Hasta fecha (YYYY-MM-DD)</td></tr>
                </tbody>
            </table>

            <h4 data-lang="en">Example cURL</h4>
            <h4 data-lang="es">Ejemplo cURL</h4>
            <pre class="code-block"><code class="language-bash">curl "http://localhost/paqueteriacz/api/crm/leads?page=1&limit=10&estado=APROBADO" \
  -H "Authorization: Bearer &lt;TOKEN&gt;"</code></pre>
        </div>

        <!-- GET /api/crm/leads/{id} -->
        <div class="section-container">
            <h2 class="section-title" data-lang="en">View Lead Detail & Timeline</h2>
            <h2 class="section-title" data-lang="es">Ver Detalle de Lead y Cronología</h2>
            
            <p data-lang="en">Access full lead details or status change history.</p>
            <p data-lang="es">Accede a los detalles completos del lead o historial de cambios de estado.</p>

            <h4 data-lang="en">Endpoints</h4>
            <h4 data-lang="es">Endpoints</h4>
            <div class="code-block"><span class="badge-endpoint badge-get">GET</span> /api/crm/leads/{id}</div>
            <div class="code-block mt-2"><span class="badge-endpoint badge-get">GET</span> /api/crm/leads/{id}/timeline</div>

            <h4 data-lang="en">Example cURL</h4>
            <h4 data-lang="es">Ejemplo cURL</h4>
            <pre class="code-block" data-lang="en"><code class="language-bash"># View detail
curl "http://localhost/paqueteriacz/api/crm/leads/1" \
  -H "Authorization: Bearer &lt;TOKEN&gt;"

# View timeline
curl "http://localhost/paqueteriacz/api/crm/leads/1/timeline" \
  -H "Authorization: Bearer &lt;TOKEN&gt;"</code></pre>
            <pre class="code-block" data-lang="es"><code class="language-bash"># Ver detalle
curl "http://localhost/paqueteriacz/api/crm/leads/1" \
  -H "Authorization: Bearer &lt;TOKEN&gt;"

# Ver cronología
curl "http://localhost/paqueteriacz/api/crm/leads/1/timeline" \
  -H "Authorization: Bearer &lt;TOKEN&gt;"</code></pre>
        </div>

        <!-- GET /api/crm/metrics -->
        <div class="section-container">
            <h2 class="section-title" data-lang="en">System Metrics</h2>
            <h2 class="section-title" data-lang="es">Métricas del Sistema</h2>
            
            <p data-lang="en">Monitor CRM health and performance (admin-only).</p>
            <p data-lang="es">Monitorea la salud y rendimiento del CRM (solo administrador).</p>

            <h4 data-lang="en">Endpoint</h4>
            <h4 data-lang="es">Endpoint</h4>
            <div class="code-block"><span class="badge-endpoint badge-get">GET</span> /api/crm/metrics</div>

            <h4 data-lang="en">Allowed Roles</h4>
            <h4 data-lang="es">Roles Permitidos</h4>
            <p data-lang="en"><code>Administrador</code> only</p>
            <p data-lang="es">Solo <code>Administrador</code></p>

            <h4 data-lang="en">Example cURL</h4>
            <h4 data-lang="es">Ejemplo cURL</h4>
            <pre class="code-block"><code class="language-bash">curl "http://localhost/paqueteriacz/api/crm/metrics" \
  -H "Authorization: Bearer &lt;ADMIN_TOKEN&gt;"</code></pre>
        </div>

        <!-- Webhooks -->
        <div class="section-container">
            <h2 class="section-title" data-lang="en">Webhooks (HMAC Signed)</h2>
            <h2 class="section-title" data-lang="es">Webhooks (Firmados con HMAC)</h2>
            
            <p data-lang="en">Receive real-time notifications via cryptographically signed webhooks.</p>
            <p data-lang="es">Recibe notificaciones en tiempo real vía webhooks firmados criptográficamente.</p>

            <h4 data-lang="en">Events</h4>
            <h4 data-lang="es">Eventos</h4>
            
            <ul data-lang="en">
                <li><strong>SEND_TO_CLIENT</strong> — Lead forwarded to client</li>
                <li><strong>SEND_TO_PROVIDER</strong> — Status updated by client</li>
            </ul>
            
            <ul data-lang="es">
                <li><strong>SEND_TO_CLIENT</strong> — Lead reenviado al cliente</li>
                <li><strong>SEND_TO_PROVIDER</strong> — Estado actualizado por el cliente</li>
            </ul>

            <h4 data-lang="en">Signature Verification (PHP)</h4>
            <h4 data-lang="es">Verificación de Firma (PHP)</h4>
            <pre class="code-block line-numbers"><code class="language-php">$payload = file_get_contents("php://input");
$signature = $_SERVER['HTTP_X_SIGNATURE'];
$secret = 'your_shared_secret';

$expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);

if (hash_equals($expected, $signature)) {
    $data = json_decode($payload, true);
    // Process webhook / Procesar webhook
} else {
    http_response_code(401);
    exit;
}</code></pre>

            <h4 data-lang="en">Configuration</h4>
            <h4 data-lang="es">Configuración</h4>
            <pre class="code-block line-numbers"><code class="language-sql">INSERT INTO crm_integrations (user_id, kind, webhook_url, secret, is_active)
VALUES (5, 'cliente', 'https://app.com/webhook', 'secret_123', 1);</code></pre>
        </div>

        <!-- Worker CLI -->
        <div class="section-container">
            <h2 class="section-title" data-lang="en">Worker CLI</h2>
            <h2 class="section-title" data-lang="es">Worker CLI</h2>
            
            <p data-lang="en">Background worker for async processing with 3-second polling interval.</p>
            <p data-lang="es">Worker en segundo plano para procesamiento asíncrono con intervalo de sondeo de 3 segundos.</p>

            <h4 data-lang="en">Commands</h4>
            <h4 data-lang="es">Comandos</h4>
            <pre class="code-block" data-lang="en"><code class="language-bash"># One-time execution (cron)
php cli/crm_worker.php --once

# Daemon mode (systemd)
php cli/crm_worker.php --loop</code></pre>
            <pre class="code-block" data-lang="es"><code class="language-bash"># Ejecución única (cron)
php cli/crm_worker.php --once

# Modo daemon (systemd)
php cli/crm_worker.php --loop</code></pre>

            <h4 data-lang="en">Systemd Service</h4>
            <h4 data-lang="es">Servicio Systemd</h4>
            <pre class="code-block line-numbers"><code class="language-ini">[Unit]
Description=CRM Relay Worker
After=mariadb.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/xampp/htdocs/paqueteriacz
ExecStart=/usr/bin/php cli/crm_worker.php --loop
Restart=always

[Install]
WantedBy=multi-user.target</code></pre>

            <pre class="code-block"><code class="language-bash">sudo systemctl enable crm-worker
sudo systemctl start crm-worker
sudo journalctl -u crm-worker -f</code></pre>
        </div>

        <!-- Troubleshooting -->
        <div class="section-container">
            <h2 class="section-title" data-lang="en">Troubleshooting</h2>
            <h2 class="section-title" data-lang="es">Solución de Problemas</h2>
            
            <ul data-lang="en">
                <li><strong>Duplicate lead detection</strong> — First: 202, Retry: 200 + <code>"duplicated":true</code></li>
                <li><strong>Invalid transition</strong> — Check state matrix (can't jump EN_ESPERA → EN_TRANSITO)</li>
                <li><strong>Worker not running</strong> — Verify: <code>ps aux | grep crm_worker</code></li>
                <li><strong>Webhook failures</strong> — Check <code>crm_outbox.last_error</code> column</li>
                <li><strong>Queue backup</strong> — Monitor: <code>SELECT COUNT(*) FROM crm_inbox WHERE status='pending'</code></li>
            </ul>
            
            <ul data-lang="es">
                <li><strong>Detección de duplicados</strong> — Primero: 202, Reintento: 200 + <code>"duplicated":true</code></li>
                <li><strong>Transición inválida</strong> — Verifica matriz de estados (no puede saltar EN_ESPERA → EN_TRANSITO)</li>
                <li><strong>Worker no ejecutándose</strong> — Verifica: <code>ps aux | grep crm_worker</code></li>
                <li><strong>Fallos de webhook</strong> — Revisa columna <code>crm_outbox.last_error</code></li>
                <li><strong>Respaldo de cola</strong> — Monitorea: <code>SELECT COUNT(*) FROM crm_inbox WHERE status='pending'</code></li>
            </ul>

            <h4 data-lang="en">Monitoring Queries</h4>
            <h4 data-lang="es">Consultas de Monitoreo</h4>
            
            <pre class="code-block line-numbers" data-lang="en"><code class="language-sql">-- Pending inbox count
SELECT COUNT(*) FROM crm_inbox WHERE status='pending';

-- Failed webhooks
SELECT id, event_type, attempts, last_error 
FROM crm_outbox 
WHERE status='failed' 
LIMIT 10;

-- Recent leads
SELECT id, proveedor_lead_id, estado_actual, created_at 
FROM crm_leads 
ORDER BY created_at DESC 
LIMIT 20;</code></pre>
            
            <pre class="code-block line-numbers" data-lang="es"><code class="language-sql">-- Conteo de inbox pendientes
SELECT COUNT(*) FROM crm_inbox WHERE status='pending';

-- Webhooks fallidos
SELECT id, event_type, attempts, last_error 
FROM crm_outbox 
WHERE status='failed' 
LIMIT 10;

-- Leads recientes
SELECT id, proveedor_lead_id, estado_actual, created_at 
FROM crm_leads 
ORDER BY created_at DESC 
LIMIT 20;</code></pre>
        </div>

        <!-- HTTP Status Codes -->
        <div class="section-container">
            <h2 class="section-title" data-lang="en">HTTP Status Codes</h2>
            <h2 class="section-title" data-lang="es">Códigos de Estado HTTP</h2>
            
            <table class="table table-bordered" data-lang="en">
                <thead><tr><th>Code</th><th>Meaning</th><th>When</th></tr></thead>
                <tbody>
                    <tr><td><span class="status-badge status-200">200</span></td><td>OK</td><td>Successful query, update, or idempotent retry</td></tr>
                    <tr><td><span class="status-badge status-202">202</span></td><td>Accepted</td><td>Lead queued for async processing</td></tr>
                    <tr><td><span class="status-badge status-400">400</span></td><td>Bad Request</td><td>Validation error, invalid transition</td></tr>
                    <tr><td><span class="status-badge status-401">401</span></td><td>Unauthorized</td><td>Missing/invalid JWT token</td></tr>
                    <tr><td><span class="status-badge status-403">403</span></td><td>Forbidden</td><td>Insufficient permissions or ownership</td></tr>
                    <tr><td><span class="status-badge status-404">404</span></td><td>Not Found</td><td>Lead ID doesn't exist</td></tr>
                </tbody>
            </table>
            
            <table class="table table-bordered" data-lang="es">
                <thead><tr><th>Código</th><th>Significado</th><th>Cuándo</th></tr></thead>
                <tbody>
                    <tr><td><span class="status-badge status-200">200</span></td><td>OK</td><td>Consulta exitosa, actualización o reintento idempotente</td></tr>
                    <tr><td><span class="status-badge status-202">202</span></td><td>Aceptado</td><td>Lead encolado para procesamiento asíncrono</td></tr>
                    <tr><td><span class="status-badge status-400">400</span></td><td>Solicitud Incorrecta</td><td>Error de validación, transición inválida</td></tr>
                    <tr><td><span class="status-badge status-401">401</span></td><td>No Autorizado</td><td>Token JWT faltante/inválido</td></tr>
                    <tr><td><span class="status-badge status-403">403</span></td><td>Prohibido</td><td>Permisos insuficientes o falta de propiedad</td></tr>
                    <tr><td><span class="status-badge status-404">404</span></td><td>No Encontrado</td><td>ID de lead no existe</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <footer>
        <p data-lang="en">&copy; 2025 CRM Relay API — Built with ❤️ for developers</p>
        <p data-lang="es">&copy; 2025 API CRM Relay — Hecho con ❤️ para desarrolladores</p>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
        integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r"
        crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js"
        integrity="sha384-BBtl+eGJRgqQAUMxJ7pMwbEyER4l1g+O15P+16Ep7Q9Q+zqX6gSbd85u4mG4QzX+"
        crossorigin="anonymous"></script>
    
    <!-- Prism.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-json.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-sql.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-bash.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-ini.min.js"></script>
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
            localStorage.setItem('crm-docs-lang', lang);
        }

        // Load saved language preference on page load
        document.addEventListener('DOMContentLoaded', function() {
            const savedLang = localStorage.getItem('crm-docs-lang') || 'en';
            setLanguage(savedLang);
        });
    </script>
</body>

</html>
