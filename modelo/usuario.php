<?php

include_once "conexion.php";


class UsuarioModel {
   

  
    public function verificarCredenciales($email, $password) {

        $db = (new Conexion())->conectar();

        $sql = "SELECT id, nombre, id_rol, contrasena FROM usuarios WHERE email = :email";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':email', $email);
       

        

        // Validar que el usuario exista y que la contraseña sea correcta
        if ( $stmt->execute()) {

            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            return [
                'ID_Usuario' => $user['id'],
                'Usuario' => $user['nombre'],
                'Rol' => $user['id_rol']
            ];
        } else {
            return false;
        }
    }
}
