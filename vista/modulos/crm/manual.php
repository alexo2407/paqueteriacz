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
                                <li><a href="#integraciones" class="text-decoration-none"><i class="bi bi-chevron-right"></i> 4. Integraciones Externas</a></li>
                                <li><a href="#api" class="text-decoration-none"><i class="bi bi-chevron-right"></i> 5. API REST (Proveedores)</a></li>
                                <li><a href="#api-clientes" class="text-decoration-none"><i class="bi bi-chevron-right"></i> 5.5. API para Clientes</a></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li><a href="#worker" class="text-decoration-none"><i class="bi bi-chevron-right"></i> 6. Worker CLI</a></li>
                                <li><a href="#seguridad" class="text-decoration-none"><i class="bi bi-chevron-right"></i> 7. Seguridad</a></li>
                                <li><a href="#casos-uso" class="text-decoration-none"><i class="bi bi-chevron-right"></i> 8. Casos de Uso</a></li>
                                <li><a href="#troubleshooting" class="text-decoration-none"><i class="bi bi-chevron-right"></i> 9. Troubleshooting</a></li>
                                <li><a href="#mejores-practicas" class="text-decoration-none"><i class="bi bi-chevron-right"></i> 10. Mejores Prácticas</a></li>
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
                        <li class="list-group-item"><i class="bi bi-check-circle text-success"></i> <strong>Recepción de Leads:</strong> Recibe leads desde CRMs externos vía webhooks</li>
                        <li class="list-group-item"><i class="bi bi-check-circle text-success"></i> <strong>Envío de Actualizaciones:</strong> Sincroniza cambios de estado hacia los CRMs externos</li>
                        <li class="list-group-item"><i class="bi bi-check-circle text-success"></i> <strong>Autenticación Segura:</strong> HMAC-SHA256 para validar todas las comunicaciones</li>
                        <li class="list-group-item"><i class="bi bi-check-circle text-success"></i> <strong>Worker Asíncrono:</strong> Procesamiento en segundo plano de mensajes</li>
                        <li class="list-group-item"><i class="bi bi-check-circle text-success"></i> <strong>Integraciones Ilimitadas:</strong> Conecta múltiples CRMs simultáneamente</li>
                        <li class="list-group-item"><i class="bi bi-check-circle text-success"></i> <strong>Trazabilidad Completa:</strong> Registro detallado de todas las operaciones</li>
                    </ul>

                    <div class="alert alert-info mt-4">
                        <i class="bi bi-info-circle"></i> <strong>Nota:</strong> 
                        Este sistema está diseñado para empresas que necesitan centralizar leads provenientes 
                        de múltiples fuentes y mantener sincronizados sus estados.
                    </div>
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

                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-warning">
                                <div class="card-header bg-warning text-dark">
                                    <strong>3. CRM Outbox</strong>
                                </div>
                                <div class="card-body">
                                    <p><strong>Propósito:</strong> Envía actualizaciones a sistemas externos</p>
                                    <p><strong>Tabla:</strong> <code>crm_outbox</code></p>
                                    <p><strong>Estados:</strong></p>
                                    <ul>
                                        <li><span class="badge bg-warning">pending</span> - Pendiente de envío</li>
                                        <li><span class="badge bg-success">sent</span> - Enviado exitosamente</li>
                                        <li><span class="badge bg-danger">failed</span> - Error al enviar</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-info">
                                <div class="card-header bg-info text-white">
                                    <strong>4. CRM Integrations</strong>
                                </div>
                                <div class="card-body">
                                    <p><strong>Propósito:</strong> Configura webhooks para usuarios del sistema</p>
                                    <p><strong>Tabla:</strong> <code>crm_integrations</code></p>
                                    <p><strong>Modelo:</strong> Cada integración está vinculada a un <strong>usuario</strong> del sistema</p>
                                    <p><strong>Campos:</strong></p>
                                    <ul>
                                        <li><code>user_id</code> - ID del usuario propietario</li>
                                        <li><code>kind</code> - Tipo: 'proveedor' o 'cliente'</li>
                                        <li><code>webhook_url</code> - URL destino para webhooks salientes</li>
                                        <li><code>secret</code> - Clave para firmar webhooks (HMAC)</li>
                                        <li><code>is_active</code> - Estado activo/inactivo</li>
                                    </ul>
                                    <div class="alert alert-info mb-0">
                                        <small><i class="bi bi-info-circle"></i> Un usuario puede tener dos integraciones: una como 'proveedor' y otra como 'cliente'.</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h5 class="mt-4"><i class="bi bi-gear"></i> Worker CLI</h5>
                    <div class="alert alert-dark">
                        <p><strong>Script:</strong> <code>cli/crm_worker.php</code></p>
                        <p><strong>Función:</strong> Procesa mensajes de inbox y outbox en segundo plano</p>
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

                    <h5><i class="bi bi-arrow-up-circle"></i> Flujo de Salida (Tu Sistema → CRM Externo)</h5>
                    
                    <div class="timeline">
                        <div class="card mb-3 border-primary">
                            <div class="card-header bg-primary text-white">
                                <strong>Paso 1:</strong> Usuario cambia estado del lead
                            </div>
                            <div class="card-body">
                                <p>Desde la interfaz web (<code>crm/editar</code>) el usuario actualiza:</p>
                                <ul class="mb-0">
                                    <li>Estado del lead (new → contacted → qualified → converted/rejected)</li>
                                    <li>Notas o comentarios</li>
                                </ul>
                            </div>
                        </div>

                        <div class="card mb-3 border-warning">
                            <div class="card-header bg-warning text-dark">
                                <strong>Paso 2:</strong> Se crea mensaje en Outbox
                            </div>
                            <div class="card-body">
                                <p>El sistema crea automáticamente un registro en <code>crm_outbox</code>:</p>
                                <ul class="mb-0">
                                    <li>lead_id (ID del lead actualizado)</li>
                                    <li>integration_id (integración del lead)</li>
                                    <li>payload (datos a enviar en JSON)</li>
                                    <li>status = <span class="badge bg-warning">pending</span></li>
                                </ul>
                            </div>
                        </div>

                        <div class="card mb-3 border-success">
                            <div class="card-header bg-success text-white">
                                <strong>Paso 3:</strong> Worker envía actualización
                            </div>
                            <div class="card-body">
                                <p><strong>El worker periódicamente:</strong></p>
                                <ol>
                                    <li>Busca mensajes con status = <span class="badge bg-warning">pending</span> en outbox</li>
                                    <li>Obtiene la URL del webhook de la integración</li>
                                    <li>Firma el payload con HMAC-SHA256 usando el Secret Key</li>
                                    <li>Envía POST al webhook externo con headers:
                                        <pre class="bg-light p-2 mt-2"><code>Content-Type: application/json
X-API-Key: [integration_api_key]
X-Signature: [hmac_signature]</code></pre>
                                    </li>
                                    <li>Si respuesta exitosa (200-299) → actualiza a <span class="badge bg-success">sent</span></li>
                                    <li>Si error → incrementa retry_count, actualiza a <span class="badge bg-danger">failed</span></li>
                                </ol>
                            </div>
                        </div>

                        <div class="card mb-3 border-info">
                            <div class="card-header bg-info text-white">
                                <strong>Paso 4:</strong> CRM Externo recibe actualización
                            </div>
                            <div class="card-body">
                                <p>El CRM externo recibe:</p>
                                <pre class="bg-light p-2"><code>{
  "external_id": "lead_12345",
  "status": "contacted",
  "updated_at": "2026-01-01T16:30:00Z",
  "notas": "Cliente contactado por teléfono"
}</code></pre>
                                <p class="mb-0">El CRM externo debe validar la firma HMAC para garantizar autenticidad.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<!-- 3.3. Flujo desde Cliente (vía API) -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-success">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-diagram-2"></i> 3.3. Flujo Alternativo: Cliente Actualiza Lead vía API
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> <strong>Escenario:</strong><br>
                    Un <strong>distribuidor/cliente</strong> recibe leads asignados y puede actualizarlos desde su
                    propio sistema usando la API,
                    sin necesidad de entrar a la interfaz web.
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
                            <strong>Paso 4:</strong> Sistema crea notificación en Outbox
                        </div>
                        <div class="card-body">
                            <p>Automáticamente, crea registro en <code>crm_outbox</code> para notificar al proveedor.
                            </p>
                        </div>
                    </div>

                    <div class="card mb-3 border-dark">
                        <div class="card-header bg-dark text-white">
                            <strong>Paso 5:</strong> Proveedor recibe notificación
                        </div>
                        <div class="card-body">
                            <p>Si el proveedor configuró webhook, recibe la actualización vía HMAC.</p>
                        </div>
                    </div>
                </div>

                <div class="alert alert-success mt-3">
                    <i class="bi bi-check-circle"></i> <strong>Ventajas:</strong>
                    Cliente gestiona leads sin entrar al sistema web, con notificación automática al proveedor.
                </div>
            </div>
        </div>
    </div>
</div>
    <!-- 4. Integraciones Externas -->
    <div class="row mb-4" id="integraciones">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">4. Integraciones Externas</h4>
                </div>
                <div class="card-body">
                    <h5><i class="bi bi-plus-circle"></i> Configurar una Integración</h5>
                    <p class="lead">El sistema CRM vincula webhook integrations a <strong>usuarios</strong> del sistema con rol Proveedor.</p>
                    
                    <ol>
                        <li class="mb-3">
                            <strong>Paso 1: Crear Usuario Proveedor</strong>
                            <p>Si aún no existe, crea un usuario en el sistema con rol <span class="badge bg-primary">Proveedor</span>.</p>
                            <p class="text-muted">Ruta: <code><?= RUTA_URL ?>usuarios/crear</code></p>
                            <div class="alert alert-info">
                                <small><i class="bi bi-info-circle"></i> Este usuario se usará para autenticar las llamadas API mediante JWT.</small>
                            </div>
                        </li>
                        
                        <li class="mb-3">
                            <strong>Paso 2: Configurar Webhook de Integración</strong>
                            <p>Si necesitas recibir actualizaciones de vuelta (webhooks salientes), configura la integración para ese usuario.</p>
                            <p class="text-muted">Ruta: <code><?= RUTA_URL ?>crm/integraciones/crear</code></p>
                            <table class="table table-bordered mt-2">
                                <tr>
                                    <th>Campo</th>
                                    <th>Descripción</th>
                                    <th>Ejemplo</th>
                                </tr>
                                <tr>
                                    <td><strong>Usuario</strong></td>
                                    <td>Usuario propietario de la integración</td>
                                    <td>Seleccionar del dropdown</td>
                                </tr>
                                <tr>
                                    <td><strong>Tipo (Kind)</strong></td>
                                    <td>Rol de la integración</td>
                                    <td>'proveedor' o 'cliente'</td>
                                </tr>
                                <tr>
                                    <td><strong>Webhook URL</strong></td>
                                    <td>URL donde enviar actualizaciones (webhooks salientes)</td>
                                    <td>https://api.externo.com/webhooks/leads</td>
                                </tr>
                                <tr>
                                    <td><strong>Secret</strong></td>
                                    <td>Clave para firmar webhooks salientes (HMAC)</td>
                                    <td>secret_key_abc123</td>
                                </tr>
                                <tr>
                                    <td><strong>Activo</strong></td>
                                    <td>Estado de la integración</td>
                                    <td>✓ Marcado</td>
                                </tr>
                            </table>
                            <div class="alert alert-warning">
                                <small><i class="bi bi-exclamation-triangle"></i> <strong>Nota:</strong> El Secret se usa solo para <strong>webhooks salientes</strong>. Los webhooks entrantes usan autenticación JWT.</small>
                            </div>
                        </li>

                        <li class="mb-3">
                            <strong>Paso 3: Obtener JWT del Usuario</strong>
                            <p>El usuario proveedor debe autenticarse para obtener un token JWT:</p>
                            <div class="bg-light p-3 border rounded">
                                <code>POST <?= RUTA_URL ?>api/auth/login</code>
                                <pre class="mb-0 mt-2">{
  "email": "proveedor@example.com",
  "password": "password_seguro"
}</pre>
                            </div>
                            <p class="text-muted mt-2"><small>Response: <code>{"success": true, "token": "eyJ0eXAi..."}</code></small></p>
                        </li>

                        <li class="mb-3">
                            <strong>Paso 4: Configurar el Sistema Externo</strong>
                            <p>Proporciona al sistema externo la siguiente información para enviar leads:</p>
                            <div class="alert alert-primary">
                                <p><strong>Endpoint para enviar leads:</strong></p>
                                <code><?= RUTA_URL ?>api/crm/leads</code>
                                <p class="mt-2"><strong>Headers requeridos:</strong></p>
                                <pre><code>Content-Type: application/json
Authorization: Bearer [JWT_TOKEN_DEL_USUARIO]</code></pre>
                                <p class="mt-2"><strong>Payload ejemplo:</strong></p>
                                <pre><code>{
  "lead": {
    "proveedor_lead_id": "ext_lead_123",
    "fecha_hora": "2026-01-02T10:30:00Z",
    "nombre": "Cliente Ejemplo",
    "email": "cliente@example.com", "telefono": "+502 1234-5678"
  }
}</code></pre>
                            </div>
                        </li>
                    </ol>

                    <div class="alert alert-success mt-4">
                        <i class="bi bi-check-circle"></i> <strong>¡Listo!</strong>
                        El sistema externo ya puede enviar leads usando JWT. Si configuraste un webhook saliente,
                        recibirás actualizaciones firmadas con HMAC cuando cambien los estados de los leads.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. API REST -->
    <div class="row mb-4" id="api">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h4 class="mb-0">5. API REST - Endpoints Disponibles</h4>
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
                                <td><code>/api/crm/metrics</code></td>
                                <td>Obtener métricas y estadísticas</td>
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

    <!-- 5.5. API para Clientes -->
    <div class="row mb-4" id="api-clientes">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">5.5. API para Usuarios Cliente - Gestión de Leads</h4>
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
                        Cuando un cliente actualiza el estado, el sistema <strong>automáticamente notifica al proveedor</strong> 
                        mediante webhook (si está configurado).
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
    <!-- 6. Worker CLI -->
    <div class="row mb-4" id="worker">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h4 class="mb-0">6. Worker CLI - Procesamiento en Segundo Plano</h4>
                </div>
                <div class="card-body">
                    <h5><i class="bi bi-terminal"></i> ¿Qué hace el Worker?</h5>
                    <p>El worker es un script PHP que se ejecuta continuamente y se encarga de:</p>
                    <ul>
                        <li>Procesar mensajes pendientes en <code>crm_inbox</code></li>
                        <li>Enviar actualizaciones pendientes en <code>crm_outbox</code></li>
                        <li>Reintentar envíos fallidos (con backoff exponencial)</li>
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

    <!-- 7. Seguridad -->
    <div class="row mb-4" id="seguridad">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-danger text-white">
                    <h4 class="mb-0">7. Seguridad</h4>
                </div>
                <div class="card-body">
                    <h5><i class="bi bi-shield-check"></i> Autenticación HMAC-SHA256</h5>
                    <p>Todas las comunicaciones entre tu sistema y los CRMs externos están protegidas con HMAC:</p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border-success mb-3">
                                <div class="card-header bg-success text-white">
                                    ✓ Ventajas
                                </div>
                                <div class="card-body">
                                    <ul class="mb-0">
                                        <li>Verifica identidad del remitente</li>
                                        <li>Garantiza integridad del mensaje</li>
                                        <li>Previene ataques man-in-the-middle</li>
                                        <li>No requiere HTTPS (pero se recomienda)</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-warning mb-3">
                                <div class="card-header bg-warning text-dark">
                                    ⚠ Mejores Prácticas
                                </div>
                                <div class="card-body">
                                    <ul class="mb-0">
                                        <li>Usa HTTPS siempre en producción</li>
                                        <li>Rota las Secret Keys periódicamente</li>
                                        <li>No compartas las Secret Keys por email</li>
                                        <li>Monitorea intentos de autenticación fallidos</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h5 class="mt-4"><i class="bi bi-lock"></i> Control de Acceso</h5>
                    <ul>
                        <li><strong>Roles:</strong> Solo usuarios con rol <code>Admin</code> pueden acceder al módulo CRM</li>
                        <li><strong>API:</strong> Cada integración tiene su propio API Key y Secret Key único</li>
                        <li><strong>Auditoría:</strong> Todas las operaciones se registran con timestamp y usuario</li>
                    </ul>

                    <div class="alert alert-danger mt-3">
                        <i class="bi bi-exclamation-octagon"></i> <strong>Nunca:</strong>
                        <ul class="mb-0">
                            <li>Commits Secret Keys en el repositorio</li>
                            <li>Compartas API Keys por canales inseguros</li>
                            <li>Desactives la validación HMAC</li>
                            <li>Uses HTTP en producción</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 8. Casos de Uso -->
    <div class="row mb-4" id="casos-uso">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h4 class="mb-0">8. Casos de Uso Comunes</h4>
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
                                        <li>Crea una integración llamada "Web Form"</li>
                                        <li>Anota el API Key y Secret Key generados</li>
                                        <li>Configura tu formulario para que al enviar, haga POST a:<br>
                                            <code><?= RUTA_URL ?>api/crm/leads</code>
                                        </li>
                                        <li>Incluye los headers HMAC en el POST</li>
                                        <li>El worker procesará automáticamente el lead</li>
                                    </ol>
                                </div>
                            </div>
                        </div>

                        <!-- Caso 2 -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#caso2">
                                    <i class="bi bi-2-circle me-2"></i> Sincronizar con HubSpot
                                </button>
                            </h2>
                            <div id="caso2" class="accordion-collapse collapse" data-bs-parent="#casosUsoAccordion">
                                <div class="accordion-body">
                                    <p><strong>Escenario:</strong> Quieres que HubSpot te envíe leads y recibir actualizaciones de vuelta.</p>
                                    <ol>
                                        <li>Crea integración "HubSpot CRM"</li>
                                        <li>Webhook URL: endpoint de HubSpot para recibir actualizaciones</li>
                                        <li>En HubSpot, configura webhook para enviar leads a tu endpoint</li>
                                        <li>Cuando cambies estado en tu sistema, se enviará automáticamente a HubSpot</li>
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

    <!-- 9. Troubleshooting -->
    <div class="row mb-4" id="troubleshooting">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h4 class="mb-0">9. Troubleshooting - Problemas Comunes</h4>
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
                            <tr>
                                <td>Error 401 al recibir webhook</td>
                                <td>Firma HMAC inválida</td>
                                <td>
                                    <ul class="mb-0">
                                        <li>Verifica que estés usando el Secret Key correcto</li>
                                        <li>Asegúrate de firmar el payload completo</li>
                                        <li>Revisa que no haya espacios extras en los headers</li>
                                    </ul>
                                </td>
                            </tr>
                            <tr>
                                <td>Leads no se procesan</td>
                                <td>Worker no está ejecutándose</td>
                                <td>
                                    <ul class="mb-0">
                                        <li>Verifica que el worker esté corriendo: <code>ps aux | grep crm_worker</code></li>
                                        <li>Revisa logs del worker</li>
                                        <li>Reinicia el worker manualmente</li>
                                    </ul>
                                </td>
                            </tr>
                            <tr>
                                <td>Mensajes en outbox no se envían</td>
                                <td>URL del webhook incorrecta o CRM caído</td>
                                <td>
                                    <ul class="mb-0">
                                        <li>Verifica la URL en la configuración de la integración</li>
                                        <li>Comprueba que el CRM externo esté disponible</li>
                                        <li>Revisa el campo <code>error_message</code> en outbox</li>
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
                        el estado de inbox y outbox.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 10. Mejores Prácticas -->
    <div class="row mb-4" id="mejores-practicas">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">10. Mejores Prácticas</h4>
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
