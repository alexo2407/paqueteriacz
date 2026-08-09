<?php
/**
 * vista/modulos/logistica_operativa/bodega/partials/modal_ubicar.php
 *
 * Modal para asignar una ubicación física a una recepción en estado RECIBIDO.
 *
 * Seguridad:
 *   - Solo muestra ubicaciones activas de la misma bodega de la recepción.
 *   - id_operador nunca se envía desde el formulario.
 *   - La bodega de la recepción es de solo lectura.
 */
?>
<div class="modal fade" id="modalUbicar" tabindex="-1"
     aria-labelledby="modalUbicarLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content border-0 shadow-lg">

            <!-- Header -->
            <div class="modal-header"
                 style="background:linear-gradient(135deg,#1B5E20,#2E7D32);">
                <h5 class="modal-title text-white fw-semibold" id="modalUbicarLabel">
                    <i class="bi bi-geo-alt-fill me-2"></i>Asignar Ubicación
                </h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <!-- Body -->
            <div class="modal-body p-4">

                <div id="alertaUbicar" class="alert d-none mb-3" role="alert"></div>

                <form id="formUbicar" novalidate>

                    <!-- Bodega (solo lectura) -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Bodega de la recepción</label>
                        <input type="text" class="form-control bg-light"
                               id="ubicarBodegaNombre" readonly>
                        <input type="hidden" id="ubicarIdBodega">
                        <input type="hidden" id="ubicarIdRecepcion">
                    </div>

                    <!-- Ubicación disponible -->
                    <div class="mb-3">
                        <label for="ubicarIdUbicacion" class="form-label fw-semibold">
                            Ubicación <span class="text-danger">*</span>
                        </label>
                        <select id="ubicarIdUbicacion" name="id_ubicacion"
                                class="form-select" required>
                            <option value="">— Cargando ubicaciones… —</option>
                        </select>
                        <div class="invalid-feedback">Selecciona una ubicación.</div>
                    </div>

                    <!-- Motivo (opcional) -->
                    <div class="mb-0">
                        <label for="ubicarMotivo" class="form-label fw-semibold">
                            Motivo <span class="text-muted fw-normal">(opcional)</span>
                        </label>
                        <input type="text" id="ubicarMotivo" name="motivo"
                               class="form-control" maxlength="300"
                               placeholder="Ej: Asignación inicial…">
                    </div>
                </form>
            </div>

            <!-- Footer -->
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary"
                        data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success fw-semibold"
                        id="btnConfirmarUbicar">
                    <span id="spinnerUbicar"
                          class="spinner-border spinner-border-sm me-1 d-none"
                          role="status" aria-hidden="true"></span>
                    <i class="bi bi-check-circle me-1" id="iconoUbicar"></i>
                    Asignar ubicación
                </button>
            </div>

        </div>
    </div>
</div>
