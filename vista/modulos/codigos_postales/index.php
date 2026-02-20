<?php include("vista/includes/header.php"); ?>

<?php
$ctrl = new CodigosPostalesController();
$paisCtrl = new PaisesController();

$paises = $paisCtrl->listar();

// Filtros
$filtros = [
    'id_pais' => $_GET['id_pais'] ?? '',
    'codigo_postal' => $_GET['codigo_postal'] ?? '',
    'activo' => $_GET['activo'] ?? '',
    'parcial' => $_GET['parcial'] ?? ''
];

$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$limite = 20;

$resultado = $ctrl->listar($filtros, $pagina, $limite);
$items = $resultado['items'];
$total = $resultado['total'];
$paginas = $resultado['paginas'];

// Roles
$rolesNombres = $_SESSION['roles_nombres'] ?? [];
$puedeEditar = in_array('Administrador', $rolesNombres, true) || in_array('Vendedor', $rolesNombres, true);
?>

<style>
.cp-header {
    background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
    color: white;
    padding: 2rem;
    border-radius: 16px;
    margin-bottom: 2rem;
    box-shadow: 0 4px 20px rgba(24, 40, 72, 0.2);
}
.filter-card {
    border: none;
    border-radius: 12px;
    background: #f8f9fa;
    margin-bottom: 2rem;
}
.table-card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
}
.badge-parcial {
    background-color: #fff3cd;
    color: #856404;
    border: 1px solid #ffeeba;
}
</style>

<div class="container-fluid py-4">
    <div class="cp-header d-flex justify-content-between align-items-center">
        <div>
            <h2 class="mb-1 fw-bold"><i class="bi bi-geo-fill me-2"></i> Homologación de CPs</h2>
            <p class="mb-0 opacity-75">Administración de la fuente de verdad para direcciones</p>
        </div>
        <?php if ($puedeEditar): ?>
        <div>
            <a href="<?= RUTA_URL ?>codigos_postales/crear" class="btn btn-light text-primary fw-bold shadow-sm">
                <i class="bi bi-plus-circle me-1"></i> Nuevo CP
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Filtros -->
    <div class="card filter-card">
        <div class="card-body">
            <form method="GET" action="<?= RUTA_URL ?>codigos_postales" class="row g-3 align-items-end">
                <input type="hidden" name="enlace" value="codigos_postales">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">País</label>
                    <select name="id_pais" class="form-select">
                        <option value="">Todos los países</option>
                        <?php foreach ($paises as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $filtros['id_pais'] == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Código Postal</label>
                    <input type="text" name="codigo_postal" class="form-control" placeholder="Buscar CP..." value="<?= htmlspecialchars($filtros['codigo_postal']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Estado</label>
                    <select name="activo" class="form-select">
                        <option value="">Ver todos</option>
                        <option value="1" <?= $filtros['activo'] == '1' ? 'selected' : '' ?>>Activos</option>
                        <option value="0" <?= $filtros['activo'] == '0' ? 'selected' : '' ?>>Inactivos</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">Completitud</label>
                    <select name="parcial" class="form-select">
                        <option value="">Cualquiera</option>
                        <option value="1" <?= $filtros['parcial'] == '1' ? 'selected' : '' ?>>Solo Parciales</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter"></i> Filtrar</button>
                    <a href="<?= RUTA_URL ?>codigos_postales" class="btn btn-outline-secondary w-100" title="Limpiar"><i class="bi bi-x-lg"></i></a>
                </div>
            </form>
        </div>
    </div>

    <div class="card table-card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>País</th>
                            <th>CP</th>
                            <th>Ubicación (Depto / Muni / Barrio)</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Actualizado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No se encontraron registros</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($items as $item): 
                            $esParcial = AddressService::isPartial($item);
                        ?>
                            <tr>
                                <td class="text-muted small">#<?= $item['id'] ?></td>
                                <td><?= htmlspecialchars($item['nombre_pais']) ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border font-monospace fs-6"><?= htmlspecialchars($item['codigo_postal']) ?></span>
                                    <?php if ($esParcial): ?>
                                        <span class="badge badge-parcial" title="Faltan datos de ubicación">Parcial</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small">
                                        <span class="<?= !$item['id_departamento'] ? 'text-danger fw-bold' : '' ?>"><?= htmlspecialchars($item['nombre_departamento'] ?? '[Falta Depto]') ?></span> / 
                                        <span class="<?= !$item['id_municipio'] ? 'text-danger fw-bold' : '' ?>"><?= htmlspecialchars($item['nombre_municipio'] ?? ($item['nombre_localidad'] ?: '[Falta Muni]')) ?></span> / 
                                        <span class="text-muted"><?= htmlspecialchars($item['nombre_barrio'] ?? '-') ?></span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="form-check form-switch d-inline-block">
                                        <input class="form-check-input btn-toggle" type="checkbox" role="switch" 
                                               data-id="<?= $item['id'] ?>" 
                                               <?= $item['activo'] ? 'checked' : '' ?>
                                               <?= !$puedeEditar ? 'disabled' : '' ?>>
                                    </div>
                                </td>
                                <td class="text-center small text-muted">
                                    <?= date('d/m/Y H:i', strtotime($item['updated_at'] ?? $item['created_at'])) ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <?php if ($puedeEditar): ?>
                                            <a href="<?= RUTA_URL ?>codigos_postales/editar/<?= $item['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación Simple -->
            <?php if ($paginas > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $paginas; $i++): 
                                $params = array_merge($filtros, ['pagina' => $i, 'enlace' => 'codigos_postales']);
                                $query = http_build_query($params);
                        ?>
                            <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= $query ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include("vista/includes/footer.php"); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle Active Status
    document.querySelectorAll('.btn-toggle').forEach(btn => {
        btn.addEventListener('change', function() {
            const id = this.getAttribute('data-id');
            const status = this.checked;
            
            fetch(`<?= RUTA_URL ?>codigos_postales/toggle/${id}`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(r => r.json())
            .then(res => {
                if (!res.success) {
                    this.checked = !status;
                    Swal.fire('Error', res.message, 'error');
                }
            })
            .catch(err => {
                this.checked = !status;
                Swal.fire('Error', 'No se pudo comunicar con el servidor', 'error');
            });
        });
    });
});
</script>
