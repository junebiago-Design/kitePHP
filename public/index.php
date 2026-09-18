<?php


// ------------------------------------------------------------
// Dev server static file passthrough
// (php -S localhost:8000 -t public public/index.php)
// ------------------------------------------------------------
if (PHP_SAPI === 'cli-server') {
    $requestedPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $requestedFile = __DIR__ . $requestedPath;
    if ($requestedPath !== '/' && is_file($requestedFile)) {
        error_log("FILE: /public{$requestedPath}", 4);
        return false;
    }
}

// ------------------------------------------------------------
// Paths — public/ lives one level below the project root
// ------------------------------------------------------------
$projectRoot = dirname(__DIR__);   // project-root/
$coreDir     = $projectRoot . '/core';
$appsDir     = $projectRoot . '/apps';

// ------------------------------------------------------------
// Autoload Core\*  →  core/
// ------------------------------------------------------------
spl_autoload_register(function ($class) use ($coreDir) {
    $prefix = 'Core\\';
    if (str_starts_with($class, $prefix)) {
        $relative = substr($class, strlen($prefix));
        $file = $coreDir . '/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            error_log("FILE: /core/" . str_replace('\\', '/', $relative) . '.php', 4);
            require $file;
        }
    }
});

// ------------------------------------------------------------
// Autoload Apps\*  →  apps/
// ------------------------------------------------------------
spl_autoload_register(function ($class) use ($appsDir) {
    $prefix = 'Apps\\';
    if (str_starts_with($class, $prefix)) {
        $relative = substr($class, strlen($prefix));
        $file = $appsDir . '/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            error_log("FILE: /apps/" . str_replace('\\', '/', $relative) . '.php', 4);
            require $file;
        }
    }
});

// ------------------------------------------------------------
// Global helpers
// ------------------------------------------------------------
$helpersFile = $coreDir . '/helpers.php';
if (is_file($helpersFile)) {
    error_log("FILE: /core/helpers.php", 4);
    require_once $helpersFile;
}

// ------------------------------------------------------------
// Ensure apps/ exists
// ------------------------------------------------------------
if (!is_dir($appsDir)) {
    http_response_code(500);
    exit("No apps/ folder. Run: php console make:apps {ProjectName}");
}

// ------------------------------------------------------------
// Load routes
//
// apps/Routes/web.php must build and return a Core\Router instance,
// e.g.:
//
//   $router = new \Core\Router();
//   $router->get('/users/edit/{id}', [UserController::class, 'edit']);
//   return $router;
// ------------------------------------------------------------
$routesFile = $appsDir . '/Routes/web.php';
if (!is_file($routesFile)) {
    http_response_code(500);
    exit("Routes file missing: {$routesFile}");
}
error_log("FILE: /apps/Routes/web.php", 4);

/** @var \Core\Router $router */
$router = require $routesFile;

// ------------------------------------------------------------
// Resolve request path relative to the app base
// ------------------------------------------------------------
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Strip the sub-directory the app is installed in (e.g. /prj, /mini-framework)
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$scriptDir = rtrim($scriptDir, '/');
if ($scriptDir !== '' && str_starts_with($path, $scriptDir)) {
    $path = substr($path, strlen($scriptDir));
}
$path = '/' . trim($path, '/');
if ($path === '') $path = '/';

// ------------------------------------------------------------
// Match the route (now supports {param} segments)
// ------------------------------------------------------------
$match = $router->match($method, $path);

if ($match === null) {
    http_response_code(404);
    exit("404 - No route for {$method} {$path}");
}

[$controllerClass, $action, $params] = $match;

error_log(
    "ROUTE: {$method} {$path} => [{$controllerClass}::class, '{$action}'] params=" . implode(',', $params),
    4
);

if (!class_exists($controllerClass)) {
    http_response_code(500);
    exit("Controller not found: {$controllerClass}");
}

// ------------------------------------------------------------
// Base URL (sub-folder aware) → passed to controller ctor
// ------------------------------------------------------------
$baseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');

$controller = new $controllerClass($baseUrl);

if (!method_exists($controller, $action)) {
    http_response_code(500);
    exit("Method not found: {$controllerClass}::{$action}");
}

echo $controller->$action(...$params);