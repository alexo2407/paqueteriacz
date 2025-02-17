<?php
// Captura cualquier salida previa para evitar errores de cabeceras
ob_start();

// Verifica si la sesión está iniciada antes de intentar destruirla
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Destruye la sesión si está activa
if (session_status() == PHP_SESSION_ACTIVE) {
    session_destroy();
}

// Redirecciona a index.php
header("Location: index.php");
exit();

// Libera el búfer de salida
ob_end_flush();
?>
