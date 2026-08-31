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
                <!-- #qrScannerViewport es el único position:relative de referencia.
                     El overlay se inyecta por JS dentro de #qrReaderRegion después de
                     que html5-qrcode inicializa el <video>, garantizando alineación exacta. -->
                <div id="qrScannerViewport" class="rounded-4 overflow-hidden shadow-inner bg-black"
                     style="position: relative; width: 100%; height: 360px;">

                    <!-- html5-qrcode renderiza su <video> aquí.
                         NO se fuerza height para no interferir con el cálculo interno de qrbox. -->
                    <div id="qrReaderRegion" style="width: 100%;"></div>

                    <!-- Overlay de apuntado visual — centrado matemáticamente respecto al viewport.
                         pointer-events:none para no bloquear eventos del video subyacente. -->
                    <div id="qrScanOverlay"
                         style="
                             position: absolute;
                             left: 50%;
                             top: 50%;
                             transform: translate(-50%, -50%);
                             width: 220px;
                             height: 220px;
                             z-index: 20;
                             pointer-events: none;
                             display: flex;
                             flex-direction: column;
                             align-items: center;
                             justify-content: center;
                         ">

                        <!-- Marco punteado: exactamente 220×220, mismo eje que el qrbox configurado en JS -->
                        <div style="
                                 position: absolute;
                                 inset: 0;
                                 border: 2px dashed rgba(59, 130, 246, 0.85);
                                 border-radius: 16px;
                                 box-shadow: 0 0 0 2000px rgba(0, 0, 0, 0.42);
                             "></div>


                        <!-- Línea horizontal de escaneo — ocupa el ancho interno del marco (inset:0 lateral) -->
                        <div id="qrScanLine"
                             style="
                                 position: absolute;
                                 left: 4px;
                                 right: 4px;
                                 height: 2px;
                                 background: linear-gradient(90deg, transparent, rgba(59,130,246,0.9) 20%, #60a5fa 50%, rgba(59,130,246,0.9) 80%, transparent);
                                 border-radius: 1px;
                                 animation: qrScanLineSweep 2s ease-in-out infinite;
                             "></div>

                        <!-- Indicador + texto — parte SUPERIOR del marco, centrado en X.
                             Posición relativa al #qrScanOverlay (220×220), NO a la línea animada.
                             La línea sigue moviéndose de forma independiente. -->
                        <div style="position:absolute; top:10px; left:50%; transform:translateX(-50%);
                                    display:flex; align-items:center; gap:6px; white-space:nowrap; z-index:21;">
                            <div class="spinner-grow spinner-grow-sm text-info" role="status" id="qrScanIndicator">
                                <span class="visually-hidden">Escaneando...</span>
                            </div>
                            <span class="badge bg-primary bg-opacity-75 text-white px-3 py-1 rounded-pill small font-monospace">Buscando código...</span>
                        </div>

                    </div><!-- /#qrScanOverlay -->
                </div><!-- /#qrScannerViewport -->

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
