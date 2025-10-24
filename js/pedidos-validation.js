(function(window, document){
    'use strict';

    function showSummaryErrors(errors) {
        const box = document.getElementById('formErrors');
        const list = document.getElementById('formErrorsList');
        if (!box || !list) return;
        list.innerHTML = '';
        if (!errors || errors.length === 0) {
            box.classList.add('d-none');
            box.setAttribute('aria-hidden', 'true');
            return;
        }
        errors.forEach(function(msg){
            const li = document.createElement('li');
            li.textContent = msg;
            list.appendChild(li);
        });
        box.classList.remove('d-none');
        box.setAttribute('aria-hidden', 'false');
        // accessibility: announce
        box.setAttribute('aria-live', 'assertive');
        if (typeof box.focus === 'function') box.focus();
    }

    // default messages by field id (fallbacks)
    const defaultMessages = {
        'numero_orden': 'Número de orden inválido.',
        'destinatario': 'Nombre inválido.',
        'telefono': 'Teléfono inválido (8-15 dígitos).',
        'producto': 'Producto requerido.',
        'cantidad': 'Cantidad inválida.',
        'direccion': 'Dirección inválida.',
        'latitud': 'Latitud inválida.',
        'longitud': 'Longitud inválida.',
        'email': 'Email inválido.'
    };

    function setInvalid(el, msg) {
        if (!el) return;
        el.classList.remove('is-valid');
        el.classList.add('is-invalid');
        const fb = el.parentElement.querySelector('.invalid-feedback');
        const finalMsg = msg || defaultMessages[el.id] || 'Campo inválido';
        if (fb) fb.textContent = finalMsg;
    }
    function clearInvalid(el) {
        if (!el) return;
        el.classList.remove('is-invalid');
        el.classList.add('is-valid');
    }

    function validarTelefono(value) { return /^\d{8,15}$/.test(value); }
    function validarDecimal(value) { return !isNaN(parseFloat(value)) && isFinite(value); }

    function validateFields(fieldDefs) {
        const errors = [];
        for (let f of fieldDefs) {
            const el = document.getElementById(f.id);
            const val = el ? el.value : '';
            const ok = f.fn(val);
            if (!ok) {
                setInvalid(el, f.msg);
                errors.push(f.msg);
            } else {
                clearInvalid(el);
            }
        }
        return errors;
    }

    // Attach realtime validation using definitions array (fieldDefs)
    function attachRealtime(fieldDefs) {
        // fieldDefs can be an array of ids or an array of {id, fn, msg}
        const defsMap = {};
        if (!fieldDefs) return;
        if (Array.isArray(fieldDefs) && fieldDefs.length > 0 && typeof fieldDefs[0] === 'string') {
            // convert simple id array to defs with default checks (non-empty)
            fieldDefs.forEach(id => defsMap[id] = {id:id});
        } else {
            fieldDefs.forEach(d => defsMap[d.id] = d);
        }

        Object.keys(defsMap).forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('input', function(){
                const def = defsMap[id] || {};
                let ok = true;
                const v = el.value;
                if (def.fn) ok = def.fn(v);
                else {
                    // fallback checks
                    if (id === 'telefono') ok = validarTelefono(v);
                    else if (id === 'cantidad') ok = v === '' || (Number.isInteger(Number(v)) && Number(v) >= 1);
                    else if (id === 'precio') ok = v === '' || validarDecimal(v);
                    else if (id === 'latitud' || id === 'longitud') ok = validarDecimal(v);
                    else ok = v.trim().length > 0;
                }
                if (ok) clearInvalid(el); else setInvalid(el, def.msg);
            });
        });
    }

    function initCrear() {
        const form = document.getElementById('formCrearPedido');
        if (!form) return;

        const summaryFields = [
            {id:'numero_orden', fn: v => v !== '' && Number(v) > 0, msg: 'Por favor ingresa un número de orden válido.'},
            {id:'destinatario', fn: v => v.trim().length >= 2, msg: 'Por favor, ingresa un nombre válido.'},
            {id:'telefono', fn: v => validarTelefono(v), msg: 'Teléfono inválido (8-15 dígitos).'},
            {id:'producto', fn: v => v.trim().length > 0, msg: 'Por favor, especifica el producto.'},
            {id:'cantidad', fn: v => Number.isInteger(Number(v)) && Number(v) >= 1, msg: 'La cantidad debe ser al menos 1.'},
            {id:'direccion', fn: v => v.trim().length > 5, msg: 'Dirección demasiado corta.'},
            {id:'latitud', fn: v => validarDecimal(v), msg: 'Latitud inválida.'},
            {id:'longitud', fn: v => validarDecimal(v), msg: 'Longitud inválida.'}
        ];

        attachRealtime(['numero_orden','destinatario','telefono','producto','cantidad','direccion','latitud','longitud']);

        form.addEventListener('submit', function(e){
            const errors = validateFields(summaryFields);
            showSummaryErrors(errors);
            if (errors.length > 0) {
                e.preventDefault();
                return false;
            }
            return true;
        });
    }

    function initEditar() {
        const form = document.getElementById('formEditarPedido');
        if (!form) return;

        const summaryFields = [
            {id:'destinatario', fn: v => v.trim().length >= 2, msg: 'Por favor, ingresa un nombre válido.'},
            {id:'telefono', fn: v => validarTelefono(v), msg: 'Teléfono inválido (8-15 dígitos).'},
            {id:'producto', fn: v => v.trim().length > 0, msg: 'Por favor, especifica el producto.'},
            {id:'cantidad', fn: v => v === '' || (Number.isInteger(Number(v)) && Number(v) >= 1), msg: 'La cantidad debe ser al menos 1 si se proporciona.'},
            {id:'precio', fn: v => v === '' || validarDecimal(v), msg: 'Precio inválido.'},
            {id:'direccion', fn: v => v.trim().length > 5, msg: 'Dirección demasiado corta.'},
            {id:'latitud', fn: v => validarDecimal(v), msg: 'Latitud inválida.'},
            {id:'longitud', fn: v => validarDecimal(v), msg: 'Longitud inválida.'}
        ];

        attachRealtime(['destinatario','telefono','producto','cantidad','precio','direccion','latitud','longitud']);

        form.addEventListener('submit', function(e){
            const errors = validateFields(summaryFields);
            showSummaryErrors(errors);
            if (errors.length > 0) {
                e.preventDefault();
                return false;
            }
            return true;
        });
    }

    // Auto-init if forms are present
    document.addEventListener('DOMContentLoaded', function(){
        if (document.getElementById('formCrearPedido')) initCrear();
        if (document.getElementById('formEditarPedido')) initEditar();
    });

    // Expose for manual init if needed
    window.PedidosValidation = { initCrear: initCrear, initEditar: initEditar };

})(window, document);
