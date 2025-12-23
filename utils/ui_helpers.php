<?php
/**
 * UI Helpers
 * 
 * Funciones auxiliares para generar componentes visuales consistentes
 * en toda la aplicación.
 */

/**
 * Generar badge de nivel de stock
 * 
 * @param int $stockActual Stock actual del producto
 * @param int $stockMinimo Stock mínimo configurado
 * @return string HTML del badge
 */
function badge_stock_nivel($stockActual, $stockMinimo = 10)
{
    if ($stockActual <= 0) {
        return '<span class="badge bg-danger">Agotado</span>';
    }
    if ($stockActual < $stockMinimo) {
        return '<span class="badge bg-warning text-dark">Stock Bajo</span>';
    }
    return '<span class="badge bg-success">Stock Normal</span>';
}

/**
 * Generar badge de prioridad de pedido
 * 
 * @param string $prioridad Nivel de prioridad (baja, normal, alta, urgente)
 * @return string HTML del badge
 */
function badge_prioridad($prioridad)
{
    $prioridad = strtolower($prioridad ?? 'normal');
    
    $configuracion = [
        'baja' => ['color' => 'secondary', 'label' => 'Baja'],
        'normal' => ['color' => 'info', 'label' => 'Normal'],
        'alta' => ['color' => 'warning', 'label' => 'Alta'],
        'urgente' => ['color' => 'danger', 'label' => 'Urgente']
    ];
    
    $config = $configuracion[$prioridad] ?? $configuracion['normal'];
    return "<span class='badge bg-{$config['color']}'>{$config['label']}</span>";
}

/**
 * Generar badge de tipo de movimiento de stock
 * 
 * @param string $tipoMovimiento Tipo de movimiento
 * @return string HTML del badge
 */
function badge_tipo_movimiento($tipoMovimiento)
{
    $tipo = strtolower($tipoMovimiento ?? '');
    
    $configuracion = [
        'entrada' => ['color' => 'success', 'icon' => 'arrow-down-circle', 'label' => 'Entrada'],
        'salida' => ['color' => 'danger', 'icon' => 'arrow-up-circle', 'label' => 'Salida'],
        'ajuste' => ['color' => 'warning', 'icon' => 'wrench', 'label' => 'Ajuste'],
        'devolucion' => ['color' => 'info', 'icon' => 'arrow-return-left', 'label' => 'Devolución'],
        'transferencia' => ['color' => 'primary', 'icon' => 'arrow-left-right', 'label' => 'Transferencia']
    ];
    
    $config = $configuracion[$tipo] ?? ['color' => 'secondary', 'icon' => 'question', 'label' => ucfirst($tipo)];
    return "<span class='badge bg-{$config['color']}'><i class='bi bi-{$config['icon']}'></i> {$config['label']}</span>";
}

/**
 * Formatear precio en USD
 * 
 * @param float $precio Precio a formatear
 * @param bool $incluirSimbolo Si incluir símbolo $
 * @return string Precio formateado
 */
function formatear_precio_usd($precio, $incluirSimbolo = true)
{
    $formateado = number_format((float)$precio, 2, '.', ',');
    return $incluirSimbolo ? '$' . $formateado : $formateado;
}

/**
 * Formatear fecha en formato legible
 * 
 * @param string $fecha Fecha en formato MySQL
 * @param bool $incluirHora Si incluir hora
 * @return string Fecha formateada
 */
function formatear_fecha($fecha, $incluirHora = false)
{
    if (empty($fecha)) return '-';
    
    $timestamp = strtotime($fecha);
    if ($timestamp === false) return $fecha;
    
    $formato = $incluirHora ? 'd/m/Y H:i' : 'd/m/Y';
    return date($formato, $timestamp);
}

/**
 * Generar ícono de estado activo/inactivo
 * 
 * @param bool $activo Estado
 * @return string HTML del ícono
 */
function icono_estado_activo($activo)
{
    if ($activo) {
        return '<i class="bi bi-check-circle-fill text-success" title="Activo"></i>';
    }
    return '<i class="bi bi-x-circle-fill text-danger" title="Inactivo"></i>';
}

/**
 * Generar opciones de select para categorías
 * 
 * @param array $categorias Lista de categorías
 * @param int|null $seleccionado ID de categoría seleccionada
 * @return string HTML de options
 */
function generar_opciones_categorias($categorias, $seleccionado = null)
{
    $html = '<option value="">Todas las categorías</option>';
    
    foreach ($categorias as $cat) {
        $selected = ($seleccionado && $cat['id'] == $seleccionado) ? 'selected' : '';
        $html .= "<option value='{$cat['id']}' {$selected}>{$cat['nombre']}</option>";
        
        // Si tiene subcategorías (en formato jerárquico)
        if (isset($cat['subcategorias']) && !empty($cat['subcategorias'])) {
            foreach ($cat['subcategorias'] as $subcat) {
                $selected = ($seleccionado && $subcat['id'] == $seleccionado) ? 'selected' : '';
                $html .= "<option value='{$subcat['id']}' {$selected}>&nbsp;&nbsp;↳ {$subcat['nombre']}</option>";
            }
        }
    }
    
    return $html;
}

/**
 * Generar card de métrica para dashboard
 * 
 * @param string $titulo Título de la métrica
 * @param string|int $valor Valor a mostrar
 * @param string $icono Ícono de Bootstrap Icons
 * @param string $color Color del card (primary, success, warning, danger, info)
 * @return string HTML del card
 */
function card_metrica($titulo, $valor, $icono, $color = 'primary')
{
    return "
    <div class='card border-{$color}'>
        <div class='card-body'>
            <div class='d-flex justify-content-between align-items-center'>
                <div>
                    <h6 class='text-muted mb-2'>{$titulo}</h6>
                    <h3 class='mb-0'>{$valor}</h3>
                </div>
                <div class='text-{$color}' style='font-size: 2.5rem;'>
                    <i class='bi bi-{$icono}'></i>
                </div>
            </div>
        </div>
    </div>
    ";
}

/**
 * Calcular porcentaje de stock
 * 
 * @param int $stockActual Stock actual
 * @param int $stockMaximo Stock máximo
 * @return int Porcentaje (0-100)
 */
function porcentaje_stock($stockActual, $stockMaximo)
{
    if ($stockMaximo <= 0) return 0;
    return min(100, round(($stockActual / $stockMaximo) * 100));
}

/**
 * Generar progress bar de stock
 * 
 * @param int $stockActual Stock actual
 * @param int $stockMinimo Stock mínimo
 * @param int $stockMaximo Stock máximo
 * @return string HTML de progress bar
 */
function progress_bar_stock($stockActual, $stockMinimo, $stockMaximo)
{
    $porcentaje = porcentaje_stock($stockActual, $stockMaximo);
    
    // Determinar color según nivel
    $color = 'success';
    if ($stockActual <= 0) {
        $color = 'danger';
    } elseif ($stockActual < $stockMinimo) {
        $color = 'warning';
    }
    
    return "
    <div class='progress' style='height: 20px;'>
        <div class='progress-bar bg-{$color}' role='progressbar' 
             style='width: {$porcentaje}%' 
             aria-valuenow='{$stockActual}' 
             aria-valuemin='0' 
             aria-valuemax='{$stockMaximo}'>
            {$stockActual}
        </div>
    </div>
    ";
}
