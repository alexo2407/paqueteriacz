<?php
/**
 * vista/modulos/logistica_operativa/bodega/partials/historial.php
 *
 * Tabla de historial de movimientos físicos del paquete.
 * Datos inyectados por JS desde el endpoint /ubicaciones/historial.
 */
?>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-light fw-bold d-flex align-items-center justify-content-between py-3">
        <span><i class="bi bi-clock-history me-2 text-primary"></i>Historial de movimientos</span>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnActualizarHistorial">
            <i class="bi bi-arrow-clockwise me-1"></i>Actualizar
        </button>
    </div>
    <div class="card-body p-4">
        <!-- Timeline vertical -->
        <div class="timeline-vertical" id="containerTimeline">
            <div class="text-center text-muted py-4" id="trHistorialVacio">
                <i class="bi bi-clock-history display-6 opacity-25 d-block mb-2"></i>
                Busca un paquete para ver su historial de movimientos.
            </div>
        </div>

        <div class="alert alert-info border-info-subtle mt-4 mb-0 py-2 px-3 small rounded-3 d-flex align-items-center">
            <i class="bi bi-info-circle me-2 fs-5 text-primary"></i>
            <div>Solo puede existir una ubicación física activa por paquete. Cada reubicación genera un registro en el historial.</div>
        </div>
    </div>
</div>
