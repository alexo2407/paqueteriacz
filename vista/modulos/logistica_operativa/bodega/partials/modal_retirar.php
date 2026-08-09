<?php
/**
 * vista/modulos/logistica_operativa/bodega/partials/modal_retirar.php
 *
 * Modal de confirmación para retirar un paquete de su ubicación actual.
 *
 * Seguridad:
 *   - id_operador nunca se envía desde el formulario.
 *   - Muestra resumen solo lectura: pedido, bodega, ubicación actual.
 */
?>
<div class="modal fade" id="modalRetirar" tabindex="-1"
     aria-labelledby="modalRetirarLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content border-0 shadow-lg">

            <!-- Header -->
            <div class="modal-header bg-danger">
                <h5 class="modal-title text-white fw-semibold" id="modalRetirarLabel">
                    <i class="bi bi-box-arrow-up me-2"></i>Retirar de Ubicación
                </h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <!-- Body -->
            <div class="modal-body p-4">

                <div id="alertaRetirar" class="alert d-none mb-3" role="alert"></div>

                <!-- Resumen -->
                <div class="alert alert-danger border mb-3 small py-2">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    El paquete será retirado de su ubicación. Esta acción puede deshacerse
                    registrando una nueva recepción.
                </div>

                <dl class="row mb-0 small">
                    <dt class="col-4">Pedido:</dt>
                    <dd class="col-8 font-monospace" id="retirarPedidoInfo">—</dd>
                    <dt class="col-4">Bodega:</dt>
                    <dd class="col-8" id="retirarBodegaInfo">—</dd>
                    <dt class="col-4">Ubicación:</dt>
                    <dd class="col-8 font-monospace" id="retirarUbicacionInfo">—</dd>
                </dl>

                <form id="formRetirar" novalidate>

                    <!-- Motivo -->
                    <div class="mt-3 mb-0">
                        <label for="retirarMotivo" class="form-label fw-semibold">
                            Motivo
                        </label>
                        <select id="retirarMotivo" name="motivo" class="form-select">
                            <option value="">— Sin motivo específico —</option>
                            <option value="Reprogramación">Reprogramación</option>
                            <option value="Devolución al cliente">Devolución al cliente</option>
                            <option value="Entrega a mensajero">Entrega a mensajero</option>
                            <option value="Revisión de incidencia">Revisión de incidencia</option>
                        </select>
                    </div>
                </form>
            </div>

            <!-- Footer -->
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary"
                        data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger fw-semibold"
                        id="btnConfirmarRetirar">
                    <span id="spinnerRetirar"
                          class="spinner-border spinner-border-sm me-1 d-none"
                          role="status" aria-hidden="true"></span>
                    <i class="bi bi-check-circle me-1" id="iconoRetirar"></i>
                    Confirmar retiro
                </button>
            </div>

        </div>
    </div>
</div>
