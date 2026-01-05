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
    <title>Crear Producto - Paquetería RutaEx-Latam</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>

<?php include __DIR__ . '/../../includes/header.php'; ?>

<style>
.crear-producto-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    overflow: hidden;
}
.crear-producto-header {
    background: linear-gradient(135deg, #FF416C 0%, #FF4B2B 100%);
    color: white;
    padding: 1.5rem 2rem;
}
.crear-producto-header h3 {
    margin: 0;
    font-weight: 600;
}
.form-section {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border: 1px solid #e9ecef;
}
.form-section-title {
    font-weight: 600;
    color: #1a1a2e;
    margin-bottom: 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.1rem;
    border-bottom: 2px solid #e9ecef;
    padding-bottom: 0.5rem;
}
.form-section-title i {
    color: #f5576c;
}
.btn-save-product {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    border: none;
    padding: 0.75rem 2rem;
    font-weight: 600;
    border-radius: 10px;
    font-size: 1rem;
    color: white;
}
.btn-save-product:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(245, 87, 108, 0.4);
    color: white;
}
.btn-back {
    background: rgba(255,255,255,0.2);
    color: white;
    border: 1px solid rgba(255,255,255,0.4);
    padding: 0.5rem 1rem;
    border-radius: 8px;
    transition: all 0.3s;
    text-decoration: none;
}
.btn-back:hover {
    background: rgba(255,255,255,0.3);
    color: white;
}
</style>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <form id="formProducto" method="POST" action="<?php echo RUTA_URL; ?>productos/guardar" enctype="multipart/form-data">
                <?php 
                    require_once __DIR__ . '/../../../utils/csrf.php';
                    echo csrf_field(); 
                ?>
                
                <div class="card crear-producto-card">
                    <div class="crear-producto-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-white bg-opacity-25 rounded-circle p-3">
                                    <i class="bi bi-box-seam fs-3"></i>
                                </div>
                                <div>
                                    <h3>Nuevo Producto</h3>
                                    <p class="mb-0 opacity-75">Ingresa los detalles del nuevo producto</p>
                                </div>
                            </div>
                            <a href="<?php echo RUTA_URL; ?>productos/listar" class="btn btn-back">
                                <i class="bi bi-arrow-left me-1"></i> Volver
                            </a>
                        </div>
                    </div>

                    <div class="card-body p-4">
                        <!-- Información Básica -->
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="bi bi-info-circle"></i> Información Básica
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="sku" class="form-label fw-bold">SKU <span class="text-muted small fw-normal">(Opcional)</span></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="sku" name="sku" placeholder="Ej: ELEC-042" maxlength="50">
                                        <button type="button" class="btn btn-outline-secondary" id="btnGenerarSKU" onclick="generarSKU()">
                                            <i class="bi bi-magic"></i> Generar
                                        </button>
                                    </div>
                                    <small class="text-muted ms-1">Usa "Generar" para crear uno automático basado en la categoría.</small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="nombre" class="form-label fw-bold">Nombre del Producto <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nombre" name="nombre" required placeholder="Ej: Laptop Dell XPS 15">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="categoria_id" class="form-label fw-bold">Categoría</label>
                                    <select class="form-select select2-searchable" id="categoria_id" name="categoria_id" data-placeholder="Buscar categoría...">
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
                                    <label for="marca" class="form-label fw-bold">Marca</label>
                                    <input type="text" class="form-control" id="marca" name="marca" placeholder="Ej: Dell" maxlength="100">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="descripcion" class="form-label fw-bold">Descripción</label>
                                <textarea class="form-control" id="descripcion" name="descripcion" rows="3" placeholder="Descripción detallada del producto"></textarea>
                            </div>
                        </div>

                        <!-- Precios e Inventario -->
                        <div class="form-section">
                            <div class="form-section-title">
                                <i class="bi bi-currency-dollar"></i> Precios e Inventario
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="precio_usd" class="form-label fw-bold">Precio (USD) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="precio_usd" name="precio_usd" step="0.01" min="0" required placeholder="0.00">
                                    </div>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="unidad" class="form-label fw-bold">Unidad de Medida</label>
                                    <select class="form-select select2-searchable" id="unidad" name="unidad" data-placeholder="Seleccionar unidad...">
                                        <option value="unidad">Unidad</option>
                                        <option value="caja">Caja</option>
                                        <option value="paquete">Paquete</option>
                                        <option value="docena">Docena</option>
                                        <option value="kg">Kilogramo</option>
                                        <option value="litro">Litro</option>
                                    </select>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="peso" class="form-label fw-bold">Peso (kg)</label>
                                    <input type="number" class="form-control" id="peso" name="peso" step="0.01" min="0" placeholder="0.00">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="stock_minimo" class="form-label fw-bold">Stock Mínimo</label>
                                    <input type="number" class="form-control" id="stock_minimo" name="stock_minimo" value="10" min="0">
                                    <small class="text-muted">Nivel para alertas de stock bajo</small>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="stock_maximo" class="form-label fw-bold">Stock Máximo</label>
                                    <input type="number" class="form-control" id="stock_maximo" name="stock_maximo" value="100" min="0">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label fw-bold">Estado Inicial</label>
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" checked>
                                        <label class="form-check-label" for="activo">
                                            Producto activo
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Imagen -->
                        <div class="form-section mb-0">
                            <div class="form-section-title">
                                <i class="bi bi-image"></i> Imagen del Producto
                            </div>
                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label for="imagen" class="form-label fw-bold">Subir Imagen</label>
                                    <input type="file" class="form-control" id="imagen" name="imagen" accept="image/jpeg,image/png,image/gif,image/webp">
                                    <small class="text-muted d-block mt-1">
                                        <i class="bi bi-info-circle"></i> Formatos: JPG, PNG, GIF, WEBP. Máximo 5MB surt
                                    </small>
                                    
                                    <div class="mt-3">
                                        <label class="form-label fw-bold small text-muted">O ingresa una URL externa:</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                                            <input type="url" class="form-control" id="imagen_url" name="imagen_url" placeholder="https://ejemplo.com/imagen.jpg" maxlength="500">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 text-center">
                                    <label class="form-label fw-bold">Vista Previa</label>
                                    <div id="imagen-preview" class="border rounded p-2 d-flex align-items-center justify-content-center mx-auto bg-white" style="height: 160px; width: 100%; max-width: 200px;">
                                        <div>
                                            <i class="bi bi-image text-muted opacity-25" style="font-size: 3rem;"></i>
                                            <p class="text-muted small mb-0 opacity-75">Sin imagen</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Botones -->
                        <div class="d-flex justify-content-end gap-3 mt-4">
                            <a href="<?php echo RUTA_URL; ?>productos/listar" class="btn btn-secondary px-4">
                                Cancelar
                            </a>
                            <button type="submit" class="btn btn-save-product shadow-lg">
                                <i class="bi bi-check-lg me-1"></i> Guardar Producto
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
    // Función para mostrar preview
    function mostrarPreview(src) {
        const preview = document.getElementById('imagen-preview');
        preview.innerHTML = `<img src="${src}" class="img-fluid rounded" style="max-height: 140px;" onerror="resetPreview()">`;
    }
    
    function resetPreview() {
        const preview = document.getElementById('imagen-preview');
        preview.innerHTML = '<div><i class="bi bi-image text-muted" style="font-size: 3rem;"></i><p class="text-muted small mb-0">Sin imagen</p></div>';
    }

    // Preview de archivo subido
    document.getElementById('imagen').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            // Limpiar URL si hay archivo
            document.getElementById('imagen_url').value = '';
            
            // Validar tamaño (5MB)
            if (file.size > 5 * 1024 * 1024) {
                Swal.fire({icon: 'error', title: 'Error', text: 'La imagen excede 5MB'});
                this.value = '';
                resetPreview();
                return;
            }
            
            // Mostrar preview
            const reader = new FileReader();
            reader.onload = function(e) {
                mostrarPreview(e.target.result);
            };
            reader.readAsDataURL(file);
        } else {
            resetPreview();
        }
    });

    // Preview de URL externa
    document.getElementById('imagen_url').addEventListener('input', function() {
        const url = this.value.trim();
        if (url) {
            // Limpiar archivo si hay URL
            document.getElementById('imagen').value = '';
            mostrarPreview(url);
        } else {
            resetPreview();
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
