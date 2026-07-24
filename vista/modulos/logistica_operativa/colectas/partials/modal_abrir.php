<?php
/**
 * vista/modulos/logistica_operativa/colectas/partials/modal_abrir.php
 *
 * Modal Bootstrap 5 para abrir una nueva colecta.
 * Se incluye desde index.php.
 *
 * Seguridad:
 *   - id_operador se obtiene del JWT (usuario autenticado) en el endpoint.
 *   - El formulario nunca envía id_operador.
 *   - CSRF token validado en sesión.
 */
?>
<div class="modal fade" id="modalAbrirColecta" tabindex="-1"
     aria-labelledby="modalAbrirColectaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content border-0 shadow-lg">

            <!-- Header -->
            <div class="modal-header"
                 style="background:linear-gradient(135deg,#061C4C,#0B4EA2);">
                <h5 class="modal-title text-white fw-semibold" id="modalAbrirColectaLabel">
                    <i class="bi bi-plus-circle me-2"></i>Abrir Colecta
                </h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <!-- Body -->
            <div class="modal-body p-4">

                <!-- Alerta de resultado (hidden por defecto) -->
                <div id="alertaAbrirColecta" class="alert d-none mb-3" role="alert"></div>

                <form id="formAbrirColecta" novalidate>

                    <!-- Cliente -->
                    <div class="mb-3">
                        <label for="abrirIdCliente" class="form-label fw-semibold">
                            Cliente <span class="text-danger">*</span>
                        </label>
                        <?php if (!empty($clientes)): ?>
                        <select id="abrirIdCliente" name="id_cliente"
                                class="form-select" required>
                            <option value="">— Seleccionar cliente —</option>
                            <?php foreach ($clientes as $cli): ?>
                            <option value="<?= (int)$cli['id'] ?>">
                                <?= htmlspecialchars($cli['nombre']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <input type="number" id="abrirIdCliente" name="id_cliente"
                               class="form-control" placeholder="ID del cliente" required min="1">
                        <div class="form-text text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            No hay clientes con pedidos en estado "Pendiente recolección" (estado 11).
                            Ingresa el ID manualmente.
                        </div>
                        <?php endif; ?>
                        <div class="invalid-feedback">Selecciona un cliente.</div>
                    </div>

                    <!-- Fecha -->
                    <div class="mb-3">
                        <label for="abrirFecha" class="form-label fw-semibold">
                            Fecha <span class="text-danger">*</span>
                        </label>
                        <input type="date" id="abrirFecha" name="fecha"
                               class="form-control" required
                               value="<?= date('Y-m-d') ?>">
                        <div class="invalid-feedback">Ingresa una fecha válida.</div>
                    </div>

                    <!-- Turno -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Turno <span class="text-danger">*</span>
                        </label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio"
                                       name="turno" id="turnoManana" value="MANANA" checked>
                                <label class="form-check-label" for="turnoManana">
                                    <i class="bi bi-sun text-warning me-1"></i>Mañana
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio"
                                       name="turno" id="turnoTarde" value="TARDE">
                                <label class="form-check-label" for="turnoTarde">
                                    <i class="bi bi-moon text-primary me-1"></i>Tarde
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Nota modo sombra -->
                    <div class="alert alert-light border border-warning-subtle py-2 px-3 mb-0">
                        <small class="text-muted">
                            <i class="bi bi-shield-check text-warning me-1"></i>
                            <strong>Modo sombra activo:</strong> la apertura registra la colecta sin
                            modificar estados de pedidos, inventario ni stock.
                        </small>
                    </div>

                </form>
            </div>

            <!-- Footer -->
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary"
                        data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning fw-semibold"
                        id="btnConfirmarAbrir">
                    <span id="spinnerAbrir" class="spinner-border spinner-border-sm me-1 d-none"
                          role="status" aria-hidden="true"></span>
                    <i class="bi bi-check-circle me-1" id="iconoAbrir"></i>
                    Abrir colecta
                </button>
            </div>

        </div>
    </div>
</div>
