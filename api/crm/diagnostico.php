<?php
require_once __DIR__ . '/../../utils/session.php';
require_once __DIR__ . '/../../utils/crm_roles.php';
require_once __DIR__ . '/../../modelo/crm_notification.php';

start_secure_session();
$userId = $_SESSION['user_id'] ?? 0;

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Diagnóstico CRM</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .section { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .ok { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #333; color: white; }
    </style>
</head>
<body>
    <h1>🔍 Diagnóstico CRM - Usuario <?= $userId ?></h1>
    
    <div class="section">
        <h2>1. Sesión y Roles</h2>
        <p><strong>User ID:</strong> <?= $userId ?></p>
        <p><strong>Es Cliente CRM:</strong> <?= isClienteCRM($userId) ? '<span class="ok">SÍ</span>' : '<span class="error">NO</span>' ?></p>
        <p><strong>Es Proveedor CRM:</strong> <?= isProveedorCRM($userId) ? '<span class="ok">SÍ</span>' : '<span class="error">NO</span>' ?></p>
        <p><strong>Es Admin:</strong> <?= isUserAdmin($userId) ? '<span class="ok">SÍ</span>' : '<span class="error">NO</span>' ?></p>
    </div>
    
    <div class="section">
        <h2>2. Notificaciones Pendientes</h2>
        <?php
        $pendientes = CrmNotificationModel::obtenerLeadsPendientes($userId);
        ?>
        <p><strong>Total leads pendientes:</strong> <?= count($pendientes) ?></p>
        
        <?php if (count($pendientes) > 0): ?>
            <table>
                <tr>
                    <th>ID Notif</th>
                    <th>Lead ID</th>
                    <th>Estado Lead</th>
                    <th>Nombre</th>
                    <th>Fecha</th>
                </tr>
                <?php foreach ($pendientes as $notif): 
                    $payload = json_decode($notif['payload'], true);
                ?>
                <tr>
                    <td><?= $notif['id'] ?></td>
                    <td><?= $payload['lead_id'] ?? 'N/A' ?></td>
                    <td><?= $notif['lead_status_live'] ?? 'N/A' ?></td>
                    <td><?= $payload['nombre'] ?? 'N/A' ?></td>
                    <td><?= date('d/m H:i', strtotime($notif['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p class="error">⚠️ No hay leads pendientes (estado EN_ESPERA o nuevo)</p>
        <?php endif; ?>
    </div>
    
    <div class="section">
        <h2>3. Actualizaciones</h2>
        <?php
        $updates = CrmNotificationModel::obtenerPorUsuario($userId, false, 100, 0);
        $updateCount = 0;
        foreach ($updates as $u) {
            if ($u['type'] === 'status_updated') $updateCount++;
        }
        ?>
        <p><strong>Total actualizaciones:</strong> <?= $updateCount ?></p>
    </div>
    
    <div class="section">
        <h2>4. Test Dropdown HTML</h2>
        <p>Si ves el dropdown aquí, significa que el código funciona:</p>
        <select class="form-select form-select-sm" style="width: auto; font-weight: 500;">
            <option selected disabled>Acción rápida...</option>
            <option value="APROBADO">✓ Aprobar Lead</option>
            <option value="CANCELADO">✗ Cancelar</option>
        </select>
    </div>
    
    <div class="section">
        <h2>5. Conclusión</h2>
        <?php if (count($pendientes) === 0): ?>
            <p class="error"><strong>⚠️ PROBLEMA IDENTIFICADO:</strong></p>
            <p>No tienes leads pendientes (con estado EN_ESPERA o nuevo).</p>
            <p>Por eso la tabla "Por Atender" está vacía y no ves el dropdown.</p>
            <p><strong>Solución:</strong> Ve a la sección CRM y asigna nuevos leads, o cambia el estado de un lead existente a EN_ESPERA.</p>
        <?php else: ?>
            <p class="ok"><strong>✅ Tienes <?= count($pendientes) ?> leads pendientes.</strong></p>
            <p>El dropdown debería aparecer en la tab "Por Atender".</p>
            <p>Si no lo ves, verifica que estés en la URL correcta: <code>crm/notificaciones?tab=leads</code></p>
        <?php endif; ?>
    </div>
    
    <p><a href="<?= RUTA_URL ?>crm/notificaciones?tab=leads" style="display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px;">← Volver a Notificaciones</a></p>
    
</body>
</html>
