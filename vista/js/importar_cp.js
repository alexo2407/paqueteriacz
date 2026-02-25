/**
 * importar_cp.js — Wizard JS para importación masiva de Códigos Postales
 * Vanilla JS, sin dependencias externas (Bootstrap ya está disponible en la app).
 */
(function () {
    'use strict';

    /* ─── Variables de estado del wizard ──────────────────────────────────── */
    let currentStep = 1;
    let jobId       = null;
    let summaryData = null;

    /* ─── Refs Globales (se populan en init) ─────────────────────────────── */
    let modal, steps, stepIndicators, btnVistaPrev, btnConfirmar, btnNueva,
        btnVolver, formPreview, archivoInput, spanFileName, alertaArchivo;

    /* ═══════════════════════════════════════════════════════════════════════
       INIT — ejecutar cuando el DOM esté listo
    ═══════════════════════════════════════════════════════════════════════ */
    document.addEventListener('DOMContentLoaded', function () {
        modal          = document.getElementById('modalImportarCp');
        if (!modal) return; // Si la vista no tiene el modal, no hacer nada

        steps          = modal.querySelectorAll('.wizard-step');
        stepIndicators = modal.querySelectorAll('.step-indicator');
        btnVistaPrev   = document.getElementById('btnCpVistaPrev');
        btnConfirmar   = document.getElementById('btnCpConfirmar');
        btnNueva       = document.getElementById('btnCpNuevaImport');
        btnVolver      = document.getElementById('btnCpVolver');
        formPreview    = document.getElementById('formCpPreview');
        archivoInput   = document.getElementById('cpArchivoInput');
        spanFileName   = document.getElementById('cpFileName');
        alertaArchivo  = document.getElementById('cpAlertaArchivo');

        /* Mostrar nombre de archivo seleccionado */
        if (archivoInput) {
            archivoInput.addEventListener('change', function () {
                const f = this.files[0];
                if (!f) { spanFileName.textContent = 'Ningún archivo seleccionado'; return; }
                if (f.size > 10 * 1024 * 1024) {
                    mostrarAlertaArchivo('El archivo supera los 10 MB permitidos.', 'danger');
                    this.value = '';
                    spanFileName.textContent = 'Ningún archivo seleccionado';
                    return;
                }
                const ext = f.name.split('.').pop().toLowerCase();
                if (!['csv', 'xlsx', 'xls'].includes(ext)) {
                    mostrarAlertaArchivo('Solo se aceptan archivos .csv o .xlsx', 'danger');
                    this.value = '';
                    spanFileName.textContent = 'Ningún archivo seleccionado';
                    return;
                }
                if (alertaArchivo) alertaArchivo.classList.add('d-none');
                spanFileName.textContent = f.name + ' (' + formatBytes(f.size) + ')';
            });
        }

        /* Botón Vista Previa */
        if (btnVistaPrev) {
            btnVistaPrev.addEventListener('click', handlePreview);
        }

        /* Botón Confirmar e Importar */
        if (btnConfirmar) {
            btnConfirmar.addEventListener('click', handleCommit);
        }

        /* Botón Volver */
        if (btnVolver) {
            btnVolver.addEventListener('click', () => irAPaso(1));
        }

        /* Botón Nueva Importación */
        if (btnNueva) {
            btnNueva.addEventListener('click', resetWizard);
        }

        /* Reset del wizard al cerrar el modal */
        modal.addEventListener('hidden.bs.modal', resetWizard);

        /* Plantillas descargables */
        document.querySelectorAll('[data-cp-plantilla]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                descargarPlantilla(this.getAttribute('data-cp-plantilla'));
            });
        });
    });

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
            body:   formData,
        })
        .then(r => r.json())
        .then(data => {
            setBtnLoading(btnVistaPrev, false, '<i class="bi bi-eye me-1"></i> Vista Previa');
            if (!data.ok) {
                mostrarAlertaArchivo(data.message || 'Error inesperado al procesar el archivo.', 'danger');
                return;
            }
            jobId       = data.job_id;
            summaryData = data.summary;
            renderPaso2(data);
            irAPaso(2);
        })
        .catch(err => {
            setBtnLoading(btnVistaPrev, false, '<i class="bi bi-eye me-1"></i> Vista Previa');
            mostrarAlertaArchivo('Error de conexión: ' + err.message, 'danger');
        });
    }

    /* ═══════════════════════════════════════════════════════════════════════
       STEP 2 → STEP 3 : COMMIT
    ═══════════════════════════════════════════════════════════════════════ */
    function handleCommit() {
        if (!jobId) return;

        setBtnLoading(btnConfirmar, true, 'Importando...');

        const body = new FormData();
        body.append('job_id', jobId);

        fetch(RUTA_URL + 'codigos_postales/import/commit', {
            method: 'POST',
            body:   body,
        })
        .then(r => r.json())
        .then(data => {
            setBtnLoading(btnConfirmar, false, '<i class="bi bi-check-circle me-1"></i> Confirmar e Importar');
            if (!data.ok) {
                mostrarError('#cpAlertaPaso2', data.message || 'Error al confirmar la importación.');
                return;
            }
            renderPaso3(data);
            irAPaso(3);
        })
        .catch(err => {
            setBtnLoading(btnConfirmar, false, '<i class="bi bi-check-circle me-1"></i> Confirmar e Importar');
            mostrarError('#cpAlertaPaso2', 'Error de conexión: ' + err.message);
        });
    }

    /* ═══════════════════════════════════════════════════════════════════════
       RENDER PASO 2 — Vista previa
    ═══════════════════════════════════════════════════════════════════════ */
    function renderPaso2(data) {
        const s = data.summary;

        // Tarjetas resumen
        document.getElementById('cpSumTotal').textContent    = s.total;
        document.getElementById('cpSumValidas').textContent  = s.validas;
        document.getElementById('cpSumErrores').textContent  = s.errores;
        document.getElementById('cpSumWarn').textContent     = s.advertencias;

        // Errores
        const errBox = document.getElementById('cpErroresLista');
        if (data.errors && data.errors.length > 0) {
            let html = '<ul class="list-group list-group-flush">';
            data.errors.slice(0, 100).forEach(e => {
                html += `<li class="list-group-item list-group-item-danger py-1 px-2 small">
                    <strong>Línea ${e.line}</strong> · <code>${esc(e.field)}</code>: ${esc(e.message)}
                </li>`;
            });
            if (data.errors.length > 100) {
                html += `<li class="list-group-item py-1 px-2 small text-muted">… y ${data.errors.length - 100} errores más.</li>`;
            }
            html += '</ul>';
            errBox.innerHTML = html;
            document.getElementById('cpErroresContainer').classList.remove('d-none');
        } else {
            document.getElementById('cpErroresContainer').classList.add('d-none');
        }

        // Advertencias
        const warnBox = document.getElementById('cpAdvertenciasLista');
        if (data.warnings && data.warnings.length > 0) {
            let html = '<ul class="list-group list-group-flush">';
            data.warnings.slice(0, 50).forEach(w => {
                html += `<li class="list-group-item list-group-item-warning py-1 px-2 small">
                    <strong>Línea ${w.line}</strong> · ${esc(w.message)}
                </li>`;
            });
            html += '</ul>';
            warnBox.innerHTML = html;
            document.getElementById('cpWarnContainer').classList.remove('d-none');
        } else {
            document.getElementById('cpWarnContainer').classList.add('d-none');
        }

        // Tabla de preview
        const tbody = document.getElementById('cpPreviewTbody');
        if (data.preview_rows && data.preview_rows.length > 0) {
            let html = '';
            data.preview_rows.forEach(r => {
                const badgeClass = r.status === 'OK' ? 'success' : (r.status === 'WARN' ? 'warning text-dark' : 'danger');
                html += `<tr>
                    <td class="text-muted small">${r.line}</td>
                    <td><span class="badge bg-${badgeClass}">${r.status}</span></td>
                    <td>${esc(r.pais)}</td>
                    <td><code>${esc(r.codigo_postal)}</code></td>
                    <td class="small">${esc(r.departamento)}</td>
                    <td class="small">${esc(r.municipio)}</td>
                    <td class="small">${esc(r.barrio)}</td>
                    <td class="small">${esc(r.nombre_localidad)}</td>
                    <td class="text-center">${r.activo}</td>
                </tr>`;
            });
            tbody.innerHTML = html;
        } else {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-3">Sin datos para previsualizar.</td></tr>';
        }

        // Botón confirmar
        if (btnConfirmar) {
            btnConfirmar.disabled = s.errores > 0;
            if (s.errores > 0) {
                btnConfirmar.title = `No se puede confirmar: hay ${s.errores} error(es) en el archivo.`;
            } else {
                btnConfirmar.title = '';
            }
        }
    }

    /* ═══════════════════════════════════════════════════════════════════════
       RENDER PASO 3 — Resultado
    ═══════════════════════════════════════════════════════════════════════ */
    function renderPaso3(data) {
        const r = data.result;
        const estadoClass = r.estado === 'completado' ? 'success' : (r.estado === 'parcial' ? 'warning' : 'danger');
        const estadoIcono = r.estado === 'completado' ? 'bi-check-circle-fill' : (r.estado === 'parcial' ? 'bi-exclamation-triangle-fill' : 'bi-x-circle-fill');

        document.getElementById('cpResEstado').innerHTML =
            `<span class="badge bg-${estadoClass} fs-6"><i class="bi ${estadoIcono} me-1"></i>${capitalizar(r.estado)}</span>`;

        document.getElementById('cpResTotal').textContent       = r.total;
        document.getElementById('cpResInsertadas').textContent  = r.insertadas;
        document.getElementById('cpResActualizadas').textContent= r.actualizadas;
        document.getElementById('cpResOmitidas').textContent    = r.omitidas;
        document.getElementById('cpResFallidas').textContent    = r.fallidas;
        document.getElementById('cpResTiempo').textContent      = r.tiempo + 's';

        // Link de errores
        const linkBox = document.getElementById('cpLinkErrores');
        if (data.archivo_errores) {
            linkBox.innerHTML = `<div class="alert alert-warning d-flex align-items-center gap-2">
                <i class="bi bi-file-earmark-csv fs-5"></i>
                <span>Se generó un archivo con los registros fallidos.</span>
                <a href="${RUTA_URL}cache/import_errors/${esc(data.archivo_errores)}" 
                   class="btn btn-sm btn-warning ms-auto" download>
                    <i class="bi bi-download me-1"></i>Descargar errores.csv
                </a>
            </div>`;
            linkBox.classList.remove('d-none');
        } else {
            linkBox.classList.add('d-none');
        }
    }

    /* ═══════════════════════════════════════════════════════════════════════
       HELPERS
    ═══════════════════════════════════════════════════════════════════════ */
    function irAPaso(paso) {
        currentStep = paso;
        steps.forEach((el, i) => {
            el.classList.toggle('d-none', (i + 1) !== paso);
        });
        stepIndicators.forEach((el, i) => {
            el.classList.toggle('active', (i + 1) === paso);
            el.classList.toggle('completed', (i + 1) < paso);
        });
    }

    function resetWizard() {
        jobId       = null;
        summaryData = null;
        if (formPreview) formPreview.reset();
        if (spanFileName) spanFileName.textContent = 'Ningún archivo seleccionado';
        if (alertaArchivo) alertaArchivo.classList.add('d-none');
        if (btnConfirmar)  btnConfirmar.disabled = true;
        // Limpiar contenido dinámico
        const tbody = document.getElementById('cpPreviewTbody');
        if (tbody) tbody.innerHTML = '';
        const errBox = document.getElementById('cpErroresLista');
        if (errBox) errBox.innerHTML = '';
        const warnBox = document.getElementById('cpAdvertenciasLista');
        if (warnBox) warnBox.innerHTML = '';
        irAPaso(1);
    }

    function setBtnLoading(btn, loading, label) {
        if (!btn) return;
        btn.disabled = loading;
        btn.innerHTML = loading
            ? '<span class="spinner-border spinner-border-sm me-1" role="status"></span>' + label
            : label;
    }

    function mostrarAlertaArchivo(msg, tipo) {
        if (!alertaArchivo) return;
        alertaArchivo.className = `alert alert-${tipo} py-2 small`;
        alertaArchivo.innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i>${msg}`;
        alertaArchivo.classList.remove('d-none');
    }

    function mostrarError(selector, msg) {
        const el = document.querySelector(selector);
        if (!el) return;
        el.innerHTML = `<i class="bi bi-x-circle me-1"></i>${msg}`;
        el.className = 'alert alert-danger py-2 small';
        el.classList.remove('d-none');
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
        if (b < 1024)       return b + ' B';
        if (b < 1048576)    return (b / 1024).toFixed(1) + ' KB';
        return (b / 1048576).toFixed(1) + ' MB';
    }

    function capitalizar(s) {
        return s ? s.charAt(0).toUpperCase() + s.slice(1) : '';
    }

    /* ─── Plantillas descargables ──────────────────────────────────────── */
    function descargarPlantilla(tipo) {
        let csv, nombre;
        if (tipo === 'completa') {
            csv   = 'id_pais,codigo_postal,departamento,municipio,barrio,nombre_localidad,activo\n' +
                    '1,10101,Guatemala,Guatemala,Zona 1,Centro Histórico,1\n' +
                    '1,10201,Guatemala,Mixco,,Santa fe,1\n';
            nombre = 'plantilla_cp_completa.csv';
        } else {
            csv    = 'id_pais,codigo_postal\n1,10101\n1,10201\n';
            nombre = 'plantilla_cp_solo.csv';
        }
        const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href     = url;
        a.download = nombre;
        a.click();
        URL.revokeObjectURL(url);
    }

})();
