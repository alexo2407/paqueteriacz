/**
 * Inicialización global de Select2 para el sistema Paquetería CruzValle
 * 
 * Este script inicializa Select2 automáticamente en todos los selects 
 * que tengan la clase 'select2-searchable'.
 * 
 * Para usar Select2 en un select, simplemente agrega la clase 'select2-searchable':
 * <select class="form-select select2-searchable">...</select>
 * 
 * También puedes especificar un placeholder con data-placeholder:
 * <select class="form-select select2-searchable" data-placeholder="Buscar producto...">
 */

(function () {
    'use strict';

    // Configuración común para Select2
    const defaultConfig = {
        theme: 'bootstrap-5',
        allowClear: true,
        width: '100%',
        language: {
            noResults: function () {
                return 'No se encontraron resultados';
            },
            searching: function () {
                return 'Buscando...';
            },
            removeAllItems: function () {
                return 'Eliminar todos';
            },
            removeItem: function () {
                return 'Eliminar';
            },
            search: function () {
                return 'Buscar...';
            },
            loadingMore: function () {
                return 'Cargando más resultados...';
            },
            inputTooShort: function (args) {
                return 'Escribe al menos ' + args.minimum + ' caracteres';
            }
        }
    };

    /**
     * Inicializa Select2 en un elemento
     * @param {HTMLSelectElement} selectElement - El elemento select a inicializar
     */
    function initSelect2(selectElement) {
        if (!selectElement || !$ || !$.fn.select2) return;

        // Evitar reinicializar si ya tiene Select2
        if ($(selectElement).hasClass('select2-hidden-accessible')) return;

        const $select = $(selectElement);
        const placeholder = $select.data('placeholder') || 'Escribe para buscar...';

        $select.select2({
            ...defaultConfig,
            placeholder: placeholder
        });
    }

    /**
     * Inicializa todos los selects con la clase 'select2-searchable'
     */
    function initAllSelect2() {
        if (typeof $ === 'undefined' || !$.fn.select2) {
            console.warn('Select2 no está disponible');
            return;
        }

        document.querySelectorAll('.select2-searchable').forEach(initSelect2);
    }

    /**
     * Destruye Select2 de un elemento (útil antes de eliminar elementos del DOM)
     * @param {HTMLSelectElement} selectElement - El elemento select
     */
    function destroySelect2(selectElement) {
        if (!selectElement || !$ || !$.fn.select2) return;

        const $select = $(selectElement);
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
