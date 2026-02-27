<?php include __DIR__ . '/../../includes/header_materialize.php'; ?>

<?php
// Incluir el modelo de categorías
require_once __DIR__ . '/../../../modelo/categoria.php';

// Obtener ID de la categoría
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($parametros[0]) ? (int)$parametros[0] : 0);

if ($id <= 0) {
    echo "<script>window.location.href = '" . RUTA_URL . "categorias/listar';</script>";
    exit;
}

// Obtener datos de la categoría
$categoria = CategoriaModel::obtenerPorId($id);
if (!$categoria) {
    echo "<script>window.location.href = '" . RUTA_URL . "categorias/listar';</script>";
    exit;
}

// Obtener categorías disponibles para el select (excluyendo la actual y sus descendientes si la lógica lo permitiera, aquí solo excluímos la misma)
$categoriasDisponibles = CategoriaModel::listar();
?>

<style>
.form-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1.5rem;
    border-radius: 12px 12px 0 0;
}
.form-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.05);
}
</style>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <form id="formCategoria" method="POST" action="<?php echo RUTA_URL; ?>categorias/actualizar/<?php echo $id; ?>" class="card form-card">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <?php 
                    require_once __DIR__ . '/../../../utils/csrf.php';
                    echo csrf_field(); 
                ?>
                
                <div class="form-header d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2"></i> Editar Categoría</h3>
                        <p class="mb-0 opacity-75 small">Modificar detalles de: <?php echo htmlspecialchars($categoria['nombre']); ?></p>
                    </div>
                </div>

                <div class="card-body p-4">
                    <!-- Información Básica -->
                    <h5 class="mb-3 text-muted border-bottom pb-2"><i class="bi bi-info-circle me-2"></i> Información General</h5>
                    
                    <div class="mb-4">
                        <label for="nombre" class="form-label fw-bold">Nombre de Categoría <span class="text-danger">*</span></label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light"><i class="bi bi-tag"></i></span>
                            <input type="text" class="form-control" id="nombre" name="nombre" required 
                                   maxlength="100" value="<?php echo htmlspecialchars($categoria['nombre']); ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="descripcion" class="form-label fw-bold">Descripción</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-card-text"></i></span>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?php echo htmlspecialchars($categoria['descripcion'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Configuración Avanzada -->
                    <h5 class="mb-3 mt-4 text-muted border-bottom pb-2"><i class="bi bi-gear me-2"></i> Configuración</h5>
                    
                    <div class="row g-4">
                        <div class="col-md-7">
                            <label for="padre_id" class="form-label fw-bold">Categoría Padre (Opcional)</label>
                            <select class="form-select select2" id="padre_id" name="padre_id" style="width: 100%;">
                                <option value="">-- Es una Categoría Principal --</option>
                                <?php foreach ($categoriasDisponibles as $cat): ?>
                                    <?php if (empty($cat['padre_id']) && $cat['id'] != $id): // Excluir a sí misma ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo ($categoria['padre_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['nombre']); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text"><i class="bi bi-info-circle"></i> No puedes seleccionar la misma categoría como padre.</div>
                        </div>

                        <div class="col-md-5">
                            <label class="form-label fw-bold">Estado</label>
                            <div class="form-check form-switch p-3 border rounded bg-light">
                                <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1"
                                       <?php echo (isset($categoria['activo']) && $categoria['activo']) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-bold ms-2" for="activo">
                                    Categoría Activa
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-light p-3 d-flex justify-content-end gap-2 border-top-0">
                    <a href="<?php echo RUTA_URL; ?>categorias/listar" class="btn btn-outline-secondary px-4">Cancelar</a>
                    <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm" style="background: #a18cd1; border-color: #a18cd1;">
                        <i class="bi bi-check-lg me-1"></i> Actualizar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer_materialize.php'; ?>

<script>
    $(document).ready(function() {
        // Inicializar Select2
        $('.select2').select2({
            theme: "bootstrap-5",
            width: '100%',
            placeholder: "Selecciona una categoría padre...",
            allowClear: true
        });

        // Validación del formulario
        $('#formCategoria').on('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            if (!formData.has('activo')) formData.append('activo', '0');
            
            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.html();
            submitBtn.prop('disabled', true).html('<i class="spinner-border spinner-border-sm me-2"></i> Guardando...');

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
                        title: '¡Actualizado!',
                        text: data.message,
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#a18cd1'
                    }).then(() => {
                        window.location.href = '<?php echo RUTA_URL; ?>categorias/listar';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'No se pudo actualizar la categoría',
                        confirmButtonColor: '#d33'
                    });
                    submitBtn.prop('disabled', false).html(originalText);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexión',
                    text: error.message,
                    confirmButtonColor: '#d33'
                });
                submitBtn.prop('disabled', false).html(originalText);
            });
        });
    });
</script>
</body>
</html>
