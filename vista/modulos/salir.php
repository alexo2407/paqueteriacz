<?php
// Captura cualquier salida previa para evitar errores de cabeceras
ob_start();

require_once __DIR__ . '/../../utils/session.php';
logout();

// Redirecciona a index.php
header("Location: index.php");
exit();

// Libera el búfer de salida
ob_end_flush();
?>
