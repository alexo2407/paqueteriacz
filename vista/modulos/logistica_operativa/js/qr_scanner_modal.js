/**
 * vista/modulos/logistica_operativa/js/qr_scanner_modal.js
 *
 * Controlador del Modal de Escáner QR por Cámara para Logística Operativa.
 * Proporciona window.abrirScannerQR() con soporte para Web Audio API, selección
 * de cámara, respuesta háptica y auto-llenado de inputs.
 */

(function () {
    'use strict';

    let html5QrcodeInstance = null;
    let targetInputEl = null;
    let onSuccessCallback = null;
    let scanCount = 0;
    let audioCtx = null;
    let availableCameras = [];
    let currentCameraId = null;

    /**
     * Web Audio API: Emite un tono sintetizado sin requerir archivos mp3 externos.
     * @param {'exito'|'error'} tipo 
     */
    function reproducirBeep(tipo) {
        try {
            if (!audioCtx) {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (AudioContext) audioCtx = new AudioContext();
            }
            if (!audioCtx) return;
            if (audioCtx.state === 'suspended') {
                audioCtx.resume();
            }

            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();

            osc.connect(gain);
            gain.connect(audioCtx.destination);

            if (tipo === 'exito') {
                // Tono doble de éxito (agudo y limpio)
                osc.type = 'sine';
                osc.frequency.setValueAtTime(880, audioCtx.currentTime); // A5
                osc.frequency.exponentialRampToValueAtTime(1320, audioCtx.currentTime + 0.08); // E6
                gain.gain.setValueAtTime(0.15, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.12);
                osc.start(audioCtx.currentTime);
                osc.stop(audioCtx.currentTime + 0.12);
            } else {
                // Tono grave de error
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(300, audioCtx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(150, audioCtx.currentTime + 0.2);
                gain.gain.setValueAtTime(0.2, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.2);
                osc.start(audioCtx.currentTime);
                osc.stop(audioCtx.currentTime + 0.2);
            }
        } catch (e) {
            console.warn('Audio Beep no soportado:', e);
        }
    }

    /**
     * Hace vibrar el dispositivo si la API es soportada.
     */
    function darFeedbackHaptico() {
        if ('vibrate' in navigator) {
            try {
                navigator.vibrate([80, 40, 80]);
            } catch (e) { }
        }
    }

    /**
     * Detiene la cámara de forma limpia.
     */
    async function detenerCamara() {
        if (html5QrcodeInstance) {
            try {
                if (html5QrcodeInstance.isScanning) {
                    await html5QrcodeInstance.stop();
                }
                html5QrcodeInstance.clear();
            } catch (err) {
                console.warn('Error al detener cámara QR:', err);
            } finally {
                html5QrcodeInstance = null;
            }
        }
    }

    /**
     * Inicia el escaneo en la cámara especificada.
     */
    async function iniciarCamara(cameraIdOrConfig) {
        await detenerCamara();

        const qrRegion = document.getElementById('qrReaderRegion');
        if (!qrRegion) return;

        html5QrcodeInstance = new Html5Qrcode('qrReaderRegion');

        const config = {
            fps: 15,
            qrbox: { width: 220, height: 220 },
            aspectRatio: 1.0,
            experimentalFeatures: {
                useBarCodeDetectorIfSupported: true
            }
        };

        try {
            await html5QrcodeInstance.start(
                cameraIdOrConfig,
                config,
                onQrScanSuccess,
                onQrScanError
            );
            actualizarEstadoFeedback('Cámara activa. Enfoca el código en el recuadro.', 'info');
        } catch (err) {
            console.error('Error al iniciar cámara:', err);
            const errStr = (err.message || String(err));
            if (errStr.includes('NotAllowedError') || errStr.includes('Permission denied') || errStr.includes('PermissionDeniedError')) {
                mostrarGuiaPermisoCamara();
            } else {
                actualizarEstadoFeedback('No se pudo acceder a la cámara: ' + errStr, 'danger');
            }
        }
    }

    /**
     * Muestra una guía interactiva cuando el navegador deniega el permiso de cámara.
     */
    function mostrarGuiaPermisoCamara() {
        const qrRegion = document.getElementById('qrReaderRegion');
        if (qrRegion) {
            qrRegion.innerHTML = `
                <div class="d-flex flex-column align-items-center justify-content-center h-100 text-white p-4 text-center bg-dark" style="min-height: 280px;">
                    <div class="rounded-circle bg-warning bg-opacity-20 p-3 mb-3 text-warning">
                        <i class="bi bi-camera-video-off fs-1"></i>
                    </div>
                    <h6 class="fw-bold mb-2 text-warning">Permiso de Cámara Denegado o Bloqueado</h6>
                    <p class="small text-white-50 mb-3" style="max-width: 400px;">
                        El navegador bloqueó el acceso a la cámara para este sitio.
                    </p>
                    <div class="bg-white bg-opacity-10 rounded-3 p-3 text-start small mb-3 border border-white border-opacity-10 text-light" style="max-width: 440px;">
                        <ol class="mb-0 ps-3">
                            <li class="mb-1">Haz clic en el ícono de <strong>candado 🔒</strong> o <strong>cámara 📷</strong> a la izquierda de la URL (barra de dirección).</li>
                            <li class="mb-1">Cambia la opción de <strong>Cámara</strong> a <span class="text-success fw-bold">"Permitir" / "Allow"</span>.</li>
                            <li>Haz clic en el botón de abajo para reintentar.</li>
                        </ol>
                    </div>
                    <button type="button" class="btn btn-warning fw-bold px-4 rounded-pill shadow-sm" id="btnReintentarCamara">
                        <i class="bi bi-arrow-clockwise me-1"></i>Reintentar Permiso de Cámara
                    </button>
                </div>
            `;

            const btnReintentar = document.getElementById('btnReintentarCamara');
            if (btnReintentar) {
                btnReintentar.addEventListener('click', function () {
                    const selectCamaras = document.getElementById('qrCamaraSelect');
                    const selectedId = selectCamaras ? selectCamaras.value : null;
                    iniciarCamara(selectedId || { facingMode: "environment" });
                });
            }
        }
        actualizarEstadoFeedback('⚠️ Permiso de cámara bloqueado por el navegador. Actívalo en la barra URL.', 'warning');
    }

    let lastScannedText = null;
    let lastScanTimestamp = 0;
    const COOLDOWN_MS = 1800; // Pausa de 1.8s para el mismo código

    /**
     * Handler ejecutado al detectar con éxito un código.
     */
    function onQrScanSuccess(decodedText, decodedResult) {
        if (!decodedText) return;

        const now = Date.now();
        // Evitar ráfagas accidentales si el usuario mantiene el mismo paquete frente a la cámara
        if (decodedText === lastScannedText && (now - lastScanTimestamp) < COOLDOWN_MS) {
            return;
        }

        lastScannedText = decodedText;
        lastScanTimestamp = now;

        reproducirBeep('exito');
        darFeedbackHaptico();

        scanCount++;
        const badge = document.getElementById('qrCountBadge');
        if (badge) badge.textContent = `${scanCount} escaneados`;

        actualizarEstadoFeedback(`✅ Registrado: <strong>${escaparHTML(decodedText)}</strong> — Pasa al siguiente paquete`, 'success');

        // Asignar al input objetivo
        if (targetInputEl) {
            targetInputEl.value = decodedText;
            targetInputEl.dispatchEvent(new Event('input', { bubbles: true }));
            targetInputEl.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // Ejecutar callback si existe
        if (typeof onSuccessCallback === 'function') {
            try {
                onSuccessCallback(decodedText, decodedResult);
            } catch (e) {
                console.error('Error en onSuccessCallback:', e);
            }
        }
    }

    function onQrScanError(errorMessage) {
        // Ignorar errores normales de frame continuo sin QR
    }

    function actualizarEstadoFeedback(mensajeHTML, tipo = 'info') {
        const fb = document.getElementById('qrStatusFeedback');
        if (!fb) return;

        fb.className = `alert alert-${tipo} d-flex align-items-center justify-content-between mt-3 mb-0 py-2 px-3 rounded-3 small`;
        fb.querySelector('span').innerHTML = `<i class="bi bi-${tipo === 'success' ? 'check-circle-fill text-success' : (tipo === 'danger' ? 'exclamation-triangle-fill' : 'info-circle')} me-2"></i>${mensajeHTML}`;
    }

    function escaparHTML(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    /**
     * API Global para abrir el Modal y comenzar escaneo.
     * @param {Object} opts - { targetInputId: string|HTMLElement, onScanSuccess: function }
     */
    window.abrirScannerQR = async function (opts = {}) {
        const modalEl = document.getElementById('modalQrScanner');
        if (!modalEl) {
            alert('El modal de escáner QR no se encuentra en el DOM actual.');
            return;
        }

        if (typeof opts.targetInputId === 'string') {
            targetInputEl = document.getElementById(opts.targetInputId);
        } else if (opts.targetInputId instanceof HTMLElement) {
            targetInputEl = opts.targetInputId;
        } else {
            targetInputEl = null;
        }

        onSuccessCallback = opts.onScanSuccess || null;

        const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        bsModal.show();
    };

    // Inicializar eventos cuando el DOM esté listo
    document.addEventListener('DOMContentLoaded', function () {
        const modalEl = document.getElementById('modalQrScanner');
        if (!modalEl) return;

        const selectCamaras = document.getElementById('qrCamaraSelect');
        const btnCambiar = document.getElementById('btnQrCambiarCam');

        // Al abrir el modal
        modalEl.addEventListener('shown.bs.modal', async function () {
            try {
                availableCameras = await Html5Qrcode.getCameras();

                if (selectCamaras) {
                    selectCamaras.innerHTML = '';
                    if (availableCameras && availableCameras.length > 0) {
                        availableCameras.forEach((cam, idx) => {
                            const opt = document.createElement('option');
                            opt.value = cam.id;
                            opt.textContent = cam.label || `Cámara ${idx + 1}`;
                            selectCamaras.appendChild(opt);
                        });

                        // Seleccionar cámara trasera preferentemente
                        let camInicial = availableCameras[0].id;
                        const camTrasera = availableCameras.find(c => /back|rear|trasera|entorno|environment/i.test(c.label));
                        if (camTrasera) camInicial = camTrasera.id;

                        selectCamaras.value = camInicial;
                        currentCameraId = camInicial;

                        iniciarCamara(camInicial);
                    } else {
                        // Intentar solicitar por FacingMode si no hay labels
                        selectCamaras.innerHTML = '<option value="environment">Cámara Trasera (Default)</option>';
                        iniciarCamara({ facingMode: "environment" });
                    }
                }
            } catch (err) {
                console.warn('No se pudieron obtener cámaras:', err);
                if (selectCamaras) {
                    selectCamaras.innerHTML = '<option value="environment">Cámara Trasera (Modo directo)</option>';
                }
                iniciarCamara({ facingMode: "environment" });
            }
        });

        // Al cambiar selector de cámara
        if (selectCamaras) {
            selectCamaras.addEventListener('change', function () {
                const selectedId = this.value;
                if (selectedId) {
                    currentCameraId = selectedId;
                    iniciarCamara(selectedId);
                }
            });
        }

        // Botón cambiar cámara rápido
        if (btnCambiar) {
            btnCambiar.addEventListener('click', function () {
                if (availableCameras && availableCameras.length > 1) {
                    const currIndex = availableCameras.findIndex(c => c.id === currentCameraId);
                    const nextIndex = (currIndex + 1) % availableCameras.length;
                    currentCameraId = availableCameras[nextIndex].id;
                    if (selectCamaras) selectCamaras.value = currentCameraId;
                    iniciarCamara(currentCameraId);
                } else {
                    // Alternar environment / user
                    currentCameraId = (currentCameraId === 'user') ? 'environment' : 'user';
                    iniciarCamara({ facingMode: currentCameraId });
                }
            });
        }

        // Al cerrar el modal -> detener cámara para liberar la webcam/celular
        modalEl.addEventListener('hide.bs.modal', function () {
            detenerCamara();
            if (targetInputEl) {
                targetInputEl.focus();
            }
        });
    });
})();
