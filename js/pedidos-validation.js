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
        box.setAttribute('aria-live', 'assertive');
        if (typeof box.focus === 'function') box.focus();
    }

    const defaultMessages = {
        'numero_orden': 'Número de orden inválido.',
        'destinatario': 'Nombre inválido.',
        'telefono': 'Teléfono inválido (8-15 dígitos).',
        'producto_id': 'Producto requerido.',
        'cantidad_producto': 'Cantidad inválida.',
        'direccion': 'Dirección inválida.',
        'latitud': 'Latitud inválida.',
        'longitud': 'Longitud inválida.',
        'estado': 'Selecciona un estado válido.',
        'vendedor': 'Selecciona un usuario asignado.',
        'moneda': 'Selecciona una moneda válida.',
        'proveedor': 'Selecciona un proveedor válido.',
        'precio_local': 'Precio inválido.',
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

    function validarTelefono(value) {
        return /^\d{8,15}$/.test(value);
    }

    function validarDecimal(value) {
        return !isNaN(parseFloat(value)) && isFinite(value);
    }

    function validateFields(fieldDefs) {
        const errors = [];
        fieldDefs.forEach(function(field){
            const el = document.getElementById(field.id);
            const val = el ? el.value : '';
            const ok = field.fn(val);
            if (!ok) {
                setInvalid(el, field.msg);
                errors.push(field.msg);
            } else {
                clearInvalid(el);
            }
        });
        return errors;
    }

    function attachRealtime(fieldDefs) {
        if (!Array.isArray(fieldDefs)) return;
        const defsMap = {};
        fieldDefs.forEach(function(def){
            defsMap[def.id] = def;
        });

        Object.keys(defsMap).forEach(function(id){
            const el = document.getElementById(id);
            if (!el) return;
            const handler = function(){
                const def = defsMap[id];
                let ok;
                if (def && typeof def.fn === 'function') {
                    ok = def.fn(el.value);
                } else {
                    const v = el.value;
                    if (id === 'telefono') ok = validarTelefono(v);
                    else if (id === 'cantidad' || id === 'cantidad_producto') ok = v === '' || (Number.isInteger(Number(v)) && Number(v) >= 1);
                    else if (id === 'precio' || id === 'precio_local' || id === 'precio_usd') ok = v === '' || validarDecimal(v);
                    else if (id === 'latitud' || id === 'longitud') ok = validarDecimal(v);
                    else ok = v.trim().length > 0;
                }
                if (ok) clearInvalid(el); else setInvalid(el, def ? def.msg : undefined);
            };
            el.addEventListener('input', handler);
            el.addEventListener('change', handler);
        });
    }

    function initCrear() {
        const form = document.getElementById('formCrearPedido');
        if (!form) return;

        const productoSelect = document.getElementById('producto_id');
        const cantidadInput = document.getElementById('cantidad_producto');
        const productoAyuda = document.getElementById('productoAyuda');
        const monedaSelect = document.getElementById('moneda');
        const precioLocalInput = document.getElementById('precio_local');
        const precioUsdInput = document.getElementById('precio_usd');

        const getSelectedOption = function(select) {
            if (!select || select.selectedIndex < 0) return null;
            return select.options[select.selectedIndex] || null;
        };

        const getStockDisponible = function() {
            const option = getSelectedOption(productoSelect);
            if (!option) return NaN;
            const raw = option.getAttribute('data-stock');
            if (raw === null || raw === '') return NaN;
            const num = Number(raw);
            return Number.isNaN(num) ? NaN : num;
        };

        const getProductoUsd = function() {
            const option = getSelectedOption(productoSelect);
            if (!option) return NaN;
            const raw = option.getAttribute('data-precio-usd');
            if (raw === null || raw === '') return NaN;
            const num = Number(raw);
            return Number.isNaN(num) ? NaN : num;
        };

        const getTasaMoneda = function() {
            const option = getSelectedOption(monedaSelect);
            if (!option) return NaN;
            const raw = option.getAttribute('data-tasa');
            if (raw === null || raw === '') return NaN;
            const num = Number(raw);
            return Number.isNaN(num) ? NaN : num;
        };

        const validarCantidadProducto = function(value) {
            if (value === null || value === '') return false;
            const numero = Number(value);
            if (!Number.isInteger(numero) || numero < 1) return false;
            const stock = getStockDisponible();
            if (!Number.isNaN(stock) && stock > 0 && numero > stock) {
                return false;
            }
            return true;
        };

        const summaryFields = [
            {id:'numero_orden', fn: v => v.trim().length > 0, msg: 'Por favor ingresa un número de orden.'},
            {id:'destinatario', fn: v => v.trim().length >= 2, msg: 'Por favor, ingresa un nombre válido.'},
            {id:'telefono', fn: v => validarTelefono(v), msg: 'Teléfono inválido (8-15 dígitos).'},
            {id:'producto_id', fn: v => v !== null && v !== '', msg: 'Por favor, selecciona un producto.'},
            {id:'cantidad_producto', fn: validarCantidadProducto, msg: 'La cantidad debe ser al menos 1 y no superar el stock disponible.'},
            {id:'direccion', fn: v => v.trim().length > 5, msg: 'Dirección demasiado corta.'},
            {id:'latitud', fn: v => validarDecimal(v), msg: 'Latitud inválida.'},
            {id:'longitud', fn: v => validarDecimal(v), msg: 'Longitud inválida.'}
        ];

        summaryFields.push(
            {id:'estado', fn: v => v !== null && v !== '', msg: 'Selecciona un estado.'},
            {id:'vendedor', fn: v => v !== null && v !== '', msg: 'Selecciona un usuario asignado.'},
            {id:'proveedor', fn: v => v !== null && v !== '', msg: 'Selecciona un proveedor.'},
            {id:'moneda', fn: v => v !== null && v !== '', msg: 'Selecciona una moneda.'}
        );

        attachRealtime(summaryFields);

        const actualizarAyudaProducto = function() {
            if (!productoSelect) return;
            const option = getSelectedOption(productoSelect);
            const stock = getStockDisponible();
            if (!option || option.value === '') {
                if (productoAyuda) {
                    productoAyuda.textContent = 'Selecciona un producto para ver el stock disponible.';
                }
                if (cantidadInput) {
                    cantidadInput.placeholder = '';
                    cantidadInput.removeAttribute('max');
                }
                return;
            }
            if (productoAyuda) {
                if (!Number.isNaN(stock)) {
                    const unidades = stock === 1 ? 'unidad' : 'unidades';
                    productoAyuda.textContent = 'Stock disponible: ' + stock + ' ' + unidades + '.';
                } else {
                    productoAyuda.textContent = 'No hay stock registrado para este producto.';
                }
            }
            if (cantidadInput) {
                if (!Number.isNaN(stock) && stock > 0) {
                    cantidadInput.placeholder = 'Disponible: ' + stock;
                    cantidadInput.max = stock;
                    const actual = parseInt(cantidadInput.value, 10);
                    if (!Number.isNaN(actual) && actual > stock) {
                        cantidadInput.value = stock;
                    }
                } else {
                    cantidadInput.placeholder = '';
                    cantidadInput.removeAttribute('max');
                }
            }
        };

        const calcularLocalDesdeUsd = function(force) {
            if (!precioUsdInput || !precioLocalInput) return;
            const usdRaw = precioUsdInput.value.trim();
            if (usdRaw === '' || !validarDecimal(usdRaw)) {
                if (force) {
                    precioLocalInput.value = '';
                }
                return;
            }
            const tasa = getTasaMoneda();
            if (Number.isNaN(tasa) || tasa <= 0) {
                if (force) {
                    precioLocalInput.value = '';
                }
                return;
            }
            if (force || precioLocalInput.value.trim() === '') {
                const usdValue = parseFloat(usdRaw);
                const local = usdValue / tasa;
                precioLocalInput.value = local.toFixed(2);
            }
        };

        const calcularUsdDesdeLocal = function() {
            if (!precioUsdInput || !precioLocalInput) return;
            const localRaw = precioLocalInput.value.trim();
            if (localRaw === '' || !validarDecimal(localRaw)) {
                precioUsdInput.value = '';
                return;
            }
            const tasa = getTasaMoneda();
            if (Number.isNaN(tasa) || tasa <= 0) {
                precioUsdInput.value = '';
                return;
            }
            const localVal = parseFloat(localRaw);
            precioUsdInput.value = (localVal * tasa).toFixed(2);
        };

        const rellenarPrecioDesdeProducto = function(force) {
            if (!productoSelect || !precioUsdInput) return;
            const usd = getProductoUsd();
            if (Number.isNaN(usd)) {
                if (force) {
                    precioUsdInput.value = '';
                    if (precioLocalInput) {
                        precioLocalInput.value = '';
                    }
                }
                return;
            }
            if (force || precioUsdInput.value.trim() === '') {
                precioUsdInput.value = usd.toFixed(2);
            }
            calcularLocalDesdeUsd(force);
        };

        if (productoSelect) {
            productoSelect.addEventListener('change', function(){
                actualizarAyudaProducto();
                rellenarPrecioDesdeProducto(true);
            });
            actualizarAyudaProducto();
            rellenarPrecioDesdeProducto(false);
        }

        if (cantidadInput) {
            cantidadInput.addEventListener('input', function(){
                const valor = parseInt(cantidadInput.value, 10);
                if (!Number.isNaN(valor) && valor < 1) {
                    cantidadInput.value = '';
                    return;
                }
                const stock = getStockDisponible();
                if (!Number.isNaN(stock) && stock > 0 && !Number.isNaN(valor) && valor > stock) {
                    cantidadInput.value = stock;
                }
            });
        }

        if (precioLocalInput) {
            precioLocalInput.addEventListener('input', calcularUsdDesdeLocal);
            precioLocalInput.addEventListener('blur', calcularUsdDesdeLocal);
        }

        if (monedaSelect) {
            monedaSelect.addEventListener('change', function(){
                const localRaw = precioLocalInput ? precioLocalInput.value.trim() : '';
                if (localRaw !== '' && validarDecimal(localRaw)) {
                    calcularUsdDesdeLocal();
                } else {
                    calcularLocalDesdeUsd(true);
                    rellenarPrecioDesdeProducto(false);
                }
            });
        }

        form.addEventListener('submit', function(e){
            if (precioLocalInput && precioLocalInput.value.trim() !== '') {
                calcularUsdDesdeLocal();
            } else {
                calcularLocalDesdeUsd(true);
            }
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

        summaryFields.push(
            {id:'estado', fn: v => v !== null && v !== '', msg: 'Selecciona un estado.'},
            {id:'vendedor', fn: v => v !== null && v !== '', msg: 'Selecciona un usuario asignado.'}
        );

        attachRealtime(summaryFields);

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

    document.addEventListener('DOMContentLoaded', function(){
        if (document.getElementById('formCrearPedido')) initCrear();
        if (document.getElementById('formEditarPedido')) initEditar();
    });

    window.PedidosValidation = { initCrear: initCrear, initEditar: initEditar };

})(window, document);
