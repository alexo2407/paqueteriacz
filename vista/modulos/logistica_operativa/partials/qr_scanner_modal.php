<?php
/**
 * vista/modulos/logistica_operativa/partials/qr_scanner_modal.php
 *
 * Parcial reutilizable de Modal para Escáner QR / Código de Barras por Cámara.
 * Incluye html5-qrcode y controlador JS global window.abrirScannerQR().
 */
?>
<!-- Modal Escáner QR por Cámara -->
<div class="modal fade" id="modalQrScanner" tabindex="-1" aria-labelledby="modalQrScannerLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white py-3 px-4" style="background: linear-gradient(135deg, #0f172a, #1e293b) !important;">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-primary bg-opacity-25 p-2 text-primary d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-qr-code-scan fs-5 text-info"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-white" id="modalQrScannerLabel">Escáner de Código QR / Barras</h5>
                        <small class="text-white-50" style="font-size: 0.75rem;">Apunta la cámara de tu dispositivo hacia la etiqueta del paquete</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar" id="btnCerrarQrModal"></button>
            </div>
            
            <div class="modal-body p-4 bg-light">
                <!-- Select de cámara y controles -->
                <div class="row g-2 align-items-center mb-3">
                    <div class="col">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-camera-video"></i></span>
                            <select class="form-select border-start-0 font-monospace small" id="qrCamaraSelect">
                                <option value="">Cargando cámaras disponibles...</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-auto d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="btnQrFlash" title="Activar/Desactivar Linterna">
                            <i class="bi bi-lightbulb"></i> <span class="d-none d-sm-inline">Linterna</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnQrCambiarCam" title="Cambiar Cámara">
                            <i class="bi bi-arrow-repeat"></i> <span class="d-none d-sm-inline">Cambiar</span>
                        </button>
                    </div>
                </div>

                <!-- Visor de Escáner por Cámara -->
                <div class="position-relative rounded-4 overflow-hidden shadow-inner bg-black" style="min-height: 280px; max-height: 420px;">
                    <div id="qrReaderRegion" style="width: 100%; min-height: 280px;"></div>
                    
                    <!-- Overlay de apuntado visual -->
                    <div class="position-absolute top-50 start-50 translate-middle pointer-events-none d-flex flex-column align-items-center justify-content-center" style="z-index: 10; width: 220px; height: 220px; border: 2px dashed rgba(59, 130, 246, 0.8); border-radius: 20px; box-shadow: 0 0 0 4000px rgba(0, 0, 0, 0.45);">
                        <div class="spinner-grow spinner-grow-sm text-info mb-2" role="status" id="qrScanIndicator">
                            <span class="visually-hidden">Escaneando...</span>
                        </div>
                        <span class="badge bg-primary bg-opacity-75 text-white px-3 py-1 rounded-pill small font-monospace">Buscando código...</span>
                    </div>
                </div>

                <!-- Notificación / Feedback de estado -->
                <div id="qrStatusFeedback" class="alert alert-info d-flex align-items-center justify-content-between mt-3 mb-0 py-2 px-3 rounded-3 small">
                    <span><i class="bi bi-info-circle me-2"></i>Asegúrate de tener buena iluminación y enfocar bien la etiqueta.</span>
                    <span class="badge bg-dark font-monospace" id="qrCountBadge">0 escaneados</span>
                </div>
            </div>

            <div class="modal-footer bg-white py-2 px-4 justify-content-between">
                <span class="text-muted small font-monospace d-flex align-items-center">
                    <span class="badge bg-success-subtle text-success border border-success-subtle me-2">🟢 Cámara activa</span>
                    Auto-procesamiento activo
                </span>
                <button type="button" class="btn btn-secondary btn-sm px-4 rounded-3" data-bs-dismiss="modal">Cerrar Visor</button>
            </div>
        </div>
    </div>
</div>

<!-- Incluir html5-qrcode library y el script de control -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<script src="<?= RUTA_URL ?>vista/modulos/logistica_operativa/js/qr_scanner_modal.js"></script>
