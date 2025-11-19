<?php
// Shim to maintain backward-compatible includes from api/pedidos/* that expect
// utils/autenticacion.php at project root. Forwards to api/utils/autenticacion.php
require_once __DIR__ . '/../api/utils/autenticacion.php';
