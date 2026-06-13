<?php
/**
 * Shimlar API Router
 * All /api/* requests land here via .htaccess rewrite
 */

// Extract the route from the rewritten URL
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Remove /api/ prefix
$route = preg_replace('#^/api/?#', '', $path);
$route = trim($route, '/');

// Store route for route files to access
$_GET['route'] = $route;

// Determine which route file to load
$routeParts = explode('/', $route);
$section = $routeParts[0] ?? '';

$routeFile = __DIR__ . '/routes/' . $section . '.php';

// Combat routes share the 'game' prefix but use a separate file
if ($section === 'game' && in_array($routeParts[1] ?? '', ['fight', 'cast', 'newfight', 'newduel'])) {
    $routeFile = __DIR__ . '/routes/combat.php';
}

// Shop and item routes are under /api/shop/* and /api/item/*
// Both load from shop.php which handles the 'item' prefix too
if ($section === 'item') {
    $routeFile = __DIR__ . '/routes/shop.php';
}

if (file_exists($routeFile)) {
    require_once $routeFile;
} else {
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => "Route not found: /api/$route",
        'available_routes' => [
            'POST /api/auth/login',
            'POST /api/auth/logout',
            'POST /api/auth/create',
            'GET  /api/player/stats',
            'GET  /api/player/inventory',
            'GET  /api/player/profile',
            'GET  /api/player/messages',
            'POST /api/game/action',
            'POST /api/game/move',
            'POST /api/game/fight',
            'POST /api/game/heal',
            'POST /api/game/bank',
            'POST /api/game/fight',
            'POST /api/game/cast',
            'POST /api/game/newfight',
            'POST /api/game/newduel',
            'GET  /api/chat/messages',
            'POST /api/chat/send',
            'POST /api/chat/channel',
            'GET  /api/clan/info',
            'POST /api/clan/create',
            'POST /api/clan/leave',
            'POST /api/clan/donate',
            'POST /api/shop/browse',
            'POST /api/shop/buy',
            'POST /api/shop/sell',
            'POST /api/item/equip',
            'POST /api/item/unequip',
            'POST /api/item/combine',
        ]
    ]);
}
