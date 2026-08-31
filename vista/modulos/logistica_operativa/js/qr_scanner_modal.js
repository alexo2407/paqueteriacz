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
     * Sincroniza #qrScanOverlay con el qrbox de html5-qrcode.
     *
     * Por qué funciona ahora:
     *   - #qrReaderRegion__header_message y __dashboard_section están ocultos
     *     → el <video> empieza en y=0 de #qrReaderRegion sin offsets.
     *   - El video tiene height:360px !important (via CSS) → html5-qrcode usa
     *     video.clientHeight = 360 para centrar su qrbox en y=180.
     *   - Nuestro overlay calcula su centro en el mismo punto → coincidencia exacta.
     *
     * readyState >= 2 (HAVE_CURRENT_DATA) garantiza dimensiones finales,
     * evitando usar valores provisionales que html5-qrcode actualiza async.
     *
     * @param {number} [intentos=0] - reintentos mientras el video carga
     */
    function sincronizarOverlay(intentos = 0) {
        const viewport = document.getElementById('qrScannerViewport');
        const overlay  = document.getElementById('qrScanOverlay');
        if (!viewport || !overlay) return;

        const video = viewport.querySelector('video');

        if (!video || video.clientWidth < 50 || video.clientHeight < 50 || video.readyState < 2) {
            if (intentos < 30) setTimeout(() => sincronizarOverlay(intentos + 1), 200);
            return;
        }

        const vpRect  = viewport.getBoundingClientRect();
        const vidRect = video.getBoundingClientRect();

        // Centro del <video> relativo al #qrScannerViewport.
        // Con el video en y=0 de su contenedor y height=360px,
        // este punto coincide exactamente con el centro del qrbox de html5-qrcode.
        const cx = (vidRect.left - vpRect.left) + vidRect.width  * 0.5;
        const cy = (vidRect.top  - vpRect.top)  + vidRect.height * 0.5;

        overlay.style.left      = cx + 'px';
        overlay.style.top       = cy + 'px';
        overlay.style.transform = 'translate(-50%, -50%)';
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
        // Limpiar ResizeObserver del video antes de detener
        const videoEl = document.querySelector('#qrScannerViewport video');
        if (videoEl && videoEl._qrResizeObserver) {
            videoEl._qrResizeObserver.disconnect();
            videoEl._qrResizeObserver = null;
        }

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
     * Aplica autoenfoque continuo, autoexposición y mejoras de nitidez al track de video activo.
     */
    async function aplicarAutoenfoqueYMejoras(videoEl) {
        if (!videoEl || !videoEl.srcObject) return;
        const stream = videoEl.srcObject;
        const tracks = stream.getVideoTracks ? stream.getVideoTracks() : [];
        if (!tracks || tracks.length === 0) return;
        const track = tracks[0];

        try {
            const capabilities = track.getCapabilities ? track.getCapabilities() : {};
            const advancedConstraints = [];

            // 1. Autoenfoque continuo (Continuous Auto-Focus)
            if (capabilities.focusMode && capabilities.focusMode.includes('continuous')) {
                advancedConstraints.push({ focusMode: 'continuous' });
            }

            // 2. Modo de exposición automática continua para compensar iluminación
            if (capabilities.exposureMode && capabilities.exposureMode.includes('continuous')) {
                advancedConstraints.push({ exposureMode: 'continuous' });
            }

            // 3. Balance de blancos continuo
            if (capabilities.whiteBalanceMode && capabilities.whiteBalanceMode.includes('continuous')) {
                advancedConstraints.push({ whiteBalanceMode: 'continuous' });
            }

            if (advancedConstraints.length > 0) {
                await track.applyConstraints({ advanced: advancedConstraints });
                console.log('Autoenfoque continuo y optimizaciones aplicadas al stream.');
            }
        } catch (e) {
            console.warn('Aviso: El dispositivo/navegador no permite ajustar el enfoque por software:', e);
        }
    }

    /**
     * Inicia el escaneo en la cámara especificada con optimizaciones de enfoque y resolución.
     */
    async function iniciarCamara(cameraIdOrConfig) {
        await detenerCamara();

        const qrRegion = document.getElementById('qrReaderRegion');
        const viewport = document.getElementById('qrScannerViewport');
        if (!qrRegion) return;

        html5QrcodeInstance = new Html5Qrcode('qrReaderRegion');

        // Calcular el aspectRatio exacto del contenedor visual actual (ancho / alto = e.g. 700 / 360).
        const vpWidth = (viewport && viewport.clientWidth > 0) ? viewport.clientWidth : (qrRegion.clientWidth || 640);
        const vpHeight = (viewport && viewport.clientHeight > 0) ? viewport.clientHeight : 360;
        const dynamicAspectRatio = vpWidth / vpHeight;

        const config = {
            fps: 20, // Mayor tasa de cuadros para escaneo más fluido y rápido
            qrbox: (viewfinderWidth, viewfinderHeight) => {
                const boxSize = Math.min(220, Math.min(viewfinderWidth - 20, viewfinderHeight - 20));
                return { width: boxSize, height: boxSize };
            },
            aspectRatio: dynamicAspectRatio,
            videoConstraints: {
                width: { min: 640, ideal: 1280, max: 1920 },
                height: { min: 480, ideal: 720, max: 1080 },
                advanced: [
                    { focusMode: "continuous" }
                ]
            },
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

            // Sincronizar overlay con el qrbox real de html5-qrcode.
            sincronizarOverlay();

            const videoEl = document.querySelector('#qrScannerViewport video');
            if (videoEl) {
                const onVideoReady = () => {
                    sincronizarOverlay();
                    aplicarAutoenfoqueYMejoras(videoEl);
                };

                videoEl.addEventListener('loadedmetadata', onVideoReady, { once: true });
                videoEl.addEventListener('canplay',        onVideoReady, { once: true });
                videoEl.addEventListener('playing',        onVideoReady, { once: true });

                // Aplicar de inmediato por si el video ya inició
                aplicarAutoenfoqueYMejoras(videoEl);

                // ResizeObserver: re-sincronizar si html5-qrcode cambia el tamaño del video
                if (window.ResizeObserver) {
                    if (videoEl._qrResizeObserver) videoEl._qrResizeObserver.disconnect();
                    videoEl._qrResizeObserver = new ResizeObserver(() => sincronizarOverlay());
                    videoEl._qrResizeObserver.observe(videoEl);
                }
            }
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

        // Tap/Click en el visor para re-enfocar manualmente si la cámara pierde foco
        const viewportEl = document.getElementById('qrScannerViewport');
        if (viewportEl) {
            viewportEl.addEventListener('click', function () {
                const videoEl = viewportEl.querySelector('video');
                if (videoEl) {
                    aplicarAutoenfoqueYMejoras(videoEl);
                }
            });
        }

        // Resincronizar overlay cuando cambia el tamaño de ventana o la orientación.
        function onResize() { sincronizarOverlay(); }
        window.addEventListener('resize',            onResize);
        window.addEventListener('orientationchange', onResize);
    });
})();
