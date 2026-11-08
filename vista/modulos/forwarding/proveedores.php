<?php include("vista/includes/header.php") ?>

<?php
$usaDataTables = true;
require_once __DIR__ . '/../../../modelo/forwarding.php';
$proveedores = ForwardingModel::obtenerProveedores();
?>

<style>
.fwd-card { border: none; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); overflow: hidden; }
.fwd-header { background: linear-gradient(135deg, #0B4EA2 0%, #061C4C 100%); color: white; padding: 1.75rem 2rem; }
.fwd-header h3 { margin: 0; font-weight: 600; }
.provider-status { font-size: 0.8rem; padding: 0.3em 0.75em; border-radius: 8px; font-weight: 600; }
.provider-status.active { background: #d1fae5; color: #065f46; }
.provider-status.inactive { background: #fee2e2; color: #991b1b; }
.btn-test { background: linear-gradient(135deg, #0B4EA2, #061C4C); color: #fff; border: none; }
.btn-test:hover { background: linear-gradient(135deg, #5a6fd6, #6a4199); color: #fff; transform: translateY(-1px); }
.test-result { border-radius: 10px; padding: 1rem; margin-top: 1rem; display: none; }
.test-result.success { background: #d1fae5; border: 1px solid #6ee7b7; }
.test-result.error { background: #fee2e2; border: 1px solid #fca5a5; }
</style>

<div class="container-fluid py-3">
    <div class="card fwd-card mb-4">
        <div class="fwd-header">
            <div class="row align-items-center">
                <div class="col-md-6 mb-3 mb-md-0">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-white bg-opacity-25 rounded-circle p-3">
                            <i class="bi bi-building fs-3"></i>
                        </div>
                        <div>
                            <h3>Proveedores Externos</h3>
                            <p class="mb-0 opacity-75">Configura las conexiones API con proveedores de logística</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-md-end gap-2 mt-3 mt-md-0">
                        <a href="<?= RUTA_URL ?>forwarding" class="btn" style="background:rgba(255,255,255,0.2);color:#fff;border:1px solid rgba(255,255,255,0.4);border-radius:10px;">
                            <i class="bi bi-arrow-left me-1"></i> Dashboard
                        </a>
                        <button class="btn" style="background:#fff;color:#0B4EA2;border:none;border-radius:10px;font-weight:600;" data-bs-toggle="modal" data-bs-target="#modalProvider">
                            <i class="bi bi-plus-circle-fill me-1"></i> Nuevo Proveedor
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-4">
            <div class="table-responsive">
                <table id="tblProviders" class="table table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Slug</th>
                            <th>URL Base</th>
                            <th>Auth</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($proveedores as $p):
                        $pCreds = json_decode($p['credentials'] ?? '{}', true) ?: [];
                        $pDefCfg = json_decode($p['default_config'] ?? '{}', true) ?: [];
                        $isShipEdge = (($p['slug'] ?? '') === 'shipedge');
                        $safeShipEdgeConfig = [
                            'shipping_method'   => $pCreds['shipping_method'] ?? $pDefCfg['shipping_method'] ?? '',
                            'carrier_name'      => $pCreds['carrier_name'] ?? $pDefCfg['carrier_name'] ?? '',
                            'store_channel'     => $pCreds['store_channel'] ?? $pDefCfg['store_channel'] ?? '',
                            'default_country'   => $pCreds['default_country'] ?? $pDefCfg['default_country'] ?? '',
                            'currency_code'     => $pCreds['currency_code'] ?? $pDefCfg['currency_code'] ?? '',
                            'enable_sold_price' => !empty($pCreds['enable_sold_price'] ?? $pDefCfg['enable_sold_price'] ?? false),
                            'has_credentials'   => !empty($pCreds['account_id'] ?? $pCreds['userName'] ?? '') && !empty($pCreds['api_key'] ?? $pCreds['password'] ?? ''),
                        ];
                    ?>
                        <tr data-id="<?= $p['id'] ?>">
                            <td><span class="badge bg-light text-dark border">#<?= $p['id'] ?></span></td>
                            <td class="fw-bold"><?= htmlspecialchars($p['nombre']) ?></td>
                            <td><code><?= htmlspecialchars($p['slug']) ?></code></td>
                            <td><small class="text-muted"><?= htmlspecialchars($p['base_url']) ?></small></td>
                            <td><span class="badge bg-info text-dark"><?= htmlspecialchars($isShipEdge ? 'AccountID + Key (v41)' : ($p['auth_method'] ?? '')) ?></span></td>
                            <td>
                                <span class="provider-status <?= $p['activo'] ? 'active' : 'inactive' ?>">
                                    <?= $p['activo'] ? '● Activo' : '● Inactivo' ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    <button class="btn btn-sm btn-outline-primary btn-edit-provider" title="Editar"
                                            data-id="<?= $p['id'] ?>"
                                            data-nombre="<?= htmlspecialchars($p['nombre']) ?>"
                                            data-slug="<?= htmlspecialchars($p['slug']) ?>"
                                            data-baseurl="<?= htmlspecialchars($p['base_url']) ?>"
                                            data-authep="<?= htmlspecialchars($p['auth_endpoint'] ?? '/ping') ?>"
                                            data-orderep="<?= htmlspecialchars($p['order_endpoint'] ?? '/orders') ?>"
                                            data-payloadformat="<?= htmlspecialchars($p['payload_format'] ?? 'json') ?>"
                                            data-authmethod="<?= htmlspecialchars($p['auth_method'] ?? 'api_key') ?>"
                                            data-username="<?= htmlspecialchars($isShipEdge ? '' : ($pCreds['userName'] ?? '')) ?>"
                                            data-webhooksecret="<?= htmlspecialchars($isShipEdge ? (!empty($pCreds['webhook_secret']) ? '********' : '') : ($pCreds['webhook_secret'] ?? '')) ?>"
                                            data-defaultconfig="<?= htmlspecialchars($p['default_config'] ?? '{}') ?>"
                                            data-shipedgeconfig="<?= htmlspecialchars(json_encode($safeShipEdgeConfig, JSON_UNESCAPED_UNICODE)) ?>"
                                            style="border-radius:8px;width:34px;height:34px;display:flex;align-items:center;justify-content:center;">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-test btn-test-provider" title="Probar Conexión"
                                            data-id="<?= $p['id'] ?>"
                                            data-baseurl="<?= htmlspecialchars($p['base_url']) ?>"
                                            data-authep="<?= htmlspecialchars($p['auth_endpoint'] ?? '/ping') ?>"
                                            data-slug="<?= htmlspecialchars($p['slug']) ?>"
                                            style="border-radius:8px;width:34px;height:34px;display:flex;align-items:center;justify-content:center;">
                                        <i class="bi bi-plug"></i>
                                    </button>
                                    <button class="btn btn-sm btn-toggle-provider <?= $p['activo'] ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                                            title="<?= $p['activo'] ? 'Desactivar' : 'Activar' ?>"
                                            data-id="<?= $p['id'] ?>"
                                            data-activo="<?= $p['activo'] ? 0 : 1 ?>"
                                            style="border-radius:8px;width:34px;height:34px;display:flex;align-items:center;justify-content:center;">
                                        <i class="bi bi-<?= $p['activo'] ? 'pause-circle' : 'play-circle' ?>"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Crear/Editar Proveedor -->
<div class="modal fade" id="modalProvider" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border:none;border-radius:16px;">
            <div class="modal-header" style="background:linear-gradient(135deg,#0B4EA2,#061C4C);color:#fff;border-radius:16px 16px 0 0;">
                <h5 class="modal-title" id="modalProviderTitle"><i class="bi bi-building me-2"></i>Nuevo Proveedor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" id="providerId" value="">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Nombre</label>
                        <input type="text" class="form-control" id="provNombre" placeholder="LogisPro México">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Slug / Driver <small class="text-muted">(tipo de integración)</small></label>
                        <input type="text" class="form-control" id="provSlug" placeholder="logispro" list="slugSuggestions" oninput="onSlugChange()" onchange="onSlugChange()">
                        <datalist id="slugSuggestions">
                            <option value="shipedge">ShipEdge OMS v41</option>
                            <option value="c807">C807 Xpress</option>
                            <option value="logispro">LogisPro (Ecuador, México, etc.)</option>
                            <option value="hlexpress">HL Express</option>
                            <option value="caex">CAEX</option>
                            <option value="dynamic">Dinámico / Genérico</option>
                        </datalist>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">URL Base de la API</label>
                        <input type="url" class="form-control" id="provBaseUrl" placeholder="https://mlacostarica.shipedge.com/apirest/v41/oms">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Endpoint de Auth / Ping</label>
                        <input type="text" class="form-control" id="provAuthEp" value="/api/AccountApi">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Endpoint de Órdenes</label>
                        <input type="text" class="form-control" id="provOrderEp" value="/api/Orders/OrderAndOrderDetail">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Formato de Payload</label>
                        <select class="form-select" id="provPayloadFormat" onchange="toggleSoapSection()">
                            <option value="json" selected>JSON</option>
                            <option value="xml">XML</option>
                            <option value="soap">SOAP/XML</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Método de Auth</label>
                        <select class="form-select" id="provAuthMethod" onchange="onAuthMethodChange()">
                            <option value="bearer_jwt" selected>Bearer JWT</option>
                            <option value="api_key">API Key / Headers (ShipEdge AccountID+Key)</option>
                            <option value="basic">Basic Auth</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold" id="lblProvUserName">Usuario API / AccountID</label>
                        <input type="text" class="form-control" id="provUserName" placeholder="usuario.api o AccountID">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold" id="lblProvPassword">Contraseña API / Key</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="provPassword" placeholder="••••••••">
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePass()">
                                <i class="bi bi-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold text-warning"><i class="bi bi-key-fill me-1"></i>Webhook Secret</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="provWebhookSecret" placeholder="(Se genera auto)">
                            <button class="btn btn-outline-secondary" type="button" onclick="generateWebhookSecret()" title="Generar Nuevo Token">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </div>
                        <input type="hidden" id="provExistingWebhookSecret" value="">
                    </div>

                    <!-- ── Sección ShipEdge OMS v41 (solo visible cuando slug = shipedge) ── -->
                    <div class="col-12" id="shipedgeSection" style="display:none;">
                        <div class="p-3 rounded-3" style="background:#f0fdf4;border:1px dashed #16a34a;">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-truck" style="color:#16a34a;font-size:1.2rem;"></i>
                                    <strong style="color:#14532d;">Parámetros de Cuenta ShipEdge OMS (API v41)</strong>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-success" id="btnReconcileShipEdge" onclick="reconcileShipEdgeOrders()">
                                    <i class="bi bi-arrow-repeat me-1"></i>Conciliar Órdenes Pendientes
                                </button>
                            </div>
                            <div class="alert alert-light border small py-2 mb-3">
                                <i class="bi bi-shield-lock me-1 text-success"></i>
                                Autenticación por encabezados <code>AccountID</code> (campo <strong>AccountID</strong> arriba) y <code>Key</code> (campo <strong>Key</strong> arriba).
                                El sistema normaliza automáticamente <code>/apirest/v41/oms</code> y bloquea el dominio <code>mlamia.shipedge.com</code>.
                            </div>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small">Método de Envío (<code>shipping_method</code>) <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm" id="shipedgeShippingMethod" placeholder="Ej: STD_CR (Confirmar con ShipEdge)">
                                    <div class="form-text" style="font-size:.72rem;">Obligatorio en <code>POST /orders</code>. Se puede sobrescribir por regla/cliente.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small">Transportista (<code>carrier_name</code>) <small class="text-muted">(opcional)</small></label>
                                    <input type="text" class="form-control form-control-sm" id="shipedgeCarrierName" placeholder="Ej: Correos CR / Mensajería">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small">Tienda / Canal ShipEdge <small class="text-muted">(opcional)</small></label>
                                    <input type="text" class="form-control form-control-sm" id="shipedgeStoreChannel" placeholder="Ej: MAIN_STORE">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small">País de Destino Predeterminado <small class="text-muted">(opcional)</small></label>
                                    <input type="text" class="form-control form-control-sm" id="shipedgeDefaultCountry" placeholder="Ej: CR">
                                    <div class="form-text" style="font-size:.72rem;">Solo se usa si el pedido no trae país asociado.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small">Moneda Confirmada (<code>sold_price</code>)</label>
                                    <input type="text" class="form-control form-control-sm" id="shipedgeCurrencyCode" placeholder="Ej: USD o CRC" maxlength="5">
                                </div>
                                <div class="col-md-4 d-flex align-items-center">
                                    <div class="form-check form-switch mt-3">
                                        <input class="form-check-input" type="checkbox" id="shipedgeEnableSoldPrice" value="1">
                                        <label class="form-check-label fw-semibold small" for="shipedgeEnableSoldPrice">
                                            Enviar <code>items[].sold_price</code> tras validar moneda
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div id="reconcileResultShipEdge" class="mt-2 small text-muted"></div>
                        </div>
                    </div>

                    <!-- ── Sección C807 Xpress (solo visible cuando slug = c807) ── -->
                    <div class="col-12" id="c807Section" style="display:none;">
                        <div class="p-3 rounded-3" style="background:#eef7ff;border:1px dashed #0B4EA2;">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-box-seam" style="color:#0B4EA2;font-size:1.2rem;"></i>
                                    <strong style="color:#061C4C;">Parámetros Operativos C807 Xpress</strong>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="btnSyncC807Geo" onclick="syncC807Catalogs()">
                                    <i class="bi bi-cloud-arrow-down me-1"></i>Sincronizar Catálogos C807
                                </button>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold small">Tipo Entrega</label>
                                    <select class="form-select form-select-sm" id="c807TipoEntrega">
                                        <option value="NRML" selected>NRML (Normal)</option>
                                        <option value="PLUS">PLUS (Entrega Plus)</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold small">Tipo Servicio</label>
                                    <select class="form-select form-select-sm" id="c807TipoServicio">
                                        <option value="AUTO" selected>Dinámico (SER / CCE)</option>
                                        <option value="SER">SER (Regular)</option>
                                        <option value="CCE">CCE (Cobro contra entrega)</option>
                                        <option value="SEC">SEC (Envío por cobrar)</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold small">Unidad de Medida</label>
                                    <select class="form-select form-select-sm" id="c807UnidadMedida">
                                        <option value="LB" selected>LB (Libras)</option>
                                        <option value="KG">KG (Kilogramos)</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold small">Sede <small class="text-muted">(opcional)</small></label>
                                    <input type="number" class="form-control form-control-sm" id="c807Sede" placeholder="1">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small">Política de Recolecta</label>
                                    <select class="form-select form-select-sm" id="c807PoliticaRecolecta">
                                        <option value="siguiente_dia_habil" selected>Siguiente día hábil (09:00 AM)</option>
                                        <option value="hoy_actual">Mismo día (+2 horas)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small">Instrucciones de Recolección</label>
                                    <input type="text" class="form-control form-control-sm" id="c807RecolectaComentario" placeholder="Recolectar en bodega">
                                </div>
                            </div>
                            <div id="syncResultC807" class="mt-2 small text-muted"></div>
                        </div>
                    </div>

                    <!-- ── Sección SOAP (solo visible cuando payload_format = soap) ── -->
                    <div class="col-12" id="soapSection" style="display:none;">
                        <div class="p-3 rounded-3" style="background:#f0f4ff;border:1px dashed #7c3aed;">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <i class="bi bi-code-slash text-purple" style="color:#7c3aed;"></i>
                                <strong style="color:#4c1d95;">Configuración SOAP</strong>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small">SOAP Action <small class="text-muted">(header SOAPAction)</small></label>
                                    <input type="text" class="form-control form-control-sm" id="soapAction"
                                           placeholder="http://ejemplo.com/ServiceBus/GenerarGuia">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small">Namespace del Body <small class="text-muted">(xmlns)</small></label>
                                    <input type="text" class="form-control form-control-sm" id="soapNamespace"
                                           placeholder="http://ejemplo.com/ServiceBus">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small">Tag Raíz del Método</label>
                                    <input type="text" class="form-control form-control-sm" id="soapEnvelopeTag"
                                           placeholder="GenerarGuia">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small">Tag de Ítem Repetido</label>
                                    <input type="text" class="form-control form-control-sm" id="soapItemTag"
                                           placeholder="Pieza">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small">Tags ID en Respuesta</label>
                                    <input type="text" class="form-control form-control-sm" id="soapResponseIdTags"
                                           placeholder="GuiaID,CodigoGuia,OrderId">
                                    <div class="form-text">Separados por coma. El primero que se encuentre se usa.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small">Tag de Auth en Body</label>
                                    <input type="text" class="form-control form-control-sm" id="soapAuthTag"
                                           placeholder="Autenticacion">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small">Sub-tag Login</label>
                                    <input type="text" class="form-control form-control-sm" id="soapAuthLoginTag"
                                           placeholder="Login">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold small">Sub-tag Password</label>
                                    <input type="text" class="form-control form-control-sm" id="soapAuthPassTag"
                                           placeholder="Password">
                                </div>
                                <div class="col-12">
                                    <div class="form-check form-switch mt-1">
                                        <input class="form-check-input" type="checkbox" id="soapAuthInBody" value="1">
                                        <label class="form-check-label fw-semibold small" for="soapAuthInBody">
                                            Credenciales dentro del body XML
                                            <small class="text-muted ms-1">(patrón CAEX — sin Authorization header)</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Test Connection -->
                <div class="mt-3">
                    <button class="btn btn-test w-100" id="btnTestModal" onclick="testConnection()">
                        <i class="bi bi-plug me-2"></i>Probar Conexión
                    </button>
                    <div id="testResultModal" class="test-result"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnSaveProvider" onclick="saveProvider()">
                    <i class="bi bi-check-lg me-1"></i>Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<?php include("vista/includes/footer.php") ?>

<script>
const BASE = '<?= RUTA_URL ?>';

function togglePass() {
    const inp = document.getElementById('provPassword');
    const ico = document.getElementById('eyeIcon');
    if (inp.type === 'password') { inp.type = 'text'; ico.className = 'bi bi-eye-slash'; }
    else { inp.type = 'password'; ico.className = 'bi bi-eye'; }
}

function generateWebhookSecret() {
    const array = new Uint8Array(16);
    crypto.getRandomValues(array);
    const hex = Array.from(array).map(b => b.toString(16).padStart(2, '0')).join('');
    document.getElementById('provWebhookSecret').value = hex;
}

function resolveEffectiveSlug() {
    let rawSlug = document.getElementById('provSlug').value.toLowerCase().trim();
    const authMethod = document.getElementById('provAuthMethod').value;
    const baseUrl = document.getElementById('provBaseUrl').value.toLowerCase().trim();
    if (!rawSlug && (authMethod === 'api_key' || baseUrl.includes('shipedge.com'))) {
        rawSlug = 'shipedge';
        document.getElementById('provSlug').value = 'shipedge';
    }
    return rawSlug || 'logispro';
}

function onAuthMethodChange() {
    const authMethod = document.getElementById('provAuthMethod').value;
    const slugInput = document.getElementById('provSlug');
    const nombreInput = document.getElementById('provNombre');
    if (authMethod === 'api_key' && (!slugInput.value.trim() || slugInput.value.trim().toLowerCase() === 'logispro')) {
        slugInput.value = 'shipedge';
        if (!nombreInput.value.trim()) {
            nombreInput.value = 'ShipEdge Costa Rica';
        }
        onSlugChange();
    }
}

function testConnection() {
    const btn = document.getElementById('btnTestModal');
    const res = document.getElementById('testResultModal');
    const slug = resolveEffectiveSlug();
    if (slug === 'shipedge') {
        onSlugChange();
    }
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat spin-icon me-2"></i>Conectando...';
    res.style.display = 'none';

    fetch(BASE + 'ajax/forwarding_providers.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({
            action: 'test',
            id: document.getElementById('providerId').value || undefined,
            slug: slug,
            base_url: document.getElementById('provBaseUrl').value,
            auth_endpoint: document.getElementById('provAuthEp').value,
            auth_method: document.getElementById('provAuthMethod').value,
            userName: document.getElementById('provUserName').value,
            password: document.getElementById('provPassword').value,
        })
    })
    .then(r => r.json())
    .then(data => {
        res.style.display = 'block';
        if (data.success) {
            res.className = 'test-result success';
            let extra = '';
            if (data.customersId !== null && data.customersId !== undefined) {
                extra += `<div class="mt-2"><strong>CustomersId:</strong> <span class="badge bg-primary">${data.customersId}</span></div>`;
            }
            if (data.api_version) {
                extra += `<div class="mt-1"><strong>API Version:</strong> <span class="badge bg-success">${data.api_version}</span></div>`;
            }
            if (data.token_preview) {
                extra += `<div class="mt-1"><strong>Token:</strong> <code>${data.token_preview}</code></div>`;
            }
            res.innerHTML = `<i class="bi bi-check-circle-fill text-success me-2"></i><strong>${data.message || 'Conexión exitosa'}</strong>${extra}`;
        } else {
            res.className = 'test-result error';
            res.innerHTML = `<i class="bi bi-x-circle-fill text-danger me-2"></i><strong>Error:</strong> ${data.message}`;
        }
    })
    .catch(err => {
        res.style.display = 'block';
        res.className = 'test-result error';
        res.innerHTML = `<i class="bi bi-x-circle-fill text-danger me-2"></i>Error de red: ${err.message}`;
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-plug me-2"></i>Probar Conexión';
    });
}

function onSlugChange() {
    const slug = document.getElementById('provSlug').value.toLowerCase().trim();
    const authEp = document.getElementById('provAuthEp');
    const orderEp = document.getElementById('provOrderEp');
    const authM = document.getElementById('provAuthMethod');
    const fmt = document.getElementById('provPayloadFormat');
    const baseU = document.getElementById('provBaseUrl');
    const lblUser = document.getElementById('lblProvUserName');
    const lblPass = document.getElementById('lblProvPassword');

    if (slug === 'c807') {
        if (!authEp.value || authEp.value === '/api/AccountApi') authEp.value = '/admin.php/sesion/get_token';
        if (!orderEp.value || orderEp.value === '/api/Orders/OrderAndOrderDetail') orderEp.value = '/guia.php/api/set_registro';
        authM.value = 'basic';
        fmt.value = 'json';
        if (!baseU.value) baseU.value = 'https://qaapp.c807.com';
        if (lblUser) lblUser.textContent = 'Usuario API';
        if (lblPass) lblPass.textContent = 'Contraseña API';
    } else if (slug === 'shipedge') {
        if (!authEp.value || authEp.value === '/api/AccountApi') authEp.value = '/ping';
        if (!orderEp.value || orderEp.value === '/api/Orders/OrderAndOrderDetail') orderEp.value = '/orders';
        authM.value = 'api_key';
        fmt.value = 'json';
        if (!baseU.value) baseU.value = 'https://mlacostarica.shipedge.com/apirest/v41/oms';
        if (lblUser) lblUser.textContent = 'AccountID (Encabezado ShipEdge)';
        if (lblPass) lblPass.textContent = 'Key / API Key (Encabezado ShipEdge)';
    } else {
        if (lblUser) lblUser.textContent = 'Usuario API';
        if (lblPass) lblPass.textContent = 'Contraseña API';
    }
    toggleSoapSection();
    toggleC807Section();
    toggleShipEdgeSection();
}

function toggleSoapSection() {
    const fmt = document.getElementById('provPayloadFormat').value;
    document.getElementById('soapSection').style.display = (fmt === 'soap') ? 'block' : 'none';
}

function toggleC807Section() {
    const slug = document.getElementById('provSlug').value.toLowerCase().trim();
    const el = document.getElementById('c807Section');
    if (el) el.style.display = (slug === 'c807') ? 'block' : 'none';
}

function toggleShipEdgeSection() {
    const slug = document.getElementById('provSlug').value.toLowerCase().trim();
    const el = document.getElementById('shipedgeSection');
    if (el) el.style.display = (slug === 'shipedge') ? 'block' : 'none';
}

function reconcileShipEdgeOrders() {
    const btn = document.getElementById('btnReconcileShipEdge');
    const res = document.getElementById('reconcileResultShipEdge');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat spin-icon me-1"></i>Conciliando...';
    res.innerHTML = '<span class="text-success"><i class="bi bi-hourglass-split me-1"></i>Consultando órdenes activas en ShipEdge v41...</span>';

    fetch(BASE + 'ajax/forwarding_providers.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({
            action: 'reconcile_shipedge',
            id: document.getElementById('providerId').value || undefined,
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            res.innerHTML = `<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>${data.message}</span>`;
        } else {
            res.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>${data.message}</span>`;
        }
    })
    .catch(err => {
        res.innerHTML = `<span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>Error: ${err.message}</span>`;
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>Conciliar Órdenes Pendientes';
    });
}

function syncC807Catalogs() {
    const btn = document.getElementById('btnSyncC807Geo');
    const res = document.getElementById('syncResultC807');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat spin-icon me-1"></i>Sincronizando...';
    res.innerHTML = '<span class="text-primary"><i class="bi bi-hourglass-split me-1"></i>Consultando catálogos de C807...</span>';

    fetch(BASE + 'ajax/forwarding_providers.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({
            action: 'sync_c807_catalogs',
            id: document.getElementById('providerId').value || undefined,
            base_url: document.getElementById('provBaseUrl').value,
            auth_endpoint: document.getElementById('provAuthEp').value,
            userName: document.getElementById('provUserName').value,
            password: document.getElementById('provPassword').value,
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            res.innerHTML = `<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>${data.message} (${data.departamentos_sincronizados} deptos, ${data.municipios_sincronizados} municipios)</span>`;
        } else {
            res.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>${data.message}</span>`;
        }
    })
    .catch(err => {
        res.innerHTML = `<span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>Error de red: ${err.message}</span>`;
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-cloud-arrow-down me-1"></i>Sincronizar Catálogos C807';
    });
}

function saveProvider() {
    const id     = document.getElementById('providerId').value;
    const action = id ? 'actualizar' : 'crear';
    const fmt    = document.getElementById('provPayloadFormat').value;
    const slug   = resolveEffectiveSlug();

    // Construir soap_config solo si aplica
    let soapConfig = null;
    if (fmt === 'soap') {
        soapConfig = {
            soap_action:          document.getElementById('soapAction').value,
            soap_namespace:       document.getElementById('soapNamespace').value,
            soap_envelope_tag:    document.getElementById('soapEnvelopeTag').value,
            soap_item_tag:        document.getElementById('soapItemTag').value,
            soap_response_id_tags:document.getElementById('soapResponseIdTags').value,
            soap_auth_tag:        document.getElementById('soapAuthTag').value,
            soap_auth_login_tag:  document.getElementById('soapAuthLoginTag').value,
            soap_auth_pass_tag:   document.getElementById('soapAuthPassTag').value,
            soap_auth_in_body:    document.getElementById('soapAuthInBody').checked ? 1 : 0,
        };
    }

    // Construir c807_config si aplica
    let c807Config = null;
    if (slug === 'c807') {
        c807Config = {
            tipo_entrega: document.getElementById('c807TipoEntrega').value,
            tipo_servicio: document.getElementById('c807TipoServicio').value,
            unidad_medida: document.getElementById('c807UnidadMedida').value,
            sede: document.getElementById('c807Sede').value || null,
            politica_recolecta_fecha: document.getElementById('c807PoliticaRecolecta').value,
            recolecta_comentario: document.getElementById('c807RecolectaComentario').value,
        };
    }

    // Construir shipedge_config si aplica
    let shipedgeConfig = null;
    if (slug === 'shipedge') {
        shipedgeConfig = {
            account_id:        document.getElementById('provUserName').value,
            api_key:           document.getElementById('provPassword').value,
            shipping_method:   document.getElementById('shipedgeShippingMethod').value,
            carrier_name:      document.getElementById('shipedgeCarrierName').value,
            store_channel:     document.getElementById('shipedgeStoreChannel').value,
            default_country:   document.getElementById('shipedgeDefaultCountry').value,
            currency_code:     document.getElementById('shipedgeCurrencyCode').value,
            enable_sold_price: document.getElementById('shipedgeEnableSoldPrice').checked ? 1 : 0,
        };
    }

    const body = {
        action,
        id: id || undefined,
        nombre:          document.getElementById('provNombre').value,
        slug:            slug,
        base_url:        document.getElementById('provBaseUrl').value,
        auth_endpoint:   document.getElementById('provAuthEp').value,
        order_endpoint:  document.getElementById('provOrderEp').value,
        payload_format:  fmt,
        auth_method:     document.getElementById('provAuthMethod').value,
        userName:        document.getElementById('provUserName').value,
        password:        document.getElementById('provPassword').value,
        webhook_secret:  document.getElementById('provWebhookSecret').value,
        existing_webhook_secret: document.getElementById('provExistingWebhookSecret').value,
        soap_config:     soapConfig,
        c807_config:     c807Config,
        shipedge_config: shipedgeConfig,
    };

    fetch(BASE + 'ajax/forwarding_providers.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify(body)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire({ icon: 'success', title: action === 'crear' ? '¡Proveedor creado!' : '¡Proveedor actualizado!', timer: 1400, showConfirmButton: false })
                .then(() => location.reload());
        } else {
            Swal.fire({ icon: 'error', title: 'Error al guardar', text: data.message || 'No se pudo guardar el proveedor.' });
        }
    })
    .catch(err => Swal.fire({ icon: 'error', title: 'Error de conexión', text: err.message }));
}

// Edit button handler
document.querySelectorAll('.btn-edit-provider').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('providerId').value  = this.dataset.id;
        document.getElementById('provNombre').value  = this.dataset.nombre;
        document.getElementById('provSlug').value    = this.dataset.slug;
        document.getElementById('provBaseUrl').value = this.dataset.baseurl;
        document.getElementById('provAuthEp').value  = this.dataset.authep;
        document.getElementById('provOrderEp').value = this.dataset.orderep;
        document.getElementById('provPayloadFormat').value = this.dataset.payloadformat || 'json';
        document.getElementById('provAuthMethod').value    = this.dataset.authmethod;
        document.getElementById('provUserName').value      = this.dataset.username || '';
        document.getElementById('provPassword').value      = '';
        document.getElementById('provWebhookSecret').value         = this.dataset.webhooksecret;
        document.getElementById('provExistingWebhookSecret').value = this.dataset.webhooksecret;
        document.getElementById('modalProviderTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Editar Proveedor';
        document.getElementById('testResultModal').style.display = 'none';

        // Cargar configuración SOAP, C807 y ShipEdge si existe
        try {
            const cfg = JSON.parse(this.dataset.defaultconfig || '{}');
            const seCfg = JSON.parse(this.dataset.shipedgeconfig || '{}');
            document.getElementById('soapAction').value         = cfg.soap_action || '';
            document.getElementById('soapNamespace').value      = cfg.soap_namespace || '';
            document.getElementById('soapEnvelopeTag').value    = cfg.soap_envelope_tag || '';
            document.getElementById('soapItemTag').value        = cfg.soap_item_tag || '';
            document.getElementById('soapResponseIdTags').value = cfg.soap_response_id_tags || '';
            document.getElementById('soapAuthTag').value        = cfg.soap_auth_tag || '';
            document.getElementById('soapAuthLoginTag').value   = cfg.soap_auth_login_tag || '';
            document.getElementById('soapAuthPassTag').value    = cfg.soap_auth_pass_tag || '';
            document.getElementById('soapAuthInBody').checked   = !!cfg.soap_auth_in_body;

            // C807
            if (document.getElementById('c807TipoEntrega')) {
                document.getElementById('c807TipoEntrega').value = cfg.tipo_entrega || 'NRML';
                document.getElementById('c807TipoServicio').value = cfg.tipo_servicio || 'AUTO';
                document.getElementById('c807UnidadMedida').value = cfg.unidad_medida || 'LB';
                document.getElementById('c807Sede').value = cfg.sede || '';
                document.getElementById('c807PoliticaRecolecta').value = cfg.politica_recolecta_fecha || 'siguiente_dia_habil';
                document.getElementById('c807RecolectaComentario').value = cfg.recolecta_comentario || '';
            }

            // ShipEdge
            if (document.getElementById('shipedgeShippingMethod')) {
                document.getElementById('shipedgeShippingMethod').value  = seCfg.shipping_method || cfg.shipping_method || '';
                document.getElementById('shipedgeCarrierName').value     = seCfg.carrier_name || cfg.carrier_name || '';
                document.getElementById('shipedgeStoreChannel').value    = seCfg.store_channel || cfg.store_channel || '';
                document.getElementById('shipedgeDefaultCountry').value  = seCfg.default_country || cfg.default_country || '';
                document.getElementById('shipedgeCurrencyCode').value    = seCfg.currency_code || cfg.currency_code || '';
                document.getElementById('shipedgeEnableSoldPrice').checked = !!(seCfg.enable_sold_price || cfg.enable_sold_price);
            }
        } catch(e) { /* sin config */ }

        onSlugChange();
        new bootstrap.Modal(document.getElementById('modalProvider')).show();
    });
});

// Toggle button handler
document.querySelectorAll('.btn-toggle-provider').forEach(btn => {
    btn.addEventListener('click', function() {
        const nuevoActivo = this.dataset.activo;
        const accion = nuevoActivo == 1 ? 'activar' : 'desactivar';
        const id = this.dataset.id;
        Swal.fire({
            title: `¿${accion.charAt(0).toUpperCase()+accion.slice(1)} proveedor?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (!result.isConfirmed) return;
            fetch(BASE + 'ajax/forwarding_providers.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                credentials: 'same-origin',
                body: JSON.stringify({ action: 'toggle', id, activo: nuevoActivo })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) location.reload();
                else Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'No se pudo cambiar el estado.' });
            })
            .catch(err => Swal.fire({ icon: 'error', title: 'Error de conexión', text: err.message }));
        });
    });
});

// Test from table — soporta usar credenciales guardadas (por ID) o ingresarlas en Swal sin exponer las actuales
document.querySelectorAll('.btn-test-provider').forEach(btn => {
    btn.addEventListener('click', function() {
        const self = this;
        const id      = this.dataset.id;
        const slug    = this.dataset.slug;
        const baseUrl = this.dataset.baseurl;
        const authEp  = this.dataset.authep;
        const isShipEdge = (slug === 'shipedge');

        Swal.fire({
            title: isShipEdge ? 'Probar Conexión ShipEdge (POST /ping)' : 'Credenciales para test',
            html:
                `<p class="small text-muted mb-2">${isShipEdge ? 'Deje en blanco para usar las credenciales guardadas de la cuenta, o ingrese AccountID y Key para probar:' : 'Deje en blanco si ya están guardadas o ingréselas:'}</p>` +
                `<input id="swal-user" class="swal2-input" placeholder="${isShipEdge ? 'AccountID (opcional si ya está guardado)' : 'Usuario API'}">` +
                `<input id="swal-pass" type="password" class="swal2-input" placeholder="${isShipEdge ? 'Key / API Key (opcional si ya está guardado)' : 'Contraseña API'}">`,
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Probar Conexión',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                const u = document.getElementById('swal-user').value;
                const p = document.getElementById('swal-pass').value;
                return { userName: u, password: p };
            }
        }).then(result => {
            if (!result.isConfirmed) return;
            self.innerHTML = '<i class="bi bi-arrow-repeat spin-icon"></i>';
            self.disabled = true;

            fetch(BASE + 'ajax/forwarding_providers.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                credentials: 'same-origin',
                body: JSON.stringify({ action: 'test', id, slug, base_url: baseUrl, auth_endpoint: authEp, ...result.value })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    let detailsHtml = `<p>${data.message || 'Conexión exitosa'}</p>`;
                    if (data.customersId) {
                        detailsHtml += `<p><strong>CustomersId:</strong> <span class="badge bg-primary fs-6">${data.customersId}</span></p>`;
                    }
                    if (data.api_version) {
                        detailsHtml += `<p><strong>API Version:</strong> <span class="badge bg-success">${data.api_version}</span></p>`;
                    }
                    if (data.token_preview) {
                        detailsHtml += `<p><strong>Token:</strong> <code>${data.token_preview}</code></p>`;
                    }
                    Swal.fire({
                        icon: 'success',
                        title: 'Conexión exitosa',
                        html: detailsHtml
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Fallo de conexión', text: data.message });
                }
            })
            .catch(err => Swal.fire({ icon: 'error', title: 'Error de red', text: err.message }))
            .finally(() => { self.innerHTML = '<i class="bi bi-plug"></i>'; self.disabled = false; });
        });
    });
});

// Reset modal on open for "new"
document.getElementById('modalProvider').addEventListener('show.bs.modal', function(e) {
    if (e.relatedTarget && !e.relatedTarget.classList.contains('btn-edit-provider')) {
        document.getElementById('providerId').value              = '';
        document.getElementById('provNombre').value              = '';
        document.getElementById('provSlug').value                = '';
        document.getElementById('provBaseUrl').value             = '';
        document.getElementById('provAuthEp').value              = '/api/AccountApi';
        document.getElementById('provOrderEp').value             = '/api/Orders/OrderAndOrderDetail';
        document.getElementById('provPayloadFormat').value       = 'json';
        document.getElementById('provAuthMethod').value          = 'bearer_jwt';
        document.getElementById('provUserName').value            = '';
        document.getElementById('provPassword').value            = '';
        document.getElementById('provWebhookSecret').value       = '';
        document.getElementById('provExistingWebhookSecret').value = '';
        document.getElementById('modalProviderTitle').innerHTML  = '<i class="bi bi-building me-2"></i>Nuevo Proveedor';
        document.getElementById('testResultModal').style.display = 'none';
        // Limpiar campos SOAP y ShipEdge
        ['soapAction','soapNamespace','soapEnvelopeTag','soapItemTag',
         'soapResponseIdTags','soapAuthTag','soapAuthLoginTag','soapAuthPassTag',
         'shipedgeShippingMethod','shipedgeCarrierName','shipedgeStoreChannel',
         'shipedgeDefaultCountry','shipedgeCurrencyCode'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        document.getElementById('soapAuthInBody').checked = false;
        if (document.getElementById('shipedgeEnableSoldPrice')) {
            document.getElementById('shipedgeEnableSoldPrice').checked = false;
        }
        onSlugChange();
    }
});

// DataTable init
$(document).ready(function() {
    if ($('#tblProviders').length) {
        $('#tblProviders').DataTable({
            responsive: true,
            language: { url: '//cdn.jsdelivr.net/npm/datatables.net-plugins@1.13.7/i18n/es-ES.json' },
            pageLength: 25,
            order: [[0, 'desc']]
        });
    }
});
</script>
