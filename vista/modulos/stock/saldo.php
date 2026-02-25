<?php
// Vista: Saldo por Producto
// Variables disponibles: $saldos
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0"><i class="bi bi-grid-3x3-gap text-success me-2"></i>Saldo por Producto</h4>
            <small class="text-muted">Disponible, reservado y neto libre por producto activo</small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= RUTA_URL ?>stock/movimientos" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-box-arrow-right me-1"></i>Movimientos
            </a>
            <a href="<?= RUTA_URL ?>stock/saldo?export=1" class="btn btn-success btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i>Exportar Excel
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
                <span class="fw-semibold"><?= count($saldos) ?> productos activos</span>
                <small class="text-muted">Actualizado: <?= date('d/m/Y H:i') ?></small>
            </div>
            <?php if (empty($saldos)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                No hay productos activos con inventario.
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4">Producto</th>
                            <th>SKU</th>
                            <th class="text-center">Disponible</th>
                            <th class="text-center">Reservado</th>
                            <th class="text-center fw-bold">Neto Libre</th>
                            <th class="text-end">Costo Prom.</th>
                            <th>Ubicación</th>
                            <th>Últ. Entrada</th>
                            <th>Últ. Salida</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($saldos as $s): ?>
                        <?php
                            $neto = (int)$s['neto_libre'];
                            $alertClass = '';
                            if ($neto <= 0) $alertClass = 'table-danger';
                            elseif ($neto <= 5) $alertClass = 'table-warning';
                        ?>
                        <tr class="<?= $alertClass ?>">
                            <td class="px-4 fw-semibold"><?= htmlspecialchars($s['producto']) ?></td>
                            <td class="text-muted"><?= htmlspecialchars($s['sku'] ?? '—') ?></td>
                            <td class="text-center"><?= (int)$s['disponible'] ?></td>
                            <td class="text-center text-warning fw-semibold">
                                <?php if ((int)$s['reservado'] > 0): ?>
                                <span class="badge bg-warning text-dark"><?= (int)$s['reservado'] ?></span>
                                <?php else: ?>
                                0
                                <?php endif; ?>
                            </td>
                            <td class="text-center fw-bold <?= $neto <= 0 ? 'text-danger' : ($neto <= 5 ? 'text-warning' : 'text-success') ?>">
                                <?= $neto ?>
                            </td>
                            <td class="text-end">
                                <?= $s['costo_promedio'] ? '$' . number_format((float)$s['costo_promedio'], 2) : '—' ?>
                            </td>
                            <td class="text-muted"><?= htmlspecialchars($s['ubicacion'] ?? 'Principal') ?></td>
                            <td class="text-muted text-nowrap">
                                <?= $s['ultima_entrada'] ? date('d/m/Y', strtotime($s['ultima_entrada'])) : '—' ?>
                            </td>
                            <td class="text-muted text-nowrap">
                                <?= $s['ultima_salida'] ? date('d/m/Y', strtotime($s['ultima_salida'])) : '—' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- Leyenda -->
            <div class="px-4 py-2 border-top bg-light d-flex gap-3 small text-muted">
                <span><span class="badge bg-danger me-1">&nbsp;</span>Sin stock libre</span>
                <span><span class="badge bg-warning text-dark me-1">&nbsp;</span>Stock bajo (≤5)</span>
                <span><span class="badge bg-warning text-dark me-1">N</span>Reservado para pedido en bodega</span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
