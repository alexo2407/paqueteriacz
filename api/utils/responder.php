<?php
/**
 * Helper responder()
 *
 * Standard response envelope for the API. Use this helper to return JSON
 * responses with a consistent shape and HTTP code.
 *
 * @param bool $success  Whether the operation succeeded
 * @param string $message Human-readable message
 * @param mixed $data    Optional payload (array/object/int)
 * @param int $code      HTTP status code (default 200)
 */
function responder($success, $message, $data = null, $code = 200, $extra = [])
{
    http_response_code($code);
    $response = [
        "success" => $success,
        "message" => $message,
        "data" => $data
    ];
    
    if (!empty($extra) && is_array($extra)) {
        $response = array_merge($response, $extra);
    }
    
    echo json_encode($response);
    exit;
}
?>
