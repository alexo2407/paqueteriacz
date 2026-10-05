<?php
/**
 * Componente: Tarjeta de Datos de Recolección (Origen)
 * Se renderiza únicamente si el pedido tiene información de recolección.
 * 
 * Variables esperadas:
 * - $recoleccion: array con los datos de pedido_recoleccion resueltos
 */
if (empty($recoleccion) || !is_array($recoleccion)) {
    return;
}

$recPais = !empty($recoleccion['pais_nombre']) ? $recoleccion['pais_nombre'] : ($recoleccion['pais'] ?? '');
$recDepto = !empty($recoleccion['departamento_nombre']) ? $recoleccion['departamento_nombre'] : ($recoleccion['departamento'] ?? '');
$recMuni = !empty($recoleccion['municipio_nombre']) ? $recoleccion['municipio_nombre'] : ($recoleccion['municipio'] ?? '');
$recDireccion = $recoleccion['direccion'] ?? '';
$recContacto = $recoleccion['contacto'] ?? '';
$recTelefono = $recoleccion['telefono'] ?? '';
$recReferencia = $recoleccion['referencia'] ?? '';
?>

<div class="card shadow-sm border-0 mb-4 recoleccion-card" style="border-radius: 12px; border-left: 4px solid #0B4EA2 !important;">
    <div class="card-header bg-light py-3 d-flex align-items-center justify-content-between">
        <h6 class="m-0 fw-bold text-primary text-uppercase" style="letter-spacing: 0.05em;">
            <i class="bi bi-box-arrow-up me-2"></i>Datos de Recolección (Origen)
        </h6>
        <span class="badge bg-primary text-white rounded-pill px-3 py-1 small">Origen</span>
    </div>
    <div class="card-body p-3">
        <div class="row g-3">
            <div class="col-md-4 col-sm-6">
                <label class="small text-muted fw-bold text-uppercase d-block mb-1">País</label>
                <div class="text-dark fw-semibold"><?= htmlspecialchars($recPais !== '' ? $recPais : '—') ?></div>
            </div>
            <div class="col-md-4 col-sm-6">
                <label class="small text-muted fw-bold text-uppercase d-block mb-1">Departamento</label>
                <div class="text-dark fw-semibold"><?= htmlspecialchars($recDepto !== '' ? $recDepto : '—') ?></div>
            </div>
            <div class="col-md-4 col-sm-12">
                <label class="small text-muted fw-bold text-uppercase d-block mb-1">Municipio</label>
                <div class="text-dark fw-semibold"><?= htmlspecialchars($recMuni !== '' ? $recMuni : '—') ?></div>
            </div>

            <div class="col-12">
                <label class="small text-muted fw-bold text-uppercase d-block mb-1">Dirección de Recolección</label>
                <div class="p-2 bg-light rounded border text-dark"><?= nl2br(htmlspecialchars($recDireccion !== '' ? $recDireccion : '—')) ?></div>
            </div>

            <?php if (!empty($recContacto)): ?>
            <div class="col-md-6">
                <label class="small text-muted fw-bold text-uppercase d-block mb-1">Contacto / Negocio</label>
                <div class="text-dark"><i class="bi bi-person me-1 text-primary"></i><?= htmlspecialchars($recContacto) ?></div>
            </div>
            <?php endif; ?>

            <?php if (!empty($recTelefono)): ?>
            <div class="col-md-6">
                <label class="small text-muted fw-bold text-uppercase d-block mb-1">Teléfono</label>
                <div class="text-dark"><i class="bi bi-telephone me-1 text-primary"></i><?= htmlspecialchars($recTelefono) ?></div>
            </div>
            <?php endif; ?>

            <?php if (!empty($recReferencia)): ?>
            <div class="col-12">
                <label class="small text-muted fw-bold text-uppercase d-block mb-1">Referencia</label>
                <div class="text-muted small fst-italic"><i class="bi bi-info-circle me-1"></i><?= htmlspecialchars($recReferencia) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
