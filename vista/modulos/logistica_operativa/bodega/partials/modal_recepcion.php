<?php
/**
 * vista/modulos/logistica_operativa/bodega/partials/modal_recepcion.php
 *
 * Modal para registrar la recepción física de un paquete en bodega.
 *
 * Seguridad:
 *   - id_operador nunca se envía desde el formulario; el endpoint lo extrae del JWT.
 *   - UUID generado de forma segura en el cliente (crypto.randomUUID o fallback).
 *   - Las ubicaciones se filtran por bodega seleccionada.
 */
?>
<div class="modal fade" id="modalRecepcion" tabindex="-1"
     aria-labelledby="modalRecepcionLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">

            <!-- Header -->
            <div class="modal-header"
                 style="background:linear-gradient(135deg,#061C4C,#0B4EA2);">
                <h5 class="modal-title text-white fw-semibold" id="modalRecepcionLabel">
                    <i class="bi bi-box-arrow-in-down me-2"></i>Registrar Recepción
                </h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <!-- Body -->
            <div class="modal-body p-4">

                <div id="alertaRecepcion" class="alert d-none mb-3" role="alert"></div>

                <!-- Info pedido (solo lectura) -->
                <div class="alert alert-light border mb-3 small py-2">
                    <i class="bi bi-info-circle text-primary me-1"></i>
                    Pedido: <strong id="recepPedidoInfo">—</strong>
                </div>

                <form id="formRecepcion" novalidate>
                    <div class="row g-3">

                        <!-- Bodega -->
                        <div class="col-12 col-md-6">
                            <label for="recepIdBodega" class="form-label fw-semibold">
                                Bodega <span class="text-danger">*</span>
                            </label>
                            <select id="recepIdBodega" name="id_bodega"
                                    class="form-select" required>
                                <option value="">— Seleccionar bodega —</option>
                            </select>
                            <div class="invalid-feedback">Selecciona una bodega.</div>
                        </div>

                        <!-- Tipo de recepción -->
                        <div class="col-12 col-md-6">
                            <label for="recepTipo" class="form-label fw-semibold">
                                Tipo de recepción <span class="text-danger">*</span>
                            </label>
                            <select id="recepTipo" name="tipo_recepcion"
                                    class="form-select" required>
                                <option value="">— Seleccionar tipo —</option>
                                <option value="COLECTA">Colecta</option>
                                <option value="RETORNO_RUTA">Retorno de ruta</option>
                                <option value="INCIDENCIA">Incidencia</option>
                                <option value="DEVOLUCION">Devolución</option>
                            </select>
                            <div class="invalid-feedback">Selecciona el tipo.</div>
                        </div>

                        <!-- Ubicación inicial (opcional) -->
                        <div class="col-12">
                            <label for="recepIdUbicacion" class="form-label fw-semibold">
                                Ubicación inicial
                                <span class="text-muted fw-normal">(opcional)</span>
                            </label>
                            <select id="recepIdUbicacion" name="id_ubicacion"
                                    class="form-select" disabled>
                                <option value="">— Selecciona primero una bodega —</option>
                            </select>
                            <div class="form-text">
                                Si no asignas ubicación ahora, puedes hacerlo después.
                            </div>
                        </div>

                        <!-- Fecha y hora de recepción -->
                        <div class="col-12 col-md-6">
                            <label for="recepFecha" class="form-label fw-semibold">
                                Fecha y hora <span class="text-danger">*</span>
                            </label>
                            <input type="datetime-local" id="recepFecha" name="recibido_at"
                                   class="form-control" required>
                            <div class="invalid-feedback">Ingresa una fecha y hora válidas.</div>
                        </div>

                        <!-- Observación -->
                        <div class="col-12 col-md-6">
                            <label for="recepObservacion" class="form-label fw-semibold">
                                Observación
                                <span class="text-muted fw-normal">(opcional)</span>
                            </label>
                            <input type="text" id="recepObservacion" name="observacion"
                                   class="form-control" maxlength="500"
                                   placeholder="Ej: Paquete en buen estado…">
                        </div>

                    </div>

                    <!-- Nota modo sombra -->
                    <div class="alert alert-light border border-warning-subtle py-2 px-3 mt-3 mb-0">
                        <small class="text-muted">
                            <i class="bi bi-shield-check text-warning me-1"></i>
                            <strong>Modo sombra activo:</strong> la recepción se registra sin
                            modificar estados de pedidos, inventario ni stock.
                        </small>
                    </div>
                </form>
            </div>

            <!-- Footer -->
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary"
                        data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary fw-semibold"
                        id="btnConfirmarRecepcion">
                    <span id="spinnerRecepcion"
                          class="spinner-border spinner-border-sm me-1 d-none"
                          role="status" aria-hidden="true"></span>
                    <i class="bi bi-check-circle me-1" id="iconoRecepcion"></i>
                    Registrar recepción
                </button>
            </div>

        </div>
    </div>
</div>
