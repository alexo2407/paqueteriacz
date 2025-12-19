<?php
/**
 * Input Validation and Sanitization Utility
 * 
 * Provides centralized validation and sanitization for user input.
 */

class Validator
{
    /**
     * Validate pedido data
     * 
     * @param array $data Pedido data to validate
     * @return array Array with 'valid' (bool) and 'errors' (array) keys
     */
    public static function validatePedido(array $data): array
    {
        $errors = [];
        
        // Número de orden (required)
        if (empty($data['numero_orden'])) {
            $errors['numero_orden'] = 'El número de orden es requerido';
        }
        
        // Destinatario (required, max 255 chars)
        if (empty($data['destinatario'])) {
            $errors['destinatario'] = 'El destinatario es requerido';
        } elseif (strlen($data['destinatario']) > 255) {
            $errors['destinatario'] = 'El destinatario no puede exceder 255 caracteres';
        }
        
        // Teléfono (required, basic format validation)
        if (empty($data['telefono'])) {
            $errors['telefono'] = 'El teléfono es requerido';
        } elseif (!preg_match('/^[\d\s\+\-\(\)]+$/', $data['telefono'])) {
            $errors['telefono'] = 'El teléfono contiene caracteres inválidos';
        }
        
        // Coordenadas (required if latitud/longitud provided)
        if (isset($data['latitud']) && isset($data['longitud'])) {
            if (!is_numeric($data['latitud']) || !is_numeric($data['longitud'])) {
                $errors['coordenadas'] = 'Las coordenadas deben ser numéricas';
            } elseif ($data['latitud'] < -90 || $data['latitud'] > 90) {
                $errors['coordenadas'] = 'La latitud debe estar entre -90 y 90';
            } elseif ($data['longitud'] < -180 || $data['longitud'] > 180) {
                $errors['coordenadas'] = 'La longitud debe estar entre -180 y 180';
            }
        }
        
        // Precio local (optional, numeric)
        if (isset($data['precio_local']) && $data['precio_local'] !== '' && $data['precio_local'] !== null) {
            if (!is_numeric($data['precio_local']) || $data['precio_local'] < 0) {
                $errors['precio_local'] = 'El precio local debe ser un número positivo';
            }
        }
        
        // Precio USD (optional, numeric)
        if (isset($data['precio_usd']) && $data['precio_usd'] !== '' && $data['precio_usd'] !== null) {
            if (!is_numeric($data['precio_usd']) || $data['precio_usd'] < 0) {
                $errors['precio_usd'] = 'El precio USD debe ser un número positivo';
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Validate usuario data
     * 
     * @param array $data Usuario data to validate
     * @param bool $isUpdate Whether this is an update (password optional)
     * @return array Array with 'valid' (bool) and 'errors' (array) keys
     */
    public static function validateUsuario(array $data, bool $isUpdate = false): array
    {
        $errors = [];
        
        // Nombre (required, max 255 chars)
        if (empty($data['nombre'])) {
            $errors['nombre'] = 'El nombre es requerido';
        } elseif (strlen($data['nombre']) > 255) {
            $errors['nombre'] = 'El nombre no puede exceder 255 caracteres';
        }
        
        // Email (required, valid format)
        if (empty($data['email'])) {
            $errors['email'] = 'El email es requerido';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'El email no tiene un formato válido';
        }
        
        // Password (required for new users, optional for updates)
        if (!$isUpdate) {
            if (empty($data['password'])) {
                $errors['password'] = 'La contraseña es requerida';
            } elseif (strlen($data['password']) < 6) {
                $errors['password'] = 'La contraseña debe tener al menos 6 caracteres';
            }
        } else {
            // If updating and password provided, validate it
            if (!empty($data['password']) && strlen($data['password']) < 6) {
                $errors['password'] = 'La contraseña debe tener al menos 6 caracteres';
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Validate producto data
     * 
     * @param array $data Producto data to validate
     * @return array Array with 'valid' (bool) and 'errors' (array) keys
     */
    public static function validateProducto(array $data): array
    {
        $errors = [];
        
        // Nombre (required, max 255 chars)
        if (empty($data['nombre'])) {
            $errors['nombre'] = 'El nombre del producto es requerido';
        } elseif (strlen($data['nombre']) > 255) {
            $errors['nombre'] = 'El nombre no puede exceder 255 caracteres';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Sanitize string input
     * 
     * @param string $input Input to sanitize
     * @return string Sanitized string
     */
    public static function sanitizeString(string $input): string
    {
        // Remove null bytes
        $input = str_replace("\0", '', $input);
        
        // Trim whitespace
        $input = trim($input);
        
        // Convert special characters to HTML entities
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Sanitize email input
     * 
     * @param string $email Email to sanitize
     * @return string Sanitized email
     */
    public static function sanitizeEmail(string $email): string
    {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }
    
    /**
     * Sanitize numeric input
     * 
     * @param mixed $value Value to sanitize
     * @return float|null Sanitized number or null if invalid
     */
    public static function sanitizeNumeric($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        // Remove whitespace
        $value = trim($value);
        
        // Check if numeric
        if (!is_numeric($value)) {
            return null;
        }
        
        return (float)$value;
    }
    
    /**
     * Sanitize integer input
     * 
     * @param mixed $value Value to sanitize
     * @return int|null Sanitized integer or null if invalid
     */
    public static function sanitizeInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        // Remove whitespace
        $value = trim($value);
        
        // Check if numeric
        if (!is_numeric($value)) {
            return null;
        }
        
        return (int)$value;
    }
}
