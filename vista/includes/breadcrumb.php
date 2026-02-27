<?php
/**
 * breadcrumb.php — Expert Arrow Breadcrumb
 * Autogestión de estilos para garantizar visualización premium independiente.
 */

$_bcMap = [
    'dashboard'            => 'Dashboard',
    'usuarios'             => 'Usuarios',
    'pedidos'              => 'Pedidos',
    'productos'            => 'Productos',
    'categorias'           => 'Categorías',
    'stock'                => 'Stock',
    'seguimiento'          => 'Seguimiento',
    'paises'               => 'Países',
    'departamentos'        => 'Departamentos',
    'municipios'           => 'Municipios',
    'barrios'              => 'Barrios',
    'codigos_postales'     => 'Códigos Postales',
    'monedas'              => 'Monedas',
    'crm'                  => 'CRM Relay',
    'logistica'            => 'Logística',
    'auditoria'            => 'Auditoría',
    'clientes'             => 'Clientes',
    'api'                  => 'API',
    'listar'               => 'Listado',
    'crear'                => 'Nuevo',
    'editar'               => 'Editar',
    'ver'                  => 'Detalle',
    'perfil'               => 'Mi Perfil',
    'historial'            => 'Historial',
    'crearPedido'          => 'Nuevo Pedido',
    'kardex'               => 'Kardex',
    'movimientos'          => 'Movimientos',
    'saldo'                => 'Saldo Producto',
    'inventario_periodo'   => 'Inventario Período',
    'notificaciones'       => 'Notificaciones',
    'integraciones'        => 'Integraciones',
    'monitor'              => 'Monitor Worker',
    'reportes'             => 'Reportes',
    'database_doc'         => 'Doc. Base de Datos',
    'logistics_worker_doc' => 'Doc. Worker',
    'doc'                  => 'API Docs',
    'crmdoc'               => 'Doc. CRM',
];

$_bcEnlace = isset($_GET['enlace']) ? trim($_GET['enlace'], '/') : '';
$_bcRaw    = array_values(array_filter(explode('/', $_bcEnlace), fn($v) => $v !== ''));

if (empty($_bcRaw) || in_array($_bcEnlace, ['login', 'salir'])) return;

function _bcName(string $seg, array $map): string {
    $seg = preg_replace('/\.php$/i', '', $seg);
    return $map[$seg] ?? ucwords(str_replace(['_','-'], ' ', $seg));
}
?>

<style>
    /* Reset y Estilos Base del Breadcrumb */
    .expert-bc-area {
        padding: 12px 20px;
        background-color: #f4f7f9;
        border-bottom: 1px solid #e1e8ed;
        margin-bottom: 10px;
    }

    .expert-bc-arrow {
        display: flex;
        align-items: center;
        list-style: none !important;
        margin: 0;
        padding: 0;
        height: 36px;
        line-height: 36px;
        background-color: #e6e9ed;
        border-radius: 4px;
        overflow: hidden;
    }

    .expert-bc-arrow li {
        margin: 0 !important;
        padding: 0 !important;
        display: flex;
        align-items: center;
    }

    /* Quitar separadores de Bootstrap */
    .expert-bc-arrow li::before {
        content: none !important;
        display: none !important;
    }

    .expert-bc-arrow li a, 
    .expert-bc-arrow li span {
        display: inline-block;
        height: 36px;
        line-height: 36px;
        padding: 0 10px 0 25px;
        position: relative;
        font-family: 'Segoe UI', Roboto, sans-serif;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.2s ease;
    }

    /* Primer elemento */
    .expert-bc-arrow li:first-child a {
        padding-left: 15px;
        border-radius: 4px 0 0 4px;
    }

    /* Enlaces Azules (Color de la paleta: Azul oscuro del shell) */
    .expert-bc-arrow li a {
        background-color: #0f3460;
        color: #ffffff !important;
        text-decoration: none !important;
        border: 1px solid #0f3460;
        z-index: 1;
    }

    .expert-bc-arrow li a:hover {
        background-color: #1a1a2e;
        border-color: #1a1a2e;
    }

    /* Efecto Flecha con Pseudoelementos */
    .expert-bc-arrow li a:after, 
    .expert-bc-arrow li a:before {
        position: absolute;
        top: -1px;
        width: 0;
        height: 0;
        content: '';
        border-top: 18px solid transparent;
        border-bottom: 18px solid transparent;
        pointer-events: none;
    }

    .expert-bc-arrow li a:before {
        right: -10px;
        z-index: 3;
        border-left: 11px solid #0f3460;
    }

    .expert-bc-arrow li a:after {
        right: -11px;
        z-index: 2;
        border-left: 11px solid #16213e;
    }

    .expert-bc-arrow li a:hover:before {
        border-left-color: #1a1a2e;
    }

    /* Página Activa (Gris) */
    .expert-bc-arrow li.active span {
        background-color: #e6e9ed;
        color: #434a54;
        border: 1px solid transparent;
        z-index: 0;
    }

    .expert-bc-arrow li:not(:first-child) {
        margin-left: -5px !important;
    }

    /* Ajuste para que se vea como la referencia */
    @media (max-width: 768px) {
        .expert-bc-arrow li a, .expert-bc-arrow li span {
            padding: 0 8px 0 20px;
            font-size: 11px;
        }
    }
</style>

<div class="expert-bc-area">
    <ol class="expert-bc-arrow">
        <li><a href="<?= RUTA_URL ?>dashboard">Home</a></li><?php
        $acc = '';
        foreach ($_bcRaw as $i => $seg):
            $acc  .= ($acc ? '/' : '') . $seg;
            $name  = _bcName($seg, $_bcMap);
            $isLast = ($i === array_key_last($_bcRaw));
            if ($isLast): 
            ?><li class="active"><span><?= htmlspecialchars($name) ?></span></li><?php
            else: 
            ?><li><a href="<?= RUTA_URL . $acc ?>"><?= htmlspecialchars($name) ?></a></li><?php
            endif;
        endforeach;
        ?>
    </ol>
</div>
