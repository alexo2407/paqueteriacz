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
    <title>Editar Producto - App RutaEx-Latam</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>

<?php include __DIR__ . '/../../includes/header.php'; ?>

<style>
.editar-producto-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    overflow: hidden;
}
.editar-producto-header {
    background: linear-gradient(135deg, #FF416C 0%, #FF4B2B 100%);
    color: white;
    padding: 1.5rem 2rem;
}
.editar-producto-header h3 {
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
.btn-action-outline {
    background: white;
    color: #f5576c;
    border: 1px solid rgba(245, 87, 108, 0.3);
    padding: 0.5rem 1rem;
    border-radius: 8px;
    transition: all 0.3s;
    text-decoration: none;
    font-weight: 500;
}
.btn-action-outline:hover {
    background: #fff0f3;
    color: #d93d52;
    border-color: #d93d52;
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
            <form id="formProducto" method="POST" action="<?php echo RUTA_URL; ?>productos/actualizar/<?php echo $id; ?>" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <input type="hidden" name="imagen_actual" value="<?php echo htmlspecialchars($producto['imagen_url'] ?? ''); ?>">
                <?php 
                    require_once __DIR__ . '/../../../utils/csrf.php';
                    echo csrf_field(); 
                ?>
                
                <div class="card editar-producto-card">
                    <div class="editar-producto-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-white bg-opacity-25 rounded-circle p-3">
                                    <i class="bi bi-pencil-square fs-3"></i>
                                </div>
                                <div>
                                    <h3>Editar Producto</h3>
                                    <p class="mb-0 opacity-75">Editando: <?php echo htmlspecialchars($producto['nombre']); ?></p>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="<?php echo RUTA_URL;?>productos/ver/<?php echo $id; ?>" class="btn btn-back">
                                    <i class="bi bi-eye"></i> Ver Detalle
                                </a>
                                <a href="<?php echo RUTA_URL; ?>productos/listar" class="btn btn-back">
                                    <i class="bi bi-arrow-left"></i> Volver
                                </a>
                            </div>
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
                                    <label for="sku" class="form-label fw-bold">SKU</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="sku" name="sku" 
                                               value="<?php echo htmlspecialchars($producto['sku'] ?? ''); ?>" maxlength="50" placeholder="Ej: ELEC-042">
                                        <?php if (empty($producto['sku'])): ?>
                                            <button type="button" class="btn btn-outline-secondary" id="generarSKU">
                                                <i class="bi bi-magic"></i> Generar
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted ms-1">
                                        <?php if (empty($producto['sku'])): ?>
                                            <i class="bi bi-lightbulb"></i> Sugerencia: Asigna un SKU para mejor organización.
                                        <?php else: ?>
                                            <i class="bi bi-check-circle text-success"></i> Código único del producto
                                        <?php endif; ?>
                                    </small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="nombre" class="form-label fw-bold">Nombre del Producto <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nombre" name="nombre" required 
                                           value="<?php echo htmlspecialchars($producto['nombre']); ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="categoria_id" class="form-label fw-bold">Categoría</label>
                                    <select class="form-select select2-searchable" id="categoria_id" name="categoria_id" data-placeholder="Buscar categoría...">
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
                                    <label for="marca" class="form-label fw-bold">Marca</label>
                                    <input type="text" class="form-control" id="marca" name="marca" 
                                           value="<?php echo htmlspecialchars($producto['marca'] ?? ''); ?>" maxlength="100">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="descripcion" class="form-label fw-bold">Descripción</label>
                                <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?php echo htmlspecialchars($producto['descripcion'] ?? ''); ?></textarea>
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
                                        <input type="number" class="form-control" id="precio_usd" name="precio_usd" step="0.01" min="0" required 
                                               value="<?php echo $producto['precio_usd']; ?>">
                                    </div>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="unidad" class="form-label fw-bold">Unidad de Medida</label>
                                    <select class="form-select select2-searchable" id="unidad" name="unidad" data-placeholder="Seleccionar unidad...">
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
                                    <label for="peso" class="form-label fw-bold">Peso (kg)</label>
                                    <input type="number" class="form-control" id="peso" name="peso" step="0.01" min="0" 
                                           value="<?php echo $producto['peso'] ?? 0; ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="stock_minimo" class="form-label fw-bold">Stock Mínimo</label>
                                    <input type="number" class="form-control" id="stock_minimo" name="stock_minimo" min="0" 
                                           value="<?php echo $producto['stock_minimo'] ?? 10; ?>">
                                    <small class="text-muted">Para alertas de stock bajo</small>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="stock_maximo" class="form-label fw-bold">Stock Máximo</label>
                                    <input type="number" class="form-control" id="stock_maximo" name="stock_maximo" min="0" 
                                           value="<?php echo $producto['stock_maximo'] ?? 100; ?>">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label fw-bold">Estado</label>
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" 
                                               <?php echo (isset($producto['activo']) && $producto['activo']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="activo">
                                            Producto activo
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3 p-3 bg-white rounded border border-info bg-opacity-10">
                                <div class="d-flex align-items-center text-info">
                                    <i class="bi bi-box-seam me-2 fs-5"></i>
                                    <strong>Stock Actual:</strong> 
                                    <span class="ms-2 fs-5 text-dark fw-bold">
                                        <?php 
                                        $stockActual = $producto['stock_total'] ?? 0;
                                        $stockMinimo = $producto['stock_minimo'] ?? 10;
                                        echo $stockActual;
                                        ?>
                                    	<span class="fs-6 fw-normal text-muted">unidades</span>
                                    </span>
                                    <?php if ($stockActual < $stockMinimo): ?>
                                        <span class="badge bg-warning text-dark ms-2">Stock Bajo</span>
                                    <?php endif; ?>
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
                                    <label for="imagen" class="form-label fw-bold">Subir Nueva Imagen</label>
                                    <input type="file" class="form-control" id="imagen" name="imagen" accept="image/jpeg,image/png,image/gif,image/webp">
                                    <small class="text-muted d-block mt-1">
                                        <i class="bi bi-info-circle"></i> Formatos: JPG, PNG, GIF, WEBP. Máximo 5MB
                                    </small>
                                    
                                    <div class="mt-3">
                                        <label class="form-label fw-bold small text-muted">O ingresa una URL externa:</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
                                            <input type="url" class="form-control" id="imagen_url" name="imagen_url" 
                                                value="<?php echo htmlspecialchars($producto['imagen_url'] ?? ''); ?>" 
                                                placeholder="https://ejemplo.com/imagen.jpg" maxlength="500">
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($producto['imagen_url'])): ?>
                                    <div class="form-check mt-3 pt-2 border-top">
                                        <input class="form-check-input" type="checkbox" id="eliminar_imagen" name="eliminar_imagen" value="1">
                                        <label class="form-check-label text-danger fw-bold" for="eliminar_imagen">
                                            <i class="bi bi-trash"></i> Eliminar imagen actual
                                        </label>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4 text-center">
                                    <label class="form-label fw-bold">Vista Previa Actual</label>
                                    <div id="imagen-preview" class="border rounded p-2 d-flex align-items-center justify-content-center mx-auto bg-white" style="height: 160px; width: 100%; max-width: 200px;">
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
                                                <i class="bi bi-image text-muted opacity-25" style="font-size: 3rem;"></i>
                                                <p class="text-muted small mb-0 opacity-75">Sin imagen</p>
                                            </div>
                                        <?php endif; ?>
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
                                <i class="bi bi-check-lg me-1"></i> Guardar Cambios
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
