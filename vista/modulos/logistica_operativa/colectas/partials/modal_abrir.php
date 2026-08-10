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
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px; overflow:hidden;">

            <!-- Header -->
            <div class="modal-header bg-white border-bottom-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center" id="modalAbrirColectaLabel">
                    <i class="bi bi-plus-circle-fill me-2 text-primary"></i>Abrir colecta
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <!-- Body -->
            <div class="modal-body p-4">

                <!-- Alerta de resultado (hidden por defecto) -->
                <div id="alertaAbrirColecta" class="alert d-none mb-3" role="alert"></div>

                <form id="formAbrirColecta" novalidate>

                    <!-- Cliente -->
                    <div class="mb-3">
                        <label for="abrirIdCliente" class="form-label fw-semibold small">
                            Cliente <span class="text-danger">*</span>
                        </label>
                        <?php if (!empty($clientes)): ?>
                        <select id="abrirIdCliente" name="id_cliente"
                                class="form-select form-select-lg fs-6" required>
                            <option value="">Buscar por nombre o ID del cliente...</option>
                            <?php foreach ($clientes as $cli): ?>
                            <option value="<?= (int)$cli['id'] ?>">
                                🏢 <?= htmlspecialchars($cli['nombre']) ?> (ID: <?= (int)$cli['id'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <input type="number" id="abrirIdCliente" name="id_cliente"
                               class="form-control form-control-lg fs-6" placeholder="Buscar por nombre o ID del cliente..." required min="1">
                        <?php endif; ?>
                        <div class="form-text text-muted small mt-1">
                            <i class="bi bi-info-circle me-1"></i>
                            Solo se muestran clientes con pedidos en estado "Pendiente recolección".
                        </div>
                        <div class="invalid-feedback">Selecciona un cliente válido.</div>
                    </div>

                    <?php if (!empty($isAdmin) && $isAdmin): ?>
                    <!-- Proveedor (Solo Admin) -->
                    <div class="mb-3">
                        <label for="abrirIdProveedor" class="form-label fw-semibold small">
                            Proveedor / Courier <span class="text-danger">*</span>
                        </label>
                        <select id="abrirIdProveedor" name="id_proveedor"
                                class="form-select form-select-lg fs-6" required>
                            <option value="">Seleccionar Proveedor...</option>
                            <?php foreach ($proveedores as $prov): ?>
                            <option value="<?= (int)$prov['id'] ?>">
                                🚚 <?= htmlspecialchars($prov['nombre']) ?> (ID: <?= (int)$prov['id'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Selecciona un proveedor válido.</div>
                    </div>
                    <?php endif; ?>

                    <!-- Fecha -->
                    <div class="mb-3">
                        <label for="abrirFecha" class="form-label fw-semibold small">
                            Fecha <span class="text-danger">*</span>
                        </label>
                        <input type="date" id="abrirFecha" name="fecha"
                               class="form-control" required
                               value="<?= date('Y-m-d') ?>">
                        <div class="invalid-feedback">Ingresa una fecha válida.</div>
                    </div>

                    <!-- Turno -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">
                            Turno <span class="text-danger">*</span>
                        </label>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="shift-select-card active d-flex align-items-center w-100" id="cardTurnoManana">
                                    <input type="radio" name="turno" id="turnoManana" value="MANANA" checked class="d-none">
                                    <i class="bi bi-sun fs-4 text-warning me-2"></i>
                                    <div>
                                        <div class="fw-bold small">Mañana</div>
                                        <div class="text-muted" style="font-size:0.7rem;">06:00 - 12:00</div>
                                    </div>
                                </label>
                            </div>
                            <div class="col-6">
                                <label class="shift-select-card d-flex align-items-center w-100" id="cardTurnoTarde">
                                    <input type="radio" name="turno" id="turnoTarde" value="TARDE" class="d-none">
                                    <i class="bi bi-moon fs-4 text-primary me-2"></i>
                                    <div>
                                        <div class="fw-bold small">Tarde</div>
                                        <div class="text-muted" style="font-size:0.7rem;">12:00 - 18:00</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Banner Modo Sombra -->
                    <div class="alert alert-warning border-warning-subtle text-dark py-2 px-3 mb-3 d-flex align-items-center rounded-3 small">
                        <i class="bi bi-shield-lock fs-5 me-2 text-warning"></i>
                        <div>
                            <strong>Modo sombra activo:</strong> esta acción no modifica pedidos, inventario ni stock.
                        </div>
                    </div>

                </form>
            </div>

            <!-- Footer -->
            <div class="modal-footer border-top-0 px-4 pb-4 pt-0">
                <button type="button" class="btn btn-outline-secondary px-3"
                        data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning fw-bold text-dark px-4 shadow-sm"
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const rManana = document.getElementById('turnoManana');
    const rTarde  = document.getElementById('turnoTarde');
    const cManana = document.getElementById('cardTurnoManana');
    const cTarde  = document.getElementById('cardTurnoTarde');

    if (cManana && cTarde) {
        cManana.addEventListener('click', function() {
            rManana.checked = true;
            cManana.classList.add('active');
            cTarde.classList.remove('active');
        });
        cTarde.addEventListener('click', function() {
            rTarde.checked = true;
            cTarde.classList.add('active');
            cManana.classList.remove('active');
        });
    }
});
</script>
