<?php
/**
 * vista/modulos/logistica_operativa/bodega/partials/buscador.php
 *
 * Buscador de paquetes por ID de pedido o número de orden.
 *
 * Seguridad:
 *   - El campo es escapado antes de mostrarlo.
 *   - No ejecuta búsqueda automática en cada tecla.
 *   - Se activa con Enter o con el botón.
 *   - Consulta vacía es rechazada.
 */
?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form id="formBuscador" novalidate autocomplete="off">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-8 col-lg-9">
                    <label for="inputBusqueda" class="form-label fw-semibold mb-1">
                        <i class="bi bi-search me-1"></i>Buscar paquete
                    </label>
                    <input type="text"
                           id="inputBusqueda"
                           name="q"
                           class="form-control"
                           placeholder="Ingresa ID de pedido o número de orden…"
                           maxlength="120"
                           spellcheck="false">
                    <div id="alertaBusqueda" class="text-danger small mt-1 d-none"></div>
                </div>
                <div class="col-12 col-md-4 col-lg-3 d-flex gap-2">
                    <button type="submit"
                            id="btnBuscar"
                            class="btn btn-primary w-100 fw-semibold">
                        <span id="spinnerBuscar"
                              class="spinner-border spinner-border-sm me-1 d-none"
                              role="status" aria-hidden="true"></span>
                        <i class="bi bi-search me-1" id="iconoBuscar"></i>
                        Buscar
                    </button>
                    <button type="button"
                            id="btnLimpiarBusqueda"
                            class="btn btn-outline-secondary"
                            title="Limpiar">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
