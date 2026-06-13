<?php
/**
 * Shimlar API — Middleware (CORS, JSON headers, error handling)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/**
 * Send JSON response
 */
function api_json($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Send success response
 */
function api_success($data = [], $message = '') {
    $response = ['success' => true];
    if ($message) $response['message'] = $message;
    if ($data) $response['data'] = $data;
    api_json($response);
}

/**
 * Send error response
 */
function api_error($message, $code = 400, $details = []) {
    $response = ['success' => false, 'error' => $message];
    if ($details) $response['details'] = $details;
    api_json($response, $code);
}

/**
 * Get JSON input from request body
 */
function api_input() {
    static $input = null;
    if ($input === null) {
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true) ?: [];
        // Also support form data
        if (empty($input) && !empty($_POST)) {
            $input = $_POST;
        }
    }
    return $input;
}

/**
 * Get a specific input field with optional default
 */
function api_get($key, $default = null) {
    $input = api_input();
    return isset($input[$key]) ? $input[$key] : $default;
}
