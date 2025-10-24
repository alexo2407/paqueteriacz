<?php
// Script de utilidad para crear un usuario de prueba
// Úsalo solo en entornos de desarrollo/local

require_once __DIR__ . '/../modelo/conexion.php';

$email = 'alexo2407@gmail.com';
$password = 'secret';
$nombre = 'Usuario Test';
$id_rol = 1; // ajusta según roles en tu BD (1 = admin/usuario)

try {
    $db = (new Conexion())->conectar();

    // Comprobar si ya existe
    $check = $db->prepare('SELECT id FROM usuarios WHERE email = :email');
    $check->bindParam(':email', $email);
    $check->execute();

    if ($check->fetch()) {
        echo "El usuario con email {$email} ya existe.\n";
        exit(0);
    }

    // Insertar usuario con contraseña hasheada
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $insert = $db->prepare('INSERT INTO usuarios (nombre, email, contrasena, id_rol) VALUES (:nombre, :email, :contrasena, :id_rol)');
    $insert->bindParam(':nombre', $nombre);
    $insert->bindParam(':email', $email);
    $insert->bindParam(':contrasena', $hash);
    $insert->bindParam(':id_rol', $id_rol, PDO::PARAM_INT);

    if ($insert->execute()) {
        echo "Usuario creado correctamente. ID: " . $db->lastInsertId() . "\n";
        exit(0);
    } else {
        $err = $insert->errorInfo();
        echo "Error al insertar usuario: " . ($err[2] ?? json_encode($err)) . "\n";
        exit(1);
    }

} catch (PDOException $e) {
    echo "Error en la operación: " . $e->getMessage() . "\n";
    exit(1);
}
