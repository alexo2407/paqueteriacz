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
                <div class="card-header bg-warning text-dark">
                    <h3 class="mb-0">
                        <i class="bi bi-gear-wide-connected"></i> Documentación Técnica: Logistics Worker
                    </h3>
                </div>
                <div class="card-body">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="<?= RUTA_URL ?>dashboard">Dashboard</a></li>
                            <li class="breadcrumb-item active">Logistics Worker</li>
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
                                <li><a href="#arquitectura" class="text-decoration-none"><i class="bi bi-chevron-right"></i> 2. Arquitectura</a></li>
                                <li><a href="#job-types" class="text-decoration-none"><i class="bi bi-chevron-right"></i> 3. Tipos de Trabajos</a></li>
                                <li><a href="#queue-management" class="text-decoration-none"><i class="bi bi-chevron-right"></i> 4. Gestión de Cola</a></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li><a href="#processors" class="text-decoration-none"><i class="bi bi-chevron-right"></i> 5. Procesadores</a></li>
                                <li><a href="#error-handling" class="text-decoration-none"><i class="bi bi-chevron-right"></i> 6. Manejo de Errores</a></li>
                                <li><a href="#monitoring" class="text-decoration-none"><i class="bi bi-chevron-right"></i> 7. Monitoreo</a></li>
                                <li><a href="#code-examples" class="text-decoration-none"><i class="bi bi-chevron-right"></i> 8. Ejemplos de Código</a></li>
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
                    <h5><i class="bi bi-info-circle"></i> ¿Qué es el Logistics Worker?</h5>
                    <p class="lead">
                        El <strong>Logistics Worker</strong> es un proceso daemon que ejecuta trabajos asíncronos relacionados con logística:
                        validación de direcciones, generación de guías, actualización de tracking y notificaciones de estado.
                    </p>

                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i> <strong>Beneficios:</strong>
                        <ul class="mb-0 mt-2">
                            <li><strong>Asincronía</strong>: Las operaciones costosas no bloquean la aplicación web</li>
                            <li><strong>Reintentos automáticos</strong>: Maneja errores transitorios con backoff exponencial</li>
                            <li><strong>Escalabilidad</strong>: Permite múltiples workers concurrentes con SKIP LOCKED</li>
                            <li><strong>Trazabilidad</strong>: Registra todos los intentos y errores</li>
                        </ul>
                    </div>

                    <h6 class="mt-4">Archivo Principal:</h6>
                    <p><code>cli/logistics_worker.php</code></p>

                    <h6>Ejecución:</h6>
                    <pre class="bg-dark text-white p-3 rounded"><code># Modo single-run (procesa trabajos pendientes una vez)
php cli/logistics_worker.php --once

# Modo loop (daemon continuo)
php cli/logistics_worker.php --loop</code></pre>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Arquitectura -->
    <div class="row mb-4" id="arquitectura">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">2. Arquitectura</h4>
                </div>
                <div class="card-body">
                    <h5><i class="bi bi-diagram-3"></i> Componentes del Sistema</h5>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th>Componente</th>
                                    <th>Ubicación</th>
                                    <th>Responsabilidad</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>logistics_worker.php</code></td>
                                    <td>cli/</td>
                                    <td>Script principal del worker, loop de procesamiento</td>
                                </tr>
                                <tr>
                                    <td><code>LogisticsQueueService.php</code></td>
                                    <td>services/</td>
                                    <td>Gestión de la cola (encolar, obtener siguiente, marcar como procesado/fallido)</td>
                                </tr>
                                <tr>
                                    <td><code>BaseProcessor.php</code></td>
                                    <td>cli/processors/</td>
                                    <td>Clase base abstracta para todos los procesadores</td>
                                </tr>
                                <tr>
                                    <td><code>ValidarDireccionProcessor.php</code></td>
                                    <td>cli/processors/</td>
                                    <td>Validación de direcciones con APIs externas</td>
                                </tr>
                                <tr>
                                    <td><code>GenerarGuiaProcessor.php</code></td>
                                    <td>cli/processors/</td>
                                    <td>Generación de documentos de envío</td>
                                </tr>
                                <tr>
                                    <td><code>ActualizarTrackingProcessor.php</code></td>
                                    <td>cli/processors/</td>
                                    <td>Sincronización de estados con proveedores logísticos</td>
                                </tr>
                                <tr>
                                    <td><code>NotificarEstadoProcessor.php</code></td>
                                    <td>cli/processors/</td>
                                    <td>Envío de notificaciones a clientes</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h6 class="mt-4">Flujo de Procesamiento:</h6>
                    <div class="card border-secondary">
                        <div class="card-body">
                            <ol>
                                <li><strong>Worker consulta</strong> la tabla <code>logistics_queue</code> para obtener el siguiente trabajo pendiente o que deba reintentarse</li>
                                <li><strong>SELECT FOR UPDATE SKIP LOCKED</strong> garantiza que múltiples workers no procesen el mismo trabajo</li>
                                <li><strong>Worker marca</strong> el trabajo como <code>'processing'</code></li>
                                <li><strong>Worker instancia</strong> el procesador correspondiente según el <code>job_type</code></li>
                                <li><strong>Procesador ejecuta</strong> la lógica específica del trabajo</li>
                                <li><strong>Si tiene éxito</strong>: se marca como <code>'completed'</code> y se registra <code>processed_at</code></li>
                                <li><strong>Si falla</strong>: se marca como <code>'failed'</code>, se incrementa <code>attempts</code> y se calcula <code>next_retry_at</code></li>
                                <li><strong>Worker espera</strong> 3 segundos y repite el ciclo</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Tipos de Trabajos -->
    <div class="row mb-4" id="job-types">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">3. Tipos de Trabajos (Job Types)</h4>
                </div>
                <div class="card-body">
                    <!-- generar_guia -->
                    <h5><i class="bi bi-file-earmark-pdf"></i> 3.1. generar_guia</h5>
                    <p><strong>Descripción:</strong> Genera documentos y etiquetas de envío mediante integración con proveedores logísticos (DHL, FedEx, UPS, etc.).</p>
                    <p><strong>Payload:</strong></p>
                    <pre class="bg-light p-3 rounded"><code class="language-json">{
  "proveedor": "DHL",
  "peso_kg": 2.5,
  "dimensiones": {"largo": 30, "ancho": 20, "alto": 10},
  "servicio": "express"
}</code></pre>
                    <p><strong>Procesador:</strong> <code>GenerarGuiaProcessor</code></p>

                    <hr class="my-4">

                    <!-- actualizar_tracking -->
                    <h5><i class="bi bi-arrow-clockwise"></i> 3.2. actualizar_tracking</h5>
                    <p><strong>Descripción:</strong> Sincroniza el estado de los pedidos con las APIs de tracking de los proveedores logísticos.</p>
                    <p><strong>Payload:</strong></p>
                    <pre class="bg-light p-3 rounded"><code class="language-json">{
  "tracking_number": "1Z999AA10123456784",
  "proveedor": "UPS"
}</code></pre>
                    <p><strong>Procesador:</strong> <code>ActualizarTrackingProcessor</code></p>

                    <hr class="my-4">

                    <!-- validar_direccion -->
                    <h5><i class="bi bi-geo-alt"></i> 3.3. validar_direccion</h5>
                    <p><strong>Descripción:</strong> Valida y normaliza direcciones de entrega usando servicios de geocodificación (Google Maps, HERE, etc.).</p>
                    <p><strong>Payload:</strong></p>
                    <pre class="bg-light p-3 rounded"><code class="language-json">{
  "direccion_id": 123,
  "servicio": "google_maps",
  "forzar_validacion": false
}</code></pre>
                    <p><strong>Procesador:</strong> <code>ValidarDireccionProcessor</code></p>

                    <hr class="my-4">

                    <!-- notificar_estado -->
                    <h5><i class="bi bi-envelope"></i> 3.4. notificar_estado</h5>
                    <p><strong>Descripción:</strong> Envía notificaciones a clientes sobre cambios de estado en sus pedidos (email, SMS, WhatsApp).</p>
                    <p><strong>Payload:</strong></p>
                    <pre class="bg-light p-3 rounded"><code class="language-json">{
  "tipo": "email",
  "destinatario": "cliente@example.com",
  "template": "pedido_entregado",
  "variables": {"numero_guia": "ABC123", "fecha": "2026-01-14"}
}</code></pre>
                    <p><strong>Procesador:</strong> <code>NotificarEstadoProcessor</code></p>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Gestión de Cola -->
    <div class="row mb-4" id="queue-management">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h4 class="mb-0">4. Gestión de Cola</h4>
                </div>
                <div class="card-body">
                    <h5><i class="bi bi-stack"></i> LogisticsQueueService</h5>
                    <p>La clase <code>LogisticsQueueService</code> proporciona métodos para interactuar con la cola de trabajos.</p>

                    <h6>Métodos Principales:</h6>
                    <table class="table table-bordered table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Método</th>
                                <th>Descripción</th>
                                <th>Retorno</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>queue()</code></td>
                                <td>Encola un nuevo trabajo</td>
                                <td>['success', 'message', 'id']</td>
                            </tr>
                            <tr>
                                <td><code>getNextPendingJob()</code></td>
                                <td>Obtiene el siguiente trabajo pendiente o a reintentar (con FOR UPDATE SKIP LOCKED)</td>
                                <td>array | null</td>
                            </tr>
                            <tr>
                                <td><code>markAsProcessing()</code></td>
                                <td>Marca un trabajo como 'processing'</td>
                                <td>bool</td>
                            </tr>
                            <tr>
                                <td><code>markAsCompleted()</code></td>
                                <td>Marca un trabajo como 'completed'</td>
                                <td>bool</td>
                            </tr>
                            <tr>
                                <td><code>markAsFailed()</code></td>
                                <td>Marca un trabajo como 'failed' y programa reintento</td>
                                <td>bool</td>
                            </tr>
                            <tr>
                                <td><code>getStatistics()</code></td>
                                <td>Obtiene estadísticas de la cola (pending, completed, failed)</td>
                                <td>array</td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="alert alert-info mt-3">
                        <i class="bi bi-lock"></i> <strong>Concurrencia Segura:</strong><br>
                        El método <code>getNextPendingJob()</code> utiliza <code>SELECT ... FOR UPDATE SKIP LOCKED</code> para evitar
                        que múltiples workers procesen el mismo trabajo simultáneamente.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Procesadores -->
    <div class="row mb-4" id="processors">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h4 class="mb-0">5. Procesadores</h4>
                </div>
                <div class="card-body">
                    <h5><i class="bi bi-cpu"></i> Arquitectura de Procesadores</h5>
                    <p>Todos los procesadores heredan de <code>BaseProcessor</code> e implementan el método <code>process($job)</code>.</p>

                    <h6>BaseProcessor (Clase Abstracta):</h6>
                    <pre class="bg-light p-3 rounded"><code class="language-php">abstract class BaseProcessor {
    /**
     * Procesa un trabajo de la cola
     * 
     * @param array $job Trabajo a procesar (row de logistics_queue)
     * @return array ['success' => bool, 'message' => string]
     */
    abstract public function process($job);

    /**
     * Valida que el payload contenga las claves requeridas
     */
    protected function validatePayload($payload, $requiredKeys) {
        // ...
    }
}</code></pre>

                    <h6 class="mt-4">Implementación de un Procesador:</h6>
                    <p>Cada procesador debe:</p>
                    <ul>
                        <li>Extender <code>BaseProcessor</code></li>
                        <li>Implementar <code>process($job)</code></li>
                        <li>Validar el payload</li>
                        <li>Ejecutar la lógica de negocio</li>
                        <li>Retornar <code>['success' => true/false, 'message' => '...']</code></li>
                        <li>Lanzar excepciones en caso de error irrecuperable</li>
                    </ul>

                    <div class="alert alert-warning mt-3">
                        <i class="bi bi-exclamation-triangle"></i> <strong>Importante:</strong><br>
                        Los procesadores NO deben interactuar directamente con <code>LogisticsQueueService</code>. El worker se encarga
                        de marcar los trabajos como completed/failed según el resultado retornado por <code>process()</code>.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 6. Manejo de Errores -->
    <div class="row mb-4" id="error-handling">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-danger text-white">
                    <h4 class="mb-0">6. Manejo de Errores y Reintentos</h4>
                </div>
                <div class="card-body">
                    <h5><i class="bi bi-arrow-repeat"></i> Estrategia de Reintentos</h5>
                    <p>El sistema utiliza <strong>backoff exponencial</strong> para programar reintentos automáticos.</p>

                    <h6>Tabla de Backoff:</h6>
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Intento</th>
                                <th>Espera</th>
                                <th>Total Acumulado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>1</td><td>1 minuto</td><td>1 min</td></tr>
                            <tr><td>2</td><td>5 minutos</td><td>6 min</td></tr>
                            <tr><td>3</td><td>15 minutos</td><td>21 min</td></tr>
                            <tr><td>4</td><td>1 hora</td><td>1h 21min</td></tr>
                            <tr><td>5+</td><td>6 horas</td><td>7h 21min+</td></tr>
                        </tbody>
                    </table>

                    <h6 class="mt-4">Límite de Reintentos:</h6>
                    <p>Por defecto, cada trabajo puede reintentarse hasta <code>5 veces</code> (configurable con <code>max_intentos</code>).</p>
                    <p>Después de alcanzar el máximo de intentos, el trabajo permanece en estado <code>'failed'</code> y no se reintenta automáticamente.</p>

                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle"></i> <strong>Logs de Errores:</strong><br>
                        El campo <code>last_error</code> almacena el mensaje del último error capturado, útil para debugging.
                        También se genera un log en <code>logs/logistics-worker-error.log</code>.
                    </div>

                    <h6 class="mt-4">Recuperación Manual:</h6>
                    <p>Los trabajos fallidos pueden reiniciarse manualmente actualizando:</p>
                    <pre class="bg-dark text-white p-3 rounded"><code class="language-sql">UPDATE logistics_queue
SET status = 'pending',
    attempts = 0,
    next_retry_at = NULL,
    last_error = NULL
WHERE id = [job_id];</code></pre>
                </div>
            </div>
        </div>
    </div>

    <!-- 7. Monitoreo -->
    <div class="row mb-4" id="monitoring">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h4 class="mb-0">7. Monitoreo</h4>
                </div>
                <div class="card-body">
                    <h5><i class="bi bi-activity"></i> Heartbeat y Estado del Worker</h5>
                    <p>El worker actualiza un archivo heartbeat cada 3 segundos para indicar que está activo:</p>
                    <p><code>logs/logistics_worker.heartbeat</code></p>

                    <p>Puedes verificar si el worker está corriendo revisando la antigüedad del archivo:</p>
                    <pre class="bg-dark text-white p-3 rounded"><code># Verificar última modificación
ls -la logs/logistics_worker.heartbeat

# Si fue modificado hace más de 2 minutos, el worker probablemente está detenido</code></pre>

                    <h6 class="mt-4">Dashboard de Monitoreo:</h6>
                    <p>El sistema incluye un dashboard que muestra el estado del worker en tiempo real:</p>
                    <p><a href="<?= RUTA_URL ?>crm/monitor" class="btn btn-sm btn-primary"><i class="bi bi-activity"></i> Ir al Monitor de Workers</a></p>

                    <h6 class="mt-4">Estadísticas de la Cola:</h6>
                    <p>Puedes consultar las estadísticas mediante:</p>
                    <pre class="bg-light p-3 rounded"><code class="language-php">$stats = LogisticsQueueService::getStatistics();
// Retorna: ['pending' => int, 'processing' => int, 'completed' => int, 'failed' => int]</code></pre>

                    <div class="alert alert-success mt-3">
                        <i class="bi bi-check-circle"></i> <strong>Recomendación:</strong><br>
                        Configura alertas automáticas si <code>failed</code> supera un umbral o si el heartbeat no se actualiza en más de 5 minutos.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 8. Ejemplos de Código -->
    <div class="row mb-4" id="code-examples">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">8. Ejemplos de Código</h4>
                </div>
                <div class="card-body">
                    <h5><i class="bi bi-code-square"></i> Encolar un Trabajo</h5>
                    <pre class="bg-light p-3 rounded"><code class="language-php">require_once 'services/LogisticsQueueService.php';

// Encolar validación de dirección
$result = LogisticsQueueService::queue(
    'validar_direccion',  // job_type
    $pedidoId,            // pedido_id
    [                     // payload
        'direccion_id' => 456,
        'servicio' => 'google_maps'
    ],
    5                     // max_intentos (opcional, default: 5)
);

if ($result['success']) {
    echo "Trabajo encolado con ID: " . $result['id'];
} else {
    echo "Error: " . $result['message'];
}</code></pre>

                    <h6 class="mt-4">Crear un Nuevo Tipo de Trabajo:</h6>
                    <p><strong>Paso 1:</strong> Agregar el nuevo tipo a <code>LogisticsQueueService::JOB_TYPES</code></p>
                    <pre class="bg-light p-3 rounded"><code class="language-php">const JOB_TYPES = [
    'generar_guia',
    'actualizar_tracking',
    'validar_direccion',
    'notificar_estado',
    'mi_nuevo_trabajo'  // <-- Nuevo
];</code></pre>

                    <p><strong>Paso 2:</strong> Crear el procesador en <code>cli/processors/MiNuevoTrabajoProcessor.php</code></p>
                    <pre class="bg-light p-3 rounded"><code class="language-php">require_once __DIR__ . '/BaseProcessor.php';

class MiNuevoTrabajoProcessor extends BaseProcessor {
    public function process($job) {
        try {
            // Validar payload
            $payload = json_decode($job['payload'], true);
            $this->validatePayload($payload, ['campo_requerido']);
            
            // Lógica de negocio
            $resultado = $this->ejecutarLogica($payload);
            
            // Retornar éxito
            return [
                'success' => true,
                'message' => 'Trabajo procesado correctamente'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    private function ejecutarLogica($payload) {
        // Implementación específica
    }
}</code></pre>

                    <p><strong>Paso 3:</strong> Registrar en <code>cli/logistics_worker.php</code></p>
                    <pre class="bg-light p-3 rounded"><code class="language-php">case 'mi_nuevo_trabajo':
    require_once __DIR__ . '/processors/MiNuevoTrabajoProcessor.php';
    $processor = new MiNuevoTrabajoProcessor();
    break;</code></pre>
                </div>
            </div>
        </div>
    </div>

    <!-- Botones de navegación -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between">
                <a href="<?= RUTA_URL ?>crm/database_doc" class="btn btn-secondary">
                    <i class="bi bi-database"></i> Ver Database Doc
                </a>
                <div>
                    <a href="<?= RUTA_URL ?>crm/monitor" class="btn btn-warning">
                        <i class="bi bi-activity"></i> Monitor de Workers
                    </a>
                    <a href="<?= RUTA_URL ?>dashboard" class="btn btn-primary ms-2">
                        <i class="bi bi-arrow-left"></i> Volver al Dashboard
                    </a>
                </div>
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
