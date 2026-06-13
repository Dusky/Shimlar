<?php
// Shared bootstrap for all API requests: sessions, CORS, JSON helpers.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Send a JSON response in the shape described by API_SPEC.md and stop.
function json_response($data = [], $updates = [], $errors = [], $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode([
        'success' => empty($errors),
        'data' => $data,
        'errors' => $errors,
        'updates' => $updates,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

// Convenience helper for simple error responses.
function json_error($message, $httpCode = 400, $errors = null) {
    json_response([], [], $errors ?? [$message], $httpCode);
}

// Decode a JSON request body, falling back to $_POST for form submissions.
function request_body() {
    $raw = file_get_contents('php://input');
    if ($raw !== false && strlen($raw) > 0) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return $_POST;
}

// Require an authenticated session. Returns [pid, pname] or sends a 401.
function require_auth() {
    if (empty($_SESSION['player_id']) || empty($_SESSION['player_name'])) {
        json_error('Not logged in', 401);
    }
    return [$_SESSION['player_id'], $_SESSION['player_name']];
}
