<?php
// Script rápido para crear un usuario en la BD usando la clase Conexion del proyecto.
// USO: php scripts/create_user.php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../modelo/conexion.php';

// Datos del nuevo usuario (modifica si hace falta)
$nombre = 'Alberto Calero';
$email = 'alexo2407@gmail.com';
$passwordPlain = 'secret123';
$id_rol = 1; // Asumimos que 1 = administrador; ajusta si tu esquema usa otro id

try {
    $db = (new Conexion())->conectar();

    // Verificar si ya existe el email
    $stmt = $db->prepare('SELECT id FROM usuarios WHERE email = :email');
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        echo "Ya existe un usuario con ese correo.\n";
        exit(1);
    }

    $hash = password_hash($passwordPlain, PASSWORD_DEFAULT);

    $insert = $db->prepare('INSERT INTO usuarios (nombre, email, contrasena, id_rol) VALUES (:nombre, :email, :contrasena, :id_rol)');
    $insert->bindParam(':nombre', $nombre);
    $insert->bindParam(':email', $email);
    $insert->bindParam(':contrasena', $hash);
    $insert->bindParam(':id_rol', $id_rol, PDO::PARAM_INT);

    if ($insert->execute()) {
        echo "Usuario creado correctamente. ID: " . $db->lastInsertId() . "\n";
    } else {
        echo "Error al crear el usuario.\n";
    }

} catch (Exception $e) {
    echo "Excepción: " . $e->getMessage() . "\n";
    exit(1);
}

?>
