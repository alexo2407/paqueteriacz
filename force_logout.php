<?php
/**
 * Script temporal para forzar el cierre de sesión de todos los usuarios
 * 
 * USO:
 * 1. Acceder a: http://localhost/paqueteriacz/force_logout.php
 * 2. Todos los usuarios serán deslogueados
 * 3. Eliminar este archivo después de usarlo
 * 
 * RAZÓN:
 * Después de intercambiar los roles en la BD, las sesiones activas
 * tienen los roles antiguos cargados. Este script fuerza a todos
 * a volver a iniciar sesión para cargar los roles correctos.
 */

require_once __DIR__ . '/utils/session.php';
start_secure_session();

// Destruir la sesión actual
session_destroy();

// Limpiar cookies de sesión
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Mensaje de confirmación
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sesión Cerrada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body text-center p-5">
                        <i class="bi bi-check-circle text-success" style="font-size: 4rem;"></i>
                        <h2 class="mt-3">Sesión Cerrada</h2>
                        <p class="text-muted">Tu sesión ha sido cerrada correctamente.</p>
                        <p class="text-muted">Los cambios en los roles han sido aplicados. Por favor, inicia sesión nuevamente.</p>
                        <hr>
                        <a href="<?= RUTA_URL ?>login" class="btn btn-primary btn-lg">
                            <i class="bi bi-box-arrow-in-right"></i> Iniciar Sesión
                        </a>
                    </div>
                </div>
                
                <div class="alert alert-warning mt-3">
                    <strong>Nota para el administrador:</strong> 
                    Elimina el archivo <code>force_logout.php</code> después de que todos los usuarios hayan cerrado sesión.
                </div>
            </div>
        </div>
    </div>
</body>
</html>
