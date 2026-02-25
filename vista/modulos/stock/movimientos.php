<?php
// Vista: Movimientos de Stock
// Variables disponibles: $movimientos, $clientes, $fechaDesde, $fechaHasta, $tipoFiltro, $clienteId
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-0"><i class="bi bi-box-arrow-right text-primary me-2"></i>Movimientos de Stock</h4>
            <small class="text-muted">Historial de entradas, salidas y devoluciones</small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= RUTA_URL ?>stock/saldo" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-grid-3x3-gap me-1"></i>Saldo por Producto
            </a>
            <a href="<?= RUTA_URL ?>stock/movimientos?<?= http_build_query(array_merge($_GET, ['export'=>'1'])) ?>" class="btn btn-success btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i>Exportar Excel
            </a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="<?= RUTA_URL ?>stock/movimientos" class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label fw-semibold small">Fecha desde</label>
                    <input type="date" name="fecha_desde" class="form-control form-control-sm" value="<?= htmlspecialchars($fechaDesde) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold small">Fecha hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control form-control-sm" value="<?= htmlspecialchars($fechaHasta) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold small">Tipo</label>
                    <select name="tipo" class="form-select form-select-sm">
                        <option value="">Todos los tipos</option>
                        <?php foreach(['entrada','salida','ajuste','devolucion','transferencia'] as $t): ?>
                        <option value="<?= $t ?>" <?= $tipoFiltro === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (!empty($clientes)): ?>
                <div class="col-md-3">
                    <label class="form-label fw-semibold small">Cliente</label>
                    <select name="id_cliente" class="form-select form-select-sm">
                        <option value="0">Todos los clientes</option>
                        <?php foreach($clientes as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $clienteId == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-funnel me-1"></i>Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
                <span class="fw-semibold">
                    <?= count($movimientos) ?> movimiento<?= count($movimientos) !== 1 ? 's' : '' ?>
                    — Del <?= date('d/m/Y', strtotime($fechaDesde)) ?> al <?= date('d/m/Y', strtotime($fechaHasta)) ?>
                </span>
            </div>
            <?php if (empty($movimientos)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                No hay movimientos para los filtros seleccionados.
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4">Fecha</th>
                            <th>Producto</th>
                            <th class="text-center">Cantidad</th>
                            <th>Tipo</th>
                            <th>Motivo</th>
                            <th>Referencia</th>
                            <th>Cliente</th>
                            <th>Usuario</th>
                            <th>Origen → Destino</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($movimientos as $m): ?>
                        <?php
                            $isPositivo = $m['cantidad'] > 0;
                            $badgeClass = match($m['tipo_movimiento']) {
                                'entrada'      => 'success',
                                'salida'       => 'danger',
                                'devolucion'   => 'warning',
                                'ajuste'       => 'info',
                                'transferencia'=> 'secondary',
                                default        => 'light',
                            };
                        ?>
                        <tr>
                            <td class="px-4 text-nowrap"><?= date('d/m/Y H:i', strtotime($m['fecha'])) ?></td>
                            <td><?= htmlspecialchars($m['producto'] ?? '—') ?></td>
                            <td class="text-center fw-bold <?= $isPositivo ? 'text-success' : 'text-danger' ?>">
                                <?= $isPositivo ? '+' : '' ?><?= $m['cantidad'] ?>
                            </td>
                            <td><span class="badge bg-<?= $badgeClass ?>"><?= ucfirst($m['tipo_movimiento']) ?></span></td>
                            <td><?= htmlspecialchars($m['motivo'] ?? '—') ?></td>
                            <td class="text-nowrap">
                                <?php if ($m['referencia_tipo'] === 'pedido' && $m['orden_referencia']): ?>
                                    <a href="<?= RUTA_URL ?>pedidos/ver/<?= $m['referencia_id'] ?>" class="text-decoration-none">
                                        #<?= $m['orden_referencia'] ?>
                                    </a>
                                <?php else: ?>
                                    <?= htmlspecialchars($m['referencia_tipo'] ?? '—') ?>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($m['cliente'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($m['usuario'] ?? '—') ?></td>
                            <td class="text-muted small">
                                <?= htmlspecialchars($m['ubicacion_origen'] ?? '—') ?> → <?= htmlspecialchars($m['ubicacion_destino'] ?? '—') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
