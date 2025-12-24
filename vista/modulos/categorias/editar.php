<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../utils/session.php';
require_once __DIR__ . '/../../../modelo/categoria.php';

start_secure_session();
require_login();

// Obtener ID de la categoría
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($parametros[0]) ? (int)$parametros[0] : 0);

if ($id <= 0) {
    header('Location: ' . RUTA_URL . 'categorias/listar');
    exit;
}

// Obtener datos de la categoría
$categoria = CategoriaModel::obtenerPorId($id);
if (!$categoria) {
    header('Location: ' . RUTA_URL . 'categorias/listar');
    exit;
}

// Obtener categorías disponibles para el select (excluyendo la actual y sus descendientes)
$categoriasDisponibles = CategoriaModel::listar();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Categoría - Paquetería CruzValle</title>
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
                    <h2><i class="bi bi-pencil"></i> Editar Categoría</h2>
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($categoria['nombre']); ?></p>
                </div>
                <div>
                    <a href="<?php echo RUTA_URL; ?>categorias/ver/<?php echo $id; ?>" class="btn btn-outline-info me-2">
                        <i class="bi bi-eye"></i> Ver
                    </a>
                    <a href="<?php echo RUTA_URL; ?>categorias/listar" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Volver
                    </a>
                </div>
            </div>

            <!-- Formulario -->
            <form id="formCategoria" method="POST" action="<?php echo RUTA_URL; ?>categorias/actualizar/<?php echo $id; ?>">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <?php 
                    require_once __DIR__ . '/../../../utils/csrf.php';
                    echo csrf_field(); 
                ?>
                
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-info-circle"></i> Información de la Categoría</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nombre" name="nombre" required 
                                       maxlength="100" value="<?php echo htmlspecialchars($categoria['nombre']); ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="descripcion" class="form-label">Descripción</label>
                                <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?php echo htmlspecialchars($categoria['descripcion'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="padre_id" class="form-label">Categoría Padre</label>
                                <select class="form-select" id="padre_id" name="padre_id">
                                    <option value="">Ninguna (Categoría Principal)</option>
                                    <?php foreach ($categoriasDisponibles as $cat): ?>
                                        <?php if (empty($cat['padre_id']) && $cat['id'] != $id): // Excluir a sí misma ?>
                                            <option value="<?php echo $cat['id']; ?>"
                                                    <?php echo ($categoria['padre_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['nombre']); ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">
                                    <i class="bi bi-info-circle"></i>
                                    No puedes seleccionar esta categoría como su propia padre
                                </small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Estado</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1"
                                           <?php echo (isset($categoria['activo']) && $categoria['activo']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="activo">
                                        Categoría activa
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botones -->
                <div class="card mt-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <a href="<?php echo RUTA_URL; ?>categorias/listar" class="btn btn-secondary">
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
    document.getElementById('formCategoria').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
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
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: data.message,
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.href = '<?php echo RUTA_URL; ?>categorias/ver/<?php echo $id; ?>';
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'No se pudo actualizar la categoría'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
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
