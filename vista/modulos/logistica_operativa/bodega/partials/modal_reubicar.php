<?php
/**
 * vista/modulos/logistica_operativa/bodega/partials/modal_reubicar.php
 *
 * Modal para reubicar un paquete dentro de la misma bodega.
 *
 * Seguridad:
 *   - Solo muestra ubicaciones de la misma bodega.
 *   - No permite traslado entre bodegas.
 *   - id_operador nunca se envía.
 */
?>
<div class="modal fade" id="modalReubicar" tabindex="-1"
     aria-labelledby="modalReubicarLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px; overflow:hidden;">

            <!-- Header -->
            <div class="modal-header text-white" style="background:#0b4ea2;">
                <h5 class="modal-title fw-bold text-white d-flex align-items-center" id="modalReubicarLabel">
                    <i class="bi bi-arrow-left-right me-2"></i>Reubicar paquete
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <!-- Body -->
            <div class="modal-body p-4">

                <div id="alertaReubicar" class="alert d-none mb-3" role="alert"></div>

                <form id="formReubicar" novalidate>

                    <!-- Ubicación actual (solo lectura con ícono de candado) -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Ubicación de origen (actual)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="bi bi-geo-alt-fill text-primary"></i>
                            </span>
                            <input type="text" class="form-control bg-light font-monospace fw-bold border-start-0"
                                   id="reubicarActual" readonly value="📍 INC-E01-A1">
                            <span class="input-group-text bg-light border-start-0">
                                <i class="bi bi-lock-fill text-muted"></i>
                            </span>
                        </div>
                    </div>

                    <!-- Ubicación destino -->
                    <div class="mb-3">
                        <label for="reubicarDestino" class="form-label fw-semibold small">
                            Nueva ubicación (destino) <span class="text-danger">*</span>
                        </label>
                        <select id="reubicarDestino" name="id_ubicacion_destino"
                                class="form-select" required>
                            <option value="">Selecciona la nueva ubicación...</option>
                        </select>
                        <div class="invalid-feedback">Selecciona una ubicación destino.</div>
                    </div>

                    <!-- Elecciones rápidas -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold small d-block">Elecciones rápidas</label>
                        <div class="d-flex gap-2 flex-wrap" id="quickLocationsContainer">
                            <button type="button" class="btn btn-quick-location" data-code="INC-E01-A5"><i class="bi bi-geo-alt me-1"></i>INC-E01-A5</button>
                            <button type="button" class="btn btn-quick-location" data-code="DEV-E01"><i class="bi bi-geo-alt me-1"></i>DEV-E01</button>
                            <button type="button" class="btn btn-quick-location" data-code="CUS-AREA-01"><i class="bi bi-geo-alt me-1"></i>CUS-AREA-01</button>
                        </div>
                    </div>

                    <!-- Motivo -->
                    <div class="mb-3">
                        <label for="reubicarMotivo" class="form-label fw-semibold small">
                            Motivo de la reubicación <span class="text-danger">*</span>
                        </label>
                        <select id="reubicarMotivo" name="motivo" class="form-select">
                            <option value="">Selecciona o ingresa el motivo...</option>
                            <option value="Organización de inventario" selected>Organización de inventario</option>
                            <option value="Optimización de espacio">Optimización de espacio</option>
                            <option value="Traslado por incidencia">Traslado por incidencia</option>
                            <option value="Preparación para despacho">Preparación para despacho</option>
                        </select>
                    </div>

                    <!-- Info banner -->
                    <div class="alert alert-info border-info-subtle py-2 px-3 mb-0 rounded-3 small">
                        <i class="bi bi-info-circle me-1"></i>
                        <strong>Importante:</strong> La reubicación actualiza la ubicación física del paquete. El inventario y el estado del pedido no se modifican.
                    </div>

                </form>
            </div>

            <!-- Footer -->
            <div class="modal-footer border-top-0 px-4 pb-4 pt-0">
                <button type="button" class="btn btn-outline-secondary px-3"
                        data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary fw-bold px-4 shadow-sm"
                        id="btnConfirmarReubicar" style="background:#0d6efd;">
                    <span id="spinnerReubicar"
                          class="spinner-border spinner-border-sm me-1 d-none"
                          role="status" aria-hidden="true"></span>
                    <i class="bi bi-check-circle me-1" id="iconoReubicar"></i>
                    Confirmar reubicación
                </button>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('quickLocationsContainer');
    const selectDest = document.getElementById('reubicarDestino');
    if (container && selectDest) {
        container.querySelectorAll('.btn-quick-location').forEach(btn => {
            btn.addEventListener('click', function() {
                const code = this.getAttribute('data-code');
                for (let i = 0; i < selectDest.options.length; i++) {
                    if (selectDest.options[i].text.includes(code) || selectDest.options[i].value === code) {
                        selectDest.selectedIndex = i;
                        break;
                    }
                }
            });
        });
    }
});
</script>
