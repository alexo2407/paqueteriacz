/**
 * Codigos Postales Autocomplete logic
 * Handle CP -> Location resolution with colision management
 */
(function() {
    document.addEventListener('DOMContentLoaded', () => {
        const cpInput = document.getElementById('codigo_postal');
        if (!cpInput) return;

        const paisSelect = document.getElementById('id_pais');
        const deptSelect = document.getElementById('id_departamento');
        const munSelect = document.getElementById('id_municipio');
        const barrioSelect = document.getElementById('id_barrio');
        const statusContainer = document.getElementById('cp_status_container');
        
        let debounceTimer;

        cpInput.addEventListener('input', (e) => {
            clearTimeout(debounceTimer);
            const cp = e.target.value.trim();
            
            if (cp.length < 3) {
                updateBadge('Pendiente', 'secondary');
                return;
            }

            updateBadge('Buscando...', 'info', true);

            debounceTimer = setTimeout(() => {
                resolveCP(cp);
            }, 400);
        });

        async function resolveCP(cp, id_pais = null) {
            try {
                let url = `api/geoinfo/codigos_postales.php?action=resolve&cp=${encodeURIComponent(cp)}`;
                if (id_pais) url += `&id_pais=${id_pais}`;

                // RUTA_URL check (if defined in template)
                const baseUrl = typeof RUTA_URL !== 'undefined' ? RUTA_URL : '';
                const response = await fetch(baseUrl + url);
                const result = await response.json();

                if (result.ok && result.data.matches.length > 0) {
                    const matches = result.data.matches;

                    if (matches.length === 1 || id_pais) {
                        const match = matches[0];
                        applyMatch(match);
                    } else {
                        // Colisión detectada
                        showConflictSelector(matches, cp);
                    }
                } else {
                    updateBadge('Desconocido', 'secondary');
                    hideConflictSelector();
                }
            } catch (error) {
                console.error('Error resolving CP:', error);
                updateBadge('Error', 'danger');
            }
        }

        function applyMatch(match) {
            hideConflictSelector();
            
            // 1. Badge status
            if (match.activo === 0) {
                updateBadge('Inactivo', 'danger');
            } else if (match.partial) {
                updateBadge('Incompleto', 'warning');
            } else {
                updateBadge('Homologado', 'success');
            }

            // 2. Autocomplete fields if they exist
            if (paisSelect) {
                paisSelect.value = match.id_pais;
                // Trigger change for dependent selects if they use Select2
                if (window.jQuery && jQuery.fn.select2) {
                    jQuery(paisSelect).trigger('change');
                } else {
                    paisSelect.dispatchEvent(new Event('change'));
                }
            }

            // Wait a bit for dependent selects to populate if they are dynamic
            // Or use the match data directly if the populator supports it
            setTimeout(() => {
                if (deptSelect && match.id_departamento) {
                    deptSelect.value = match.id_departamento;
                    if (window.jQuery && jQuery.fn.select2) jQuery(deptSelect).trigger('change');
                    else deptSelect.dispatchEvent(new Event('change'));
                }

                setTimeout(() => {
                    if (munSelect && match.id_municipio) {
                        munSelect.value = match.id_municipio;
                        if (window.jQuery && jQuery.fn.select2) jQuery(munSelect).trigger('change');
                        else munSelect.dispatchEvent(new Event('change'));
                    }

                    setTimeout(() => {
                        if (barrioSelect && match.id_barrio) {
                            barrioSelect.value = match.id_barrio;
                            if (window.jQuery && jQuery.fn.select2) jQuery(barrioSelect).trigger('change');
                            else barrioSelect.dispatchEvent(new Event('change'));
                        }
                    }, 300);
                }, 300);
            }, 300);
            
            // Set hidden field if present
            const hiddenId = document.getElementById('id_codigo_postal');
            if (hiddenId) hiddenId.value = match.id_codigo_postal;
        }

        function updateBadge(text, type, spinner = false) {
            const badge = document.getElementById('cp_badge');
            if (!badge) return;

            badge.className = `badge bg-${type}`;
            badge.innerHTML = spinner ? `<span class="spinner-border spinner-border-sm me-1"></span> ${text}` : text;
        }

        function showConflictSelector(matches, cp) {
            let selector = document.getElementById('cp_conflict_selector');
            let optionsContainer = document.getElementById('cp_conflict_options');
            
            if (!selector || !optionsContainer) return;

            updateBadge('Varios Países', 'info');
            optionsContainer.innerHTML = '';
            
            matches.forEach(m => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-xs btn-outline-info me-1 mb-1';
                btn.innerHTML = `<small>${m.pais}</small>`;
                btn.onclick = () => resolveCP(cp, m.id_pais);
                optionsContainer.appendChild(btn);
            });

            selector.style.display = 'block';
        }

        function hideConflictSelector() {
            const selector = document.getElementById('cp_conflict_selector');
            if (selector) selector.style.display = 'none';
        }
    });
})();
