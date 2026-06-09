/**
 * Inicialización global de Select2 para el sistema App RutaEx-Latam
 *
 * Este script inicializa Select2 automáticamente en todos los selects
 * que tengan la clase 'select2-searchable'.
 *
 * Para usar Select2 en un select, simplemente agrega la clase 'select2-searchable':
 * <select class="form-select select2-searchable">...</select>
 *
 * FIX Bootstrap 5 focus-trap:
 * Bootstrap 5 intercepts 'focusin' events to keep focus inside the modal.
 * The canonical fix is to use jQuery to override Bootstrap's focusin handler
 * at the document level, so our handler runs last and can stop propagation.
 * Additionally, dropdownParent must be set to the modal element so the Select2
 * dropdown is rendered inside the modal DOM tree.
 */

(function () {
    'use strict';

    // ─── FIX Bootstrap 5 modal focus-trap ────────────────────────────────────
    // Bootstrap 5 binds its focus-trap handler via jQuery on document 'focusin'.
    // We use $(document).on() which appends our handler AFTER Bootstrap's.
    // However, we use capture-phase native listener registered immediately
    // (before Bootstrap if this script loads before bootstrap.bundle, or as
    // a parallel interception otherwise) to cover both cases.
    //
    // The most reliable fix for Bootstrap 5 + Select2 is:
    //   1. Block focusin when target is inside a Select2 element (capture phase)
    //   2. Set dropdownParent to the modal so dropdown is inside modal DOM
    //
    // We register the native capture-phase listener immediately here so it
    // is registered on the document before any bubble-phase handlers run.
    document.addEventListener('focusin', function (e) {
        if (!e.target) return;
        var t = e.target;
        // Walk up to detect Select2 search input or dropdown
        while (t && t !== document) {
            var cls = t.className || '';
            if (
                typeof cls === 'string' && (
                    cls.indexOf('select2-search__field') !== -1 ||
                    cls.indexOf('select2-container') !== -1 ||
                    cls.indexOf('select2-dropdown') !== -1 ||
                    cls.indexOf('select2-results') !== -1
                )
            ) {
                // Stop Bootstrap's focus-trap from seeing this event
                e.stopImmediatePropagation();
                return;
            }
            t = t.parentElement;
        }
    }, true); // true = capture phase, runs before bubble-phase handlers

    // ─── Configuración común para Select2 ────────────────────────────────────
    var defaultConfig = {
        theme: 'bootstrap-5',
        allowClear: true,
        width: '100%',
        language: {
            noResults: function () { return 'No se encontraron resultados'; },
            searching: function () { return 'Buscando...'; },
            removeAllItems: function () { return 'Eliminar todos'; },
            removeItem: function () { return 'Eliminar'; },
            search: function () { return 'Buscar...'; },
            loadingMore: function () { return 'Cargando más resultados...'; },
            inputTooShort: function (args) {
                return 'Escribe al menos ' + args.minimum + ' caracteres';
            }
        }
    };

    /**
     * Inicializa Select2 en un elemento, detectando si está dentro de un modal
     * para asignar dropdownParent y evitar problemas de focus-trap.
     */
    function initSelect2(selectElement) {
        if (!selectElement || typeof $ === 'undefined' || !$.fn.select2) return;
        if ($(selectElement).hasClass('select2-hidden-accessible')) return;

        var $select = $(selectElement);
        var placeholder = $select.data('placeholder') || 'Escribe para buscar...';

        var config = $.extend(true, {}, defaultConfig, { placeholder: placeholder });

        // Detectar modal padre y asignar dropdownParent
        var $modal = $select.closest('.modal');
        if ($modal.length) {
            config.dropdownParent = $modal;
        }

        $select.select2(config);

        // Después de inicializar: cuando el dropdown se abre, forzar foco en el input
        $select.on('select2:open', function () {
            // Pequeño delay para que Select2 termine de renderizar el dropdown
            setTimeout(function () {
                var searchField = document.querySelector('.select2-container--open .select2-search__field');
                if (searchField) {
                    searchField.focus();
                }
            }, 10);
        });
    }

    /**
     * Inicializa todos los selects con la clase 'select2-searchable'
     */
    function initAllSelect2() {
        if (typeof $ === 'undefined' || !$.fn.select2) {
            console.warn('[Select2Utils] Select2 no está disponible');
            return;
        }
        document.querySelectorAll('.select2-searchable').forEach(initSelect2);
    }

    /**
     * Destruye Select2 de un elemento
     */
    function destroySelect2(selectElement) {
        if (!selectElement || typeof $ === 'undefined' || !$.fn.select2) return;
        var $select = $(selectElement);
        if ($select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }
    }

    // Inicializar cuando el DOM esté listo
    document.addEventListener('DOMContentLoaded', initAllSelect2);

    // Exponer funciones globalmente para uso dinámico
    window.Select2Utils = {
        init: initSelect2,
        initAll: initAllSelect2,
        destroy: destroySelect2,
        defaultConfig: defaultConfig
    };

})();
