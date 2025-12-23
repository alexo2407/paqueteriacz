<?php
/**
 * PHP Components
 * Componentes PHP reutilizables para vistas
 */

/**
 * Renderizar KPI Card
 */
function render_kpi_card($titulo, $valor, $icono, $color = 'primary', $trend = null) {
    $trendHtml = '';
    if ($trend) {
        $trendClass = $trend['direccion'] === 'up' ? 'text-success' : 'text-danger';
        $trendIcon = $trend['direccion'] === 'up' ? 'arrow-up' : 'arrow-down';
        $trendHtml = "<small class='trend {$trendClass}'><i class='bi bi-{$trendIcon}'></i> {$trend['porcentaje']}% vs {$trend['periodo']}</small>";
    }
    
    return "
    <div class='col-md-3 mb-3'>
        <div class='card kpi-card border-{$color}'>
            <div class='card-body'>
                <div class='d-flex justify-content-between align-items-start'>
                    <div>
                        <h6 class='text-muted mb-2'>{$titulo}</h6>
                        <h2 class='mb-1'>{$valor}</h2>
                        {$trendHtml}
                    </div>
                    <div class='icon-large text-{$color}'>
                        <i class='bi bi-{$icono}'></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    ";
}

/**
 * Renderizar Tab Nav
 */
function render_tab_nav($tabs, $activeTab = null) {
    $html = "<ul class='nav nav-tabs-modern' role='tablist'>";
    
    foreach ($tabs as $key => $tab) {
        $isActive = ($activeTab === null && $key === array_key_first($tabs)) || $activeTab === $key;
        $activeClass = $isActive ? 'active' : '';
        $badge = isset($tab['count']) ? "<span class='badge bg-{$tab['badge-color'] ?? 'secondary'} ms-2'>{$tab['count']}</span>" : '';
        
        $html .= "
        <li class='nav-item' role='presentation'>
            <button class='nav-link {$activeClass}' 
                    id='{$key}-tab' 
                    data-bs-toggle='tab' 
                    data-bs-target='#{$key}' 
                    type='button' 
                    role='tab'>
                {$tab['icon'] ? "<i class='bi bi-{$tab['icon']}'></i> " : ''}
                {$tab['label']}
                {$badge}
            </button>
        </li>";
    }
    
    $html .= "</ul>";
    return $html;
}

/**
 * Renderizar Empty State
 */
function render_empty_state($mensaje = 'No hay datos para mostrar', $icono = 'inbox', $accion = null) {
    $accionHtml = '';
    if ($accion) {
        $accionHtml = "<a href='{$accion['url']}' class='btn btn-primary mt-3'><i class='bi bi-{$accion['icon']}'></i> {$accion['label']}</a>";
    }
    
    return "
    <div class='empty-state'>
        <i class='bi bi-{$icono}'></i>
        <h5>{$mensaje}</h5>
        {$accionHtml}
    </div>
    ";
}

/**
 * Renderizar Timeline Item
 */
function render_timeline_item($titulo, $fecha, $usuario = null, $estado = 'default') {
    $usuarioHtml = $usuario ? " - {$usuario}" : '';
    $estadoClass = $estado === 'completed' ? 'completed' : ($estado === 'active' ? 'active' : '');
    
    return "
    <div class='timeline-item {$estadoClass}'>
        <div class='timeline-marker'></div>
        <div class='timeline-content'>
            <h6>{$titulo}</h6>
            <small>{$fecha}{$usuarioHtml}</small>
        </div>
    </div>
    ";
}

/**
 * Renderizar Action Bar
 */
function render_action_bar($filtros = [], $busqueda = true, $sticky = false) {
    $stickyClass = $sticky ? 'sticky' : '';
    $html = "<div class='action-bar {$stickyClass}'><div class='row g-2'>";
    
    foreach ($filtros as $filtro) {
        $html .= "<div class='col-md-{$filtro['col'] ?? 3}'>";
        $html .= "<label class='form-label small'>{$filtro['label']}</label>";
        
        if ($filtro['type'] === 'select') {
            $html .= "<select class='form-select' id='{$filtro['id']}'>";
            foreach ($filtro['options'] as $value => $label) {
                $html .= "<option value='{$value}'>{$label}</option>";
            }
            $html .= "</select>";
        } elseif ($filtro['type'] === 'date') {
            $html .= "<input type='text' class='form-control datepicker' id='{$filtro['id']}' placeholder='{$filtro['placeholder'] ?? ''}'>";
        }
        
        $html .= "</div>";
    }
    
    if ($busqueda) {
        $html .= "
        <div class='col-md-4'>
            <label class='form-label small'>Buscar</label>
            <input type='search' class='form-control' placeholder='Buscar...' data-search-table='dataTable'>
        </div>
        <div class='col-md-2'>
            <label class='form-label small'>&nbsp;</label>
            <button class='btn btn-primary w-100'><i class='bi bi-funnel'></i> Filtrar</button>
        </div>";
    }
    
    $html .= "</div></div>";
    return $html;
}

/**
 * Renderizar Wizard Steps
 */
function render_wizard_steps($pasos, $currentStep = 0) {
    $html = "<div class='wizard-steps'>";
    
    foreach ($pasos as $index => $paso) {
        $estado = '';
        if ($index < $currentStep) $estado = 'completed';
        elseif ($index === $currentStep) $estado = 'active';
        
        $html .= "
        <div class='wizard-step {$estado}'>
            <div class='wizard-step-number'>" . ($index + 1) . "</div>
            <div class='wizard-step-label'>{$paso}</div>
        </div>";
    }
    
    $html .= "</div>";
    return $html;
}

/**
 * Renderizar Table Headers con ordenamiento
 */
function render_table_headers($columnas) {
    $html = "<thead><tr>";
    
    foreach ($columnas as $col) {
        $sortable = isset($col['sortable']) && $col['sortable'] ? 'cursor-pointer' : '';
        $width = isset($col['width']) ? "style='width: {$col['width']}'" : '';
        
        $html .= "<th class='{$sortable}' {$width}>";
        $html .= $col['label'];
        if ($sortable) {
            $html .= " <i class='bi bi-arrow-down-up text-muted'></i>";
        }
        $html .= "</th>";
    }
    
    $html .= "</tr></thead>";
    return $html;
}

/**
 * Renderizar botones de acción inline
 */
function render_action_buttons($id, $acciones = ['ver', 'editar', 'eliminar']) {
    $html = "<div class='actions'>";
    
    foreach ($acciones as $accion) {
        $config = [
            'ver' => ['icon' => 'eye', 'class' => 'btn-info', 'title' => 'Ver'],
            'editar' => ['icon' => 'pencil', 'class' => 'btn-primary', 'title' => 'Editar'],
            'eliminar' => ['icon' => 'trash', 'class' => 'btn-danger', 'title' => 'Eliminar']
        ];
        
        if (isset($config[$accion])) {
            $c = $config[$accion];
            $html .= "<button class='btn btn-sm {$c['class']}' title='{$c['title']}' data-action='{$accion}' data-id='{$id}'><i class='bi bi-{$c['icon']}'></i></button> ";
        }
    }
    
    $html .= "</div>";
    return $html;
}

/**
 * Incluir assets necesarios
 */
function include_ui_assets() {
    echo '
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Custom Components CSS -->
    <link rel="stylesheet" href="' . RUTA_URL . 'assets/css/custom-components.css">
    
    <!-- Flatpickr (Date Picker) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
    
    <!--SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Custom Components JS -->
    <script src="' . RUTA_URL . 'assets/js/ui-components.js"></script>
    ';
}
