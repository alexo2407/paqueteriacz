<?php 

start_secure_session();

if(!isset($_SESSION['registrado'])) {
    header('location:'.RUTA_URL.'login');
    die();
}

require_once __DIR__ . '/../../../utils/permissions.php';
if (!isAdmin()) {
    header('Location: ' . RUTA_URL . 'dashboard');
    exit;
}

include("vista/includes/header.php");
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">
                        <i class="bi bi-book"></i> Manual CRM - Guía Completa del Sistema
                    </h3>
                </div>
                <div class="card-body">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="<?= RUTA_URL ?>dashboard">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="<?= RUTA_URL ?>crm/dashboard">CRM</a></li>
                            <li class="breadcrumb-item active">Manual</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Índice rápido -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-list-ul"></i> Índice</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li><a href="#introduccion" class="text-decoration-none"><i class="bi bi-chevron-right"></i> 1. Introducción</a></li>
                                <li><a href="#arquitectura" class="text-decoration-none"><i class="bi bi-chevron-right"></i> 2. Arquitectura del Sistema</a></li>
                                <li><a href="#flujo-leads" class="text-decoration-none"><i class="bi bi-chevron-right"></i> 3. Flujo de Leads</a></li>
                                <li><a href="#api" class="text-decoration-none"><i class="bi bi-chevron-right"></i> 4. API REST (Proveedores)</a></li>
                                <li><a href="#api-clientes" class="text-decoration-none"><i class="bi bi-chevron-right"></i> 4.5. API para Clientes</a></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li><a href="#worker" class="text-decoration-none"><i class="bi bi-chevron-right"></i> 5. Worker CLI</a></li>
                                <li><a href="#casos-uso" class="text-decoration-none"><i class="bi bi-chevron-right"></i> 6. Casos de Uso</a></li>
                                <li><a href="#troubleshooting" class="text-decoration-none"><i class="bi bi-chevron-right"></i> 7. Troubleshooting</a></li>
                                <li><a href="#mejores-practicas" class="text-decoration-none"><i class="bi bi-chevron-right"></i> 8. Mejores Prácticas</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 1. Introducción -->
    <div class="row mb-4" id="introduccion">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h4 class="mb-0">1. Introducción</h4>
                </div>
                <div class="card-body">
                    <h5><i class="bi bi-question-circle"></i> ¿Qué es CRM Relay?</h5>
                    <p class="lead">
                        CRM Relay es un sistema de gestión de leads bidireccional que actúa como intermediario 
                        entre múltiples sistemas CRM externos y tu aplicación.
                    </p>
                    
                    <h5 class="mt-4"><i class="bi bi-star"></i> Características Principales</h5>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item"><i class="bi bi-check-circle text-success"></i> <strong>Recepción de Leads:</strong> Recibe leads desde CRMs externos vía webhooks/API</li>
                        <li class="list-group-item"><i class="bi bi-check-circle text-success"></i> <strong>Worker Asíncrono:</strong> Procesamiento en segundo plano de mensajes</li>
                        <li class="list-group-item"><i class="bi bi-check-circle text-success"></i> <strong>Trazabilidad Completa:</strong> Registro detallado de todas las operaciones</li>
                    </ul>

                    <div class="alert alert-info mt-4">
                        <i class="bi bi-info-circle"></i> <strong>Nota:</strong> 
                        Este sistema está diseñado para empresas que necesitan centralizar leads provenientes 
                        de múltiples fuentes y mantener sincronizados sus estados.
                    </div>

                    <h5 class="mt-4"><i class="bi bi-speedometer2"></i> Dashboard Administrativo</h5>
                    <p>
                        Los administradores cuentan con un Dashboard dedicado (`/crm/dashboard`) para monitorear:
                        <ul>
                             <li><strong>Métricas de Leads:</strong> Totales, estados y tendencias.</li>
                             <li><strong>Colas de Mensajes:</strong> Estado del inbox.</li>
                             <li><strong>Filtros Avanzados:</strong> Capacidad de visualizar métricas por <strong>Proveedor</strong> y rango de <strong>Fechas</strong>.</li>
                        </ul>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Arquitectura del Sistema -->
    <div class="row mb-4" id="arquitectura">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h4 class="mb-0">2. Arquitectura del Sistema</h4>
                </div>
                <div class="card-body">
                    <h5><i class="bi bi-diagram-3"></i> Componentes Principales</h5>
                    
                    <div class="row mt-3">
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-primary">
                                <div class="card-header bg-primary text-white">
                                    <strong>1. CRM Inbox</strong>
                                </div>
                                <div class="card-body">
                                    <p><strong>Propósito:</strong> Cola de entrada para procesar leads de proveedores</p>
                                    <p><strong>Tabla:</strong> <code>crm_inbox</code></p>
                                    <p><strong>Campos clave:</strong></p>
                                    <ul>
                                        <li><code>source</code> - Origen: 'proveedor' o 'cliente'</li>
                                        <li><code>idempotency_key</code> - Clave única para evitar duplicados</li>
                                        <li><code>payload</code> - Datos JSON del lead</li>
                                        <li><code>status</code> - pending/processed/failed</li>
                                    </ul>
                                    <p><strong>Estados:</strong></p>
                                    <ul>
                                        <li><span class="badge bg-warning">pending</span> - Esperando procesamiento</li>
                                        <li><span class="badge bg-success">processed</span> - Procesado exitosamente</li>
                                        <li><span class="badge bg-danger">failed</span> - Error al procesar</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-success">
                                <div class="card-header bg-success text-white">
                                    <strong>2. CRM Leads</strong>
                                </div>
                                <div class="card-body">
                                    <p><strong>Propósito:</strong> Almacena y gestiona los leads</p>
                                    <p><strong>Tabla:</strong> <code>crm_leads</code></p>
                                    <p><strong>Estados:</strong></p>
                                    <ul>
                                        <li><span class="badge bg-info">new</span> - Lead nuevo</li>
                                        <li><span class="badge bg-primary">contacted</span> - Contactado</li>
                                        <li><span class="badge bg-warning">qualified</span> - Calificado</li>
                                        <li><span class="badge bg-success">converted</span> - Convertido</li>
                                        <li><span class="badge bg-danger">rejected</span> - Rechazado</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h5 class="mt-4"><i class="bi bi-gear"></i> Worker CLI</h5>
                    <div class="alert alert-dark">
                        <p><strong>Script:</strong> <code>cli/crm_worker.php</code></p>
                        <p><strong>Función:</strong> Procesa mensajes de inbox en segundo plano</p>
                        <p><strong>Ejecución:</strong> <code>php cli/crm_worker.php</code></p>
                        <p class="mb-0"><strong>Recomendación:</strong> Ejecutar con supervisor o como servicio de sistema</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Flujo de Leads -->
    <div class="row mb-4" id="flujo-leads">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">3. Flujo de Leads - Paso a Paso</h4>
                </div>
                <div class="card-body">
                    <h5><i class="bi bi-arrow-down-circle"></i> Flujo de Entrada (CRM Externo → Tu Sistema)</h5>
                    
                    <div class="timeline">
                        <div class="card mb-3 border-primary">
                            <div class="card-header bg-primary text-white">
                                <strong>Paso 1:</strong> Usuario Proveedor envía lead
                            </div>
                            <div class="card-body">
                                <p><strong>Endpoint:</strong> <code>POST /api/crm/leads</code></p>
                                <p><strong>Headers requeridos:</strong></p>
                                <pre class="bg-light p-2"><code>Content-Type: application/json
Authorization: Bearer [JWT_TOKEN]</code></pre>
                                <p><strong>Payload ejemplo (individual):</strong></p>
                                <pre class="bg-light p-2"><code>{
  "lead": {
    "proveedor_lead_id": "lead_12345",
    "fecha_hora": "2026-01-02T10:30:00Z",
    "nombre": "Juan Pérez",
    "email": "juan@example.com",
    "telefono": "+502 1234-5678",
    "mensaje": "Interesado en servicio de paquetería",
    "origen": "Formulario Web"
  }
}</code></pre>
                                <p><strong>Payload ejemplo (batch):</strong></p>
                                <pre class="bg-light p-2"><code>{
  "leads": [
    {
      "proveedor_lead_id": "lead_001",
      "fecha_hora": "2026-01-02T10:30:00Z",
      "nombre": "Cliente 1",
      "email": "cliente1@example.com"
    },
    {
      "proveedor_lead_id": "lead_002",
      "fecha_hora": "2026-01-02T10:35:00Z",
      "nombre": "Cliente 2",
      "email": "cliente2@example.com"
    }
  ]
}</code></pre>
                            </div>
                        </div>

                        <div class="card mb-3 border-warning">
                            <div class="card-header bg-warning text-dark">
                                <strong>Paso 2:</strong> Sistema valida autenticación JWT
                            </div>
                            <div class="card-body">
                                <ul class="mb-0">
                                    <li>Decodifica y valida el JWT del header <code>Authorization: Bearer</code></li>
                                    <li>Verifica que el token no esté expirado</li>
                                    <li>Extrae el <code>user_id</code> del payload del token</li>
                                    <li>Verifica que el usuario tenga rol <strong>Proveedor</strong> o <strong>Admin</strong></li>
                                    <li>Si falta token → <span class="badge bg-danger">401 Unauthorized</span></li>
                                    <li>Si rol insuficiente → <span class="badge bg-danger">403 Forbidden</span></li>
                                </ul>
                            </div>
                        </div>

                        <div class="card mb-3 border-info">
                            <div class="card-header bg-info text-white">
                                <strong>Paso 3:</strong> Se crea registro en CRM Inbox
                            </div>
                            <div class="card-body">
                                <p>Se guarda en <code>crm_inbox</code> con:</p>
                                <ul class="mb-0">
                                    <li><strong>source</strong> = 'proveedor' (origen del mensaje)</li>
                                    <li><strong>idempotency_key</strong> = hash SHA-256 del payload (evita duplicados)</li>
                                    <li><strong>payload</strong> = JSON completo incluyendo user_id y datos del lead</li>
                                    <li><strong>status</strong> = <span class="badge bg-warning">pending</span></li>
                                    <li><strong>created_at</strong> = timestamp actual</li>
                                </ul>
                                <div class="alert alert-info mt-2 mb-0">
                                    <small><i class="bi bi-shield-check"></i> <strong>Idempotencia:</strong> Si se reenvía el mismo payload, el sistema detecta el duplicado por idempotency_key y no crea un registro nuevo.</small>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-3 border-success">
                            <div class="card-header bg-success text-white">
                                <strong>Paso 4:</strong> Worker procesa el mensaje
                            </div>
                            <div class="card-body">
                                <p><strong>El worker (<code>crm_worker.php</code>) cada N segundos:</strong></p>
                                <ol>
                                    <li>Busca mensajes con status = <span class="badge bg-warning">pending</span> en inbox</li>
                                    <li>Extrae datos del payload JSON</li>
                                    <li>Crea un lead en <code>crm_leads</code> con status = <span class="badge bg-info">new</span></li>
                                    <li>Actualiza inbox a status = <span class="badge bg-success">processed</span></li>
                                    <li>Si hay error → status = <span class="badge bg-danger">failed</span> + guarda el error</li>
                                </ol>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <h5><i class="bi bi-diagram-2"></i> Flujo Alternativo: Cliente Actualiza Lead vía API</h5>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> <strong>Escenario:</strong><br>
                        Un <strong>distribuidor/cliente</strong> recibe leads asignados y puede actualizarlos desde su
                        propio sistema usando la API, sin necesidad de entrar a la interfaz web.
                    </div>

                    <div class="timeline">
                        <div class="card mb-3 border-primary">
                            <div class="card-header bg-primary text-white">
                                <strong>Paso 1:</strong> Cliente se autentica vía API
                            </div>
                            <div class="card-body">
                                <p>El cliente obtiene su token JWT:</p>
                                <pre class="bg-light p-2"><code>POST <?= RUTA_URL ?>api/auth/login
{
  "email": "cliente@distribuidor.com",
  "password": "password_seguro"
}
// Response: {"success": true, "token": "eyJ0eXAi..."}</code></pre>
                            </div>
                        </div>

                        <div class="card mb-3 border-info">
                            <div class="card-header bg-info text-white">
                                <strong>Paso 2:</strong> Cliente lista sus leads pendientes
                            </div>
                            <div class="card-body">
                                <p>Obtiene la lista de leads asignados:</p>
                                <pre class="bg-light p-2"><code>GET <?= RUTA_URL ?>api/crm/leads?estado=new
Authorization: Bearer [JWT_TOKEN]
// Solo ve leads donde cliente_id = user_id</code></pre>
                            </div>
                        </div>

                        <div class="card mb-3 border-warning">
                            <div class="card-header bg-warning text-dark">
                                <strong>Paso 3:</strong> Cliente actualiza estado del lead
                            </div>
                            <div class="card-body">
                                <pre class="bg-light p-2"><code>POST <?= RUTA_URL ?>api/crm/leads/456/estado
{
  "estado": "qualified",
  "observaciones": "Cliente confirmó interés"
}</code></pre>
                            </div>
                        </div>

                        <div class="card mb-3 border-success">
                            <div class="card-header bg-success text-white">
                                <strong>Paso 4:</strong> Sistema actualiza el lead
                            </div>
                            <div class="card-body">
                                <p>El sistema actualiza el estado del lead y registra la observación en el historial.</p>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-success mt-3">
                        <i class="bi bi-check-circle"></i> <strong>Ventajas:</strong>
                        Cliente gestiona leads sin entrar al sistema web.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. API REST -->
    <div class="row mb-4" id="api">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h4 class="mb-0">4. API REST - Endpoints Disponibles</h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="bi bi-book"></i> Para documentación completa y ejemplos interactivos, visita:
                        <a href="<?= RUTA_URL ?>api/doc/crmdoc.php" target="_blank" class="alert-link">
                            Documentación CRM API <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                    </div>

                    <h5 class="mt-3">Endpoints Principales</h5>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Método</th>
                                <th>Endpoint</th>
                                <th>Descripción</th>
                                <th>Auth</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge bg-success">POST</span></td>
                                <td><code>/api/crm/leads</code></td>
                                <td>Recibir nuevo lead desde CRM externo</td>
                                <td>JWT</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-primary">GET</span></td>
                                <td><code>/api/crm/leads</code></td>
                                <td>Listar todos los leads (con filtros)</td>
                                <td>JWT</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-primary">GET</span></td>
                                <td><code>/api/crm/leads/{id}</code></td>
                                <td>Obtener detalle de un lead específico</td>
                                <td>JWT</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-warning">PUT</span></td>
                                <td><code>/api/crm/leads/{id}/status</code></td>
                                <td>Actualizar estado de un lead</td>
                                <td>JWT</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-primary">GET</span></td>
                                <td><code>/api/crm/provider-metrics</code></td>
                                <td>Obtener métricas de leads del proveedor</td>
                                <td>JWT</td>
                            </tr>
                        </tbody>
                    </table>

                    <h5 class="mt-4"><i class="bi bi-bell"></i> API de Notificaciones</h5>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Método</th>
                                <th>Endpoint</th>
                                <th>Descripción</th>
                                <th>Auth</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge bg-primary">GET</span></td>
                                <td><code>/api/crm/notifications</code></td>
                                <td>Obtener notificaciones no leídas</td>
                                <td>JWT</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-warning">POST</span></td>
                                <td><code>/api/crm/notifications/mark-read</code></td>
                                <td>Marcar notificación como leída</td>
                                <td>JWT</td>
                            </tr>
                        </tbody>
                    </table>

                    <h5 class="mt-4">Autenticación JWT - Paso a Paso</h5>
                    <div class="card border-primary mb-3">
                        <div class="card-header bg-primary text-white">
                            <strong>Paso 1: Obtener Token JWT</strong>
                        </div>
                        <div class="card-body">
                            <p>Primero debes autenticarte en el sistema para obtener un token JWT:</p>
                            <pre class="bg-dark text-light p-3"><code>// PHP - Login para obtener JWT
$loginUrl = '<?= RUTA_URL ?>api/auth/login';
$credentials = [
    'email' => 'proveedor@example.com',
    'password' => 'tu_password_seguro'
];

$ch = curl_init($loginUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($credentials));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$data = json_decode($response, true);
$token = $data['token']; // Tu JWT token

curl_close($ch);</code></pre>
                        </div>
                    </div>

                    <div class="card border-success">
                        <div class="card-header bg-success text-white">
                            <strong>Paso 2: Usar el Token en Llamadas API</strong>
                        </div>
                        <div class="card-body">
                            <p>Incluye el token en el header <code>Authorization</code>:</p>
                            <pre class="bg-dark text-light p-3"><code>// PHP - Enviar lead usando JWT
$apiUrl = '<?= RUTA_URL ?>api/crm/leads';
$leadData = [
    'lead' => [
        'proveedor_lead_id' => 'lead_12345',
        'fecha_hora' => date('c'),
        'nombre' => 'Juan Pérez',
        'email' => 'juan@example.com',
        'telefono' => '+502 1234-5678'
    ]
];

$headers = [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token  // <-- Token JWT aquí
];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($leadData));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
$result = json_decode($response, true);

curl_close($ch);</code></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4.5. API para Clientes -->
    <div class="row mb-4" id="api-clientes">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">4.5. API para Usuarios Cliente - Gestión de Leads</h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-success">
                        <i class="bi bi-people"></i> <strong>¿Quién puede usar esta API?</strong><br>
                        Usuarios con rol <span class="badge bg-primary">Cliente</span> o <span class="badge bg-danger">Admin</span> pueden usar estos endpoints para gestionar los leads asignados a ellos.
                    </div>

                    <h5 class="mt-3"><i class="bi bi-key"></i> Autenticación</h5>
                    <p>Los clientes usan la misma autenticación JWT que los proveedores:</p>
                    
                    <div class="card border-primary mb-3">
                        <div class="card-header bg-primary text-white">
                            <strong>Obtener Token JWT</strong>
                        </div>
                        <div class="card-body">
                            <pre class="bg-dark text-light p-3"><code>POST <?= RUTA_URL ?>api/auth/login

{
  "email": "cliente@empresa.com",
  "password": "password_seguro"
}

// Response
{
  "success": true,
  "token": "eyJ0eXAiOiJKV1QiLCJhbGci..."
}</code></pre>
                        </div>
                    </div>

                    <h5 class="mt-4"><i class="bi bi-server"></i> Endpoints Disponibles</h5>
                    <table class="table table-bordered table-hover">
                        <thead class="table-success">
                            <tr>
                                <th>Método</th>
                                <th>Endpoint</th>
                                <th>Descripción</th>
                                <th>Restricción</th>
                            </tr>
                        </thead>
                        <tbody>
                        <tbody>
                            <tr>
                                <td><span class="badge bg-primary">GET</span></td>
                                <td><code>/api/crm/leads</code></td>
                                <td>Listar leads asignados al cliente</td>
                                <td>Solo ve sus propios leads</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-primary">GET</span></td>
                                <td><code>/api/crm/leads/{id}</code></td>
                                <td>Ver detalle de un lead específico</td>
                                <td>Solo sus leads</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-warning">POST</span></td>
                                <td><code>/api/crm/leads/{id}/estado</code></td>
                                <td>Actualizar estado de un lead</td>
                                <td>Solo sus leads</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-warning">POST</span></td>
                                <td><code>/api/crm/leads/bulk-status</code></td>
                                <td>Actualización masiva de estados (Async)</td>
                                <td>Solo sus leads</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-primary">GET</span></td>
                                <td><code>/api/crm/jobs/{id}</code></td>
                                <td>Consultar estado de tarea asíncrona</td>
                                <td>Solo sus tareas</td>
                            </tr>
                        </tbody>
                    </table>

                    <h5 class="mt-4"><i class="bi bi-list-check"></i> Ejemplo 1: Listar Leads del Cliente</h5>
                    <div class="card border-info mb-3">
                        <div class="card-header bg-info text-white">
                            Request
                        </div>
                        <div class="card-body">
                            <pre class="bg-dark text-light p-3"><code>GET <?= RUTA_URL ?>api/crm/leads?estado=new&page=1&limit=10
Authorization: Bearer [JWT_TOKEN]

// Parámetros opcionales:
// - estado: new, contacted, qualified, converted, rejected
// - fecha_desde: 2026-01-01
// - fecha_hasta: 2026-01-31
// - page: número de página (default: 1)
// - limit: resultados por página (default: 50, max: 100)</code></pre>
                        </div>
                    </div>

                    <div class="card border-success mb-3">
                        <div class="card-header bg-success text-white">
                            Response
                        </div>
                        <div class="card-body">
                            <pre class="bg-dark text-light p-3"><code>{
  "success": true,
  "total": 25,
  "page": 1,
  "limit": 10,
  "leads": [
    {
      "id": 123,
      "proveedor_lead_id": "fb_lead_98765",
      "nombre": "María González",
      "email": "maria@example.com",
      "telefono": "+502 5555-1234",
      "estado_actual": "new",
      "origen": "Facebook Ads",
      "created_at": "2026-01-02 10:30:00"
    }
  ]
}</code></pre>
                        </div>
                    </div>

                    <h5 class="mt-4"><i class="bi bi-pencil-square"></i> Ejemplo 2: Actualizar Estado de un Lead</h5>
                    <p>Cuando el cliente (distribuidor) contacta al lead y quiere actualizar su estado:</p>

                    <div class="card border-warning mb-3">
                        <div class="card-header bg-warning text-dark">
                            Request
                        </div>
                        <div class="card-body">
                            <pre class="bg-dark text-light p-3"><code>POST <?= RUTA_URL ?>api/crm/leads/123/estado
Authorization: Bearer [JWT_TOKEN]
Content-Type: application/json

{
  "estado": "qualified",
  "observaciones": "Cliente muy interesado, listo para cierre."
}</code></pre>
                        </div>
                    </div>

                   <div class="card border-success mb-3">
                        <div class="card-header bg-success text-white">
                            Response
                        </div>
                        <div class="card-body">
                            <pre class="bg-dark text-light p-3"><code>{
  "success": true,
  "message": "Estado actualizado a qualified",
  "estado_anterior": "contacted",
  "estado_nuevo": "qualified"
}</code></pre>
                        </div>
                    </div>

                    <div class="alert alert-info mt-3">
                        <i class="bi bi-bell"></i> <strong>Notificación Automática:</strong><br>
                        El sistema actualizará el estado del lead para que puedas dar seguimiento.
                    </div>

                    <h5 class="mt-4"><i class="bi bi-tags"></i> Estados Válidos para Pedidos/Leads</h5>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> <strong>Importante:</strong> Estos estados representan el ciclo de vida de un pedido/orden en el sistema de paquetería. 
                        El cliente tiene <strong>total flexibilidad</strong> para cambiar a cualquier estado según su necesidad.
                    </div>
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Estado</th>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th>Cuándo usar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge bg-secondary">EN_ESPERA</span></td>
                                <td><code>EN_ESPERA</code></td>
                                <td>Pedido recibido, esperando aprobación</td>
                                <td>Estado inicial cuando se crea el pedido</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-success">APROBADO</span></td>
                                <td><code>APROBADO</code></td>
                                <td>Pedido aprobado y validado</td>
                                <td>Después de revisar y aprobar el pedido</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-primary">CONFIRMADO</span></td>
                                <td><code>CONFIRMADO</code></td>
                                <td>Pedido confirmado con el cliente</td>
                                <td>Cliente confirmó que procede con el pedido</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-warning text-dark">EN_TRANSITO</span></td>
                                <td><code>EN_TRANSITO</code></td>
                                <td>Paquete en camino al destino</td>
                                <td>El paquete salió y está siendo transportado</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-info">EN_BODEGA</span></td>
                                <td><code>EN_BODEGA</code></td>
                                <td>Paquete llegó a bodega/almacén</td>
                                <td>Paquete recibido y almacenado</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-danger">CANCELADO</span></td>
                                <td><code>CANCELADO</code></td>
                                <td>Pedido cancelado</td>
                                <td>Pedido no procede por cualquier razón</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> <strong>Aliases Permitidos:</strong><br>
                        El sistema acepta variaciones del nombre y las normaliza automáticamente:
                        <ul class="mb-0 mt-2">
                            <li><code>"approved"</code>, <code>"aprovado"</code> → <code>APROBADO</code></li>
                            <li><code>"cancelled"</code>, <code>"canceled"</code> → <code>CANCELADO</code></li>
                            <li><code>"confirmed"</code> → <code>CONFIRMADO</code></li>
                            <li><code>"pending"</code>, <code>"waiting"</code> → <code>EN_ESPERA</code></li>
                            <li><code>"transit"</code> → <code>EN_TRANSITO</code></li>
                            <li><code>"warehouse"</code> → <code>EN_BODEGA</code></li>
                        </ul>
                    </div>

                    <h5 class="mt-4"><i class="bi bi-shield-check"></i> Permisos</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border-success">
                                <div class="card-header bg-success text-white">✅ Sí puede</div>
                                <div class="card-body"><ul class="mb-0">
                                    <li>Ver solo sus leads</li>
                                    <li>Actualizar estado</li>
                                    <li>Agregar observaciones</li>
                                </ul></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-danger">
                                <div class="card-header bg-danger text-white">❌ No puede</div>
                                <div class="card-body"><ul class="mb-0">
                                    <li>Ver leads ajenos</li>
                                    <li>Eliminar leads</li>
                                    <li>Crear leads</li>
                                </ul></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- 5. Worker CLI -->
    <div class="row mb-4" id="worker">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h4 class="mb-0">5. Worker CLI - Procesamiento en Segundo Plano</h4>
                </div>
                <div class="card-body">
                    <h5><i class="bi bi-terminal"></i> ¿Qué hace el Worker?</h5>
                    <p>El worker es un script PHP que se ejecuta continuamente y se encarga de:</p>
                    <ul>
                        <li>Procesar mensajes pendientes en <code>crm_inbox</code></li>
                    </ul>

                    <h5 class="mt-4"><i class="bi bi-play-circle"></i> Cómo Ejecutar el Worker</h5>
                    
                    <div class="card border-dark mb-3">
                        <div class="card-header">Opción 1: Ejecución Manual (Desarrollo)</div>
                        <div class="card-body">
                            <pre class="bg-dark text-light p-3"><code>cd /ruta/a/tu/proyecto
php cli/crm_worker.php</code></pre>
                            <p class="mb-0 text-muted">El worker se ejecutará indefinidamente. Presiona Ctrl+C para detener.</p>
                        </div>
                    </div>

                    <div class="card border-dark mb-3">
                        <div class="card-header">Opción 2: Supervisor (Producción Linux)</div>
                        <div class="card-body">
                            <p>Crea archivo <code>/etc/supervisor/conf.d/crm_worker.conf</code>:</p>
                            <pre class="bg-dark text-light p-3"><code>[program:crm_worker]
command=php /var/www/App/cli/crm_worker.php
directory=/var/www/App
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/crm_worker.log</code></pre>
                            <p>Luego ejecuta:</p>
                            <pre class="bg-dark text-light p-3"><code>sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start crm_worker</code></pre>
                        </div>
                    </div>

                    <div class="card border-dark mb-3">
                        <div class="card-header">Opción 3: Systemd Service (Linux)</div>
                        <div class="card-body">
                            <p>Crea archivo <code>/etc/systemd/system/crm-worker.service</code>:</p>
                            <pre class="bg-dark text-light p-3"><code>[Unit]
Description=CRM Worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/App
ExecStart=/usr/bin/php /var/www/App/cli/crm_worker.php
Restart=always

[Install]
WantedBy=multi-user.target</code></pre>
                            <p>Luego ejecuta:</p>
                            <pre class="bg-dark text-light p-3"><code>sudo systemctl daemon-reload
sudo systemctl enable crm-worker
sudo systemctl start crm-worker
sudo systemctl status crm-worker</code></pre>
                        </div>
                    </div>

                    <h5 class="mt-4"><i class="bi bi-activity"></i> Monitorear el Worker</h5>
                    <p>Desde la interfaz web: <a href="<?= RUTA_URL ?>crm/monitor" class="btn btn-sm btn-primary">
                        <i class="bi bi-activity"></i> Ir al Monitor
                    </a></p>
                </div>
            </div>
        </div>
    </div>

    <!-- 6. Casos de Uso -->
    <div class="row mb-4" id="casos-uso">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h4 class="mb-0">6. Casos de Uso Comunes</h4>
                </div>
                <div class="card-body">
                    <div class="accordion" id="casosUsoAccordion">
                        <!-- Caso 1 -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#caso1">
                                    <i class="bi bi-1-circle me-2"></i> Recibir leads desde formulario web externo
                                </button>
                            </h2>
                            <div id="caso1" class="accordion-collapse collapse show" data-bs-parent="#casosUsoAccordion">
                                <div class="accordion-body">
                                    <p><strong>Escenario:</strong> Tienes un sitio web con formulario que debe enviar leads a tu sistema.</p>
                                    <ol>
                                        <li>Crea un usuario "Web Form" con rol Proveedor</li>
                                        <li>Obtén su token JWT (vía login)</li>
                                        <li>Configura tu formulario para que al enviar, haga POST a:<br>
                                            <code><?= RUTA_URL ?>api/crm/leads</code>
                                        </li>
                                        <li>Incluye el header <code>Authorization: Bearer [JWT]</code></li>
                                    </ol>
                                </div>
                            </div>
                        </div>

                        <!-- Caso 3 -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#caso3">
                                    <i class="bi bi-3-circle me-2"></i> Múltiples fuentes de leads
                                </button>
                            </h2>
                            <div id="caso3" class="accordion-collapse collapse" data-bs-parent="#casosUsoAccordion">
                                <div class="accordion-body">
                                    <p><strong>Escenario:</strong> Recibes leads de Facebook Ads, Google Ads y tu sitio web.</p>
                                    <ol>
                                        <li>Crea una integración para cada fuente:
                                            <ul>
                                                <li>Integración "Facebook Ads"</li>
                                                <li>Integración "Google Ads"</li>
                                                <li>Integración "Sitio Web"</li>
                                            </ul>
                                        </li>
                                        <li>Cada una tiene su propio API Key y Secret Key</li>
                                        <li>Todos los leads llegan al mismo sistema centralizado</li>
                                        <li>En la lista de leads, verás de qué integración vino cada uno</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 7. Troubleshooting -->
    <div class="row mb-4" id="troubleshooting">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h4 class="mb-0">7. Troubleshooting - Problemas Comunes</h4>
                </div>
                <div class="card-body">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Problema</th>
                                <th>Causa</th>
                                <th>Solución</th>
                            </tr>
                        </thead>
                        <tbody>
                                    <ul class="mb-0">
                                        <li>Verifica que el worker esté corriendo: <code>ps aux | grep crm_worker</code></li>
                                        <li>Revisa logs del worker</li>
                                        <li>Reinicia el worker manualmente</li>
                                    </ul>
                                </td>
                            </tr>
                            <tr>
                                <td>Datos incompletos en leads</td>
                                <td>Payload no coincide con estructura esperada</td>
                                <td>
                                    <ul class="mb-0">
                                        <li>Revisa el payload en la tabla <code>crm_inbox</code></li>
                                        <li>Asegúrate que incluya campos: nombre, email, teléfono</li>
                                        <li>Ajusta el sistema externo para enviar todos los campos</li>
                                    </ul>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="alert alert-info mt-3">
                        <i class="bi bi-lightbulb"></i> <strong>Tip:</strong>
                        Usa el <a href="<?= RUTA_URL ?>crm/monitor" class="alert-link">Monitor de Worker</a> para ver en tiempo real
                        el estado del inbox.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 8. Mejores Prácticas -->
    <div class="row mb-4" id="mejores-practicas">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">8. Mejores Prácticas</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5><i class="bi bi-check-circle"></i> Recomendaciones</h5>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">
                                    <strong>Monitoreo:</strong> Revisa regularmente el monitor de worker para detectar problemas
                                </li>
                                <li class= "list-group-item">
                                    <strong>Logs:</strong> Configura rotación de logs para evitar que crezcan indefinidamente
                                </li>
                                <li class="list-group-item">
                                    <strong>Backups:</strong> Respalda las tablas del CRM periódicamente
                                </li>
                                <li class="list-group-item">
                                    <strong>Testing:</strong> Prueba nuevas integraciones en ambiente de desarrollo primero
                                </li>
                                <li class="list-group-item">
                                    <strong>Documentación:</strong> Documenta el propósito de cada integración
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h5><i class="bi bi-graph-up"></i> Escalabilidad</h5>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">
                                    <strong>Múltiples Workers:</strong> Puedes ejecutar varios workers en paralelo
                                </li>
                                <li class="list-group-item">
                                    <strong>Índices DB:</strong> Asegúrate que haya índices en campos status y created_at
                                </li>
                                <li class="list-group-item">
                                    <strong>Limpieza:</strong> Elimina registros antiguos procesados/enviados periódicamente
                                </li>
                                <li class="list-group-item">
                                    <strong>Cache:</strong> Considera usar Redis para caché de integraciones activas
                                </li>
                                <li class="list-group-item">
                                    <strong>Queue:</strong> Para alto volumen, migra a un sistema de colas (RabbitMQ, etc.)
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="alert alert-success mt-4">
                        <i class="bi bi-trophy"></i> <strong>¡Listo!</strong>
                        Ya conoces el funcionamiento completo del Sistema CRM Relay. Para más información técnica,
                        consulta la <a href="<?= RUTA_URL ?>api/doc/crmdoc.php" class="alert-link">documentación de la API</a>.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Botones de navegación -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between">
                <a href="<?= RUTA_URL ?>crm/dashboard" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Volver al Dashboard CRM
                </a>
                <a href="<?= RUTA_URL ?>api/doc/crmdoc.php" class="btn btn-primary" target="_blank">
                    <i class="bi bi-book"></i> Ver Documentación API
                </a>
            </div>
        </div>
    </div>
</div>

<style>
    .timeline .card {
        margin-left: 20px;
        border-left: 4px solid;
    }
    
    .timeline .card.border-primary {
        border-left-color: #0d6efd !important;
    }
    
    .timeline .card.border-warning {
        border-left-color: #ffc107 !important;
    }
    
    .timeline .card.border-info {
        border-left-color: #0dcaf0 !important;
    }
    
    .timeline .card.border-success {
        border-left-color: #198754 !important;
    }
    
    pre {
        border-radius: 5px;
    }
    
    .card-header h4, .card-header h5 {
        margin-bottom: 0;
    }
    
    /* Smooth scroll */
    html {
        scroll-behavior: smooth;
    }
    
    /* Highlight on scroll */
    :target {
        scroll-margin-top: 100px;
    }
</style>

<?php 
include("vista/includes/footer.php");
?>
