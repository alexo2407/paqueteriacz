<?php
require_once "controlador/logistica.php";

// Instanciar controlador
$controller = new LogisticaController();

// Obtener ID del URL (proporcionado por EnlacesController en $parametros)
$idPedido = $parametros[0] ?? null;

// Obtener datos
$datos = $controller->obtenerDatosPedido($idPedido);

// Si no hay datos (no existe o no autorizado), redirigir
if (!$idPedido || !$datos) {
    echo "<script>window.location.href='".RUTA_URL."logistica/dashboard';</script>";
    exit;
}

$pedido = $datos['pedido'];
$historialCambios = $datos['historial'] ?? [];
$estadosDisponibles = $datos['estados'] ?? [];

// Crear un mapa de ID => Nombre para registros antiguos
$mapaEstados = [];
foreach ($estadosDisponibles as $e) {
    $mapaEstados[$e['id']] = $e['nombre_estado'];
}

// Constantes de color semántico (definidas aquí porque ver.php puede cargarse de forma independiente)
if (!defined('CLR_LOGISTICA'))  define('CLR_LOGISTICA',  'background:#3498db;color:#fff');
if (!defined('CLR_TRANSITO'))   define('CLR_TRANSITO',   'background:#2ecc71;color:#212529');
if (!defined('CLR_COMPLETADO')) define('CLR_COMPLETADO', 'background:#27ae60;color:#fff');
if (!defined('CLR_EXCEPCION'))  define('CLR_EXCEPCION',  'background:#f39c12;color:#212529');
if (!defined('CLR_FALLO'))      define('CLR_FALLO',      'background:#c0392b;color:#fff');
if (!defined('CLR_CRITICO'))    define('CLR_CRITICO',    'background:#e74c3c;color:#fff');
if (!defined('CLR_GRIS'))       define('CLR_GRIS',       'background:#95a5a6;color:#212529');

// Mapa de colores — 17 estados de estados_pedidos
// ORDEN IMPORTA: claves específicas primero
$estadoColores = [
    'DEVOLUCION'            => CLR_FALLO,      // #15 Devolución – entregado a bodega
    'DEVUELTO'              => CLR_FALLO,      // #7  Devuelto
    'PENDIENTE RECOLECCION' => CLR_EXCEPCION,  // #11 Pendiente recolección por mensajería
    'RECOLECTADO'           => CLR_LOGISTICA,  // #12 Recolectado por mensajería
    'TRASLADO'              => CLR_LOGISTICA,  // #13 Traslado a punto de distribución
    'DOMICILIO CERRADO'     => CLR_EXCEPCION,  // #5  Domicilio cerrado
    'DOMICILIO NO'          => CLR_EXCEPCION,  // #8  Domicilio no encontrado
    'NO HAY QUIEN'          => CLR_EXCEPCION,  // #6  No hay quien reciba en domicilio
    'NO PUEDE PAGAR'        => CLR_FALLO,      // #10 No puede pagar recaudo
    'EN BODEGA'             => CLR_LOGISTICA,  // #1  En bodega
    'EN RUTA'               => CLR_TRANSITO,   // #2  En ruta o proceso
    'REPROGRAMADO'          => CLR_EXCEPCION,  // #4  Reprogramado
    'ENTREGADO'             => CLR_COMPLETADO, // #3 y #14
    'RECHAZADO'             => CLR_FALLO,      // #9  Rechazado
    'LIQUIDADO'             => CLR_COMPLETADO, // #14 cierre contable
    'INCIDENCIA'            => CLR_CRITICO,    // #16 Incidencia
    'CANCELADO'             => CLR_FALLO,      // #17 Cancelado
    'DOMICILIO'             => CLR_EXCEPCION,
    'PENDIENTE'             => CLR_EXCEPCION,
    'EN_ESPERA'             => CLR_GRIS,
    'TRANSITO'              => CLR_TRANSITO,
    'VENDIDO'               => CLR_COMPLETADO,
];

function getBadgeColor($estado, $map) {
    if (empty($estado)) return CLR_GRIS;
    $upper = strtoupper($estado);
    // strtoupper() no convierte acentos UTF-8; los normalizamos con strtr
    $norm = strtr($upper, [
        'á'=>'A','é'=>'E','í'=>'I','ó'=>'O','ú'=>'U','ü'=>'U','ñ'=>'N',
        'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N',
    ]);
    foreach ($map as $key => $val) {
        if (strpos($norm, $key) !== false) return $val;
    }
    return CLR_GRIS;
}

$badgeColor = getBadgeColor($pedido['nombre_estado'], $estadoColores);

// --- Lógica de Alerta de Fecha de Entrega ---
$fechaAlertaBadge = '';
$fechaAlertaLabel = '';
$fechaBadgeColor = 'secondary';
$fechaEntregaRaw = $pedido['fecha_entrega'] ?? null;

if (!empty($fechaEntregaRaw)) {
    $hoy = new DateTime(date('Y-m-d'));
    $entrega = new DateTime($fechaEntregaRaw);
    $intervalo = $hoy->diff($entrega);
    $dias = (int)$intervalo->format('%r%a');

    if (strtoupper($pedido['nombre_estado']) === 'ENTREGADO') {
       $fechaBadgeColor = 'outline-success';
       $fechaAlertaLabel = 'Entregado';
       $fechaSubLabel = 'Fecha de entrega registrada';
    } elseif ($dias === 0) {
        $fechaBadgeColor = 'danger';
        $fechaAlertaLabel = '¡HOY!';
        $fechaSubLabel = 'Entrega estimada durante el día';
    } elseif ($dias === 1) {
        $fechaBadgeColor = 'warning text-dark';
        $fechaAlertaLabel = '¡MAÑANA!';
        $fechaSubLabel = 'Entrega programada para mañana';
    } elseif ($dias > 1) {
        $fechaBadgeColor = 'success';
        $fechaAlertaLabel = 'PROGRAMADO';
        $fechaSubLabel = 'Entrega programada para esta fecha';
    } elseif ($dias < 0) {
        $fechaBadgeColor = 'danger';
        $fechaAlertaLabel = 'ATRASADO';
        $fechaSubLabel = 'La entrega se encuentra demorada';
    }
} else {
    $fechaAlertaLabel = 'No programada';
    $fechaSubLabel = 'Pendiente de definir fecha';
}

// ¿El pedido tiene coordenadas válidas?
$hasCoords = !empty($pedido['latitud']) && !empty($pedido['longitud'])
             && (float)$pedido['latitud'] != 0 && (float)$pedido['longitud'] != 0;


include("vista/includes/header.php"); 
?>

<div class="container-fluid py-4">
    
    <!-- Header Page -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="bi bi-file-text me-2"></i>Detalle del Pedido #<?= htmlspecialchars($pedido['numero_orden']) ?>
            </h1>
            <p class="text-muted mb-0">Gestiona y revisa el historial de este pedido.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
           <span class="badge fs-6 px-3 py-2 align-self-center" style="<?= $badgeColor ?>"><?= htmlspecialchars($pedido['nombre_estado'] ?? 'Desconocido') ?></span>

           <?php if ($hasCoords): ?>
               <button type="button" class="btn btn-success align-self-center" data-bs-toggle="modal" data-bs-target="#mapaModal">
                   <i class="bi bi-geo-alt-fill me-1"></i> Ver Ubicación
               </button>
           <?php endif; ?>

           <?php if (!in_array(strtoupper($pedido['nombre_estado']), ['ENTREGADO', 'CANCELADO', 'DEVOLUCION COMPLETA', 'LIQUIDADO'])): ?>
                <button type="button" class="btn btn-warning text-dark align-self-center" data-bs-toggle="modal" data-bs-target="#cambiarEstadoModal">
                    <i class="bi bi-arrow-repeat"></i> Cambiar Estado
                </button>
           <?php endif; ?>

           <a href="<?= RUTA_URL ?>logistica/dashboard" class="btn btn-outline-secondary">
               <i class="bi bi-arrow-left"></i> Volver
           </a>
        </div>
    </div>

    <div class="row">
        <!-- Columna Izquierda: Información -->
        <div class="col-lg-8 mb-4">
            <div class="card shadow border-0 h-100">
                <div class="card-header bg-primary text-white py-3">
                    <h6 class="m-0 fw-bold"><i class="bi bi-info-circle me-2"></i>Información del Pedido</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="small text-muted fw-bold text-uppercase">Número de Orden</label>
                            <div class="fs-5 text-dark"><?= htmlspecialchars($pedido['numero_orden']) ?></div>
                        </div>

                         <div class="col-md-6">
                            <label class="small text-muted fw-bold text-uppercase">Proveedor</label>
                            <div class="text-dark">
                                <i class="bi bi-shop me-1"></i>
                                <?= htmlspecialchars($pedido['proveedor_nombre'] ?? 'No asignado') ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="small text-muted fw-bold text-uppercase">Cliente</label>
                            <div class="text-dark">
                                <i class="bi bi-person me-1"></i>
                                <?= htmlspecialchars($pedido['cliente_nombre'] ?? 'No asignado') ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="small text-muted fw-bold text-uppercase">Fecha Creación</label>
                            <div><?= date('d/m/Y H:i', strtotime($pedido['fecha_ingreso'])) ?></div>
                        </div>

                        <?php if (!empty($pedido['fecha_liquidacion'])): ?>
                        <div class="col-md-6">
                            <label class="small text-muted fw-bold text-uppercase">Fecha de Liquidación</label>
                            <div class="fw-bold text-success">
                                <i class="bi bi-check-circle-fill me-1"></i>
                                <?= date('d/m/Y', strtotime($pedido['fecha_liquidacion'])) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-6">
                            <label class="small text-muted fw-bold text-uppercase">Destinatario</label>
                            <div class="fw-bold"><?= htmlspecialchars($pedido['destinatario']) ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="small text-muted fw-bold text-uppercase">Teléfono</label>
                            <div><?= htmlspecialchars($pedido['telefono']) ?></div>
                        </div>

                        <div class="col-12">
                            <label class="small text-muted fw-bold text-uppercase">Comentario del Pedido</label>
                            <div class="p-3 bg-light rounded border mt-1">
                                <?php if (!empty($pedido['comentario'])): ?>
                                    <i class="bi bi-chat-left-text me-1 text-secondary"></i>
                                    <?= nl2br(htmlspecialchars($pedido['comentario'])) ?>
                                <?php else: ?>
                                    <span class="text-muted fst-italic">Sin comentarios registrados.</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-12">
                            <hr class="my-2">
                        </div>
                        
                        <div class="col-12 mt-1">
                            <label class="small text-muted fw-bold text-uppercase">Dirección de Entrega</label>
                            <div class="p-3 bg-light rounded border">
                                <div class="mb-1">
                                    <?= htmlspecialchars($pedido['direccion'] ?? 'Sin dirección específica') ?>
                                </div>
                                <?php
                                    // Resolver nombres geográficos desde IDs FK
                                    $nomPais = $nomDepto = $nomMuni = $nomBarrio = null;
                                    try {
                                        $dbTmp = (new Conexion())->conectar();
                                        if (!empty($pedido['id_pais'])) {
                                            $st = $dbTmp->prepare("SELECT nombre FROM paises WHERE id = :id LIMIT 1");
                                            $st->execute([':id' => $pedido['id_pais']]);
                                            $nomPais = $st->fetchColumn();
                                        }
                                        if (!empty($pedido['id_departamento'])) {
                                            $st = $dbTmp->prepare("SELECT nombre FROM departamentos WHERE id = :id LIMIT 1");
                                            $st->execute([':id' => $pedido['id_departamento']]);
                                            $nomDepto = $st->fetchColumn();
                                        }
                                        if (!empty($pedido['id_municipio'])) {
                                            $st = $dbTmp->prepare("SELECT nombre FROM municipios WHERE id = :id LIMIT 1");
                                            $st->execute([':id' => $pedido['id_municipio']]);
                                            $nomMuni = $st->fetchColumn();
                                        }
                                        if (!empty($pedido['id_barrio'])) {
                                            $st = $dbTmp->prepare("SELECT nombre FROM barrios WHERE id = :id LIMIT 1");
                                            $st->execute([':id' => $pedido['id_barrio']]);
                                            $nomBarrio = $st->fetchColumn();
                                        }

                                        // ── FALLBACK: resolución de CP para display ───────────────────────
                                        // Nivel 0: búsqueda exacta (cubre CPs ya prefijados: CR10110)
                                        // Nivel 1: agrega prefijo del país si se conoce (10110 + CR → CR10110)
                                        // Nivel 2: sufijo numérico LIKE (10110 → %10110, cuando id_pais=NULL)
                                        // Solo para display, no modifica la BD.
                                        if ((!$nomDepto || !$nomMuni) && !empty($pedido['codigo_postal'])) {
                                            $cpBruto = strtoupper(trim($pedido['codigo_postal']));
                                            $cpFound = false;
                                            $cpSql = "
                                                SELECT d.nombre AS nom_depto,
                                                       mu.nombre AS nom_muni,
                                                       b.nombre AS nom_barrio
                                                FROM codigos_postales cp
                                                LEFT JOIN departamentos d  ON d.id  = cp.id_departamento
                                                LEFT JOIN municipios    mu ON mu.id = cp.id_municipio
                                                LEFT JOIN barrios       b  ON b.id  = cp.id_barrio
                                                WHERE cp.codigo_postal = :cp
                                                  AND cp.id_departamento IS NOT NULL
                                                LIMIT 1
                                            ";

                                            // Nivel 0: búsqueda exacta con el CP tal como está guardado
                                            $st = $dbTmp->prepare($cpSql);
                                            $st->execute([':cp' => $cpBruto]);
                                            $cpRow = $st->fetch(PDO::FETCH_ASSOC);
                                            if ($cpRow) {
                                                if (!$nomDepto  && $cpRow['nom_depto'])  $nomDepto  = $cpRow['nom_depto'];
                                                if (!$nomMuni   && $cpRow['nom_muni'])   $nomMuni   = $cpRow['nom_muni'];
                                                if (!$nomBarrio && $cpRow['nom_barrio']) $nomBarrio = $cpRow['nom_barrio'];
                                                $cpFound = true;
                                            }

                                            // Nivel 1: agregar prefijo del país al CP numérico
                                            // Si id_pais es NULL pero hay id_moneda, deriva el país desde la moneda
                                            // (paises.id_moneda_local → prefijo_postal)
                                            if (!$cpFound) {
                                                // Priorizar moneda sobre id_pais (la moneda es más confiable)
                                                $idPaisEfectivo = null;
                                                if (!empty($pedido['id_moneda'])) {
                                                    $stPais = $dbTmp->prepare("SELECT id FROM paises WHERE id_moneda_local = :id_moneda LIMIT 1");
                                                    $stPais->execute([':id_moneda' => (int)$pedido['id_moneda']]);
                                                    $idPaisEfectivo = (int)($stPais->fetchColumn() ?: 0) ?: null;
                                                }
                                                if (!$idPaisEfectivo && !empty($pedido['id_pais'])) {
                                                    $idPaisEfectivo = (int)$pedido['id_pais'];
                                                }

                                                if ($idPaisEfectivo) {
                                                    require_once __DIR__ . '/../../../services/AddressService.php';
                                                    $cpConPrefijo = AddressService::normalizarCP($pedido['codigo_postal'], $idPaisEfectivo);
                                                    if ($cpConPrefijo !== $cpBruto) {
                                                        $st = $dbTmp->prepare($cpSql);
                                                        $st->execute([':cp' => $cpConPrefijo]);
                                                        $cpRow = $st->fetch(PDO::FETCH_ASSOC);
                                                        if ($cpRow) {
                                                            if (!$nomDepto  && $cpRow['nom_depto'])  $nomDepto  = $cpRow['nom_depto'];
                                                            if (!$nomMuni   && $cpRow['nom_muni'])   $nomMuni   = $cpRow['nom_muni'];
                                                            if (!$nomBarrio && $cpRow['nom_barrio']) $nomBarrio = $cpRow['nom_barrio'];
                                                            $cpFound = true;
                                                        }
                                                    }
                                                }
                                            }

                                            // Nivel 2: rellenar con ceros a la izquierda (sin prefijo)
                                            // Cubre casos como "1" → "0001", "10" → "0010"
                                            if (!$cpFound && ctype_digit($cpBruto)) {
                                                $cpPadded = str_pad($cpBruto, 4, '0', STR_PAD_LEFT);
                                                if ($cpPadded !== $cpBruto) {
                                                    $st = $dbTmp->prepare($cpSql);
                                                    $st->execute([':cp' => $cpPadded]);
                                                    $cpRow = $st->fetch(PDO::FETCH_ASSOC);
                                                    if ($cpRow) {
                                                        if (!$nomDepto  && $cpRow['nom_depto'])  $nomDepto  = $cpRow['nom_depto'];
                                                        if (!$nomMuni   && $cpRow['nom_muni'])   $nomMuni   = $cpRow['nom_muni'];
                                                        if (!$nomBarrio && $cpRow['nom_barrio']) $nomBarrio = $cpRow['nom_barrio'];
                                                        $cpFound = true;
                                                    }
                                                }
                                            }

                                            // Nivel 3: prefijo del país + ceros a la izquierda
                                            // Cubre casos como "1" con id_pais=6 → "GT0001"
                                            if (!$cpFound && ctype_digit($cpBruto) && !empty($idPaisEfectivo ?? null)) {
                                                $cpPaddedConPrefijo = AddressService::normalizarCP(
                                                    str_pad($cpBruto, 4, '0', STR_PAD_LEFT),
                                                    $idPaisEfectivo
                                                );
                                                if ($cpPaddedConPrefijo !== $cpBruto) {
                                                    $st = $dbTmp->prepare($cpSql);
                                                    $st->execute([':cp' => $cpPaddedConPrefijo]);
                                                    $cpRow = $st->fetch(PDO::FETCH_ASSOC);
                                                    if ($cpRow) {
                                                        if (!$nomDepto  && $cpRow['nom_depto'])  $nomDepto  = $cpRow['nom_depto'];
                                                        if (!$nomMuni   && $cpRow['nom_muni'])   $nomMuni   = $cpRow['nom_muni'];
                                                        if (!$nomBarrio && $cpRow['nom_barrio']) $nomBarrio = $cpRow['nom_barrio'];
                                                    }
                                                }
                                            }
                                        }


                                        // ────────────────────────────────────────────────────────────────


                                    } catch(Exception $e) {}
                                    // Fallback: zona es campo texto libre en pedidos
                                    if (!$nomBarrio && !empty($pedido['zona'])) $nomBarrio = $pedido['zona'];
                                ?>

                                <div class="row row-cols-2 row-cols-md-4 g-2 mt-1">
                                    <?php if ($nomPais): ?>
                                    <div class="col">
                                        <span class="small text-muted d-block">País</span>
                                        <span class="fw-semibold"><?= htmlspecialchars($nomPais) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($pedido['codigo_postal'])): ?>
                                    <div class="col">
                                        <span class="small text-muted d-block">Código Postal</span>
                                        <span class="fw-semibold"><?= htmlspecialchars($pedido['codigo_postal']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($nomDepto): ?>
                                    <div class="col">
                                        <span class="small text-muted d-block">Departamento</span>
                                        <span class="fw-semibold"><?= htmlspecialchars($nomDepto) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($nomMuni): ?>
                                    <div class="col">
                                        <span class="small text-muted d-block">Municipio</span>
                                        <span class="fw-semibold"><?= htmlspecialchars($nomMuni) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($nomBarrio): ?>
                                    <div class="col">
                                        <span class="small text-muted d-block">Barrio / Zona</span>
                                        <span class="fw-semibold"><?= htmlspecialchars($nomBarrio) ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (empty($pedido['codigo_postal'])): ?>
                                <!-- Campos de dirección especial (pedidos sin código postal homologado) -->
                                <div class="row row-cols-2 row-cols-md-4 g-2 mt-2 pt-2 border-top">
                                    <?php if (!empty($pedido['departmentName'])): ?>
                                    <div class="col">
                                        <span class="small text-muted d-block">Departamento</span>
                                        <span class="fw-semibold"><?= htmlspecialchars($pedido['departmentName']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($pedido['municipalitiesName'])): ?>
                                    <div class="col">
                                        <span class="small text-muted d-block">Municipio</span>
                                        <span class="fw-semibold"><?= htmlspecialchars($pedido['municipalitiesName']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($pedido['postalCode'])): ?>
                                    <div class="col">
                                        <span class="small text-muted d-block">Código Postal</span>
                                        <span class="fw-semibold"><?= htmlspecialchars($pedido['postalCode']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($pedido['Location'])): ?>
                                    <div class="col">
                                        <span class="small text-muted d-block">Ubicación</span>
                                        <span class="fw-semibold"><?= htmlspecialchars($pedido['Location']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($pedido['betweenStreets'])): ?>
                                    <div class="col-12">
                                        <span class="small text-muted d-block">Entre Calles</span>
                                        <span class="fw-semibold"><?= htmlspecialchars($pedido['betweenStreets']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>

                            </div>
                        </div>

                        <?php if ($hasCoords): ?>
                        <div class="col-12 mt-2">
                            <label class="small text-muted fw-bold text-uppercase">
                                <i class="bi bi-geo-alt-fill text-danger me-1"></i>Coordenadas GPS
                            </label>
                            <div class="p-0 mt-1 rounded border overflow-hidden position-relative" style="cursor:pointer;" data-bs-toggle="modal" data-bs-target="#mapaModal">
                                <iframe
                                    id="mapaPreview"
                                    src="https://www.openstreetmap.org/export/embed.html?bbox=<?= ((float)$pedido['longitud'] - 0.005) ?>,<?= ((float)$pedido['latitud'] - 0.005) ?>,<?= ((float)$pedido['longitud'] + 0.005) ?>,<?= ((float)$pedido['latitud'] + 0.005) ?>&layer=mapnik&marker=<?= (float)$pedido['latitud'] ?>,<?= (float)$pedido['longitud'] ?>"
                                    width="100%" height="200" frameborder="0"
                                    style="pointer-events:none;display:block;"
                                    title="Mapa de ubicación"
                                    loading="lazy">
                                </iframe>
                                <div class="position-absolute top-0 end-0 m-2">
                                    <span class="badge bg-dark bg-opacity-75">
                                        <i class="bi bi-arrows-fullscreen me-1"></i>Expandir mapa
                                    </span>
                                </div>
                                <div class="position-absolute bottom-0 start-0 m-2">
                                    <span class="badge bg-dark bg-opacity-75">
                                        <i class="bi bi-crosshair me-1"></i>
                                        <?= number_format((float)$pedido['latitud'], 6) ?>, <?= number_format((float)$pedido['longitud'], 6) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="mt-1 d-flex gap-2">
                                <a href="https://www.google.com/maps?q=<?= (float)$pedido['latitud'] ?>,<?= (float)$pedido['longitud'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-map me-1"></i>Google Maps
                                </a>
                                <a href="https://waze.com/ul?ll=<?= (float)$pedido['latitud'] ?>,<?= (float)$pedido['longitud'] ?>&navigate=yes" target="_blank" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-signpost-2 me-1"></i>Waze
                                </a>
                                <span class="text-muted small align-self-center ms-auto"><?= number_format((float)$pedido['latitud'], 6) ?>, <?= number_format((float)$pedido['longitud'], 6) ?></span>
                            </div>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>

        <!-- Columna Derecha: Entrega y Productos -->
        <div class="col-lg-4 mb-4">
            
            <!-- TARJETA DEDICADA: FECHA DE ENTREGA -->
            <div class="card shadow border-0 mb-4 overflow-hidden">
                <div class="card-header bg-<?= explode(' ', $fechaBadgeColor)[0] ?> text-white py-3">
                    <h6 class="m-0 fw-bold"><i class="bi bi-calendar-check me-2"></i>Fecha de Entrega</h6>
                </div>
                <div class="card-body text-center py-4">
                    <?php if (empty($fechaEntregaRaw)): ?>
                        <div class="text-muted">
                            <i class="bi bi-calendar-x fs-1 mb-2 d-block"></i>
                            <span class="fw-bold fs-5">Pendiente de programación</span>
                        </div>
                    <?php else: ?>
                        <div class="mb-2">
                            <span class="badge bg-<?= $fechaBadgeColor ?> rounded-pill px-3 py-2 fs-6 mb-3">
                                <?= $fechaAlertaLabel ?>
                            </span>
                        </div>
                        <div class="display-5 fw-bold text-dark border-bottom pb-2 mb-2 mx-auto" style="max-width: fit-content;">
                            <?= date('d', strtotime($fechaEntregaRaw)) ?>
                        </div>
                        <div class="fs-4 text-uppercase text-muted fw-light">
                            <?= date('M Y', strtotime($fechaEntregaRaw)) ?>
                        </div>
                        <div class="mt-3 small text-secondary">
                             <i class="bi bi-clock me-1"></i> <?= $fechaSubLabel ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow border-0 mb-4 ">
                <div class="card-header bg-success text-white py-3">
                    <h6 class="m-0 fw-bold"><i class="bi bi-box-seam me-2"></i>Productos</h6>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($pedido['productos'])): ?>
                        <div class="p-4 text-center text-muted">No hay productos registrados.</div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                        <?php foreach ($pedido['productos'] as $prod): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold"><?= htmlspecialchars($prod['nombre']) ?></div>
                                    <small class="text-muted">Cant: <?= $prod['cantidad'] ?></small>
                                </div>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                        <div class="card-footer bg-light border-top-0 d-flex justify-content-between align-items-center py-3">
                            <span class="small text-muted fw-bold text-uppercase">Total a Pagar:</span>
                            <span class="fs-4 fw-bold text-primary">
                                <?= htmlspecialchars($pedido['moneda_codigo'] ?? 'GTQ') ?> 
                                <?= number_format($pedido['precio_total_local'] ?? 0, 2) ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Fila Inferior: Historial de Estados -->
        <div class="col-12">
            <div class="card shadow border-0">
                <div class="card-header bg-info text-white py-3">
                    <h6 class="m-0 fw-bold"><i class="bi bi-clock-history me-2"></i>Historial de Estados y Cambios</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($historialCambios)): ?>
                        <p class="text-muted text-center my-3">No hay historial de cambios registrado.</p>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($historialCambios as $cambio): 
                                $datosNuevos = $cambio['datos_nuevos'] ?? [];
                                $datosAnt = $cambio['datos_anteriores'] ?? [];
                                
                                $titulo = "Actualización de Pedido";
                                $detalle = "";
                                $badgeColor = CLR_LOGISTICA; // azul por defecto

                                if ($cambio['accion'] == 'crear') {
                                    $titulo = "Pedido Creado";
                                    $detalle = "El pedido fue ingresado al sistema.";
                                    $badgeColor = CLR_COMPLETADO; // verde
                                } else {
                                    // 1. Si hay cambio de estado (Formato Nuevo)
                                    if (isset($datosNuevos['estado'])) {
                                        $titulo = "Cambio de Estado: " . htmlspecialchars($datosNuevos['estado']);
                                        $detalle = $datosNuevos['observaciones'] ?? 'Sin observaciones.';
                                        $badgeColor = getBadgeColor($datosNuevos['estado'], $estadoColores);
                                    } 
                                    // 2. Si es formato antiguo pero cambió el ID del estado
                                    elseif (isset($datosNuevos['id_estado'])) {
                                        $nombreEs = $mapaEstados[$datosNuevos['id_estado']] ?? "Estado #" . $datosNuevos['id_estado'];
                                        $titulo = "Cambio de Estado: " . htmlspecialchars($nombreEs);
                                        $detalle = "Actualización de estado procesada por el sistema.";
                                        $badgeColor = getBadgeColor($nombreEs, $estadoColores);
                                    }
                                    // 3. Otros cambios — mostrar etiquetas amigables
                                    else {
                                        $etiquetas = [
                                            'coordenadas'       => 'Ubicación GPS',
                                            'id_pais'           => 'País',
                                            'id_departamento'   => 'Departamento',
                                            'id_municipio'      => 'Municipio',
                                            'id_barrio'         => 'Barrio / Zona',
                                            'id_codigo_postal'  => 'Código Postal',
                                            'codigo_postal'     => 'Código Postal',
                                            'direccion'         => 'Dirección',
                                            'zona'              => 'Zona',
                                            'municipio'         => 'Municipio',
                                            'barrio'            => 'Barrio',
                                            'destinatario'      => 'Destinatario',
                                            'telefono'          => 'Teléfono',
                                            'comentario'        => 'Comentario',
                                            'fecha_entrega'     => 'Fecha de Entrega',
                                            'precio_local'      => 'Precio Local',
                                            'precio_usd'        => 'Precio USD',
                                            'precio_total_local'=> 'Total Local',
                                            'precio_total_usd'  => 'Total USD',
                                            'id_proveedor'      => 'Proveedor',
                                            'id_cliente'        => 'Cliente',
                                            'id_vendedor'       => 'Vendedor',
                                            'id_estado'         => 'Estado',
                                            'activo'            => 'Estado activo',
                                        ];
                                        $keys = array_keys(array_diff_assoc($datosNuevos, $datosAnt));
                                        $labels = array_map(fn($k) => $etiquetas[$k] ?? ucfirst(str_replace('_', ' ', $k)), $keys);
                                        $detalle = "Cambios: " . implode(', ', array_unique($labels));
                                    }
                                }
                            ?>
                            <div class="d-flex mb-3 pb-3 border-bottom position-relative">
                                <div class="flex-shrink-0 me-3">
                                    <div class="badge p-2 rounded-circle" style="<?= $badgeColor ?>; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                        <i class="bi bi-clock"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between">
                                        <h6 class="mb-1 fw-bold"><?= $titulo ?></h6>
                                        <small class="text-muted"><?= date('d/m/Y H:i', strtotime($cambio['created_at'])) ?></small>
                                    </div>
                                    <p class="mb-0 text-muted small"><?= $detalle ?></p>
                                    <div class="small mt-1 text-secondary fst-italic">
                                        Por: <?= htmlspecialchars($cambio['usuario_nombre'] ?? 'Sistema') ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<?php if ($hasCoords): ?>
<!-- Modal Ver Mapa -->
<div class="modal fade" id="mapaModal" tabindex="-1" aria-labelledby="mapaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="mapaModalLabel">
                    <i class="bi bi-geo-alt-fill me-2"></i>Ubicación del Pedido #<?= htmlspecialchars($pedido['numero_orden']) ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <iframe
                    src="https://www.openstreetmap.org/export/embed.html?bbox=<?= ((float)$pedido['longitud'] - 0.01) ?>,<?= ((float)$pedido['latitud'] - 0.01) ?>,<?= ((float)$pedido['longitud'] + 0.01) ?>,<?= ((float)$pedido['latitud'] + 0.01) ?>&layer=mapnik&marker=<?= (float)$pedido['latitud'] ?>,<?= (float)$pedido['longitud'] ?>"
                    width="100%" height="540" frameborder="0"
                    style="display:block;"
                    title="Mapa de ubicación completo"
                    loading="lazy">
                </iframe>
            </div>
            <div class="modal-footer bg-light justify-content-between">
                <div class="d-flex gap-2">
                    <a href="https://www.google.com/maps?q=<?= (float)$pedido['latitud'] ?>,<?= (float)$pedido['longitud'] ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-map me-1"></i>Abrir en Google Maps
                    </a>
                    <a href="https://waze.com/ul?ll=<?= (float)$pedido['latitud'] ?>,<?= (float)$pedido['longitud'] ?>&navigate=yes" target="_blank" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-signpost-2 me-1"></i>Abrir en Waze
                    </a>
                    <a href="https://www.openstreetmap.org/?mlat=<?= (float)$pedido['latitud'] ?>&mlon=<?= (float)$pedido['longitud'] ?>#map=17/<?= (float)$pedido['latitud'] ?>/<?= (float)$pedido['longitud'] ?>" target="_blank" class="btn btn-outline-dark btn-sm">
                        <i class="bi bi-globe me-1"></i>OpenStreetMap
                    </a>
                </div>
                <span class="text-muted small">
                    <i class="bi bi-crosshair me-1"></i>
                    <?= number_format((float)$pedido['latitud'], 6) ?>, <?= number_format((float)$pedido['longitud'], 6) ?>
                </span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal Cambiar Estado -->
<div class="modal fade" id="cambiarEstadoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="<?= RUTA_URL ?>logistica/cambiarEstado/<?= $pedido['id'] ?>" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Cambiar Estado</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nuevo Estado</label>
                        <select name="estado" class="form-select" required>
                            <option value="">Seleccione un estado...</option>
                            <?php foreach ($estadosDisponibles as $est): ?>
                                <option value="<?= htmlspecialchars($est['nombre_estado']) ?>" <?= ($est['nombre_estado'] == $pedido['nombre_estado']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($est['nombre_estado']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text text-muted">Seleccione el nuevo estado para esta orden.</div>
                    </div>
                    
                    <!-- Campo de Fecha (Solo para Reprogramado) -->
                    <div id="reprogramarFechaSection" class="mb-3" style="display: none;">
                        <label class="form-label fw-bold"><i class="bi bi-calendar-event me-1"></i>Nueva Fecha de Entrega</label>
                        <input type="date" name="fecha_entrega" class="form-control" value="<?= $pedido['fecha_entrega'] ? date('Y-m-d', strtotime($pedido['fecha_entrega'])) : '' ?>">
                        <div class="form-text">Indique la nueva fecha estimada para la entrega.</div>
                    </div>

                    <!-- Campo Fecha de Liquidación (Solo para Entregado – liquidado) -->
                    <div id="liquidacionFechaSection" class="mb-3" style="display: none;">
                        <label class="form-label fw-bold text-success"><i class="bi bi-cash-coin me-1"></i>Fecha de Liquidación</label>
                        <input type="date" name="fecha_liquidacion" class="form-control"
                               value="<?= !empty($pedido['fecha_liquidacion']) ? date('Y-m-d', strtotime($pedido['fecha_liquidacion'])) : date('Y-m-d') ?>"
                               disabled>
                        <div class="form-text">Fecha en que el pedido fue liquidado/cobrado.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observaciones</label>
                        <textarea name="observaciones" class="form-control" rows="3" placeholder="Razón del cambio..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>



<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('#cambiarEstadoModal form');
    const submitBtn = form.querySelector('button[type="submit"]');
    const estadoSelect = form.querySelector('select[name="estado"]');
    const fechaSection = document.getElementById('reprogramarFechaSection');
    const fechaInput = fechaSection.querySelector('input[name="fecha_entrega"]');
    const liquidacionSection = document.getElementById('liquidacionFechaSection');
    const liquidacionInput = liquidacionSection.querySelector('input[name="fecha_liquidacion"]');

    function normalizeEstado(val) {
        return val.toUpperCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^A-Z0-9 ]/g, ' ').trim();
    }

    // Lógica para mostrar/ocultar campos condicionales
    estadoSelect.addEventListener('change', function() {
        const norm = normalizeEstado(this.value);
        // Reprogramado → mostrar fecha de entrega
        if (norm === 'REPROGRAMADO') {
            fechaSection.style.display = 'block';
            fechaInput.required = true;
            fechaInput.disabled = false;
        } else {
            fechaSection.style.display = 'none';
            fechaInput.required = false;
            fechaInput.disabled = true;
        }
        // Entregado liquidado → mostrar fecha de liquidación
        if (norm.includes('LIQUIDADO')) {
            liquidacionSection.style.display = 'block';
            liquidacionInput.required = true;
            liquidacionInput.disabled = false;
        } else {
            liquidacionSection.style.display = 'none';
            liquidacionInput.required = false;
            liquidacionInput.disabled = true;
        }
    });

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(form);
        const originalBtnText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...';

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(async response => {
            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch (err) {
                console.error('Error parseando JSON. Respuesta del servidor:', text);
                throw new Error('La respuesta del servidor no es un JSON válido.');
            }
        })
        .then(data => {
            if (data.success) {
                // Cerrar modal
                const modalEl = document.getElementById('cambiarEstadoModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
                
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: data.message || 'Estado actualizado correctamente',
                        confirmButtonColor: '#0d6efd'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    alert(data.message || 'Estado actualizado correctamente');
                    window.location.reload();
                }
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Error al actualizar el estado',
                        confirmButtonColor: '#0d6efd'
                    });
                } else {
                    alert(data.message || 'Error al actualizar el estado');
                }
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        })
        .catch(error => {
            console.error('Error en fetch:', error);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de Conexión',
                    text: error.message || 'Error en la comunicación con el servidor',
                    confirmButtonColor: '#0d6efd'
                });
            } else {
                alert(error.message || 'Error en la comunicación con el servidor');
            }
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        });
    });
});
</script>

<?php include("vista/includes/footer.php"); ?>
