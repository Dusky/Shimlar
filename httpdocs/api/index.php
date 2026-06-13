<?php
// Router for /api/* — dispatches to routes/<resource>.php based on the
// path after /api/. Works whether reached via .htaccess rewrite (PATH_INFO
// preserved) or by hitting index.php directly, e.g.
//   /api/auth/login            (via .htaccess rewrite)
//   /api/index.php/auth/login  (PATH_INFO, no rewrite needed)
//   /api/index.php?r=auth/login

require_once __DIR__ . '/middleware.php';

$path = $_SERVER['PATH_INFO'] ?? '';
if ($path === '' && isset($_GET['r'])) {
    $path = $_GET['r'];
}

$segments = array_values(array_filter(explode('/', trim($path, '/')), function ($s) {
    return $s !== '';
}));

$resource = $segments[0] ?? '';
$rest = array_slice($segments, 1);

$routeFile = __DIR__ . '/routes/' . basename($resource) . '.php';

if ($resource === '' || !is_file($routeFile)) {
    json_error('Unknown API endpoint', 404);
}

require $routeFile;
