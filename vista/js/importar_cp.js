/**
 * importar_cp.js — Wizard JS para importación masiva de Códigos Postales
 * Vanilla JS, sin dependencias externas (Bootstrap ya está disponible en la app).
 */
(function () {
    'use strict';

    /* ─── Variables de estado del wizard ──────────────────────────────────── */
    let currentStep = 1;
    let jobId = null;
    let summaryData = null;

    /* ─── Refs Globales (se populan en init) ─────────────────────────────── */
    let modal, steps, stepIndicators, btnVistaPrev, btnConfirmar, btnNueva,
        btnVolver, formPreview, archivoInput, spanFileName, alertaArchivo;

    /* ═══════════════════════════════════════════════════════════════════════
       INIT — ejecutar cuando el DOM esté listo
    ═══════════════════════════════════════════════════════════════════════ */
    document.addEventListener('DOMContentLoaded', function () {
        init();
    });

    /**
     * Inicializa selectores y eventos
     */
    function init() {
        modal = document.getElementById('modalImportarCp');
        if (!modal) return;

        formPreview = document.getElementById('formCpPreview');
        archivoInput = document.getElementById('cpArchivoInput');
        btnVistaPrev = document.getElementById('btnCpVistaPrev');
        btnConfirmar = document.getElementById('btnCpConfirmar'); // ID en HTML es btnCpConfirmar o btnCpConfirm? Checar index.php
        btnNueva = document.getElementById('btnCpNuevaImport');
        btnVolver = document.getElementById('btnCpVolver');
        spanFileName = document.getElementById('cpFileName');
        alertaArchivo = document.getElementById('cpAlertaArchivo');

        steps = modal.querySelectorAll('.wizard-step');
        stepIndicators = modal.querySelectorAll('.mz-step');

        // Listeners
        if (btnVistaPrev) btnVistaPrev.addEventListener('click', handlePreview);
        if (btnConfirmar) btnConfirmar.addEventListener('click', handleCommit);
        if (btnVolver) btnVolver.addEventListener('click', () => irAPaso(1));
        if (btnNueva) btnNueva.addEventListener('click', resetWizard);

        // Reset wizard al cerrar modal (Materialize way)
        const instance = M.Modal.getInstance(modal);
        if (instance) {
            const originalClose = instance.close;
            instance.close = function () {
                resetWizard();
                originalClose.apply(this, arguments);
            };
        }

        // Mantener compatibilidad si se usa Bootstrap
        modal.addEventListener('hidden.bs.modal', resetWizard);

        /* Mostrar nombre de archivo seleccionado */
        if (archivoInput) {
            archivoInput.addEventListener('change', function () {
                const f = this.files[0];
                if (!f) { if (spanFileName) spanFileName.textContent = 'Ningún archivo seleccionado'; return; }
                if (f.size > 10 * 1024 * 1024) {
                    mostrarAlertaArchivo('El archivo supera los 10 MB permitidos.', 'danger');
                    this.value = '';
                    if (spanFileName) spanFileName.textContent = 'Ningún archivo seleccionado';
                    return;
                }
                const ext = f.name.split('.').pop().toLowerCase();
                if (!['csv', 'xlsx', 'xls'].includes(ext)) {
                    mostrarAlertaArchivo('Solo se aceptan archivos .csv o .xlsx', 'danger');
                    this.value = '';
                    if (spanFileName) spanFileName.textContent = 'Ningún archivo seleccionado';
                    return;
                }
                if (alertaArchivo) alertaArchivo.classList.add('hide'); // Cambiado d-none -> hide
                if (spanFileName) spanFileName.textContent = f.name + ' (' + formatBytes(f.size) + ')';
            });
        }

        /* Plantillas descargables */
        document.querySelectorAll('[data-cp-plantilla]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                descargarPlantilla(this.getAttribute('data-cp-plantilla'));
            });
        });
    }

    /* ═══════════════════════════════════════════════════════════════════════
       STEP 1 → STEP 2 : PREVIEW
    ═══════════════════════════════════════════════════════════════════════ */
    function handlePreview() {
        if (!archivoInput || !archivoInput.files[0]) {
            mostrarAlertaArchivo('Por favor selecciona un archivo CSV o XLSX antes de continuar.', 'warning');
            return;
        }

        const formData = new FormData(formPreview);
        // Asegurarnos de que el archivo va con la key correcta
        formData.set('archivo', archivoInput.files[0]);

        setBtnLoading(btnVistaPrev, true, 'Procesando...');

        fetch(RUTA_URL + 'codigos_postales/import/preview', {
            method: 'POST',
            body: formData,
        })
            .then(r => r.json())
            .then(data => {
                setBtnLoading(btnVistaPrev, false, '<i class="material-icons left">visibility</i> Vista Previa');
                if (!data.ok) {
                    mostrarAlertaArchivo(data.message || 'Error inesperado al procesar el archivo.', 'danger');
                    return;
                }
                jobId = data.job_id;
                summaryData = data.summary;
                renderPaso2(data);
                irAPaso(2);
            })
            .catch(err => {
                setBtnLoading(btnVistaPrev, false, '<i class="material-icons left">visibility</i> Vista Previa');
                mostrarAlertaArchivo('Error de conexión: ' + err.message, 'danger');
            });
    }

    /* ═══════════════════════════════════════════════════════════════════════
       STEP 2 → STEP 3 : COMMIT
    ═══════════════════════════════════════════════════════════════════════ */
    function handleCommit() {
        if (!jobId) return;

        const formData = new FormData();
        formData.set('job_id', jobId); // Corregido: job_id en vez de jobId

        setBtnLoading(btnConfirmar, true, 'Importando...');

        fetch(RUTA_URL + 'codigos_postales/import/commit', {
            method: 'POST',
            body: formData,
        })
            .then(r => r.json())
            .then(data => {
                setBtnLoading(btnConfirmar, false, '<i class="material-icons left">check_circle</i> Confirmar e Importar');
                if (!data.ok) {
                    mostrarError('#cpAlertaPaso2', data.message || 'Error al confirmar la importación.');
                    return;
                }
                renderPaso3(data);
                irAPaso(3);
            })
            .catch(err => {
                setBtnLoading(btnConfirmar, false, '<i class="material-icons left">check_circle</i> Confirmar e Importar');
                mostrarError('#cpAlertaPaso2', 'Error de conexión: ' + err.message);
            });
    }

    /* ═══════════════════════════════════════════════════════════════════════
       RENDER PASO 2 — Vista previa
    ═══════════════════════════════════════════════════════════════════════ */
    function renderPaso2(data) {
        const summary = data.summary;
        const tbody = document.getElementById('cpPreviewTbody');
        if (tbody) tbody.innerHTML = '';

        // Resumen
        document.getElementById('cpSumTotal').textContent = summary.total;
        document.getElementById('cpSumValidas').textContent = summary.validas;
        document.getElementById('cpSumErrores').textContent = summary.errores;
        document.getElementById('cpSumWarn').textContent = summary.advertencias;

        // Limpiar alertas previas
        const alertCont = document.getElementById('cpAlertaPaso2');
        if (alertCont) alertCont.classList.add('hide');

        // Render table
        let html = '';
        data.preview_rows.forEach(r => {
            const statusColor = r.status === 'OK' ? 'green' : (r.status === 'WARN' ? 'orange' : 'red');
            const textColor = (r.status === 'WARN') ? 'black-text' : 'white-text';

            html += `<tr>
                <td class="grey-text small">${r.line}</td>
                <td><span class="chip ${statusColor} ${textColor}" style="font-size:10px;height:22px;line-height:22px">${r.status}</span></td>
                <td>${r.codigo_postal}</td>
                <td class="grey-text small">${r.pais}</td>
                <td>${r.departamento || '-'}</td>
                <td>${r.municipio || '-'}</td>
                <td>${r.barrio || '-'}</td>
                <td>${r.activo === 1 ? 'S' : 'N'}</td>
            </tr>`;
        });
        if (tbody) tbody.innerHTML = html;

        // Errores detallados
        const errCont = document.getElementById('cpErroresContainer');
        const errList = document.getElementById('cpErroresLista');
        if (errCont && errList) {
            if (data.errors.length > 0) {
                errList.innerHTML = data.errors.map(e => `<div class="red-text small" style="margin-bottom:4px">Línea ${e.line}: [${e.field}] ${e.message}</div>`).join('');
                errCont.classList.remove('hide');
            } else {
                errCont.classList.add('hide');
            }
        }

        // Advertencias detalladas
        const warnCont = document.getElementById('cpWarnContainer');
        const warnList = document.getElementById('cpAdvertenciasLista');
        if (warnCont && warnList) {
            if (data.warnings.length > 0) {
                warnList.innerHTML = data.warnings.map(w => `<div class="orange-text small" style="margin-bottom:4px">Línea ${w.line}: [${w.field}] ${w.message}</div>`).join('');
                warnCont.classList.remove('hide');
            } else {
                warnCont.classList.add('hide');
            }
        }

        // Habilitar botón si hay al menos una válida
        if (btnConfirmar) {
            btnConfirmar.disabled = (summary.validas === 0);
        }
    }

    /* ═══════════════════════════════════════════════════════════════════════
       RENDER PASO 3 — Resultado
    ═══════════════════════════════════════════════════════════════════════ */
    function renderPaso3(data) {
        const r = data.result;
        const estadoColor = r.estado === 'completado' ? 'green' : (r.estado === 'parcial' ? 'orange' : 'red');
        const estadoIcono = r.estado === 'completado' ? 'check_circle' : (r.estado === 'parcial' ? 'warning' : 'cancel');

        document.getElementById('cpResEstado').innerHTML =
            `<span class="chip ${estadoColor} white-text"><i class="material-icons left tiny">${estadoIcono}</i>${capitalizar(r.estado)}</span>`;

        document.getElementById('cpResTotal').textContent = r.total;
        document.getElementById('cpResInsertadas').textContent = r.insertadas;
        document.getElementById('cpResActualizadas').textContent = r.actualizadas;
        document.getElementById('cpResOmitidas').textContent = r.omitidas;
        document.getElementById('cpResFallidas').textContent = r.fallidas;
        document.getElementById('cpResTiempo').textContent = r.tiempo + 's';

        // Link de errores
        const linkBox = document.getElementById('cpLinkErrores');
        if (data.archivo_errores) {
            if (linkBox) {
                linkBox.innerHTML = `<p class="red-text" style="font-weight:600">
                        Se encontraron algunos errores durante la importación. 
                        <a href="${RUTA_URL}cache/import_errors/${data.archivo_errores}" target="_blank" class="blue-text underline">Descargar informe de errores</a>
                    </p>`;
                linkBox.classList.remove('hide');
            }
        } else {
            if (linkBox) linkBox.classList.add('hide');
        }
    }

    /* ═══════════════════════════════════════════════════════════════════════
       HELPERS
    ═══════════════════════════════════════════════════════════════════════ */
    function irAPaso(paso) {
        currentStep = paso;

        // Mostrar/ocultar contenedores con style.display (los pasos 2 y 3 tienen style="display:none" inline)
        steps.forEach((el, i) => {
            el.style.display = (i + 1) === paso ? '' : 'none';
        });

        // Actualizar indicadores del stepper
        stepIndicators.forEach((el, i) => {
            el.classList.toggle('active', (i + 1) === paso);
            el.classList.toggle('completed', (i + 1) < paso);
        });
    }

    function resetWizard() {
        currentStep = 1;
        jobId = null;
        summaryData = null;
        if (formPreview) formPreview.reset();
        if (archivoInput) archivoInput.value = '';
        if (spanFileName) spanFileName.textContent = 'Ningún archivo seleccionado';
        if (alertaArchivo) { alertaArchivo.classList.add('hide'); alertaArchivo.style.display = ''; }
        if (btnConfirmar) btnConfirmar.disabled = true;
        // Limpiar contenido dinámico
        const tbody = document.getElementById('cpPreviewTbody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="8" class="center-align grey-text">Cargando vista previa...</td></tr>';
        const errBox = document.getElementById('cpErroresLista');
        if (errBox) errBox.innerHTML = '';
        const warnBox = document.getElementById('cpAdvertenciasLista');
        if (warnBox) warnBox.innerHTML = '';
        // Ir al paso 1 (restablece display de todos los pasos)
        irAPaso(1);
    }

    function setBtnLoading(btn, loading, label) {
        if (!btn) return;
        btn.disabled = loading;
        btn.innerHTML = loading
            ? '<i class="material-icons left">hourglass_empty</i> ' + label
            : label;
    }

    function mostrarAlertaArchivo(msg, type = 'danger') {
        if (!alertaArchivo) {
            Swal.fire({ icon: type === 'danger' ? 'error' : type, text: msg });
            return;
        }
        alertaArchivo.textContent = msg;
        alertaArchivo.className = `card-panel white-text ${type === 'danger' ? 'red' : (type === 'warning' ? 'orange' : 'green')}`;
        alertaArchivo.classList.remove('hide');
    }

    function mostrarError(selector, msg) {
        const el = document.querySelector(selector);
        if (!el) return;
        el.innerHTML = `<i class="material-icons left tiny">error</i>${msg}`;
        el.className = 'red-text small';
        el.classList.remove('hide');
    }

    function esc(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatBytes(b) {
        if (b < 1024) return b + ' B';
        if (b < 1048576) return (b / 1024).toFixed(1) + ' KB';
        return (b / 1048576).toFixed(1) + ' MB';
    }

    function capitalizar(s) {
        return s ? s.charAt(0).toUpperCase() + s.slice(1) : '';
    }

    /* ─── Plantillas descargables ──────────────────────────────────────── */
    function descargarPlantilla(tipo) {
        let csv, nombre;
        if (tipo === 'completa') {
            csv = 'id_pais,codigo_postal,departamento,municipio,barrio,nombre_localidad,activo\n' +
                '1,10101,Guatemala,Guatemala,Zona 1,Centro Histórico,1\n' +
                '1,10201,Guatemala,Mixco,,Santa fe,1\n';
            nombre = 'plantilla_cp_completa.csv';
        } else {
            csv = 'id_pais,codigo_postal\n1,10101\n1,10201\n';
            nombre = 'plantilla_cp_solo.csv';
        }
        const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = nombre;
        a.click();
        URL.revokeObjectURL(url);
    }

})();
