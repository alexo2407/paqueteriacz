<?php
/**
 * historial.php — Historial de documentos API generados
 * Acceso: Solo Admin
 */

// CWD to root to ensure relative paths resolve correctly
chdir(dirname(__DIR__, 2));

if (!defined('RUTA_URL')) {
    require_once 'config/config.php';
}
require_once 'modelo/conexion.php';
require_once 'modelo/api_doc.php';

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

// Acción AJAX: eliminar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    if ($action === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        $ok = $id > 0 ? ApiDocModel::eliminar($id) : false;
        echo json_encode(['success' => $ok]);
        exit;
    }
}

// Acción: ver HTML de un documento
if (isset($_GET['ver'])) {
    $id  = (int)$_GET['ver'];
    $doc = ApiDocModel::obtenerPorId($id);
    if ($doc) {
        // Mostrar el HTML con cabecera mínima de impresión
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>' . htmlspecialchars($doc['titulo']) . '</title>';
        echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">';
        echo '<style>';
        echo 'body{font-family:Inter,sans-serif;background:#fff;color:#1a1a2e;padding:30px 40px;max-width:820px;margin:0 auto}';
        echo '.print-title{text-align:center;font-size:22px;font-weight:700;margin-bottom:6px}';
        echo '.print-company{text-align:center;font-size:14px;color:#555;margin-bottom:20px}';
        echo '.print-url-link{color:#6366f1;font-weight:600}';
        echo '.print-section{margin-bottom:28px}';
        echo '.print-section-title{font-size:15px;font-weight:700;margin-bottom:10px}';
        echo '.print-endpoint-box{background:#1a1a2e;border-radius:8px;padding:12px 16px;margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;color:#fff}';
        echo '.print-method{font-family:Fira Code,monospace;font-size:11px;font-weight:700;padding:3px 10px;border-radius:4px;color:#fff}';
        echo '.print-method.post{background:#10b981}.print-method.get{background:#3b82f6}';
        echo '.print-path{font-family:Fira Code,monospace;font-size:13px;color:#e2e8f0;margin-left:10px}';
        echo '.print-badge-auth{font-size:10px;padding:2px 8px;border-radius:10px;color:#fff}';
        echo '.print-badge-auth.public{background:#10b981}.print-badge-auth.auth{background:#6366f1}';
        echo '.print-table{width:100%;border-collapse:collapse;margin-bottom:12px;font-size:12px}';
        echo '.print-table th{background:#e8ecf0;padding:7px 10px;text-align:left;font-weight:600;text-transform:uppercase;font-size:11px}';
        echo '.print-table td{padding:6px 10px;border-bottom:1px solid #e5e7eb}';
        echo '.print-table code{background:#f3f4f6;color:#6366f1;padding:1px 5px;border-radius:3px;font-family:Fira Code,monospace}';
        echo '.print-code-block{background:#1e2538;border-radius:6px;padding:12px 14px;font-family:Fira Code,monospace;font-size:11px;color:#e2e8f0;white-space:pre-wrap;word-break:break-all;margin-bottom:10px}';
        echo '.print-label{font-size:12px;font-weight:600;color:#374151;margin-bottom:6px;margin-top:10px;display:block}';
        echo '.print-creds{background:#f8fafc;border:1px solid #e5e7eb;border-radius:6px;padding:12px;font-size:12px;margin-bottom:16px}';
        echo '.print-creds p{margin-bottom:4px;color:#374151}';
        echo '.print-tip{background:#eff6ff;border-left:3px solid #3b82f6;padding:8px 12px;font-size:11px;color:#1e40af;border-radius:0 4px 4px 0;margin-bottom:10px}';
        echo '.badge-req-yes{background:#d1fae5;color:#065f46;padding:2px 6px;border-radius:3px;font-size:10px;font-weight:700}';
        echo '.badge-req-no{background:#f3f4f6;color:#6b7280;padding:2px 6px;border-radius:3px;font-size:10px}';
        echo '.no-print-bar{background:#1a1a2e;color:#e2e8f0;padding:10px 20px;display:flex;gap:10px;align-items:center;margin:-30px -40px 30px;font-size:13px}';
        echo '.no-print-bar a,.no-print-bar button{color:#6366f1;text-decoration:none;font-weight:600;background:none;border:none;cursor:pointer;font-size:13px;padding:4px 10px;border:1px solid #6366f1;border-radius:6px}';
        echo '@media print{.no-print-bar{display:none}}';
        echo '</style></head><body>';
        echo '<div class="no-print-bar">';
        echo '<span>📄 ' . htmlspecialchars($doc['titulo']) . '</span>';
        echo '<button onclick="window.print()">🖨️ Exportar PDF</button>';
        echo '<a href="' . RUTA_URL . 'api/doc/historial.php">← Volver al Historial</a>';
        echo '</div>';
        echo $doc['html_generado'];
        echo '</body></html>';
        exit;
    }
    header('Location: ' . RUTA_URL . 'api/doc/historial.php');
    exit;
}

// Listado normal
$docs  = ApiDocModel::listar(100);
$total = ApiDocModel::contar();
?>
<?php
include "vista/includes/header.php";
?>

<div class="wizard-theme-container">
<style>
/* ===== RESET & BASE ===== */
.wizard-theme-container * {box-sizing:border-box}
.wizard-theme-container {
  --bg:#0f1117;--bg2:#161b27;--bg3:#1e2538;--bg4:#252d40;
  --accent:#6366f1;--accentg:linear-gradient(135deg,#6366f1,#8b5cf6);
  --green:#10b981;--blue:#3b82f6;--red:#ef4444;
  --text:#e2e8f0;--textm:#94a3b8;--texts:#64748b;
  --border:rgba(99,102,241,.25);--glassb:rgba(99,102,241,.08);
  --radius:12px;--radius2:8px;
  
  background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;-webkit-font-smoothing:antialiased;
  padding: 1.5rem 2rem;
  border-radius: var(--radius);
  margin-top: 1rem;
}
.wizard-theme-container .top-bar{background:var(--bg2);border-bottom:1px solid var(--border);padding:.875rem 1.5rem;display:flex;align-items:center;justify-content:space-between;border-radius:var(--radius) var(--radius) 0 0}
.wizard-theme-container .top-bar-brand{display:flex;align-items:center;gap:.75rem;font-weight:700;font-size:1rem}
.wizard-theme-container .top-bar-brand i{font-size:1.25rem;background:var(--accentg);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.wizard-theme-container .back-btn{display:flex;align-items:center;gap:.4rem;color:var(--textm);text-decoration:none;font-size:.85rem;transition:.2s;padding:.4rem .75rem;border-radius:var(--radius2);border:1px solid var(--border)}
.wizard-theme-container .back-btn:hover{color:var(--text);border-color:var(--accent);background:var(--glassb)}
.wizard-theme-container .page-content{max-width:1100px;margin:2rem auto;padding:0 1.5rem}
.wizard-theme-container .page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem}
.wizard-theme-container .page-title{font-size:1.5rem;font-weight:700;background:var(--accentg);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.wizard-theme-container .page-subtitle{font-size:.85rem;color:var(--textm);margin-top:.25rem}
.wizard-theme-container .btn{display:inline-flex;align-items:center;gap:.5rem;padding:.55rem 1.2rem;border-radius:var(--radius2);font-size:.875rem;font-weight:600;cursor:pointer;transition:.2s;border:none;text-decoration:none}
.wizard-theme-container .btn-primary{background:var(--accentg);color:#fff;box-shadow:0 4px 15px rgba(99,102,241,.3)}
.wizard-theme-container .btn-primary:hover{transform:translateY(-1px)}
.wizard-theme-container .empty-state{text-align:center;padding:4rem 2rem;color:var(--textm)}
.wizard-theme-container .empty-state i{font-size:3rem;display:block;margin-bottom:1rem;opacity:.4}
.wizard-theme-container .empty-state h3{font-size:1.1rem;font-weight:600;color:var(--text);margin-bottom:.5rem}
.wizard-theme-container .doc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1.25rem}
.wizard-theme-container .doc-card{background:var(--bg2);border:1px solid var(--border);border-radius:14px;padding:1.25rem;transition:.25s;display:flex;flex-direction:column;gap:.75rem}
.wizard-theme-container .doc-card:hover{border-color:var(--accent);transform:translateY(-2px);box-shadow:0 8px 30px rgba(99,102,241,.15)}
.wizard-theme-container .doc-card-header{display:flex;align-items:flex-start;justify-content:space-between;gap:.5rem}
.wizard-theme-container .doc-card-title{font-size:.95rem;font-weight:700;color:var(--text);line-height:1.3}
.wizard-theme-container .doc-card-company{font-size:.8rem;color:var(--accent);font-weight:600;margin-top:.15rem}
.wizard-theme-container .doc-card-meta{display:flex;flex-wrap:wrap;gap:.5rem}
.wizard-theme-container .tag{display:inline-flex;align-items:center;gap:.25rem;font-size:.72rem;padding:.2rem .55rem;border-radius:20px;font-weight:500}
.wizard-theme-container .tag-url{background:rgba(59,130,246,.12);color:#93c5fd;border:1px solid rgba(59,130,246,.2)}
.wizard-theme-container .tag-date{background:rgba(99,102,241,.1);color:#a5b4fc;border:1px solid rgba(99,102,241,.2)}
.wizard-theme-container .tag-sec{background:rgba(16,185,129,.08);color:#6ee7b7;border:1px solid rgba(16,185,129,.15)}
.wizard-theme-container .doc-card-actions{display:flex;gap:.5rem;margin-top:.25rem}
.wizard-theme-container .btn-sm{padding:.35rem .75rem;font-size:.8rem}
.wizard-theme-container .btn-view{background:var(--glassb);color:var(--accent);border:1px solid var(--border)}
.wizard-theme-container .btn-view:hover{border-color:var(--accent);background:rgba(99,102,241,.15)}
.wizard-theme-container .btn-del{background:rgba(239,68,68,.08);color:var(--red);border:1px solid rgba(239,68,68,.2)}
.wizard-theme-container .btn-del:hover{background:rgba(239,68,68,.18)}
.wizard-theme-container .count-badge{background:var(--bg3);border:1px solid var(--border);padding:.3rem .75rem;border-radius:20px;font-size:.8rem;color:var(--textm)}
</style>

<div class="top-bar">
  <div class="top-bar-brand">
    <i class="bi bi-clock-history"></i>
    Historial de Documentación
  </div>
  <div style="display:flex;gap:.75rem;align-items:center">
    <a href="<?= RUTA_URL ?>api/doc/generator.php" class="back-btn">
      <i class="bi bi-magic"></i> Nuevo Documento
    </a>
    <a href="<?= RUTA_URL ?>api/doc/" class="back-btn">
      <i class="bi bi-arrow-left"></i> API Docs
    </a>
  </div>
</div>

<div class="page-content">
  <div class="page-header">
    <div>
      <div class="page-title">📂 Documentos Generados</div>
      <div class="page-subtitle">Historial de manuales de API creados con el asistente</div>
    </div>
    <div style="display:flex;align-items:center;gap:.75rem">
      <span class="count-badge"><?= $total ?> documento<?= $total != 1 ? 's' : '' ?></span>
      <a href="<?= RUTA_URL ?>api/doc/generator.php" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Generar Nuevo
      </a>
    </div>
  </div>

  <?php if (empty($docs)): ?>
  <div class="empty-state">
    <i class="bi bi-file-earmark-x"></i>
    <h3>No hay documentos generados aún</h3>
    <p style="margin-bottom:1.5rem">Usa el asistente para generar tu primer manual de API en segundos.</p>
    <a href="<?= RUTA_URL ?>api/doc/generator.php" class="btn btn-primary">
      <i class="bi bi-magic"></i> Crear primer documento
    </a>
  </div>
  <?php else: ?>
  <div class="doc-grid">
    <?php foreach ($docs as $doc): ?>
    <?php
      $secciones = json_decode($doc['secciones'] ?? '[]', true) ?? [];
      $fechaFormatada = date('d/m/Y H:i', strtotime($doc['created_at']));
      $urlDisplay = strlen($doc['url_base']) > 35 ? substr($doc['url_base'], 0, 35) . '...' : $doc['url_base'];
    ?>
    <div class="doc-card" id="card-<?= $doc['id'] ?>">
      <div class="doc-card-header">
        <div>
          <div class="doc-card-title"><?= htmlspecialchars($doc['titulo']) ?></div>
          <div class="doc-card-company"><i class="bi bi-building me-1" style="font-size:.75rem"></i><?= htmlspecialchars($doc['empresa_cliente']) ?></div>
        </div>
        <i class="bi bi-file-earmark-pdf" style="font-size:1.5rem;color:var(--accent);opacity:.7;flex-shrink:0"></i>
      </div>
      <div class="doc-card-meta">
        <span class="tag tag-date"><i class="bi bi-calendar3"></i> <?= $fechaFormatada ?></span>
        <span class="tag tag-url"><i class="bi bi-link-45deg"></i> <?= htmlspecialchars($urlDisplay) ?></span>
        <?php if (!empty($secciones)): ?>
        <span class="tag tag-sec"><i class="bi bi-list-check"></i> <?= count($secciones) ?> secciones</span>
        <?php endif; ?>
      </div>
      <?php if (!empty($secciones)): ?>
      <div style="display:flex;flex-wrap:wrap;gap:.35rem">
        <?php foreach ($secciones as $sec): ?>
        <span style="font-size:.68rem;padding:.15rem .5rem;background:var(--bg3);border-radius:4px;color:var(--textm);border:1px solid var(--border)"><?= htmlspecialchars($sec) ?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <div class="doc-card-actions">
        <a href="?ver=<?= $doc['id'] ?>" target="_blank" class="btn btn-sm btn-view">
          <i class="bi bi-eye"></i> Ver / PDF
        </a>
        <button class="btn btn-sm btn-del" onclick="eliminar(<?= $doc['id'] ?>, this)">
          <i class="bi bi-trash"></i> Eliminar
        </button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<script>
async function eliminar(id, btn) {
  if (!confirm('¿Eliminar este documento del historial? Esta acción no se puede deshacer.')) return;
  btn.disabled = true;
  btn.innerHTML = '<i class="bi bi-hourglass-split"></i>';

  const form = new FormData();
  form.append('action', 'eliminar');
  form.append('id', id);

  try {
    const res  = await fetch('', { method:'POST', body: form });
    const data = await res.json();
    if (data.success) {
      const card = document.getElementById('card-' + id);
      card.style.transition = 'all .3s';
      card.style.opacity = '0';
      card.style.transform = 'scale(.95)';
      setTimeout(() => card.remove(), 300);
    } else {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-trash"></i> Eliminar';
      alert('No se pudo eliminar');
    }
  } catch(e) {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-trash"></i> Eliminar';
    alert('Error de conexión');
  }
}
</script>
</div><!-- /wizard-theme-container -->

<?php
include "vista/includes/footer.php";
?>
