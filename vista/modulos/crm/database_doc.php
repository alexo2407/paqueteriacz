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

include("vista/includes/header_materialize.php");
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">
                        <i class="bi bi-database"></i> Documentación Base de Datos CRM y Logística
                    </h3>
                </div>
                <div class="card-body">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="<?= RUTA_URL ?>dashboard">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="<?= RUTA_URL ?>crm/dashboard">CRM</a></li>
                            <li class="breadcrumb-item active">Base de Datos</li>
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
                                <li><a href="#resumen-tablas" class="text-decoration-none"><i class="bi bi-chevron-right"></i> 2. Resumen de Tablas</a></li>
                                <li><a href="#tablas-core" class="text-decoration-none"><i class="bi bi-chevron-right"></i> 3. Tablas Principales</a></li>
                                <li><a href="#tablas-cola" class="text-decoration-none"><i class="bi bi-chevron-right"></i> 4. Tablas de Cola</a></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li><a href="#tablas-integracion" class="text-decoration-none"><i class="bi bi-chevron-right"></i> 5. Tablas de Integración</a></li>
                                <li><a href="#flujo-datos" class="text-decoration-none"><i class="bi bi-chevron-right"></i> 6. Flujo de Datos</a></li>
                                <li><a href="#relaciones" class="text-decoration-none"><i class="bi bi-chevron-right"></i> 7. Relaciones</a></li>
                                <li><a href="#consultas" class="text-decoration-none"><i class="bi bi-chevron-right"></i> 8. Consultas Comunes</a></li>
                                <li><a href="#tablas-logistica" class="text-decoration-none"><i class="bi bi-chevron-right"></i> 9. Tablas de Logística</a></li>
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
                    <h5><i class="bi bi-info-circle"></i> Arquitectura de Base de Datos</h5>
                    <p class="lead">
                        El sistema utiliza 8 tablas especializadas: 7 tablas CRM para gestionar el flujo completo de leads
                        y 1 tabla de logística para gestionar trabajos asíncronos de validación y tracking.
                    </p>

                    <div class="alert alert-info">
                        <i class="bi bi-lightbulb"></i> <strong>Diseño Orientado a Colas:</strong><br>
                        El sistema implementa un patrón de arquitectura basado en colas de mensajes (message queue)
                        para garantizar procesamiento asíncrono, idempotencia y trazabilidad completa.
                    </div>

                    <h5 class="mt-4"><i class="bi bi-check2-circle"></i> Estado de las Tablas</h5>
                    <div class="alert alert-success">
                        <strong>✓ Todas las 8 tablas están en uso activo</strong><br>
                        Cada tabla cumple una función específica: 7 para CRM y 1 para procesamiento logístico asíncrono.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Resumen de Tablas -->
    <div class="row mb-4" id="resumen-tablas">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h4 class="mb-0">2. Resumen de Tablas</h4>
                </div>
                <div class="card-body">
                    <table class="table table-hover table-bordered">
                        <thead class="table-dark">
                            <tr>
                                <th>Tabla</th>
                                <th>Propósito</th>
                                <th>Tipo</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>crm_leads</code></td>
                                <td>Almacena los leads/órdenes principales</td>
                                <td>Core</td>
                                <td><span class="badge bg-success">Activa</span></td>
                            </tr>
                            <tr>
                                <td><code>crm_lead_status_history</code></td>
                                <td>Historial de cambios de estado (auditoría)</td>
                                <td>Core</td>
                                <td><span class="badge bg-success">Activa</span></td>
                            </tr>
                            <tr>
                                <td><code>crm_inbox</code></td>
                                <td>Cola de mensajes entrantes</td>
                                <td>Cola</td>
                                <td><span class="badge bg-success">Activa</span></td>
                            </tr>
                            <tr>
                                <td><code>crm_outbox</code></td>
                                <td>Cola de notificaciones salientes</td>
                                <td>Cola</td>
                                <td><span class="badge bg-success">Activa</span></td>
                            </tr>
                            <tr>
                                <td><code>crm_integrations</code></td>
                                <td>Configuración de webhooks externos</td>
                                <td>Integración</td>
                                <td><span class="badge bg-success">Activa</span></td>
                            </tr>
                            <tr>
                                <td><code>crm_bulk_jobs</code></td>
                                <td>Seguimiento de operaciones masivas</td>
                                <td>Jobs</td>
                                <td><span class="badge bg-success">Activa</span></td>
                            </tr>
                            <tr>
                                <td><code>crm_notifications</code></td>
                                <td>Notificaciones para usuarios</td>
                                <td>Notificaciones</td>
                                <td><span class="badge bg-success">Activa</span></td>
                            </tr>
                            <tr>
                                <td><code>logistics_queue</code></td>
                                <td>Cola de trabajos asíncronos de logística</td>
                                <td>Logística</td>
                                <td><span class="badge bg-success">Activa</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Tablas Principales -->
    <div class="row mb-4" id="tablas-core">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">3. Tablas Principales (Core)</h4>
                </div>
                <div class="card-body">
                    
                    <!-- crm_leads -->
                    <h5><i class="bi bi-table"></i> 3.1. crm_leads</h5>
                    <p><strong>Propósito:</strong> Tabla principal que almacena todos los leads/órdenes del sistema.</p>
                    
                    <h6>Estructura:</h6>
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Campo</th>
                                <th>Tipo</th>
                                <th>Descripción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>id</code></td>
                                <td>INT (PK)</td>
                                <td>Identificador único del lead</td>
                            </tr>
                            <tr>
                                <td><code>proveedor_id</code></td>
                                <td>INT (FK)</td>
                                <td>Usuario proveedor que envió el lead</td>
                            </tr>
                            <tr>
                                <td><code>cliente_id</code></td>
                                <td>INT (FK)</td>
                                <td>Usuario cliente asignado (distribuidor)</td>
                            </tr>
                            <tr>
                                <td><code>proveedor_lead_id</code></td>
                                <td>VARCHAR(120)</td>
                                <td>ID del lead en el sistema del proveedor</td>
                            </tr>
                            <tr>
                                <td><code>fecha_hora</code></td>
                                <td>DATETIME</td>
                                <td>Fecha y hora del lead</td>
                            </tr>
                            <tr>
                                <td><code>nombre</code></td>
                                <td>VARCHAR(255)</td>
                                <td>Nombre del contacto</td>
                            </tr>
                            <tr>
                                <td><code>telefono</code></td>
                                <td>VARCHAR(30)</td>
                                <td>Teléfono del contacto</td>
                            </tr>
                            <tr>
                                <td><code>producto</code></td>
                                <td>VARCHAR(255)</td>
                                <td>Producto o servicio solicitado</td>
                            </tr>
                            <tr>
                                <td><code>precio</code></td>
                                <td>DECIMAL(10,2)</td>
                                <td>Precio del producto/servicio</td>
                            </tr>
                            <tr>
                                <td><code>estado_actual</code></td>
                                <td>ENUM</td>
                                <td>Estado: EN_ESPERA, APROBADO, CONFIRMADO, EN_TRANSITO, EN_BODEGA, CANCELADO</td>
                            </tr>
                            <tr>
                                <td><code>duplicado</code></td>
                                <td>TINYINT(1)</td>
                                <td>Marca si es un lead duplicado</td>
                            </tr>
                            <tr>
                                <td><code>created_at</code></td>
                                <td>TIMESTAMP</td>
                                <td>Fecha de creación</td>
                            </tr>
                            <tr>
                                <td><code>updated_at</code></td>
                                <td>TIMESTAMP</td>
                                <td>Última actualización</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="alert alert-warning mt-3">
                        <i class="bi bi-exclamation-triangle"></i> <strong>Índices:</strong>
                        <ul class="mb-0">
                            <li><code>proveedor_id</code> - Para filtrar leads por proveedor</li>
                            <li><code>cliente_id</code> - Para filtrar leads asignados a cliente</li>
                            <li><code>fecha_hora</code> - Para consultas por rango de fechas</li>
                            <li><code>telefono</code> - Para detección de duplicados</li>
                            <li><code>estado_actual</code> - Para filtros por estado</li>
                            <li><code>created_at</code> - Para ordenamiento cronológico</li>
                        </ul>
                    </div>

                    <hr class="my-4">

                    <!-- crm_lead_status_history -->
                    <h5><i class="bi bi-clock-history"></i> 3.2. crm_lead_status_history</h5>
                    <p><strong>Propósito:</strong> Registra todos los cambios de estado de los leads para auditoría y trazabilidad.</p>
                    
                    <h6>Estructura:</h6>
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Campo</th>
                                <th>Tipo</th>
                                <th>Descripción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>id</code></td>
                                <td>INT (PK)</td>
                                <td>Identificador único del registro</td>
                            </tr>
                            <tr>
                                <td><code>lead_id</code></td>
                                <td>INT (FK)</td>
                                <td>Referencia al lead en crm_leads</td>
                            </tr>
                            <tr>
                                <td><code>estado_anterior</code></td>
                                <td>VARCHAR(50)</td>
                                <td>Estado previo del lead</td>
                            </tr>
                            <tr>
                                <td><code>estado_nuevo</code></td>
                                <td>VARCHAR(50)</td>
                                <td>Nuevo estado del lead</td>
                            </tr>
                            <tr>
                                <td><code>actor_user_id</code></td>
                                <td>INT (FK)</td>
                                <td>Usuario que realizó el cambio</td>
                            </tr>
                            <tr>
                                <td><code>observaciones</code></td>
                                <td>TEXT</td>
                                <td>Notas o comentarios del cambio</td>
                            </tr>
                            <tr>
                                <td><code>created_at</code></td>
                                <td>TIMESTAMP</td>
                                <td>Momento del cambio</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle"></i> <strong>Uso:</strong><br>
                        Esta tabla permite rastrear quién cambió el estado de un lead, cuándo y por qué.
                        Es fundamental para auditorías y resolución de disputas.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Tablas de Cola -->
    <div class="row mb-4" id="tablas-cola">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h4 class="mb-0">4. Tablas de Cola (Message Queue)</h4>
                </div>
                <div class="card-body">
                    
                    <!-- crm_inbox -->
                    <h5><i class="bi bi-inbox"></i> 4.1. crm_inbox</h5>
                    <p><strong>Propósito:</strong> Cola de mensajes entrantes desde proveedores o clientes.</p>
                    
                    <h6>Estructura:</h6>
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Campo</th>
                                <th>Tipo</th>
                                <th>Descripción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>id</code></td>
                                <td>INT (PK)</td>
                                <td>Identificador único del mensaje</td>
                            </tr>
                            <tr>
                                <td><code>source</code></td>
                                <td>ENUM</td>
                                <td>Origen: 'proveedor' o 'cliente'</td>
                            </tr>
                            <tr>
                                <td><code>idempotency_key</code></td>
                                <td>VARCHAR(150)</td>
                                <td>Hash SHA-256 para evitar duplicados</td>
                            </tr>
                            <tr>
                                <td><code>payload</code></td>
                                <td>LONGTEXT</td>
                                <td>Datos JSON del mensaje</td>
                            </tr>
                            <tr>
                                <td><code>status</code></td>
                                <td>ENUM</td>
                                <td>Estado: pending, processed, failed</td>
                            </tr>
                            <tr>
                                <td><code>received_at</code></td>
                                <td>TIMESTAMP</td>
                                <td>Momento de recepción</td>
                            </tr>
                            <tr>
                                <td><code>processed_at</code></td>
                                <td>TIMESTAMP</td>
                                <td>Momento de procesamiento</td>
                            </tr>
                            <tr>
                                <td><code>last_error</code></td>
                                <td>TEXT</td>
                                <td>Último error si falló</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="alert alert-success mt-3">
                        <i class="bi bi-shield-check"></i> <strong>Idempotencia:</strong><br>
                        El campo <code>idempotency_key</code> garantiza que el mismo mensaje no se procese dos veces,
                        incluso si el proveedor lo reenvía.
                    </div>

                    <hr class="my-4">

                    <!-- crm_outbox -->
                    <h5><i class="bi bi-send"></i> 4.2. crm_outbox</h5>
                    <p><strong>Propósito:</strong> Cola de notificaciones salientes hacia proveedores o clientes.</p>
                    
                    <h6>Estructura:</h6>
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Campo</th>
                                <th>Tipo</th>
                                <th>Descripción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>id</code></td>
                                <td>INT (PK)</td>
                                <td>Identificador único</td>
                            </tr>
                            <tr>
                                <td><code>event_type</code></td>
                                <td>ENUM</td>
                                <td>Tipo: SEND_TO_CLIENT, SEND_TO_PROVIDER</td>
                            </tr>
                            <tr>
                                <td><code>lead_id</code></td>
                                <td>INT (FK)</td>
                                <td>Lead relacionado</td>
                            </tr>
                            <tr>
                                <td><code>destination_user_id</code></td>
                                <td>INT (FK)</td>
                                <td>Usuario destinatario</td>
                            </tr>
                            <tr>
                                <td><code>payload</code></td>
                                <td>LONGTEXT</td>
                                <td>Datos JSON a enviar</td>
                            </tr>
                            <tr>
                                <td><code>status</code></td>
                                <td>ENUM</td>
                                <td>Estado: pending, sending, sent, failed</td>
                            </tr>
                            <tr>
                                <td><code>attempts</code></td>
                                <td>INT</td>
                                <td>Número de intentos realizados</td>
                            </tr>
                            <tr>
                                <td><code>max_intentos</code></td>
                                <td>INT</td>
                                <td>Máximo de reintentos (default: 5)</td>
                            </tr>
                            <tr>
                                <td><code>next_retry_at</code></td>
                                <td>DATETIME</td>
                                <td>Próximo reintento programado</td>
                            </tr>
                            <tr>
                                <td><code>last_error</code></td>
                                <td>TEXT</td>
                                <td>Último error si falló</td>
                            </tr>
                            <tr>
                                <td><code>created_at</code></td>
                                <td>TIMESTAMP</td>
                                <td>Fecha de creación</td>
                            </tr>
                            <tr>
                                <td><code>updated_at</code></td>
                                <td>TIMESTAMP</td>
                                <td>Última actualización</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="alert alert-warning mt-3">
                        <i class="bi bi-arrow-repeat"></i> <strong>Reintentos Automáticos:</strong><br>
                        El sistema reintenta envíos fallidos con backoff exponencial hasta alcanzar <code>max_intentos</code>.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Tablas de Integración -->
    <div class="row mb-4" id="tablas-integracion">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">5. Tablas de Integración y Jobs</h4>
                </div>
                <div class="card-body">
                    
                    <!-- crm_integrations -->
                    <h5><i class="bi bi-link-45deg"></i> 5.1. crm_integrations</h5>
                    <p><strong>Propósito:</strong> Configuración de webhooks para integraciones externas.</p>
                    
                    <h6>Estructura:</h6>
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Campo</th>
                                <th>Tipo</th>
                                <th>Descripción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>id</code></td>
                                <td>INT (PK)</td>
                                <td>Identificador único</td>
                            </tr>
                            <tr>
                                <td><code>user_id</code></td>
                                <td>INT (FK)</td>
                                <td>Usuario propietario de la integración</td>
                            </tr>
                            <tr>
                                <td><code>kind</code></td>
                                <td>ENUM</td>
                                <td>Tipo: 'cliente' o 'proveedor'</td>
                            </tr>
                            <tr>
                                <td><code>webhook_url</code></td>
                                <td>VARCHAR(500)</td>
                                <td>URL del webhook externo</td>
                            </tr>
                            <tr>
                                <td><code>secret</code></td>
                                <td>VARCHAR(255)</td>
                                <td>Clave secreta para HMAC</td>
                            </tr>
                            <tr>
                                <td><code>is_active</code></td>
                                <td>TINYINT(1)</td>
                                <td>Estado activo/inactivo</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="alert alert-info mt-3">
                        <i class="bi bi-shield-lock"></i> <strong>Seguridad:</strong><br>
                        El campo <code>secret</code> se usa para firmar los webhooks con HMAC-SHA256,
                        garantizando que los mensajes no sean alterados.
                    </div>

                    <hr class="my-4">

                    <!-- crm_bulk_jobs -->
                    <h5><i class="bi bi-gear-wide-connected"></i> 5.2. crm_bulk_jobs</h5>
                    <p><strong>Propósito:</strong> Seguimiento de operaciones masivas asíncronas.</p>
                    
                    <h6>Estructura:</h6>
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Campo</th>
                                <th>Tipo</th>
                                <th>Descripción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>id</code></td>
                                <td>VARCHAR(50) (PK)</td>
                                <td>UUID del job</td>
                            </tr>
                            <tr>
                                <td><code>user_id</code></td>
                                <td>INT (FK)</td>
                                <td>Usuario que creó el job</td>
                            </tr>
                            <tr>
                                <td><code>lead_ids</code></td>
                                <td>LONGTEXT</td>
                                <td>JSON array de IDs de leads</td>
                            </tr>
                            <tr>
                                <td><code>estado</code></td>
                                <td>VARCHAR(50)</td>
                                <td>Nuevo estado a aplicar</td>
                            </tr>
                            <tr>
                                <td><code>observaciones</code></td>
                                <td>TEXT</td>
                                <td>Observaciones del cambio</td>
                            </tr>
                            <tr>
                                <td><code>status</code></td>
                                <td>ENUM</td>
                                <td>Estado: queued, processing, completed, failed</td>
                            </tr>
                            <tr>
                                <td><code>total_leads</code></td>
                                <td>INT</td>
                                <td>Total de leads a procesar</td>
                            </tr>
                            <tr>
                                <td><code>processed_leads</code></td>
                                <td>INT</td>
                                <td>Leads procesados</td>
                            </tr>
                            <tr>
                                <td><code>successful_leads</code></td>
                                <td>INT</td>
                                <td>Leads actualizados exitosamente</td>
                            </tr>
                            <tr>
                                <td><code>failed_leads</code></td>
                                <td>INT</td>
                                <td>Leads que fallaron</td>
                            </tr>
                            <tr>
                                <td><code>failed_details</code></td>
                                <td>LONGTEXT</td>
                                <td>JSON con detalles de fallos</td>
                            </tr>
                            <tr>
                                <td><code>error_message</code></td>
                                <td>TEXT</td>
                                <td>Mensaje de error general</td>
                            </tr>
                            <tr>
                                <td><code>started_at</code></td>
                                <td>DATETIME</td>
                                <td>Inicio del procesamiento</td>
                            </tr>
                            <tr>
                                <td><code>completed_at</code></td>
                                <td>DATETIME</td>
                                <td>Finalización del job</td>
                            </tr>
                            <tr>
                                <td><code>created_at</code></td>
                                <td>DATETIME</td>
                                <td>Creación del job</td>
                            </tr>
                            <tr>
                                <td><code>updated_at</code></td>
                                <td>DATETIME</td>
                                <td>Última actualización</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="alert alert-success mt-3">
                        <i class="bi bi-speedometer"></i> <strong>Procesamiento Asíncrono:</strong><br>
                        Los jobs se procesan en segundo plano por el worker <code>crm_bulk_worker.php</code>,
                        permitiendo actualizar miles de leads sin bloquear la interfaz.
                    </div>

                    <hr class="my-4">

                    <!-- crm_notifications -->
                    <h5><i class="bi bi-bell"></i> 5.3. crm_notifications</h5>
                    <p><strong>Propósito:</strong> Sistema de notificaciones para usuarios del sistema.</p>
                    
                    <h6>Estructura:</h6>
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Campo</th>
                                <th>Tipo</th>
                                <th>Descripción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>id</code></td>
                                <td>INT (PK)</td>
                                <td>Identificador único</td>
                            </tr>
                            <tr>
                                <td><code>user_id</code></td>
                                <td>INT (FK)</td>
                                <td>Usuario destinatario</td>
                            </tr>
                            <tr>
                                <td><code>type</code></td>
                                <td>VARCHAR(50)</td>
                                <td>Tipo de notificación</td>
                            </tr>
                            <tr>
                                <td><code>event_type</code></td>
                                <td>VARCHAR(50)</td>
                                <td>Tipo de evento</td>
                            </tr>
                            <tr>
                                <td><code>related_lead_id</code></td>
                                <td>INT (FK)</td>
                                <td>Lead relacionado</td>
                            </tr>
                            <tr>
                                <td><code>payload</code></td>
                                <td>LONGTEXT</td>
                                <td>Datos JSON de la notificación</td>
                            </tr>
                            <tr>
                                <td><code>is_read</code></td>
                                <td>TINYINT(1)</td>
                                <td>Estado leído/no leído</td>
                            </tr>
                            <tr>
                                <td><code>created_at</code></td>
                                <td>TIMESTAMP</td>
                                <td>Fecha de creación</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle"></i> <strong>Uso:</strong><br>
                        Las notificaciones se muestran en la interfaz web y pueden consultarse vía API.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 6. Flujo de Datos -->
    <div class="row mb-4" id="flujo-datos">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h4 class="mb-0">6. Flujo de Datos</h4>
                </div>
                <div class="card-body">
                    <h5><i class="bi bi-diagram-3"></i> Flujo Completo de un Lead</h5>
                    
                    <div class="card border-primary mb-3">
                        <div class="card-header bg-primary text-white">
                            <strong>Fase 1: Recepción</strong>
                        </div>
                        <div class="card-body">
                            <ol>
                                <li>Proveedor envía lead vía API → <code>POST /api/crm/leads</code></li>
                                <li>Sistema valida JWT y permisos</li>
                                <li>Se crea registro en <code>crm_inbox</code> con status = <span class="badge bg-warning">pending</span></li>
                                <li>Se genera <code>idempotency_key</code> (hash SHA-256 del payload)</li>
                                <li>API responde inmediatamente con éxito</li>
                            </ol>
                        </div>
                    </div>

                    <div class="card border-info mb-3">
                        <div class="card-header bg-info text-white">
                            <strong>Fase 2: Procesamiento</strong>
                        </div>
                        <div class="card-body">
                            <ol>
                                <li>Worker <code>crm_worker.php</code> busca mensajes pending en inbox</li>
                                <li>Extrae datos del payload JSON</li>
                                <li>Crea lead en <code>crm_leads</code> con estado_actual = <span class="badge bg-secondary">EN_ESPERA</span></li>
                                <li>Actualiza inbox a status = <span class="badge bg-success">processed</span></li>
                                <li>Registra entrada inicial en <code>crm_lead_status_history</code></li>
                            </ol>
                        </div>
                    </div>

                    <div class="card border-warning mb-3">
                        <div class="card-header bg-warning text-dark">
                            <strong>Fase 3: Asignación y Actualización</strong>
                        </div>
                        <div class="card-body">
                            <ol>
                                <li>Admin asigna lead a cliente (distribuidor) → actualiza <code>cliente_id</code></li>
                                <li>Cliente actualiza estado vía web o API</li>
                                <li>Sistema registra cambio en <code>crm_lead_status_history</code></li>
                                <li>Se crea notificación en <code>crm_notifications</code> para el proveedor</li>
                                <li>Si hay webhook configurado, se crea mensaje en <code>crm_outbox</code></li>
                            </ol>
                        </div>
                    </div>

                    <div class="card border-success mb-3">
                        <div class="card-header bg-success text-white">
                            <strong>Fase 4: Notificación</strong>
                        </div>
                        <div class="card-body">
                            <ol>
                                <li>Worker <code>crm_outbox_service.php</code> procesa mensajes en outbox</li>
                                <li>Busca configuración en <code>crm_integrations</code></li>
                                <li>Firma payload con HMAC-SHA256 usando el secret</li>
                                <li>Envía webhook al proveedor</li>
                                <li>Actualiza outbox a status = <span class="badge bg-success">sent</span> o <span class="badge bg-danger">failed</span></li>
                                <li>Si falla, programa reintento con backoff exponencial</li>
                            </ol>
                        </div>
                    </div>

                    <div class="alert alert-dark mt-4">
                        <h6><i class="bi bi-lightning"></i> Operaciones Masivas</h6>
                        <p>Para actualizaciones masivas (bulk updates):</p>
                        <ol class="mb-0">
                            <li>Cliente envía array de lead_ids vía API</li>
                            <li>Sistema crea job en <code>crm_bulk_jobs</code> con UUID único</li>
                            <li>Worker <code>crm_bulk_worker.php</code> procesa el job en background</li>
                            <li>Cliente consulta progreso vía <code>GET /api/crm/jobs/{id}</code></li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 7. Relaciones -->
    <div class="row mb-4" id="relaciones">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h4 class="mb-0">7. Relaciones entre Tablas</h4>
                </div>
                <div class="card-body">
                    <h5><i class="bi bi-diagram-2"></i> Diagrama de Relaciones</h5>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-dark">
                                <tr>
                                <th>Tabla Origen</th>
                                    <th>Campo FK</th>
                                    <th>Tabla Destino</th>
                                    <th>Relación</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>crm_leads</code></td>
                                    <td><code>proveedor_id</code></td>
                                    <td><code>usuarios</code></td>
                                    <td>N:1 (Muchos leads → Un proveedor)</td>
                                </tr>
                                <tr>
                                    <td><code>crm_leads</code></td>
                                    <td><code>cliente_id</code></td>
                                    <td><code>usuarios</code></td>
                                    <td>N:1 (Muchos leads → Un cliente)</td>
                                </tr>
                                <tr>
                                    <td><code>crm_lead_status_history</code></td>
                                    <td><code>lead_id</code></td>
                                    <td><code>crm_leads</code></td>
                                    <td>N:1 (Muchos cambios → Un lead)</td>
                                </tr>
                                <tr>
                                    <td><code>crm_lead_status_history</code></td>
                                    <td><code>actor_user_id</code></td>
                                    <td><code>usuarios</code></td>
                                    <td>N:1 (Muchos cambios → Un usuario)</td>
                                </tr>
                                <tr>
                                    <td><code>crm_outbox</code></td>
                                    <td><code>lead_id</code></td>
                                    <td><code>crm_leads</code></td>
                                    <td>N:1 (Muchas notificaciones → Un lead)</td>
                                </tr>
                                <tr>
                                    <td><code>crm_outbox</code></td>
                                    <td><code>destination_user_id</code></td>
                                    <td><code>usuarios</code></td>
                                    <td>N:1 (Muchas notificaciones → Un usuario)</td>
                                </tr>
                                <tr>
                                    <td><code>crm_integrations</code></td>
                                    <td><code>user_id</code></td>
                                    <td><code>usuarios</code></td>
                                    <td>N:1 (Muchas integraciones → Un usuario)</td>
                                </tr>
                                <tr>
                                    <td><code>crm_bulk_jobs</code></td>
                                    <td><code>user_id</code></td>
                                    <td><code>usuarios</code></td>
                                    <td>N:1 (Muchos jobs → Un usuario)</td>
                                </tr>
                                <tr>
                                    <td><code>crm_notifications</code></td>
                                    <td><code>user_id</code></td>
                                    <td><code>usuarios</code></td>
                                    <td>N:1 (Muchas notificaciones → Un usuario)</td>
                                </tr>
                                <tr>
                                    <td><code>crm_notifications</code></td>
                                    <td><code>related_lead_id</code></td>
                                    <td><code>crm_leads</code></td>
                                    <td>N:1 (Muchas notificaciones → Un lead)</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle"></i> <strong>Nota:</strong><br>
                        Todas las relaciones con <code>usuarios</code> se refieren a la tabla principal de usuarios del sistema,
                        que contiene proveedores, clientes y administradores.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 8. Consultas Comunes -->
    <div class="row mb-4" id="consultas">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">8. Consultas SQL Comunes</h4>
                </div>
                <div class="card-body">
                    
                    <h5><i class="bi bi-code-square"></i> Ejemplos Útiles</h5>

                    <div class="card border-primary mb-3">
                        <div class="card-header bg-primary text-white">
                            <strong>1. Ver leads con su historial completo</strong>
                        </div>
                        <div class="card-body">
                            <pre class="bg-dark text-light p-3"><code>SELECT 
    l.id,
    l.nombre,
    l.estado_actual,
    l.created_at,
    h.estado_anterior,
    h.estado_nuevo,
    h.observaciones,
    h.created_at as fecha_cambio,
    u.nombre as usuario_cambio
FROM crm_leads l
LEFT JOIN crm_lead_status_history h ON l.id = h.lead_id
LEFT JOIN usuarios u ON h.actor_user_id = u.id
WHERE l.id = 123
ORDER BY h.created_at DESC;</code></pre>
                        </div>
                    </div>

                    <div class="card border-info mb-3">
                        <div class="card-header bg-info text-white">
                            <strong>2. Monitorear cola de inbox</strong>
                        </div>
                        <div class="card-body">
                            <pre class="bg-dark text-light p-3"><code>SELECT 
    status,
    COUNT(*) as total,
    MIN(received_at) as mas_antiguo,
    MAX(received_at) as mas_reciente
FROM crm_inbox
GROUP BY status;</code></pre>
                        </div>
                    </div>

                    <div class="card border-warning mb-3">
                        <div class="card-header bg-warning text-dark">
                            <strong>3. Ver notificaciones pendientes de envío</strong>
                        </div>
                        <div class="card-body">
                            <pre class="bg-dark text-light p-3"><code>SELECT 
    o.id,
    o.event_type,
    o.status,
    o.attempts,
    o.next_retry_at,
    l.nombre as lead_nombre,
    u.nombre as destinatario
FROM crm_outbox o
LEFT JOIN crm_leads l ON o.lead_id = l.id
LEFT JOIN usuarios u ON o.destination_user_id = u.id
WHERE o.status IN ('pending', 'failed')
ORDER BY o.created_at ASC
LIMIT 50;</code></pre>
                        </div>
                    </div>

                    <div class="card border-success mb-3">
                        <div class="card-header bg-success text-white">
                            <strong>4. Estadísticas de leads por proveedor</strong>
                        </div>
                        <div class="card-body">
                            <pre class="bg-dark text-light p-3"><code>SELECT 
    u.nombre as proveedor,
    COUNT(*) as total_leads,
    SUM(CASE WHEN l.estado_actual = 'EN_ESPERA' THEN 1 ELSE 0 END) as en_espera,
    SUM(CASE WHEN l.estado_actual = 'APROBADO' THEN 1 ELSE 0 END) as aprobados,
    SUM(CASE WHEN l.estado_actual = 'CONFIRMADO' THEN 1 ELSE 0 END) as confirmados,
    SUM(CASE WHEN l.estado_actual = 'EN_TRANSITO' THEN 1 ELSE 0 END) as en_transito,
    SUM(CASE WHEN l.estado_actual = 'EN_BODEGA' THEN 1 ELSE 0 END) as en_bodega,
    SUM(CASE WHEN l.estado_actual = 'CANCELADO' THEN 1 ELSE 0 END) as cancelados
FROM crm_leads l
JOIN usuarios u ON l.proveedor_id = u.id
WHERE l.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY u.id, u.nombre
ORDER BY total_leads DESC;</code></pre>
                        </div>
                    </div>

                    <div class="card border-dark mb-3">
                        <div class="card-header bg-dark text-white">
                            <strong>5. Ver progreso de jobs masivos</strong>
                        </div>
                        <div class="card-body">
                            <pre class="bg-dark text-light p-3"><code>SELECT 
    id,
    status,
    total_leads,
    processed_leads,
    successful_leads,
    failed_leads,
    ROUND((processed_leads / total_leads) * 100, 2) as progreso_pct,
    TIMESTAMPDIFF(SECOND, started_at, COALESCE(completed_at, NOW())) as duracion_segundos
FROM crm_bulk_jobs
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY created_at DESC;</code></pre>
                        </div>
                    </div>

                    <div class="card border-danger mb-3">
                        <div class="card-header bg-danger text-white">
                            <strong>6. Detectar posibles duplicados</strong>
                        </div>
                        <div class="card-body">
                            <pre class="bg-dark text-light p-3"><code>SELECT 
    telefono,
    COUNT(*) as cantidad,
    GROUP_CONCAT(id) as lead_ids,
    GROUP_CONCAT(nombre) as nombres
FROM crm_leads
WHERE telefono IS NOT NULL 
  AND telefono != ''
  AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY telefono
HAVING COUNT(*) > 1
ORDER BY cantidad DESC;</code></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 9. Tablas de Logística -->
    <div class="row mb-4" id="tablas-logistica">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h4 class="mb-0">9. Tablas de Logística</h4>
                </div>
                <div class="card-body">
                    
                    <!-- logistics_queue -->
                    <h5><i class="bi bi-gear-wide-connected"></i> 9.1. logistics_queue</h5>
                    <p><strong>Propósito:</strong> Cola de trabajos asíncronos para procesamiento de tareas logísticas (validación de direcciones, generación de guías, tracking, etc.).</p>
                    
                    <h6>Estructura:</h6>
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Campo</th>
                                <th>Tipo</th>
                                <th>Descripción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>id</code></td>
                                <td>BIGINT (PK)</td>
                                <td>Identificador único del trabajo</td>
                            </tr>
                            <tr>
                                <td><code>job_type</code></td>
                                <td>VARCHAR(50)</td>
                                <td>Tipo de trabajo: generar_guia, actualizar_tracking, validar_direccion, notificar_estado</td>
                            </tr>
                            <tr>
                                <td><code>pedido_id</code></td>
                                <td>INT (FK)</td>
                                <td>Referencia al pedido asociado</td>
                            </tr>
                            <tr>
                                <td><code>payload</code></td>
                                <td>JSON</td>
                                <td>Datos adicionales necesarios para procesar el trabajo</td>
                            </tr>
                            <tr>
                                <td><code>status</code></td>
                                <td>ENUM</td>
                                <td>Estado: pending, processing, completed, failed</td>
                            </tr>
                            <tr>
                                <td><code>attempts</code></td>
                                <td>INT</td>
                                <td>Número de intentos realizados</td>
                            </tr>
                            <tr>
                                <td><code>max_intentos</code></td>
                                <td>INT</td>
                                <td>Máximo de intentos permitidos (default: 5)</td>
                            </tr>
                            <tr>
                                <td><code>next_retry_at</code></td>
                                <td>TIMESTAMP</td>
                                <td>Fecha/hora del próximo reintento programado</td>
                            </tr>
                            <tr>
                                <td><code>last_error</code></td>
                                <td>TEXT</td>
                                <td>Último mensaje de error capturado</td>
                            </tr>
                            <tr>
                                <td><code>created_at</code></td>
                                <td>TIMESTAMP</td>
                                <td>Fecha de creación del trabajo</td>
                            </tr>
                            <tr>
                                <td><code>updated_at</code></td>
                                <td>TIMESTAMP</td>
                                <td>Última actualización</td>
                            </tr>
                            <tr>
                                <td><code>processed_at</code></td>
                                <td>TIMESTAMP</td>
                                <td>Fecha/hora de procesamiento exitoso</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="alert alert-warning mt-3">
                        <i class="bi bi-database"></i> <strong>Índices:</strong>
                        <ul class="mb-0">
                            <li><code>idx_status</code> - Para filtrar por estado</li>
                            <li><code>idx_job_type</code> - Para filtrar por tipo de trabajo</li>
                            <li><code>idx_pedido</code> - Para buscar trabajos de un pedido</li>
                            <li><code>idx_retry</code> - Para consultas de reintentos (status, next_retry_at)</li>
                            <li><code>idx_composite_processing</code> - Optimizado para workers con SKIP LOCKED</li>
                        </ul>
                    </div>

                    <div class="alert alert-info mt-3">
                        <i class="bi bi-arrow-repeat"></i> <strong>Mecanismo de Reintentos (Backoff Exponencial):</strong><br>
                        El sistema calcula <code>next_retry_at</code> con backoff incremental:
                        <ul class="mb-0 mt-2">
                            <li>Intento 1: 1 minuto después</li>
                            <li>Intento 2: 5 minutos después</li>
                            <li>Intento 3: 15 minutos después</li>
                            <li>Intento 4: 1 hora después</li>
                            <li>Intento 5+: 6 horas después</li>
                        </ul>
                    </div>

                    <h6 class="mt-4">Tipos de Trabajos (Job Types):</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th>job_type</th>
                                    <th>Descripción</th>
                                    <th>Payload Ejemplo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>generar_guia</code></td>
                                    <td>Generar guía de envío con proveedor logístico</td>
                                    <td><code>{"proveedor": "DHL", "peso_kg": 2.5}</code></td>
                                </tr>
                                <tr>
                                    <td><code>actualizar_tracking</code></td>
                                    <td>Actualizar estado de tracking desde API externa</td>
                                    <td><code>{"tracking_number": "ABC123456"}</code></td>
                                </tr>
                                <tr>
                                    <td><code>validar_direccion</code></td>
                                    <td>Validar y normalizar dirección de entrega</td>
                                    <td><code>{"direccion_id": 123, "servicio": "google_maps"}</code></td>
                                </tr>
                                <tr>
                                    <td><code>notificar_estado</code></td>
                                    <td>Enviar notificación de cambio de estado</td>
                                    <td><code>{"tipo": "email", "destinatario": "cliente@example.com"}</code></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="alert alert-success mt-3">
                        <i class="bi bi-speedometer"></i> <strong>Procesamiento Concurrente:</strong><br>
                        El worker <code>logistics_worker.php</code> procesa trabajos usando <code>SELECT ... FOR UPDATE SKIP LOCKED</code>,
                        permitiendo múltiples instancias del worker ejecutarse simultáneamente sin colisiones.
                    </div>

                    <div class="alert alert-primary mt-3">
                        <i class="bi bi-link-45deg"></i> <strong>Relación con pedidos:</strong><br>
                        La constraint <code>fk_logistics_queue_pedido</code> con <code>ON DELETE CASCADE</code> garantiza que
                        si se elimina un pedido, todos sus trabajos pendientes se eliminan automáticamente.
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
                <a href="<?= RUTA_URL ?>crm/manual" class="btn btn-primary">
                    <i class="bi bi-book"></i> Ver Manual de Usuario
                </a>
            </div>
        </div>
    </div>
</div>

<style>
    .table code {
        background-color: #f8f9fa;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 0.9em;
    }
    
    pre {
        border-radius: 5px;
        margin: 0;
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
include("vista/includes/footer_materialize.php");
?>
