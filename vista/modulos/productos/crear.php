<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../utils/session.php';
require_once __DIR__ . '/../../../modelo/producto.php';
require_once __DIR__ . '/../../../modelo/categoria.php';

start_secure_session();
require_login();

// Obtener categorías para el selector
$categorias = CategoriaModel::listarJerarquico();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Producto - Paquetería CruzValle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>

<?php include __DIR__ . '/../../includes/header.php'; ?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="bi bi-plus-circle"></i> Crear Nuevo Producto</h2>
                    <p class="text-muted mb-0">Completa la información del producto</p>
                </div>
                <a href="<?php echo RUTA_URL; ?>productos/listar" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>

            <!-- Formulario -->
            <form id="formProducto" method="POST" action="<?php echo RUTA_URL; ?>productos/guardar">
                <?php 
                    require_once __DIR__ . '/../../../utils/csrf.php';
                    echo csrf_field(); 
                ?>
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-info-circle"></i> Información Básica</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="sku" class="form-label">SKU <span class="text-muted">(Recomendado)</span></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="sku" name="sku" placeholder="Ej: ELEC-042" maxlength="50">
                                    <button type="button" class="btn btn-outline-secondary" id="btnGenerarSKU" onclick="generarSKU()">
                                        <i class="bi bi-magic"></i> Generar
                                    </button>
                                </div>
                                <small class="text-muted"><i class="bi bi-info-circle"></i> Recomendado para mejor organización. Usa "Generar" para crear uno automático</small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="nombre" class="form-label">Nombre del Producto <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nombre" name="nombre" required placeholder="Ej: Laptop Dell XPS 15">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="categoria_id" class="form-label">Categoría</label>
                                <select class="form-select" id="categoria_id" name="categoria_id">
                                    <option value="">Sin categoría</option>
                                    <?php foreach ($categorias as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>">
                                            <?php echo htmlspecialchars($cat['nombre']); ?>
                                        </option>
                                        <?php if (!empty($cat['subcategorias'])): ?>
                                            <?php foreach ($cat['subcategorias'] as $subcat): ?>
                                                <option value="<?php echo $subcat['id']; ?>">
                                                    &nbsp;&nbsp;↳ <?php echo htmlspecialchars($subcat['nombre']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="marca" class="form-label">Marca</label>
                                <input type="text" class="form-control" id="marca" name="marca" placeholder="Ej: Dell" maxlength="100">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="3" placeholder="Descripción detallada del producto"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Precios e Inventario -->
                <div class="card mt-3">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-cash-stack"></i> Precios e Inventario</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="precio_usd" class="form-label">Precio (USD) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="precio_usd" name="precio_usd" step="0.01" min="0" required placeholder="0.00">
                                </div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="unidad" class="form-label">Unidad de Medida</label>
                                <select class="form-select" id="unidad" name="unidad">
                                    <option value="unidad">Unidad</option>
                                    <option value="caja">Caja</option>
                                    <option value="paquete">Paquete</option>
                                    <option value="docena">Docena</option>
                                    <option value="kg">Kilogramo</option>
                                    <option value="litro">Litro</option>
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="peso" class="form-label">Peso (kg)</label>
                                <input type="number" class="form-control" id="peso" name="peso" step="0.01" min="0" placeholder="0.00">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="stock_minimo" class="form-label">Stock Mínimo</label>
                                <input type="number" class="form-control" id="stock_minimo" name="stock_minimo" value="10" min="0">
                                <small class="text-muted">Para alertas</small>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="stock_maximo" class="form-label">Stock Máximo</label>
                                <input type="number" class="form-control" id="stock_maximo" name="stock_maximo" value="100" min="0">
                                <small class="text-muted">Capacidad máxima</small>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Estado</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" checked>
                                    <label class="form-check-label" for="activo">
                                        Producto activo
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Imagen -->
                <div class="card mt-3">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-image"></i> Imagen del Producto</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="imagen_url" class="form-label">URL de la Imagen</label>
                                <input type="url" class="form-control" id="imagen_url" name="imagen_url" placeholder="https://ejemplo.com/imagen.jpg" maxlength="500">
                                <small class="text-muted">Ingresa la URL de la imagen del producto</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Vista Previa</label>
                                <div id="imagen-preview" class="border rounded p-2 text-center" style="height: 120px; background: #f8f9fa;">
                                    <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted small mb-0">Sin imagen</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botones -->
                <div class="card mt-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <a href="<?php echo RUTA_URL; ?>productos/listar" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-save"></i> Guardar Producto
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<script>
    // Preview de imagen
    document.getElementById('imagen_url').addEventListener('input', function() {
        const url = this.value;
        const preview = document.getElementById('imagen-preview');
        
        if (url) {
            preview.innerHTML = `<img src="${url}" class="img-fluid" style="max-height: 110px;" onerror="this.parentElement.innerHTML='<i class=\\'bi bi-exclamation-triangle text-warning\\' style=\\'font-size: 3rem;\\'></i><p class=\\'text-muted small mb-0\\'>Error al cargar</p>'">`;
        } else {
            preview.innerHTML = '<i class="bi bi-image text-muted" style="font-size: 3rem;"></i><p class="text-muted small mb-0">Sin imagen</p>';
        }
    });

    // Función para generar SKU automático
    function generarSKU() {
        const categoriaSelect = document.getElementById('categoria_id');
        const skuInput = document.getElementById('sku');
        
        // Obtener el texto de la categoría seleccionada
        let prefijo = 'PROD';
        if (categoriaSelect.value) {
            const categoriaTexto = categoriaSelect.options[categoriaSelect.selectedIndex].text.trim();
            // Limpiar subcategorías (si hay ↳)
            const categoriaSinFlecha = categoriaTexto.replace(/↳/g, '').trim();
            // Tomar primeras 4 letras de la categoría
            prefijo = categoriaSinFlecha.substring(0, 4).toUpperCase().replace(/[^A-Z]/g, '');
            // Si después de limpiar queda vacío, usar PROD
            if (prefijo.length === 0) prefijo = 'PROD';
        }
        
        // Generar número aleatorio de 3 dígitos
        const numero = String(Math.floor(Math.random() * 1000)).padStart(3, '0');
        
        // Construir SKU
        const skuGenerado = `${prefijo}-${numero}`;
        
        // Asignar al input con animación
        skuInput.style.transition = 'all 0.3s';
        skuInput.style.transform = 'scale(1.05)';
        skuInput.style.backgroundColor = '#e7f1ff';
        skuInput.value = skuGenerado;
        
        setTimeout(() => {
            skuInput.style.transform = 'scale(1)';
            skuInput.style.backgroundColor = '';
        }, 300);
        
        // Mostrar toast de confirmación
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'SKU generado',
            text: skuGenerado,
            showConfirmButton: false,
            timer: 2000
        });
    }

    // Auto-generar SKU cuando cambia la categoría (opcional)
    document.getElementById('categoria_id').addEventListener('change', function() {
        const skuInput = document.getElementById('sku');
        // Solo auto-generar si el SKU está vacío o es un SKU generado previamente
        if (!skuInput.value || skuInput.value.match(/^[A-Z]{2,4}-\d{3}$/)) {
            generarSKU();
        }
    });

    // Validación del formulario antes de enviar
    document.getElementById('formProducto').addEventListener('submit', function(e) {
        const nombre = document.getElementById('nombre').value.trim();
        const precio = document.getElementById('precio_usd').value;
        
        if (nombre === '') {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'El nombre del producto es obligatorio'
            });
            return false;
        }
        
        if (precio === '' || parseFloat(precio) < 0) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'El precio es obligatorio y debe ser válido'
            });
            return false;
        }
        
        // Asegurar que activo tenga valor
        const activoCheckbox = document.getElementById('activo');
        if (!activoCheckbox.checked) {
            // Agregar campo hidden para enviar 0
            let hiddenActivo = document.querySelector('input[name="activo"][type="hidden"]');
            if (!hiddenActivo) {
                hiddenActivo = document.createElement('input');
                hiddenActivo.type = 'hidden';
                hiddenActivo.name = 'activo';
                hiddenActivo.value = '0';
                this.appendChild(hiddenActivo);
            }
        }
        
        // Permitir submit normal - la ruta web maneja la respuesta
        return true;
    });

</script>
</body>
</html>
