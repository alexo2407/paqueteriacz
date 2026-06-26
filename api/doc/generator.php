<?php
/**
 * generator.php — Wizard interactivo de documentación API
 * Genera manuales rápidos de la API en formato PDF
 * Acceso: Solo Admin
 */

// CWD to root to ensure relative paths resolve correctly
chdir(dirname(__DIR__, 2));

if (!defined('RUTA_URL')) {
    require_once 'config/config.php';
}
require_once 'modelo/conexion.php';
require_once 'modelo/api_doc.php';
require_once 'modelo/pedido.php';
require_once 'modelo/pais.php';

// ── Protección: solo sesión activa con rol admin ─────────────────────────────────────────
require_once 'utils/session.php';
start_secure_session();
require_once 'utils/permissions.php';

$rolesNombres = $_SESSION['roles_nombres'] ?? [];
$isAdmin = in_array(ROL_NOMBRE_ADMIN, $rolesNombres, true);

if (!$isAdmin) {
    if (in_array(ROL_NOMBRE_CLIENTE, $rolesNombres, true)) {
        set_flash('error', 'Acceso denegado para tu rol.');
        header('Location: ' . RUTA_URL . 'seguimiento/admin_tracking');
    } else {
        set_flash('error', 'Acceso denegado para tu rol.');
        header('Location: ' . RUTA_URL . 'dashboard');
    }
    exit;
}

$idUsuario = $_SESSION['user_id'] ?? $_SESSION['idUsuario'] ?? null;

// ── Acción AJAX: guardar documento ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'guardar_doc') {
        $config = json_decode($_POST['config'] ?? '{}', true);
        $html   = $_POST['html'] ?? '';

        if (!$config || !$html) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            exit;
        }

        $id = ApiDocModel::guardar([
            'titulo'          => $config['titulo'] ?? 'Documento API',
            'empresa_cliente' => $config['empresa'] ?? 'Sin nombre',
            'url_base'        => $config['urlBase'] ?? '',
            'secciones'       => $config['secciones'] ?? [],
            'config_json'     => $config,
            'html_generado'   => $html,
            'id_usuario'      => $idUsuario,
        ]);

        echo json_encode(['success' => true, 'id' => $id, 'message' => 'Documento guardado en historial']);
        exit;
    }
}

// Fetch lists for the select dropdowns
$clientes = [];
try {
    $clientes = PedidosModel::obtenerClientes();
} catch (Exception $e) {}

$proveedores = [];
try {
    $proveedores = PedidosModel::obtenerProveedores();
} catch (Exception $e) {}

$monedas = [];
try {
    $monedas = PedidosModel::obtenerMonedas();
} catch (Exception $e) {}

$paises = [];
try {
    $paises = PaisModel::listar();
} catch (Exception $e) {}

include "vista/includes/header.php";
?>

<div class="wizard-theme-container">
<style>
@import url('https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500&display=swap');
/* ===== RESET & BASE ===== */
.wizard-theme-container * {box-sizing:border-box}
.wizard-theme-container {
  --bg:#0f1117;--bg2:#161b27;--bg3:#1e2538;--bg4:#252d40;
  --accent:#6366f1;--accent2:#8b5cf6;--accentg:linear-gradient(135deg,#6366f1,#8b5cf6);
  --green:#10b981;--blue:#3b82f6;--orange:#f59e0b;--red:#ef4444;
  --text:#e2e8f0;--textm:#94a3b8;--texts:#64748b;
  --border:rgba(99,102,241,.25);--borderh:rgba(99,102,241,.5);
  --glass:rgba(30,37,56,.7);--glassb:rgba(99,102,241,.08);
  --radius:12px;--radius2:8px;
  --shadow:0 20px 60px rgba(0,0,0,.5);
  
  background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;-webkit-font-smoothing:antialiased;
  padding: 1.5rem 2rem;
  border-radius: var(--radius);
  margin-top: 1rem;
}

/* ===== LAYOUT ===== */
.wizard-theme-container .page-wrap{display:flex;flex-direction:column}
.wizard-theme-container .top-bar{background:var(--bg2);border-bottom:1px solid var(--border);padding:.875rem 1.5rem;display:flex;align-items:center;justify-content:space-between;border-radius:var(--radius) var(--radius) 0 0}
.wizard-theme-container .top-bar-brand{display:flex;align-items:center;gap:.75rem;font-weight:700;font-size:1rem;color:var(--text)}
.wizard-theme-container .top-bar-brand i{font-size:1.25rem;background:var(--accentg);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.wizard-theme-container .back-btn{display:flex;align-items:center;gap:.4rem;color:var(--textm);text-decoration:none;font-size:.85rem;transition:.2s;padding:.4rem .75rem;border-radius:var(--radius2);border:1px solid var(--border)}
.wizard-theme-container .back-btn:hover{color:var(--text);border-color:var(--accent);background:var(--glassb)}

/* ===== WIZARD CONTAINER ===== */
.wizard-theme-container .wizard-wrap{display:flex;flex-direction:column;align-items:center;padding:2rem 0;gap:2rem}
.wizard-theme-container .wizard-card{width:100%;max-width:760px;background:var(--glass);border:1px solid var(--border);border-radius:16px;backdrop-filter:blur(20px);padding:2.5rem;box-shadow:var(--shadow)}

/* ===== PROGRESS BAR ===== */
.wizard-theme-container .progress-bar-wrap{width:100%;max-width:760px}
.wizard-theme-container .steps-row{display:flex;align-items:center;gap:0;justify-content:space-between;position:relative}
.wizard-theme-container .steps-row::before{content:'';position:absolute;top:50%;left:0;right:0;height:2px;background:var(--border);transform:translateY(-50%);z-index:0}
.wizard-theme-container .step-dot{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;border:2px solid var(--border);background:var(--bg2);color:var(--textm);position:relative;z-index:1;transition:.3s;flex-shrink:0}
.wizard-theme-container .step-dot.active{border-color:var(--accent);background:var(--accent);color:#fff;box-shadow:0 0 20px rgba(99,102,241,.5)}
.wizard-theme-container .step-dot.done{border-color:var(--green);background:var(--green);color:#fff}
.wizard-theme-container .step-line{flex:1;height:2px;background:var(--border);transition:.4s;z-index:0}
.wizard-theme-container .step-line.done{background:var(--green)}
.wizard-theme-container .steps-labels{display:flex;justify-content:space-between;margin-top:.5rem;padding:0 2px}
.wizard-theme-container .step-label{font-size:.7rem;color:var(--texts);text-align:center;width:36px;transition:.3s}
.wizard-theme-container .step-label.active{color:var(--accent)}
.wizard-theme-container .step-label.done{color:var(--green)}

/* ===== STEP HEADINGS ===== */
.wizard-theme-container .step-title{font-size:1.4rem;font-weight:700;margin-bottom:.4rem;background:var(--accentg);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.wizard-theme-container .step-subtitle{color:var(--textm);font-size:.9rem;margin-bottom:1.75rem}

/* ===== FORM ELEMENTS ===== */
.wizard-theme-container .form-group{margin-bottom:1.25rem}
.wizard-theme-container label{display:block;font-size:.8rem;font-weight:600;color:var(--textm);margin-bottom:.4rem;text-transform:uppercase;letter-spacing:.05em}
.wizard-theme-container input[type=text], .wizard-theme-container input[type=url], .wizard-theme-container input[type=email], .wizard-theme-container input[type=password], .wizard-theme-container input[type=number], .wizard-theme-container textarea, .wizard-theme-container select{
  width:100%;background:var(--bg3);border:1.5px solid var(--border);border-radius:var(--radius2);
  color:var(--text);font-family:'Inter',sans-serif;font-size:.9rem;padding:.65rem .9rem;
  transition:.2s;outline:none}
.wizard-theme-container input:focus, .wizard-theme-container textarea:focus, .wizard-theme-container select:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(99,102,241,.15)}
.wizard-theme-container input::placeholder, .wizard-theme-container textarea::placeholder{color:var(--texts)}
.wizard-theme-container .form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
.wizard-theme-container .form-row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem}
.wizard-theme-container textarea{min-height:80px;resize:vertical}

/* ===== CHECKBOX GRID ===== */
.wizard-theme-container .section-grid{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
.wizard-theme-container .section-card{background:var(--bg3);border:1.5px solid var(--border);border-radius:var(--radius2);padding:1rem;cursor:pointer;transition:.25s;display:flex;align-items:flex-start;gap:.75rem}
.wizard-theme-container .section-card:hover{border-color:var(--accent);background:var(--glassb)}
.wizard-theme-container .section-card.selected{border-color:var(--accent);background:rgba(99,102,241,.12);box-shadow:0 0 0 3px rgba(99,102,241,.15)}
.wizard-theme-container .section-card input[type=checkbox]{display:none}
.wizard-theme-container .section-check{width:20px;height:20px;border-radius:4px;border:2px solid var(--border);display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px;transition:.2s}
.wizard-theme-container .section-card.selected .section-check{background:var(--accent);border-color:var(--accent);color:#fff}
.wizard-theme-container .section-card.selected .section-check::after{content:'✓';font-size:.75rem;font-weight:700}
.wizard-theme-container .section-info h4{font-size:.9rem;font-weight:600;color:var(--text);margin-bottom:.2rem}
.wizard-theme-container .section-info span{font-size:.75rem;color:var(--textm)}
.wizard-theme-container .badge-method{padding:.15rem .5rem;border-radius:4px;font-family:'Fira Code',monospace;font-size:.7rem;font-weight:600;color:#fff}
.wizard-theme-container .m-post{background:var(--green)}
.wizard-theme-container .m-get{background:var(--blue)}

/* ===== EXAMPLE FIELDS ===== */
.wizard-theme-container .example-block{background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius2);padding:1.25rem;margin-bottom:1rem}
.wizard-theme-container .example-block h4{font-size:.9rem;font-weight:600;color:var(--text);margin-bottom:.75rem;display:flex;align-items:center;gap:.5rem}
.wizard-theme-container .field-row{display:flex;align-items:center;gap:.75rem;margin-bottom:.6rem}
.wizard-theme-container .field-label{font-size:.8rem;color:var(--textm);width:160px;flex-shrink:0;font-family:'Fira Code',monospace}
.wizard-theme-container .field-input{flex:1;background:var(--bg2);border:1px solid var(--border);border-radius:6px;color:var(--text);font-family:'Fira Code',monospace;font-size:.82rem;padding:.45rem .7rem}
.wizard-theme-container .field-input:focus{border-color:var(--accent);outline:none}

/* ===== BUTTONS ===== */
.wizard-theme-container .btn-row{display:flex;gap:.75rem;justify-content:flex-end;margin-top:2rem}
.wizard-theme-container .btn{display:inline-flex;align-items:center;gap:.5rem;padding:.65rem 1.4rem;border-radius:var(--radius2);font-size:.9rem;font-weight:600;cursor:pointer;transition:.2s;border:none;text-decoration:none}
.wizard-theme-container .btn-primary{background:var(--accentg);color:#fff;box-shadow:0 4px 15px rgba(99,102,241,.35)}
.wizard-theme-container .btn-primary:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(99,102,241,.5)}
.wizard-theme-container .btn-secondary{background:var(--bg3);color:var(--textm);border:1.5px solid var(--border)}
.wizard-theme-container .btn-secondary:hover{border-color:var(--accent);color:var(--text)}
.wizard-theme-container .btn-success{background:linear-gradient(135deg,#10b981,#059669);color:#fff;box-shadow:0 4px 15px rgba(16,185,129,.3)}
.wizard-theme-container .btn-success:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(16,185,129,.5)}
.wizard-theme-container .btn-danger{background:rgba(239,68,68,.1);color:var(--red);border:1px solid rgba(239,68,68,.3)}
.wizard-theme-container .btn-danger:hover{background:rgba(239,68,68,.2)}
.wizard-theme-container .btn-sm{padding:.4rem .8rem;font-size:.8rem}

/* ===== PREVIEW PANEL ===== */
.wizard-theme-container #step-preview{display:none}
.wizard-theme-container .preview-actions{display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1.5rem;padding-bottom:1.25rem;border-bottom:1px solid var(--border)}
.wizard-theme-container .saved-badge{display:inline-flex;align-items:center;gap:.4rem;font-size:.8rem;color:var(--green);background:rgba(16,185,129,.1);padding:.3rem .75rem;border-radius:20px;border:1px solid rgba(16,185,129,.3)}

/* ===== ALERT ===== */
.wizard-theme-container .alert-info{background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.3);color:#93c5fd;border-radius:var(--radius2);padding:.75rem 1rem;font-size:.85rem;margin-bottom:1rem}
.wizard-theme-container .alert-success{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.3);color:#6ee7b7;border-radius:var(--radius2);padding:.75rem 1rem;font-size:.85rem;display:none}

/* ===== STEP VISIBILITY ===== */
.wizard-theme-container .step-panel{display:none}
.wizard-theme-container .step-panel.active{display:block}

/* ===== LOADER ===== */
.wizard-theme-container .loader-overlay{display:none;position:fixed;inset:0;background:rgba(15,17,23,.8);z-index:999;align-items:center;justify-content:center;flex-direction:column;gap:1rem}
.wizard-theme-container .loader-overlay.show{display:flex}
.wizard-theme-container .spinner{width:48px;height:48px;border:4px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:spin .8s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}

/* ===== PRINT STYLES ===== */
@media print{
  body{background:#fff!important;color:#000!important;font-family:'Inter',sans-serif}
  .top-bar,.preview-actions,.wizard-wrap>:not(#print-output),.wizard-card:not(#print-output),.btn-row{display:none!important}
  #print-output{display:block!important;width:100%;padding:0;margin:0;box-shadow:none;border:none;background:#fff}
  .print-doc{max-width:820px;margin:0 auto;padding:30px 40px;color:#1a1a2e}
  .print-title{text-align:center;font-size:22px;font-weight:700;margin-bottom:6px}
  .print-company{text-align:center;font-size:14px;color:#555;margin-bottom:20px}
  .print-url-link{color:#6366f1;font-weight:600}
  .print-section{margin-bottom:28px;page-break-inside:avoid}
  .print-section-title{font-size:15px;font-weight:700;margin-bottom:10px;color:#061C4C;border-left:3px solid #FF8A00;padding-left:8px}
  .print-endpoint-box{background:#061C4C!important;border-radius:8px;padding:12px 16px;margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;color:#fff!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .print-method{font-family:'Fira Code',monospace;font-size:11px;font-weight:700;padding:3px 10px;border-radius:4px;color:#fff!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .print-method.post{background:#10b981!important}
  .print-method.get{background:#0B4EA2!important}
  .print-path{font-family:'Fira Code',monospace;font-size:13px;color:#e2e8f0!important;margin-left:10px}
  .print-badge-auth{font-size:10px;padding:2px 8px;border-radius:10px;color:#fff!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .print-badge-auth.public{background:#10b981!important}
  .print-badge-auth.auth{background:#0B4EA2!important}
  .print-table{width:100%;border-collapse:collapse;margin-bottom:12px;font-size:12px}
  .print-table th{background:#EEF2F6!important;color:#061C4C!important;padding:7px 10px;text-align:left;font-weight:600;text-transform:uppercase;font-size:11px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .print-table td{padding:6px 10px;border-bottom:1px solid #e5e7eb}
  .print-table code{background:#EEF2F6!important;color:#0B4EA2!important;padding:1px 5px;border-radius:3px;font-family:'Fira Code',monospace;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .print-code-block{background:#061C4C!important;border-radius:6px;padding:12px 14px;font-family:'Fira Code',monospace;font-size:11px;color:#e2e8f0!important;overflow:hidden;white-space:pre-wrap;word-break:break-all;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .print-label{font-size:12px;font-weight:600;color:#374151;margin-bottom:6px;margin-top:10px}
  .print-creds{background:#f8fafc!important;border:1px solid #e5e7eb;border-radius:6px;padding:12px;font-size:12px;margin-bottom:16px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .print-creds p{margin-bottom:4px;color:#374151}
  .print-creds strong{color:#061C4C}
  .print-tip{background:#EEF2F6!important;border-left:3px solid #0B4EA2!important;padding:8px 12px;font-size:11px;color:#061C4C!important;border-radius:0 4px 4px 0;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  /* Ocultar footer del layout del sistema en PDF */
  .bs-footer,footer.bs-footer,#bsSidebar,
  body footer,body footer *{display:none!important;visibility:hidden!important}
  .print-page-break{page-break-before:always}
  .badge-req-yes{background:#d1fae5!important;color:#065f46!important;padding:2px 6px;border-radius:3px;font-size:10px;font-weight:700;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .badge-req-no{background:#f3f4f6!important;color:#6b7280!important;padding:2px 6px;border-radius:3px;font-size:10px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
}
#print-output{display:none}
</style>
</head>
<body>

<div class="loader-overlay" id="loaderOverlay">
  <div class="spinner"></div>
  <span style="color:var(--textm);font-size:.9rem">Generando documento...</span>
</div>

<!-- TOP BAR -->
<div class="top-bar">
  <div class="top-bar-brand">
    <i class="bi bi-magic"></i>
    Generador de Documentación API
  </div>
  <a href="<?= RUTA_URL ?>api/doc/" class="back-btn">
    <i class="bi bi-arrow-left"></i> API Docs
  </a>
</div>

<!-- WIZARD WRAP -->
<div class="wizard-wrap">

  <!-- PROGRESS -->
  <div class="progress-bar-wrap">
    <div class="steps-row">
      <div class="step-dot active" id="dot1">1</div>
      <div class="step-line" id="line1"></div>
      <div class="step-dot" id="dot2">2</div>
      <div class="step-line" id="line2"></div>
      <div class="step-dot" id="dot3">3</div>
      <div class="step-line" id="line3"></div>
      <div class="step-dot" id="dot4">4</div>
      <div class="step-line" id="line4"></div>
      <div class="step-dot" id="dot5">5</div>
    </div>
    <div class="steps-labels">
      <span class="step-label active" id="lbl1">Info</span>
      <span class="step-label" id="lbl2">Acceso</span>
      <span class="step-label" id="lbl3">Secciones</span>
      <span class="step-label" id="lbl4">Ejemplos</span>
      <span class="step-label" id="lbl5">PDF</span>
    </div>
  </div>

  <!-- WIZARD CARD -->
  <div class="wizard-card">

    <!-- ═══ PASO 1: Información General ═══ -->
    <div class="step-panel active" id="step1">
      <div class="step-title">📋 Información General</div>
      <div class="step-subtitle">Datos básicos que aparecerán en la portada del documento.</div>

      <div class="form-group">
        <label>Título del Documento</label>
        <input type="text" id="f_titulo" placeholder="Manual rápido – API de Pedidos" value="Manual rápido – API de Pedidos">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Empresa / Cliente Destinatario</label>
          <input type="text" id="f_empresa" placeholder="Ej: EcoGlobal Uruguay">
        </div>
        <div class="form-group">
          <label>Fecha del Documento</label>
          <input type="text" id="f_fecha" placeholder="Ej: Junio 2026">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Cliente (Comercio)</label>
          <select id="sel_cliente" onchange="onClienteChange()">
            <option value="">-- Selecciona Cliente --</option>
            <?php foreach ($clientes as $c): ?>
              <option value="<?= $c['id'] ?>" data-nombre="<?= htmlspecialchars($c['nombre']) ?>" data-email="<?= htmlspecialchars($c['email'] ?? '') ?>">
                <?= htmlspecialchars($c['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Proveedor (Mensajería)</label>
          <select id="sel_proveedor" onchange="onProveedorChange()">
            <option value="">-- Selecciona Proveedor --</option>
            <?php foreach ($proveedores as $p): ?>
              <option value="<?= $p['id'] ?>" data-nombre="<?= htmlspecialchars($p['nombre']) ?>">
                <?= htmlspecialchars($p['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>País de Ejemplo</label>
          <select id="sel_pais" onchange="onPaisChange()">
            <option value="">-- Selecciona País --</option>
            <?php foreach ($paises as $p): ?>
              <option value="<?= $p['id'] ?>" data-nombre="<?= htmlspecialchars($p['nombre']) ?>">
                <?= htmlspecialchars($p['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Moneda de Ejemplo</label>
          <select id="sel_moneda" onchange="onMonedaChange()">
            <option value="">-- Selecciona Moneda --</option>
            <?php foreach ($monedas as $m): ?>
              <option value="<?= $m['id'] ?>" data-nombre="<?= htmlspecialchars($m['nombre']) ?>">
                <?= htmlspecialchars($m['nombre']) ?> (<?= htmlspecialchars($m['codigo']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label>URL Base de la API</label>
        <input type="text" id="f_url_base" placeholder="https://rutaexlatam.com/api" value="https://rutaexlatam.com/api">
      </div>
      <div class="form-group">
        <label>URL de Documentación Completa <span style="color:var(--texts);font-weight:400;text-transform:none">(opcional)</span></label>
        <input type="text" id="f_url_doc" placeholder="https://rutaexlatam.com/api/doc/" value="https://rutaexlatam.com/api/doc/">
      </div>
      <div class="form-group">
        <label>Nota o descripción adicional <span style="color:var(--texts);font-weight:400;text-transform:none">(opcional)</span></label>
        <textarea id="f_nota" placeholder="Ej: Esta guía es para integración con el sistema de Uruguay..."></textarea>
      </div>

      <div class="btn-row">
        <button class="btn btn-primary" onclick="goStep(2)">Siguiente <i class="bi bi-arrow-right"></i></button>
      </div>
    </div>

    <!-- ═══ PASO 2: Credenciales de Ejemplo ═══ -->
    <div class="step-panel" id="step2">
      <div class="step-title">🔐 Credenciales de Ejemplo</div>
      <div class="step-subtitle">Datos del usuario de prueba que aparecerán en el documento (para que el cliente pueda hacer sus primeras pruebas).</div>

      <div class="form-row">
        <div class="form-group">
          <label>ID del Usuario de Prueba</label>
          <input type="number" id="f_cred_id" placeholder="Ej: 52">
        </div>
        <div class="form-group">
          <label>Nombre / Alias</label>
          <input type="text" id="f_cred_nombre" placeholder="Ej: EcoGlobal Uruguay">
        </div>
      </div>
      <div class="form-group">
        <label>Email de Ejemplo</label>
        <input type="email" id="f_cred_email" placeholder="usuario@empresa.com">
      </div>
      <div class="form-group">
        <label>Contraseña de Ejemplo</label>
        <input type="text" id="f_cred_pass" placeholder="Ej: *Mz6*VI7cU">
      </div>

      <div class="alert-info">
        <i class="bi bi-info-circle me-1"></i>
        <strong>Nota:</strong> Estas credenciales son solo de ejemplo para el manual. Asegúrate de que el usuario exista en el sistema antes de entregar el documento.
      </div>

      <div class="btn-row">
        <button class="btn btn-secondary" onclick="goStep(1)"><i class="bi bi-arrow-left"></i> Anterior</button>
        <button class="btn btn-primary" onclick="goStep(3)">Siguiente <i class="bi bi-arrow-right"></i></button>
      </div>
    </div>

    <!-- ═══ PASO 3: Secciones a incluir ═══ -->
    <div class="step-panel" id="step3">
      <div class="step-title">📑 Secciones a Incluir</div>
      <div class="step-subtitle">Selecciona qué endpoints documentar. La autenticación siempre se incluye.</div>

      <div class="section-grid">
        <!-- Auth: siempre incluida -->
        <div class="section-card selected" id="card_auth" onclick="toggleSection('auth', true)">
          <div class="section-check"></div>
          <div class="section-info">
            <h4><span class="badge-method m-post">POST</span> Autenticación</h4>
            <span>/api/auth/login · Obtener Token JWT</span>
          </div>
        </div>
        <div class="section-card selected" id="card_crear" onclick="toggleSection('crear')">
          <div class="section-check"></div>
          <div class="section-info">
            <h4><span class="badge-method m-post">POST</span> Crear Pedido</h4>
            <span>/api/pedidos/crear · Crear nueva guía</span>
          </div>
        </div>
        <div class="section-card selected" id="card_listar" onclick="toggleSection('listar')">
          <div class="section-check"></div>
          <div class="section-info">
            <h4><span class="badge-method m-get">GET</span> Consultar Pedidos</h4>
            <span>/api/pedidos/listar · Lista paginada</span>
          </div>
        </div>
        <div class="section-card selected" id="card_historial" onclick="toggleSection('historial')">
          <div class="section-check"></div>
          <div class="section-info">
            <h4><span class="badge-method m-get">GET</span> Historial de Estados</h4>
            <span>/api/pedidos/historial · Cambios de estado</span>
          </div>
        </div>
        <div class="section-card" id="card_ver" onclick="toggleSection('ver')">
          <div class="section-check"></div>
          <div class="section-info">
            <h4><span class="badge-method m-get">GET</span> Ver Pedido</h4>
            <span>/api/pedidos/ver · Detalle por ID</span>
          </div>
        </div>
        <div class="section-card" id="card_estados" onclick="toggleSection('estados')">
          <div class="section-check"></div>
          <div class="section-info">
            <h4><span class="badge-method m-get">GET</span> Listar Estados</h4>
            <span>/api/pedidos/estados · Catálogo de estados</span>
          </div>
        </div>
        <div class="section-card" id="card_buscar" onclick="toggleSection('buscar')">
          <div class="section-check"></div>
          <div class="section-info">
            <h4><span class="badge-method m-get">GET</span> Buscar Pedido</h4>
            <span>/api/pedidos/buscar · Por número de orden</span>
          </div>
        </div>
        <div class="section-card" id="card_rastreo" onclick="toggleSection('rastreo')">
          <div class="section-check"></div>
          <div class="section-info">
            <h4><span class="badge-method m-get">GET</span> Rastreo Público</h4>
            <span>/api/pedidos/rastreo · Sin autenticación</span>
          </div>
        </div>
      </div>

      <div class="btn-row">
        <button class="btn btn-secondary" onclick="goStep(2)"><i class="bi bi-arrow-left"></i> Anterior</button>
        <button class="btn btn-primary" onclick="goStep(4)">Siguiente <i class="bi bi-arrow-right"></i></button>
      </div>
    </div>

    <!-- ═══ PASO 4: Datos para JSON de Ejemplo ═══ -->
    <div class="step-panel" id="step4">
      <div class="step-title">🛠️ Datos para los Ejemplos</div>
      <div class="step-subtitle">Personaliza los valores que aparecerán en los JSON de ejemplo del documento.</div>

      <!-- Crear Pedido - campos destacados -->
      <div class="example-block" id="blk_crear_ex">
        <h4><span class="badge-method m-post" style="font-size:.75rem">POST</span> Crear Pedido — Valores de Ejemplo</h4>
        <div class="field-row"><span class="field-label">id_cliente</span><input class="field-input" id="ex_id_cliente" placeholder="52" value="52"><span style="font-size:.75rem;color:var(--texts);margin-left:.5rem">ID del cliente dueño</span></div>
        <div class="field-row"><span class="field-label">id_proveedor</span><input class="field-input" id="ex_id_proveedor" placeholder="53" value="53"><span style="font-size:.75rem;color:var(--texts);margin-left:.5rem">ID del proveedor</span></div>
        <div class="field-row"><span class="field-label">id_pais</span><input class="field-input" id="ex_id_pais" placeholder="9" value="9"><span style="font-size:.75rem;color:var(--texts);margin-left:.5rem">ID del país</span></div>
        <div class="field-row"><span class="field-label">id_moneda</span><input class="field-input" id="ex_id_moneda" placeholder="10" value="10"><span style="font-size:.75rem;color:var(--texts);margin-left:.5rem">ID de moneda</span></div>
        <div class="field-row"><span class="field-label">precio_total_local</span><input class="field-input" id="ex_precio" placeholder="780.50" value="780.50"><span style="font-size:.75rem;color:var(--texts);margin-left:.5rem">Precio total local</span></div>
        <div class="field-row"><span class="field-label">es_combo</span>
          <select class="field-input" id="ex_es_combo" style="width:80px"><option value="0">0</option><option value="1">1</option></select>
          <span style="font-size:.75rem;color:var(--texts);margin-left:.5rem">1 = Combo, 0 = Estándar</span>
        </div>
        <div class="field-row"><span class="field-label">numero_orden</span><input class="field-input" id="ex_numero_orden" placeholder="697896" value="697896"></div>
        <div class="field-row"><span class="field-label">destinatario</span><input class="field-input" id="ex_destinatario" placeholder="Carlos Mendoza" value="Carlos Mendoza"></div>
        <div class="field-row"><span class="field-label">telefono</span><input class="field-input" id="ex_telefono" placeholder="(502) 5555-1234" value="(502) 5555-1234"></div>
        <div class="field-row"><span class="field-label">direccion</span><input class="field-input" id="ex_direccion" placeholder="6 Avenida 12-34 Zona 3" value="6 Avenida 12-34 Zona 3"></div>
      </div>

      <div class="example-block">
        <h4>🌐 Información de la Región de Ejemplo</h4>
        <div class="field-row"><span class="field-label">País de ejemplo</span><input class="field-input" id="ex_pais_nombre" placeholder="Uruguay" value="Uruguay"></div>
        <div class="field-row"><span class="field-label">Moneda de ejemplo</span><input class="field-input" id="ex_moneda_nombre" placeholder="Peso Uruguayo" value="Peso Uruguayo"></div>
        <div class="field-row"><span class="field-label">fecha_entrega</span><input class="field-input" id="ex_fecha_entrega" placeholder="2026-03-15" value="2026-03-15"></div>
      </div>

      <div class="btn-row">
        <button class="btn btn-secondary" onclick="goStep(3)"><i class="bi bi-arrow-left"></i> Anterior</button>
        <button class="btn btn-success" onclick="generateDoc()"><i class="bi bi-magic"></i> Generar Documento</button>
      </div>
    </div>

    <!-- ═══ PASO 5: Vista Previa + PDF ═══ -->
    <div class="step-panel" id="step-preview">
      <div class="preview-actions">
        <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Exportar PDF</button>
        <button class="btn btn-success" id="btnGuardar" onclick="guardarDoc()"><i class="bi bi-cloud-arrow-up"></i> Guardar en Historial</button>
        <button class="btn btn-secondary" onclick="goStep(4)"><i class="bi bi-arrow-left"></i> Volver a editar</button>
        <a href="<?= RUTA_URL ?>api/doc/historial.php" class="btn btn-secondary"><i class="bi bi-clock-history"></i> Ver Historial</a>
        <span class="saved-badge" id="savedBadge" style="display:none"><i class="bi bi-check-circle"></i> Guardado</span>
      </div>
      <div id="docPreview"></div>
    </div>

  </div><!-- /wizard-card -->
</div><!-- /wizard-wrap -->

<!-- OUTPUT PARA PRINT -->
<div id="print-output"></div>

<script>
// ═══════════════════════════════════════════════════
// STATE
// ═══════════════════════════════════════════════════
let currentStep = 1;
const totalSteps = 5;
const selectedSections = new Set(['auth','crear','listar','historial']);
let lastConfig = null;
let lastHtml   = null;

// ═══════════════════════════════════════════════════
// SELECTORS CHANGE HANDLERS
// ═══════════════════════════════════════════════════
function onClienteChange() {
  const sel = document.getElementById('sel_cliente');
  const opt = sel.options[sel.selectedIndex];
  if (!opt || !opt.value) return;

  const id = opt.value;
  const nombre = opt.getAttribute('data-nombre');
  const email = opt.getAttribute('data-email');

  // Auto-populate
  document.getElementById('f_empresa').value = nombre;
  document.getElementById('f_cred_id').value = id;
  document.getElementById('f_cred_nombre').value = nombre;
  document.getElementById('f_cred_email').value = email;

  // Auto-populate step 4 values
  document.getElementById('ex_id_cliente').value = id;
}

function onProveedorChange() {
  const sel = document.getElementById('sel_proveedor');
  const opt = sel.options[sel.selectedIndex];
  if (!opt || !opt.value) return;

  const id = opt.value;
  document.getElementById('ex_id_proveedor').value = id;
}

function onPaisChange() {
  const sel = document.getElementById('sel_pais');
  const opt = sel.options[sel.selectedIndex];
  if (!opt || !opt.value) return;

  const id = opt.value;
  const nombre = opt.getAttribute('data-nombre');

  document.getElementById('ex_id_pais').value = id;
  document.getElementById('ex_pais_nombre').value = nombre;
}

function onMonedaChange() {
  const sel = document.getElementById('sel_moneda');
  const opt = sel.options[sel.selectedIndex];
  if (!opt || !opt.value) return;

  const id = opt.value;
  const nombre = opt.getAttribute('data-nombre');

  document.getElementById('ex_id_moneda').value = id;
  document.getElementById('ex_moneda_nombre').value = nombre;
}

// ═══════════════════════════════════════════════════
// NAVIGATION
// ═══════════════════════════════════════════════════
function goStep(n) {
  if (n === 1 && !validateStep(currentStep)) return;
  if (n > currentStep && !validateStep(currentStep)) return;

  // Hide all panels
  document.querySelectorAll('.step-panel').forEach(p => p.classList.remove('active'));

  if (n === 5) {
    document.getElementById('step-preview').style.display = 'block';
    document.getElementById('step-preview').classList.add('active');
  } else {
    document.getElementById('step-preview').style.display = 'none';
    document.getElementById('step' + n)?.classList.add('active');
  }

  // Update dots
  for (let i = 1; i <= 5; i++) {
    const dot = document.getElementById('dot' + i);
    const lbl = document.getElementById('lbl' + i);
    dot.classList.remove('active','done');
    lbl.classList.remove('active','done');
    if (i < n) { dot.classList.add('done'); dot.innerHTML = '✓'; lbl.classList.add('done'); }
    else if (i === n) { dot.classList.add('active'); dot.innerHTML = i; lbl.classList.add('active'); }
    else { dot.innerHTML = i; }

    if (i < 5) {
      const line = document.getElementById('line' + i);
      line.classList.toggle('done', i < n);
    }
  }
  currentStep = n;
  window.scrollTo({top:0,behavior:'smooth'});
}

function validateStep(n) {
  if (n === 1) {
    if (!v('f_titulo')) { flash('f_titulo','Ingresa el título del documento'); return false; }
    if (!v('f_empresa')) { flash('f_empresa','Ingresa el nombre del cliente o empresa'); return false; }
    if (!v('f_url_base')) { flash('f_url_base','Ingresa la URL base del API'); return false; }
  }
  if (n === 2) {
    if (!v('f_cred_email')) { flash('f_cred_email','Ingresa el email de ejemplo'); return false; }
  }
  return true;
}

function v(id) { return document.getElementById(id)?.value?.trim() || ''; }

function flash(id, msg) {
  const el = document.getElementById(id);
  if (!el) return;
  el.style.borderColor = 'var(--red)';
  el.focus();
  el.setAttribute('placeholder', msg);
  setTimeout(() => { el.style.borderColor = ''; el.setAttribute('placeholder', el.getAttribute('data-ph') || ''); }, 2500);
}

// ═══════════════════════════════════════════════════
// SECTION TOGGLE
// ═══════════════════════════════════════════════════
function toggleSection(name, locked=false) {
  if (locked) return; // auth siempre seleccionada
  const card = document.getElementById('card_' + name);
  if (selectedSections.has(name)) {
    selectedSections.delete(name);
    card.classList.remove('selected');
  } else {
    selectedSections.add(name);
    card.classList.add('selected');
  }
}

// ═══════════════════════════════════════════════════
// GENERATE DOCUMENT
// ═══════════════════════════════════════════════════
function generateDoc() {
  document.getElementById('loaderOverlay').classList.add('show');

  const config = {
    titulo:       v('f_titulo'),
    empresa:      v('f_empresa'),
    fecha:        v('f_fecha') || new Date().toLocaleDateString('es-MX',{month:'long',year:'numeric'}),
    urlBase:      v('f_url_base').replace(/\/$/, ''),
    urlDoc:       v('f_url_doc'),
    nota:         v('f_nota'),
    credId:       v('f_cred_id'),
    credNombre:   v('f_cred_nombre'),
    credEmail:    v('f_cred_email'),
    credPass:     v('f_cred_pass'),
    secciones:    [...selectedSections],
    ex: {
      id_cliente:     v('ex_id_cliente')    || '52',
      id_proveedor:   v('ex_id_proveedor')  || '53',
      id_pais:        v('ex_id_pais')       || '9',
      id_moneda:      v('ex_id_moneda')     || '10',
      precio:         v('ex_precio')        || '780.50',
      es_combo:       document.getElementById('ex_es_combo')?.value || '0',
      numero_orden:   v('ex_numero_orden')  || '697896',
      destinatario:   v('ex_destinatario')  || 'Carlos Mendoza',
      telefono:       v('ex_telefono')      || '(502) 5555-1234',
      direccion:      v('ex_direccion')     || '6 Avenida 12-34 Zona 3',
      pais_nombre:    v('ex_pais_nombre')   || 'Uruguay',
      moneda_nombre:  v('ex_moneda_nombre') || 'Peso Uruguayo',
      fecha_entrega:  v('ex_fecha_entrega') || '2026-03-15',
    }
  };

  lastConfig = config;
  const html = buildDocument(config);
  lastHtml = html;

  document.getElementById('docPreview').innerHTML = html;
  document.getElementById('print-output').innerHTML = html;

  setTimeout(() => {
    document.getElementById('loaderOverlay').classList.remove('show');
    document.getElementById('savedBadge').style.display = 'none';
    document.getElementById('btnGuardar').disabled = false;
    goStep(5);
  }, 600);
}

// ═══════════════════════════════════════════════════
// BUILD DOCUMENT HTML
// ═══════════════════════════════════════════════════
function buildDocument(c) {
  const secs = c.secciones;
  const base = c.urlBase;
  let html = `<div class="print-doc">`;

  // PORTADA
  html += `
    <div style="text-align:center;margin-bottom:28px;padding-bottom:20px;border-bottom:2px solid #e5e7eb">
      <h1 class="print-title">${esc(c.titulo)}</h1>
      <p class="print-company">${esc(c.empresa)}</p>
      <p style="font-size:12px;color:#9ca3af">${esc(c.fecha)}</p>
      ${c.urlDoc ? `<p style="font-size:12px;margin-top:8px">Documentación completa API <a href="${esc(c.urlDoc)}" class="print-url-link">${esc(c.urlDoc)}</a></p>` : ''}
      ${c.nota ? `<p style="font-size:12px;color:#6b7280;margin-top:8px;font-style:italic">${esc(c.nota)}</p>` : ''}
    </div>`;

  // CREDENCIALES
  if (c.credEmail) {
    html += `
    <div class="print-section">
      <div class="print-label">📋 Ejemplo de credenciales válidas (para pruebas en la API)</div>
      <div class="print-creds">
        ${c.credId     ? `<p><strong>id:</strong> ${esc(c.credId)}</p>` : ''}
        ${c.credNombre ? `<p><strong>Empresa/Usuario:</strong> ${esc(c.credNombre)}</p>` : ''}
        <p><strong>Usuario:</strong> ${esc(c.credEmail)}</p>
        <p><strong>Contraseña:</strong> ${esc(c.credPass)}</p>
        <p style="margin-top:10px;padding-top:10px;border-top:1px solid #e5e7eb">
          <strong>id_proveedor:</strong> ${esc(c.ex.id_proveedor)} &nbsp;·&nbsp;
          <strong>id_pais:</strong> ${esc(c.ex.id_pais)} &nbsp;·&nbsp;
          <strong>id_moneda:</strong> ${esc(c.ex.id_moneda)} &nbsp;·&nbsp;
          <strong>es_combo:</strong> ${esc(c.ex.es_combo)} <span style="color:#6b7280">(${c.ex.es_combo == '1' ? 'Combo' : 'Estándar'})</span>
        </p>
      </div>
    </div>`;
  }


  // AUTENTICACIÓN (siempre)
  html += buildAuth(c);

  // CREAR PEDIDO
  if (secs.includes('crear')) html += buildCrear(c);

  // CONSULTAR PEDIDOS
  if (secs.includes('listar')) html += buildListar(c);

  // HISTORIAL
  if (secs.includes('historial')) html += buildHistorial(c);

  // VER PEDIDO
  if (secs.includes('ver')) html += buildVer(c);

  // ESTADOS
  if (secs.includes('estados')) html += buildEstados(c);

  // BUSCAR
  if (secs.includes('buscar')) html += buildBuscar(c);

  // RASTREO
  if (secs.includes('rastreo')) html += buildRastreo(c);

  html += `</div>`;
  return html;
}

function esc(s) {
  if (!s) return '';
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function endpointBox(method, path, authType) {
  const cls = method.toLowerCase();
  const authLabel = authType === 'public' ? 'Público' : 'Autenticado';
  const authCls   = authType === 'public' ? 'public' : 'auth';
  return `<div class="print-endpoint-box"><div><span class="print-method ${cls}">${method}</span><span class="print-path">${path}</span></div><span class="print-badge-auth ${authCls}">${authLabel}</span></div>`;
}

function table(headers, rows) {
  let t = `<table class="print-table"><thead><tr>${headers.map(h=>`<th>${h}</th>`).join('')}</tr></thead><tbody>`;
  rows.forEach(r => { t += `<tr>${r.map(c=>`<td>${c}</td>`).join('')}</tr>`; });
  t += `</tbody></table>`;
  return t;
}

function codeBlock(code) {
  return `<div class="print-code-block">${esc(code)}</div>`;
}

function req(yes) { return yes ? '<span class="badge-req-yes">✓ Sí</span>' : '<span class="badge-req-no">—</span>'; }

// ─── SECCIONES ───────────────────────────────────────────────────────────────

function buildAuth(c) {
  const base = c.urlBase;
  return `
  <div class="print-section">
    <h2 class="print-section-title">🔐 Generar Token (Autenticación)</h2>
    <p class="print-label">Endpoint</p>
    ${endpointBox('POST', '/api/auth/login', 'public')}
    <p class="print-label" style="margin-top:12px">Cuerpo de la Petición</p>
    ${table(['Campo','Tipo','Req.','Descripción'],[
      ['<code>email</code>',    'string', req(true),  'Email del usuario registrado'],
      ['<code>password</code>', 'string', req(true),  'Contraseña del usuario'],
    ])}
    <p class="print-label">Ejemplo de Petición</p>
    ${codeBlock(`curl -X POST "${base}/auth/login" \\
  -H "Content-Type: application/json" \\
  -d '{"email":"${c.credEmail || 'admin@ejemplo.com'}","password":"${c.credPass || 'secure_password'}"}'`)}
    <p class="print-label">Respuesta <strong>200 OK</strong></p>
    ${codeBlock(`{\n  "success": true,\n  "message": "Login exitoso",\n  "data": {\n    "token": "eyJ0e... (your_token_here) ... "\n  }\n}`)}
    <p class="print-label">Usar el Token en siguientes peticiones</p>
    ${codeBlock(`Authorization: Bearer <TU_TOKEN_JWT>`)}
  </div>`;
}

function buildCrear(c) {
  const base = c.urlBase;
  const ex = c.ex;
  return `
  <div class="print-section print-page-break">
    <h2 class="print-section-title">📦 Crear Guía (Pedido)</h2>
    <p class="print-label">Endpoint</p>
    ${endpointBox('POST', '/api/pedidos/crear', 'auth')}
    <p class="print-label" style="margin-top:12px">🔑 Campos Obligatorios</p>
    ${table(['Campo','Tipo','Validación','Descripción'],[
      ['<code>numero_orden</code>',  'int/string', 'ESTRICTO, único',     'ID externo del pedido'],
      ['<code>destinatario</code>',  'string',     'ESTRICTO',            'Nombre del destinatario'],
      ['<code>producto_id</code>',   'array',      'ESTRICTO',            'Array de productos [{producto_id, cantidad}]'],
      ['<code>id_cliente</code>',    'entero',     'ESTRICTO, existe',    'ID del cliente dueño'],
      ['<code>id_proveedor</code>',  'entero',     'ESTRICTO, existe',    'ID del proveedor asignado'],
      ['<code>telefono</code>',      'string',     'ESTRICTO',            'Teléfono de contacto'],
      ['<code>direccion</code>',     'string',     'ESTRICTO',            'Dirección completa'],
      ['<code>comentario</code>',    'string',     'ESTRICTO',            'Notas de entrega'],
      ['<code>precio_total_local</code>', 'decimal', 'ESTRICTO, > 0',    'Precio total en moneda local'],
      ['<code>es_combo</code>',      'entero',     'ESTRICTO (0 ó 1)',    '1 si es combo, 0 si estándar'],
      ['<code>fecha_entrega</code>', 'string',     'ESTRICTO YYYY-MM-DD','Fecha estimada de entrega'],
    ])}
    <p class="print-label">Ejemplo JSON de Petición</p>
    <div class="print-tip">⚠️ Nota: JSON no permite comentarios (//) ni comas extra. Usar el formato exacto.</div>
    ${codeBlock(`{\n  "numero_orden": ${ex.numero_orden},\n  "destinatario": "${ex.destinatario}",\n  "id_cliente": ${ex.id_cliente},\n  "id_pais": ${ex.id_pais},\n  "telefono": "${ex.telefono}",\n  "direccion": "${ex.direccion}",\n  "comentario": "Dejar con el guardia si no hay nadie.",\n  "id_proveedor": ${ex.id_proveedor},\n  "id_moneda": ${ex.id_moneda},\n  "precio_total_local": ${ex.precio},\n  "es_combo": ${ex.es_combo},\n  "fecha_entrega": "${ex.fecha_entrega}",\n  "productos": [\n    { "producto_id": 49, "cantidad": 3 },\n    { "producto_id": 50, "cantidad": 2 }\n  ]\n}`)}
    <p class="print-label">Respuesta <strong>200 OK</strong></p>
    ${codeBlock(`{\n  "success": true,\n  "message": "Pedido creado exitosamente",\n  "data": {\n    "pedido_id": 1245,\n    "numero_orden": ${ex.numero_orden}\n  }\n}`)}
    <p class="print-label">Errores Comunes</p>
    ${table(['Código','Descripción'],[
      ['<span class="badge-req-yes">200</span>', 'Pedido creado correctamente'],
      ['<code>400</code>', 'Datos inválidos o vacíos'],
      ['<code>401</code>', 'Token ausente o expirado'],
      ['<code>422</code>', 'Error de validación de campos — ver campo "fields" en la respuesta'],
      ['<code>500</code>', 'Error interno del servidor'],
    ])}
  </div>`;
}

function buildListar(c) {
  const base = c.urlBase;
  return `
  <div class="print-section print-page-break">
    <h2 class="print-section-title">📋 Recuperar Pedidos</h2>
    <h3 style="font-size:13px;font-weight:600;color:#374151;margin-bottom:8px">Listar Pedidos</h3>
    ${endpointBox('GET', '/api/pedidos/listar', 'auth')}
    <p class="print-label" style="margin-top:10px">Parámetros de Consulta (Filtros)</p>
    ${table(['Parámetro','Tipo','Descripción','Ejemplo'],[
      ['<code>page</code>',         'int',    'Número de página (defecto: 1)',         '1'],
      ['<code>limit</code>',        'int',    'Resultados por página (máx: 100)',       '20'],
    ])}
    <p class="print-label">Ejemplo de Uso</p>
    ${codeBlock(`GET ${base}/pedidos/listar?page=1&limit=20`)}
    <p class="print-label">Respuesta <strong>200 OK</strong></p>
    ${codeBlock(`{\n  "success": true,\n  "data": [ { "id": 1245, "numero_orden": "697896", ... } ],\n  "pagination": {\n    "total": 350,\n    "per_page": 20,\n    "current_page": 1,\n    "total_pages": 18\n  }\n}`)}
    <h3 style="font-size:13px;font-weight:600;color:#374151;margin:16px 0 8px">Ver Pedido por ID</h3>
    ${endpointBox('GET', '/api/pedidos/ver', 'auth')}
    ${codeBlock(`GET ${base}/pedidos/ver?id=1245`)}
  </div>`;
}

function buildHistorial(c) {
  const base = c.urlBase;
  return `
  <div class="print-section print-page-break">
    <h2 class="print-section-title">🕒 Histórico de Estados</h2>
    ${endpointBox('GET', '/api/pedidos/historial', 'auth')}
    <div class="print-tip" style="margin-top:10px">💡 Tip: Todos los filtros son opcionales y combinables libremente. Sin filtros, se devuelve todo el historial paginado.</div>
    <p class="print-label" style="margin-top:12px">Parámetros de Consulta</p>
    ${table(['Parámetro','Tipo','Defecto','Descripción','Ejemplo'],[
      ['<code>numero_orden</code>',      'string', '—', 'Filtrar por número de orden',         '697896'],
      ['<code>id_pedido</code>',         'entero', '—', 'Filtrar por ID interno del pedido',   '45'],
      ['<code>id_estado_anterior</code>','entero', '—', 'Filtrar por ID exacto del estado ant.','1'],
      ['<code>id_estado_nuevo</code>',   'entero', '—', 'Filtrar por ID exacto del estado nuevo','3'],
      ['<code>estados</code>',           'string', '—', 'IDs separados por coma — anterior O nuevo','1,2,3'],
      ['<code>fecha_desde</code>',       'fecha',  '—', 'Fecha inicio del cambio (Y-m-d)',     '2026-01-01'],
      ['<code>fecha_hasta</code>',       'fecha',  '—', 'Fecha fin del cambio (Y-m-d)',        '2026-12-31'],
      ['<code>id_usuario</code>',        'entero', '—', 'Filtrar por usuario que realizó el cambio','7'],
      ['<code>page</code>',              'entero', '1', 'Número de página',                    '2'],
    ])}
    <p class="print-label">Ejemplo de Uso</p>
    ${codeBlock(`GET ${base}/pedidos/historial?numero_orden=697896&fecha_desde=2026-01-01`)}
    <p class="print-label">Respuesta <strong>200 OK</strong></p>
    ${codeBlock(`{\n  "success": true,\n  "data": [\n    {\n      "id": 12,\n      "id_pedido": 45,\n      "numero_orden": "697896",\n      "estado_anterior": "En bodega",\n      "estado_nuevo": "En tránsito",\n      "comentario": "Recogido por mensajero",\n      "realizado_por": "Juan Pérez",\n      "fecha_cambio": "2026-03-04 09:30:00"\n    }\n  ],\n  "pagination": { "total": 5, "current_page": 1, "total_pages": 1 }\n}`)}
  </div>`;
}

function buildVer(c) {
  const base = c.urlBase;
  return `
  <div class="print-section">
    <h2 class="print-section-title">🔍 Ver Pedido Individual</h2>
    ${endpointBox('GET', '/api/pedidos/ver', 'auth')}
    <p class="print-label" style="margin-top:10px">Parámetros</p>
    ${table(['Parámetro','Tipo','Descripción'],[
      ['<code>id</code>',           'entero', 'ID interno del pedido'],
      ['<code>numero_orden</code>', 'string', 'Número de orden externo (alternativo al id)'],
    ])}
    ${codeBlock(`GET ${base}/pedidos/ver?id=1245`)}
  </div>`;
}

function buildEstados(c) {
  return `
  <div class="print-section">
    <h2 class="print-section-title">🏷️ Catálogo de Estados</h2>
    ${endpointBox('GET', '/api/pedidos/estados', 'public')}
    <p style="font-size:12px;color:#6b7280;margin-top:8px;margin-bottom:12px">Devuelve todos los estados disponibles. Útil para poblar selectores y validar IDs.</p>
    ${table(['ID','Nombre Estado'],[
      ['1','En bodega'],['2','En ruta o proceso'],['3','Entregado'],
      ['4','Reprogramado'],['5','Domicilio cerrado'],['6','No hay quien reciba'],
      ['7','Devuelto'],['8','Domicilio no encontrado'],['9','Rechazado'],
      ['10','No puede pagar recaudo'],['11','Pendiente recolección'],
      ['12','Recolectado por mensajería'],['13','Traslado a distribución'],
      ['14','Entregado-liquidado'],['15','Devuelto a bodega'],
      ['16','Incidencia'],['17','Cancelado'],
    ])}
  </div>`;
}

function buildBuscar(c) {
  const base = c.urlBase;
  return `
  <div class="print-section">
    <h2 class="print-section-title">🔎 Buscar Pedido</h2>
    ${endpointBox('GET', '/api/pedidos/buscar', 'auth')}
    ${table(['Parámetro','Tipo','Descripción'],[
      ['<code>numero_orden</code>', 'string', 'Número de orden a buscar'],
    ])}
    ${codeBlock(`GET ${base}/pedidos/buscar?numero_orden=697896`)}
  </div>`;
}

function buildRastreo(c) {
  const base = c.urlBase;
  return `
  <div class="print-section">
    <h2 class="print-section-title">📡 Rastreo Público</h2>
    ${endpointBox('GET', '/api/pedidos/rastreo', 'public')}
    <p style="font-size:12px;color:#6b7280;margin-top:8px;margin-bottom:12px">No requiere autenticación. Devuelve estado actual del pedido para tracking público.</p>
    ${table(['Parámetro','Tipo','Descripción'],[
      ['<code>numero_orden</code>', 'string', 'Número de orden a rastrear'],
    ])}
    ${codeBlock(`GET ${base}/pedidos/rastreo?numero_orden=697896`)}
  </div>`;
}

// ═══════════════════════════════════════════════════
// GUARDAR EN HISTORIAL
// ═══════════════════════════════════════════════════
async function guardarDoc() {
  const btn = document.getElementById('btnGuardar');
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Guardando...';

  const form = new FormData();
  form.append('action', 'guardar_doc');
  form.append('config', JSON.stringify(lastConfig));
  form.append('html',   lastHtml);

  try {
    const res  = await fetch('', { method:'POST', body: form });
    const data = await res.json();
    if (data.success) {
      btn.style.display = 'none';
      document.getElementById('savedBadge').style.display = 'inline-flex';
    } else {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-cloud-arrow-up"></i> Guardar en Historial';
      alert('Error al guardar: ' + data.message);
    }
  } catch(e) {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-cloud-arrow-up"></i> Guardar en Historial';
    alert('Error de conexión');
  }
}

// Init fecha
window.addEventListener('DOMContentLoaded', () => {
  if (!document.getElementById('f_fecha').value) {
    const now = new Date();
    document.getElementById('f_fecha').value = now.toLocaleDateString('es-MX',{month:'long',year:'numeric'});
  }
});

// Ocultar footer del sistema al generar PDF
window.addEventListener('beforeprint', () => {
  const footer = document.querySelector('footer.bs-footer');
  if (footer) { footer.setAttribute('data-was', footer.style.display); footer.style.setProperty('display','none','important'); }
});
window.addEventListener('afterprint', () => {
  const footer = document.querySelector('footer.bs-footer');
  if (footer) { footer.style.display = footer.getAttribute('data-was') || ''; }
});
</script>
</div><!-- /wizard-theme-container -->

<?php
include "vista/includes/footer.php";
?>
