(function(window, document){
    'use strict';

    function showSummaryErrors(errors) {
        const box = document.getElementById('formErrors');
        const list = document.getElementById('formErrorsList');
        if (!box || !list) return;
        list.innerHTML = '';
        if (!errors || errors.length === 0) {
            box.classList.add('d-none');
            return;
        }
        errors.forEach(function(msg){
            const li = document.createElement('li');
            li.textContent = msg;
            list.appendChild(li);
        });
        box.classList.remove('d-none');
        box.focus && box.focus();
    }

    function setInvalid(el, msg) {
        if (!el) return;
        el.classList.remove('is-valid');
        el.classList.add('is-invalid');
        const fb = el.parentElement.querySelector('.invalid-feedback');
        if (fb && msg) fb.textContent = msg;
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

    function attachRealtime(fields) {
        fields.forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('input', function(){
                let ok = true;
                if (id === 'telefono') ok = validarTelefono(el.value);
                else if (id === 'cantidad') ok = el.value === '' || (Number.isInteger(Number(el.value)) && Number(el.value) >= 1);
                else if (id === 'precio') ok = el.value === '' || validarDecimal(el.value);
                else if (id === 'latitud' || id === 'longitud') ok = validarDecimal(el.value);
                else ok = el.value.trim().length > 0;
                if (ok) clearInvalid(el); else setInvalid(el);
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
