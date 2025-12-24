<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../utils/session.php';
require_once __DIR__ . '/../../../utils/permissions.php';
require_once __DIR__ . '/../../../modelo/producto.php';
require_once __DIR__ . '/../../../modelo/categoria.php';

start_secure_session();
require_login();

// Obtener ID del producto
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($parametros[0]) ? (int)$parametros[0] : 0);

if ($id <= 0) {
    header('Location: ' . RUTA_URL . 'productos/listar');
    exit;
}

// Obtener datos del producto
$producto = ProductoModel::obtenerPorId($id);
if (!$producto) {
    header('Location: ' . RUTA_URL . 'productos/listar');
    exit;
}

// Verificar permisos: solo admin o el creador pueden editar
if (!canEditProduct($producto)) {
    // Redirigir con mensaje de error
    header('Location: ' . RUTA_URL . 'productos/listar?error=no_autorizado');
    exit;
}

// Obtener categorías
$categorias = CategoriaModel::listarJerarquico();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Producto - Paquetería CruzValle</title>
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
                    <h2><i class="bi bi-pencil"></i> Editar Producto</h2>
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($producto['nombre']); ?></p>
                </div>
                <div>
                    <a href="<?php echo RUTA_URL;?>productos/ver/<?php echo $id; ?>" class="btn btn-outline-info me-2">
                        <i class="bi bi-eye"></i> Ver
                    </a>
                    <a href="<?php echo RUTA_URL; ?>productos/listar" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Volver
                    </a>
                </div>
            </div>

            <!-- Formulario -->
            <form id="formProducto" method="POST" action="<?php echo RUTA_URL; ?>productos/actualizar/<?php echo $id; ?>" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <input type="hidden" name="imagen_actual" value="<?php echo htmlspecialchars($producto['imagen_url'] ?? ''); ?>">
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
                                    <input type="text" class="form-control" id="sku" name="sku" 
                                           value="<?php echo htmlspecialchars($producto['sku'] ?? ''); ?>" maxlength="50" placeholder="Ej: ELEC-042">
                                    <?php if (empty($producto['sku'])): ?>
                                        <button type="button" class="btn btn-outline-secondary" id="generarSKU">
                                            <i class="bi bi-magic"></i> Generar
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted">
                                    <?php if (empty($producto['sku'])): ?>
                                        <i class="bi bi-lightbulb"></i> <strong>Sugerencia:</strong> Asigna un SKU para mejor organización. Puedes generarlo automáticamente o escribirlo manualmente.
                                    <?php else: ?>
                                        <i class="bi bi-check-circle text-success"></i> Código único del producto
                                    <?php endif; ?>
                                </small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="nombre" class="form-label">Nombre del Producto <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nombre" name="nombre" required 
                                       value="<?php echo htmlspecialchars($producto['nombre']); ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="categoria_id" class="form-label">Categoría</label>
                                <select class="form-select" id="categoria_id" name="categoria_id">
                                    <option value="">Sin categoría</option>
                                    <?php foreach ($categorias as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" 
                                                <?php echo ($producto['categoria_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['nombre']); ?>
                                        </option>
                                        <?php if (!empty($cat['subcategorias'])): ?>
                                            <?php foreach ($cat['subcategorias'] as $subcat): ?>
                                                <option value="<?php echo $subcat['id']; ?>"
                                                        <?php echo ($producto['categoria_id'] == $subcat['id']) ? 'selected' : ''; ?>>
                                                    &nbsp;&nbsp;↳ <?php echo htmlspecialchars($subcat['nombre']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="marca" class="form-label">Marca</label>
                                <input type="text" class="form-control" id="marca" name="marca" 
                                       value="<?php echo htmlspecialchars($producto['marca'] ?? ''); ?>" maxlength="100">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?php echo htmlspecialchars($producto['descripcion'] ?? ''); ?></textarea>
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
                                    <input type="number" class="form-control" id="precio_usd" name="precio_usd" step="0.01" min="0" required 
                                           value="<?php echo $producto['precio_usd']; ?>">
                                </div>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="unidad" class="form-label">Unidad de Medida</label>
                                <select class="form-select" id="unidad" name="unidad">
                                    <?php 
                                    $unidades = ['unidad', 'caja', 'paquete', 'docena', 'kg', 'litro'];
                                    foreach ($unidades as $u):
                                    ?>
                                        <option value="<?php echo $u; ?>" <?php echo ($producto['unidad'] == $u) ? 'selected' : ''; ?>>
                                            <?php echo ucfirst($u); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="peso" class="form-label">Peso (kg)</label>
                                <input type="number" class="form-control" id="peso" name="peso" step="0.01" min="0" 
                                       value="<?php echo $producto['peso'] ?? 0; ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="stock_minimo" class="form-label">Stock Mínimo</label>
                                <input type="number" class="form-control" id="stock_minimo" name="stock_minimo" min="0" 
                                       value="<?php echo $producto['stock_minimo'] ?? 10; ?>">
                                <small class="text-muted">Para alertas</small>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label for="stock_maximo" class="form-label">Stock Máximo</label>
                                <input type="number" class="form-control" id="stock_maximo" name="stock_maximo" min="0" 
                                       value="<?php echo $producto['stock_maximo'] ?? 100; ?>">
                                <small class="text-muted">Capacidad máxima</small>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Estado</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" 
                                           <?php echo (isset($producto['activo']) && $producto['activo']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="activo">
                                        Producto activo
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> <strong>Stock Actual:</strong> 
                            <?php 
                            $stockActual = $producto['stock_total'] ?? 0;
                            $stockMinimo = $producto['stock_minimo'] ?? 10;
                            echo $stockActual . ' unidades';
                            if ($stockActual < $stockMinimo) {
                                echo ' <span class="badge bg-warning text-dark">Stock Bajo</span>';
                            }
                            ?>
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
                                <label for="imagen" class="form-label">Subir Nueva Imagen</label>
                                <input type="file" class="form-control" id="imagen" name="imagen" accept="image/jpeg,image/png,image/gif,image/webp">
                                <small class="text-muted">
                                    <i class="bi bi-info-circle"></i> Formatos: JPG, PNG, GIF, WEBP. Máximo 5MB
                                </small>
                                
                                <div class="mt-2">
                                    <small class="text-muted">O ingresa una URL externa:</small>
                                    <input type="url" class="form-control form-control-sm mt-1" id="imagen_url" name="imagen_url" 
                                           value="<?php echo htmlspecialchars($producto['imagen_url'] ?? ''); ?>" 
                                           placeholder="https://ejemplo.com/imagen.jpg" maxlength="500">
                                </div>
                                
                                <?php if (!empty($producto['imagen_url'])): ?>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="eliminar_imagen" name="eliminar_imagen" value="1">
                                    <label class="form-check-label text-danger" for="eliminar_imagen">
                                        <i class="bi bi-trash"></i> Eliminar imagen actual
                                    </label>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Vista Previa</label>
                                <div id="imagen-preview" class="border rounded p-2 text-center d-flex align-items-center justify-content-center" style="height: 150px; background: #f8f9fa;">
                                    <?php if (!empty($producto['imagen_url'])): ?>
                                        <?php 
                                        $imgSrc = $producto['imagen_url'];
                                        if (!str_starts_with($imgSrc, 'http')) {
                                            $imgSrc = RUTA_URL . $imgSrc;
                                        }
                                        ?>
                                        <img src="<?php echo htmlspecialchars($imgSrc); ?>" class="img-fluid rounded" style="max-height: 140px;" 
                                             onerror="this.parentElement.innerHTML='<div><i class=\'bi bi-exclamation-triangle text-warning\' style=\'font-size: 3rem;\'></i><p class=\'text-muted small mb-0\'>Error al cargar</p></div>'">
                                    <?php else: ?>
                                        <div>
                                            <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                                            <p class="text-muted small mb-0">Sin imagen</p>
                                        </div>
                                    <?php endif; ?>
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
                                <i class="bi bi-save"></i> Guardar Cambios
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
    // Auto-generar SKU
    <?php if (empty($producto['sku'])): ?>
    document.getElementById('generarSKU')?.addEventListener('click', function() {
        const categoriaId = document.getElementById('categoria_id').value;
        
        if (!categoriaId) {
            Swal.fire({
                icon: 'warning',
                title: 'Selecciona una categoría',
                text: 'Primero selecciona una categoría para generar el SKU'
            });
            return;
        }
        
        // Obtener prefijo de la categoría
        const categoriaSelect = document.getElementById('categoria_id');
        const categoriaNombre = categoriaSelect.options[categoriaSelect.selectedIndex].text.trim();
        const prefijo = categoriaNombre.substring(0, 4).toUpperCase().replace(/[^A-Z0-9]/g, '');
        
        // Generar número aleatorio de 3 dígitos
        const numero = Math.floor(Math.random() * 900) + 100;
        
        // Generar SKU
        const sku = `${prefijo}-${numero}`;
        document.getElementById('sku').value = sku;
        
        Swal.fire({
            icon: 'success',
            title: 'SKU Generado',
            text: `SKU: ${sku}`,
            timer: 2000,
            showConfirmButton: false
        });
    });
    <?php endif; ?>

    // Funciones de preview de imagen
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
            document.getElementById('imagen_url').value = '';
            if (document.getElementById('eliminar_imagen')) {
                document.getElementById('eliminar_imagen').checked = false;
            }
            
            if (file.size > 5 * 1024 * 1024) {
                Swal.fire({icon: 'error', title: 'Error', text: 'La imagen excede 5MB'});
                this.value = '';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                mostrarPreview(e.target.result);
            };
            reader.readAsDataURL(file);
        }
    });

    // Preview de URL externa
    document.getElementById('imagen_url').addEventListener('input', function() {
        const url = this.value.trim();
        if (url) {
            document.getElementById('imagen').value = '';
            if (document.getElementById('eliminar_imagen')) {
                document.getElementById('eliminar_imagen').checked = false;
            }
            mostrarPreview(url);
        } else {
            resetPreview();
        }
    });
    
    // Limpiar preview si se marca eliminar
    const eliminarCheck = document.getElementById('eliminar_imagen');
    if (eliminarCheck) {
        eliminarCheck.addEventListener('change', function() {
            if (this.checked) {
                resetPreview();
                document.getElementById('imagen').value = '';
                document.getElementById('imagen_url').value = '';
            }
        });
    }

    // Validación del formulario
    document.getElementById('formProducto').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        // Convertir checkbox a 1 o 0
        if (!formData.has('activo')) {
            formData.append('activo', '0');
        }
        
        fetch(this.action, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return response.text().then(text => {
                console.log('Response text:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.error('Response was:', text);
                    throw new Error('La respuesta no es JSON válido');
                }
            });
        })
        .then(data => {
            console.log('Parsed data:', data);
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: 'Producto actualizado correctamente',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.href = '<?php echo RUTA_URL; ?>productos/ver/<?php echo $id; ?>';
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'No se pudo actualizar el producto'
                });
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error de conexión: ' + error.message
            });
        });
    });
</script>
</body>
</html>
