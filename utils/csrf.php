<?php
/**
 * CSRF Protection Utility
 * 
 * Provides functions to generate and validate CSRF tokens for form protection.
 * Tokens are stored in session and validated using timing-safe comparison.
 */

/**
 * Generate or retrieve the current CSRF token from session
 * 
 * @return string The CSRF token
 */
function csrf_token(): string
{
    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Generate new token if not exists
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Generate HTML hidden input field with CSRF token
 * 
 * @return string HTML input field
 */
function csrf_field(): string
{
    $token = csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verify CSRF token from request
 * 
 * @param string|null $token The token to verify (usually from $_POST['csrf_token'])
 * @return bool True if token is valid, false otherwise
 */
function verify_csrf_token(?string $token): bool
{
    // Allow disabling CSRF for debugging (NOT for production)
    if (getenv('CSRF_DISABLED') === '1') {
        return true;
    }
    
    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if token exists in session
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    // Check if token was provided
    if ($token === null || $token === '') {
        return false;
    }
    
    // Use timing-safe comparison to prevent timing attacks
    $valid = hash_equals($_SESSION['csrf_token'], $token);
    
    // FIXED: Don't regenerate token after validation
    // The same token should be valid for the entire session
    // This prevents issues with multiple forms or page refreshes
    
    return $valid;
}

/**
 * Require valid CSRF token or terminate with 403 error
 * 
 * @param string|null $token The token to verify (usually from $_POST['csrf_token'])
 * @return void Terminates execution if token is invalid
 */
function require_csrf_token(?string $token = null): void
{
    // Get token from POST if not provided
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? null;
    }
    
    if (!verify_csrf_token($token)) {
        http_response_code(403);
        
        // Check if this is an API request
        $isApi = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false;
        
        if ($isApi) {
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Token CSRF inválido o ausente',
                'code' => 'CSRF_VALIDATION_FAILED'
            ]);
        } else {
            echo '<!DOCTYPE html>
<html>
<head>
    <title>Error 403 - Token CSRF Inválido</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        h1 { color: #d32f2f; }
        p { color: #666; }
        a { color: #1976d2; text-decoration: none; }
    </style>
</head>
<body>
    <h1>403 - Acceso Denegado</h1>
    <p>Token CSRF inválido o ausente. Por favor, recargue la página e intente nuevamente.</p>
    <p><a href="javascript:history.back()">← Volver</a></p>
</body>
</html>';
        }
        exit;
    }
}
