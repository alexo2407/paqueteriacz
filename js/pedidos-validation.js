(function (window, document) {
    'use strict';
    /**
     * pedidos-validation.js
     *
     * Valida formularios de creación/edición de pedidos en el frontend.
     * Proporciona validación en tiempo real, mensajes resumen y envío por AJAX
     * (fetch) con manejo de respuestas JSON y fallback a envío tradicional.
     *
     * API pública:
     *  - PedidosValidation.initCrear()
     *  - PedidosValidation.initEditar()
     *
     * El script se auto-inicializa en DOMContentLoaded si detecta los formularios
     * con ids `formCrearPedido` o `formEditarPedido`.
     */

    // Mostrar caja resumen de errores en la parte superior del formulario.
    // `errors` es un array de mensajes. El HTML esperado contiene
    // un contenedor con id `formErrors` y una lista con id `formErrorsList`.
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
        errors.forEach(function (msg) {
            const li = document.createElement('li');
            li.textContent = msg;
            list.appendChild(li);
        });
        box.classList.remove('d-none');
        box.setAttribute('aria-hidden', 'false');
        box.setAttribute('aria-live', 'assertive');
        if (typeof box.focus === 'function') box.focus();
    }

    // Mensajes por defecto usados cuando no se pasa un mensaje específico
    // en las definiciones de validación.
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

    // Marca un control como inválido aplicando clases Bootstrap y mostrando
    // el mensaje en el elemento `.invalid-feedback` más cercano.
    function setInvalid(el, msg) {
        if (!el) return;
        el.classList.remove('is-valid');
        el.classList.add('is-invalid');
        const fb = el.parentElement.querySelector('.invalid-feedback');
        const finalMsg = msg || defaultMessages[el.id] || 'Campo inválido';
        if (fb) fb.textContent = finalMsg;
    }

    // Marca un control como válido (quita is-invalid y añade is-valid).
    function clearInvalid(el) {
        if (!el) return;
        el.classList.remove('is-invalid');
        el.classList.add('is-valid');
    }

    // Validador simple para teléfonos: sólo dígitos (8-15 caracteres).
    function validarTelefono(value) {
        return /^\d{8,15}$/.test(value);
    }

    // Validador para números decimales (acepta notación con punto).
    function validarDecimal(value) {
        return !isNaN(parseFloat(value)) && isFinite(value);
    }

    // Valida un conjunto de campos definido como array de objetos:
    // { id: 'elementId', fn: validatorFn, msg: 'mensaje' }
    // Devuelve un array con los mensajes de error.
    function validateFields(fieldDefs) {
        const errors = [];
        fieldDefs.forEach(function (field) {
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

    // Adjunta validación en tiempo real a los campos listados en fieldDefs.
    // Esto permite feedback instantáneo cuando el usuario escribe o cambia
    // el valor del campo.
    function attachRealtime(fieldDefs) {
        if (!Array.isArray(fieldDefs)) return;
        const defsMap = {};
        fieldDefs.forEach(function (def) {
            defsMap[def.id] = def;
        });

        Object.keys(defsMap).forEach(function (id) {
            const el = document.getElementById(id);
            if (!el) return;
            const handler = function () {
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

        // Primary product select: prefer the first dynamic '.producto-select' row, fall back to legacy '#producto_id'
        function getPrimaryProductSelect() {
            // Prefer the first dynamic producto-select that already has a value (user filled).
            const dyns = document.querySelectorAll('#productosContainer .producto-select');
            for (let i = 0; i < dyns.length; i++) {
                const s = dyns[i];
                if (s && s.value && s.value !== '') return s;
            }
            // Fallback to the first dynamic select (even if empty) or legacy select
            return document.querySelector('#productosContainer .producto-select') || document.getElementById('producto_id');
        }
        const cantidadInput = document.getElementById('cantidad_producto');
        const productoAyuda = document.getElementById('productoAyuda');
        const monedaSelect = document.getElementById('moneda');
        const precioLocalInput = document.getElementById('precio_local');
        const precioUsdInput = document.getElementById('precio_usd');

        const getSelectedOption = function (select) {
            // Defensive: ensure select is a HTMLSelectElement and has options
            if (!select) return null;
            try {
                // selectedIndex sometimes may be undefined for non-select elements
                if (typeof select.selectedIndex !== 'number') return null;
                const idx = select.selectedIndex;
                if (!select.options || idx < 0) return null;
                return select.options[idx] || null;
            } catch (err) {
                // In case select is not a proper DOM element or other runtime issue
                return null;
            }
        };

        const getStockDisponible = function () {
            const option = getSelectedOption(getPrimaryProductSelect());
            if (!option) return NaN;
            const raw = option.getAttribute('data-stock');
            if (raw === null || raw === '') return NaN;
            const num = Number(raw);
            return Number.isNaN(num) ? NaN : num;
        };

        const getProductoUsd = function () {
            const option = getSelectedOption(getPrimaryProductSelect());
            if (!option) return NaN;
            const raw = option.getAttribute('data-precio-usd');
            if (raw === null || raw === '') return NaN;
            const num = Number(raw);
            return Number.isNaN(num) ? NaN : num;
        };

        const getTasaMoneda = function () {
            const option = getSelectedOption(monedaSelect);
            if (!option) return NaN;
            const raw = option.getAttribute('data-tasa');
            if (raw === null || raw === '') return NaN;
            const num = Number(raw);
            return Number.isNaN(num) ? NaN : num;
        };

        const validarCantidadProducto = function (value) {
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
            { id: 'numero_orden', fn: v => v.trim().length > 0, msg: 'Por favor ingresa un número de orden.' },
            { id: 'destinatario', fn: v => v.trim().length >= 2, msg: 'Por favor, ingresa un nombre válido.' },
            { id: 'telefono', fn: v => validarTelefono(v), msg: 'Teléfono inválido (8-15 dígitos).' },
            { id: 'producto_id', fn: v => v !== null && v !== '', msg: 'Por favor, selecciona un producto.' },
            { id: 'cantidad_producto', fn: validarCantidadProducto, msg: 'La cantidad debe ser al menos 1 y no superar el stock disponible.' },
            { id: 'direccion', fn: v => v.trim().length > 5, msg: 'Dirección demasiado corta.' },
            { id: 'latitud', fn: v => validarDecimal(v), msg: 'Latitud inválida.' },
            { id: 'longitud', fn: v => validarDecimal(v), msg: 'Longitud inválida.' }
        ];

        summaryFields.push(
            { id: 'estado', fn: v => v !== null && v !== '', msg: 'Selecciona un estado.' },
            { id: 'vendedor', fn: v => v !== null && v !== '', msg: 'Selecciona un usuario asignado.' },
            { id: 'proveedor', fn: v => v !== null && v !== '', msg: 'Selecciona un proveedor.' },
            { id: 'moneda', fn: v => v !== null && v !== '', msg: 'Selecciona una moneda.' }
        );

        // If the form uses multiple product rows (productos[]), validate them instead
        const productosContainer = document.getElementById('productosContainer');
        if (productosContainer) {
            summaryFields.push({
                id: 'productos',
                fn: function () {
                    const rows = productosContainer.querySelectorAll('.producto-row');
                    if (!rows || rows.length === 0) return false;
                    for (let i = 0; i < rows.length; i++) {
                        const sel = rows[i].querySelector('.producto-select');
                        const qty = rows[i].querySelector('.producto-cantidad');
                        if (!sel || !qty) return false;
                        const val = sel.value;
                        const qv = qty.value;
                        if (!val || val === '') return false;
                        const num = Number(qv);
                        if (!Number.isInteger(num) || num < 1) return false;
                        // check stock if available
                        const opt = sel.options[sel.selectedIndex];
                        if (opt) {
                            const stockRaw = opt.getAttribute('data-stock');
                            if (stockRaw !== null && stockRaw !== '') {
                                const stock = Number(stockRaw);
                                if (!Number.isNaN(stock) && stock > 0 && num > stock) return false;
                            }
                        }
                    }
                    return true;
                },
                msg: 'Agrega al menos un producto con cantidad válida.'
            });
        }

        attachRealtime(summaryFields);

        const actualizarAyudaProducto = function () {
            const primary = getPrimaryProductSelect();
            if (!primary) return;
            const option = getSelectedOption(primary);
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

        const calcularLocalDesdeUsd = function (force) {
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
                const local = usdValue * tasa;  // USD × tasa = Local
                precioLocalInput.value = local.toFixed(2);
            }
        };

        const calcularUsdDesdeLocal = function () {
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
            precioUsdInput.value = (localVal / tasa).toFixed(2);  // Local / tasa = USD
        };

        const rellenarPrecioDesdeProducto = function (force) {
            const primary = getPrimaryProductSelect();
            if (!primary || !precioUsdInput) return;
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

        // Use event delegation: when any dynamic product select changes, update stock/help and price
        if (productosContainer) {
            productosContainer.addEventListener('change', function (e) {
                if (e.target && e.target.classList && e.target.classList.contains('producto-select')) {
                    actualizarAyudaProducto();
                    rellenarPrecioDesdeProducto(true);
                }
            });
        }
        // initialize helpers on load
        actualizarAyudaProducto();
        rellenarPrecioDesdeProducto(false);

        if (cantidadInput) {
            cantidadInput.addEventListener('input', function () {
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
            monedaSelect.addEventListener('change', function () {
                const localRaw = precioLocalInput ? precioLocalInput.value.trim() : '';
                if (localRaw !== '' && validarDecimal(localRaw)) {
                    calcularUsdDesdeLocal();
                } else {
                    calcularLocalDesdeUsd(true);
                    rellenarPrecioDesdeProducto(false);
                }
            });
        }

        // Interceptamos el submit para enviar por AJAX (fetch). Si el navegador
        // no soporta fetch o la llamada falla, hacemos fallback al envío tradicional.
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            // Estado de carga: deshabilitar el botón submit y cambiar texto
            const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
            const setLoading = function (on) {
                if (!submitBtn) return;
                if (on) {
                    submitBtn.dataset._orig = submitBtn.innerHTML || submitBtn.value || '';
                    try { submitBtn.innerHTML = 'Guardando...'; } catch (err) { submitBtn.value = 'Guardando...'; }
                    submitBtn.disabled = true;
                } else {
                    if (submitBtn.dataset._orig !== undefined) {
                        try { submitBtn.innerHTML = submitBtn.dataset._orig; } catch (err) { submitBtn.value = submitBtn.dataset._orig; }
                        delete submitBtn.dataset._orig;
                    }
                    submitBtn.disabled = false;
                }
            };

            if (precioLocalInput && precioLocalInput.value.trim() !== '') {
                calcularUsdDesdeLocal();
            } else {
                calcularLocalDesdeUsd(true);
            }

            // Prune empty product rows first so validation sees only real items
            const rows = productosContainer ? productosContainer.querySelectorAll('.producto-row') : [];
            rows.forEach(row => {
                const sel = row.querySelector('.producto-select');
                const qty = row.querySelector('.producto-cantidad');
                if (!sel || sel.value === '' || !qty || qty.value === '' || parseInt(qty.value) < 1) {
                    row.remove();
                }
            });

            const errors = validateFields(summaryFields);
            showSummaryErrors(errors);
            if (errors.length > 0) {
                return false;
            }

            // Preparar envío AJAX
            const submitUrl = form.getAttribute('action') || window.location.href;
            const fd = new FormData(form);

            setLoading(true);

            if (!window.fetch) {
                // Fallback: browser no soporta fetch
                form.submit();
                return true;
            }

            fetch(submitUrl, {
                method: 'POST',
                body: fd,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            }).then(function (resp) {
                return resp.json().catch(function () {
                    throw new Error('Respuesta no es JSON');
                });
            }).then(function (data) {
                // quitar loading
                setLoading(false);
                if (data && data.success) {
                    // Mostrar SweetAlert de éxito y mantener los datos en pantalla
                    if (window.Swal && typeof window.Swal.fire === 'function') {
                        // Mostrar mensaje breve y redirigir automáticamente a la interfaz de edición
                        // si el servidor devolvió una URL. Si no hay URL, mantenemos el formulario
                        // con el id agregado.
                        if (data && data.redirect) {
                            // Usar un pequeño retardo para que el usuario vea la notificación
                            window.Swal.fire({ icon: 'success', title: 'Pedido creado', text: data.message || 'Redirigiendo a edición...', showConfirmButton: false, timer: 800 });
                            setTimeout(function () { window.location.href = data.redirect; }, 900);
                            return;
                        }
                        // Si no hay redirect, pero se devolvió el id, lo guardamos en el formulario
                        if (data.id) {
                            let idField = form.querySelector('input[name="id_pedido"]');
                            if (!idField) {
                                idField = document.createElement('input');
                                idField.type = 'hidden';
                                idField.name = 'id_pedido';
                                form.appendChild(idField);
                            }
                            idField.value = data.id;
                        }
                    } else {
                        alert(data.message || 'Pedido guardado correctamente.');
                    }
                } else {
                    if (window.Swal && typeof window.Swal.fire === 'function') {
                        window.Swal.fire({ icon: 'error', title: 'Error', text: (data && data.message) ? data.message : 'No fue posible guardar el pedido.' });
                    }
                    if (data && data.errors) showSummaryErrors(data.errors);
                }
            }).catch(function (err) {
                setLoading(false);
                console.error('Error al enviar por AJAX:', err);
                // Fallback conservador: enviar el formulario tradicionalmente
                if (window.Swal && typeof window.Swal.fire === 'function') {
                    window.Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo conectar con el servidor. Se realizará el envío tradicional.' }).then(function () { form.submit(); });
                } else {
                    form.submit();
                }
            });

            return false;
        });
    }

    function initEditar() {
        const form = document.getElementById('formEditarPedido');
        if (!form) return;

        const productoSelect = document.getElementById('producto_id');
        const cantidadInput = document.getElementById('cantidad_producto');
        const monedaSelect = document.getElementById('moneda');
        const precioLocalInput = document.getElementById('precio_local');
        const precioUsdInput = document.getElementById('precio_usd');

        const getSelectedOption = function (select) {
            if (!select || select.tagName !== 'SELECT' || select.selectedIndex < 0) return null;
            return select.options[select.selectedIndex] || null;
        };
        const getProductoUsd = function () {
            const option = getSelectedOption(productoSelect);
            if (!option) return NaN;
            const raw = option.getAttribute('data-precio-usd');
            if (raw === null || raw === '') return NaN;
            const num = Number(raw);
            return Number.isNaN(num) ? NaN : num;
        };
        const getTasaMoneda = function () {
            const option = getSelectedOption(monedaSelect);
            if (!option) return NaN;
            const raw = option.getAttribute('data-tasa');
            if (raw === null || raw === '') return NaN;
            const num = Number(raw);
            return Number.isNaN(num) ? NaN : num;
        };

        const summaryFields = [
            { id: 'destinatario', fn: v => v.trim().length >= 2, msg: 'Por favor, ingresa un nombre válido.' },
            { id: 'telefono', fn: v => validarTelefono(v), msg: 'Teléfono inválido (8-15 dígitos).' },
            { id: 'producto_id', fn: v => v !== null && v !== '', msg: 'Por favor, selecciona un producto.' },
            { id: 'cantidad_producto', fn: v => v !== '' && Number.isInteger(Number(v)) && Number(v) >= 1, msg: 'La cantidad debe ser al menos 1.' },
            { id: 'precio_local', fn: v => v === '' || validarDecimal(v), msg: 'Precio local inválido.' },
            { id: 'direccion', fn: v => v.trim().length > 5, msg: 'Dirección demasiado corta.' },
            { id: 'latitud', fn: v => validarDecimal(v), msg: 'Latitud inválida.' },
            { id: 'longitud', fn: v => validarDecimal(v), msg: 'Longitud inválida.' },
            { id: 'estado', fn: v => v !== null && v !== '', msg: 'Selecciona un estado.' },
            { id: 'vendedor', fn: v => v !== null && v !== '', msg: 'Selecciona un usuario asignado.' },
            { id: 'proveedor', fn: v => v !== null && v !== '', msg: 'Selecciona un proveedor.' },
            { id: 'moneda', fn: v => v !== null && v !== '', msg: 'Selecciona una moneda.' }
        ];

        // If the form uses multiple product rows (productos[]), validate them instead
        const productosContainerEd = document.getElementById('productosContainer');
        if (productosContainerEd) {
            summaryFields.push({
                id: 'productos',
                fn: function () {
                    const rows = productosContainerEd.querySelectorAll('.producto-row');
                    if (!rows || rows.length === 0) return false;
                    for (let i = 0; i < rows.length; i++) {
                        const sel = rows[i].querySelector('.producto-select');
                        const qty = rows[i].querySelector('.producto-cantidad');
                        if (!sel || !qty) return false;
                        const val = sel.value;
                        const qv = qty.value;
                        if (!val || val === '') return false;
                        const num = Number(qv);
                        if (!Number.isInteger(num) || num < 1) return false;
                    }
                    return true;
                },
                msg: 'Agrega al menos un producto con cantidad válida.'
            });
        }

        attachRealtime(summaryFields);

        // Rellenar precio desde producto si está vacío
        if (productoSelect) {
            const fillFromProduct = function (force) {
                const usd = getProductoUsd();
                if (!Number.isNaN(usd) && (force || (precioUsdInput && precioUsdInput.value.trim() === ''))) {
                    if (precioUsdInput) precioUsdInput.value = usd.toFixed(2);
                    // si tenemos tasa, calcular local
                    const tasa = getTasaMoneda();
                    if (!Number.isNaN(tasa) && tasa > 0 && precioLocalInput) {
                        precioLocalInput.value = (usd * tasa).toFixed(2);  // USD × tasa = Local
                    }
                }
            };
            productoSelect.addEventListener('change', function () { fillFromProduct(true); });
            fillFromProduct(false);
        }

        // Recalcular precio cuando cambia moneda
        if (monedaSelect) {
            monedaSelect.addEventListener('change', function () {
                if (!precioUsdInput || !precioLocalInput) return;
                const usd = precioUsdInput.value.trim();
                const tasa = getTasaMoneda();
                if (usd !== '' && validarDecimal(usd) && !Number.isNaN(tasa) && tasa > 0) {
                    precioLocalInput.value = (parseFloat(usd) * tasa).toFixed(2);  // USD × tasa = Local
                }
            });
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            // Asegurar consistencia de precios antes de validar
            if (precioLocalInput && precioLocalInput.value.trim() === '' && precioUsdInput && precioUsdInput.value.trim() !== '') {
                const tasa = getTasaMoneda();
                if (!Number.isNaN(tasa) && tasa > 0) {
                    precioLocalInput.value = (parseFloat(precioUsdInput.value) * tasa).toFixed(2);  // USD × tasa = Local
                }
            }

            const errors = validateFields(summaryFields);
            showSummaryErrors(errors);
            if (errors.length > 0) {
                return false;
            }

            // Estado de carga para edición
            const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
            const setLoading = function (on) {
                if (!submitBtn) return;
                if (on) {
                    submitBtn.dataset._orig = submitBtn.innerHTML || submitBtn.value || '';
                    try { submitBtn.innerHTML = 'Guardando...'; } catch (err) { submitBtn.value = 'Guardando...'; }
                    submitBtn.disabled = true;
                } else {
                    if (submitBtn.dataset._orig !== undefined) {
                        try { submitBtn.innerHTML = submitBtn.dataset._orig; } catch (err) { submitBtn.value = submitBtn.dataset._orig; }
                        delete submitBtn.dataset._orig;
                    }
                    submitBtn.disabled = false;
                }
            };

            // Envío por AJAX para edición también
            const submitUrl = form.getAttribute('action') || window.location.href;
            const fd = new FormData(form);

            if (!window.fetch) { form.submit(); return true; }

            setLoading(true);

            fetch(submitUrl, {
                method: 'POST',
                body: fd,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            }).then(function (resp) {
                return resp.json().catch(function () { throw new Error('Respuesta no es JSON'); });
            }).then(function (data) {
                setLoading(false);
                if (data && data.success) {
                    if (window.Swal && typeof window.Swal.fire === 'function') {
                        window.Swal.fire({ icon: 'success', title: 'Guardado', text: data.message || 'Pedido actualizado correctamente.' });
                    } else {
                        alert(data.message || 'Pedido actualizado correctamente.');
                    }
                } else {
                    if (window.Swal && typeof window.Swal.fire === 'function') {
                        window.Swal.fire({ icon: 'error', title: 'Error', text: (data && data.message) ? data.message : 'No fue posible actualizar el pedido.' });
                    }
                    if (data && data.errors) showSummaryErrors(data.errors);
                }
            }).catch(function (err) {
                setLoading(false);
                console.error('Error AJAX editar:', err);
                if (window.Swal && typeof window.Swal.fire === 'function') {
                    window.Swal.fire({ icon: 'error', title: 'Error de red', text: 'No se pudo conectar con el servidor. Se realizará el envío tradicional.' }).then(function () { form.submit(); });
                } else {
                    form.submit();
                }
            });

            return false;
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (document.getElementById('formCrearPedido')) initCrear();
        if (document.getElementById('formEditarPedido')) initEditar();
    });

    window.PedidosValidation = { initCrear: initCrear, initEditar: initEditar };

})(window, document);
