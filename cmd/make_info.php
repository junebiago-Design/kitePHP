<?php

// ------------------------------------------------------------
// Flags
// ------------------------------------------------------------
$flags  = array_slice($argv, 1);
$asJson = in_array('--json', $flags, true);
$only   = null;
foreach ($flags as $f) {
    if (in_array($f, ['--routes', '--controllers', '--views', '--models', '--logs'], true)) {
        $only = substr($f, 2);
    }
}

$rootDir  = dirname(__DIR__);
$appsDir  = $rootDir . '/apps';
$metaFile = $appsDir . '/config.json';
$logsDir  = $rootDir . '/logs';

// ============================================================
// Helpers (wrapped in a class — no collision with PHP built-ins)
// ============================================================
final class Info
{
    public static function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public static function dirSize(string $dir): int
    {
        if (!is_dir($dir)) {
            return 0;
        }
        $size = 0;
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        return $size;
    }

    public static function line(string $label, string $value): void
    {
        printf("  %-16s %s\n", $label . ':', $value);
    }

    public static function section(string $title): void
    {
        echo "\n" . strtoupper($title) . "\n";
        echo str_repeat('─', 52) . "\n";
    }
}

// ------------------------------------------------------------
// Collect data
// ------------------------------------------------------------
$data = [
    'project'     => [],
    'routes'      => [],
    'controllers' => [],
    'views'       => [],
    'models'      => [],
    'backups'     => [],
    'warnings'    => [],
    'stats'       => [],
];

// ---- Project metadata ----
if (!is_dir($appsDir)) {
    $data['warnings'][] = "apps/ folder not found. Run: php console make:apps {ProjectName}";
} elseif (!file_exists($metaFile)) {
    $data['warnings'][] = "apps/config.json not found. Re-run make:apps to regenerate.";
} else {
    $meta = json_decode(@file_get_contents($metaFile), true);
    if (!is_array($meta)) {
        $data['warnings'][] = "apps/config.json is not valid JSON.";
    } else {
        $data['project'] = [
            'name'        => $meta['projectName'] ?? '(unknown)',
            'namespace'   => $meta['namespace']   ?? '(unknown)',
            'generatedAt' => $meta['generatedAt'] ?? '(unknown)',
            'root'        => $rootDir,
            'url'         => 'http://localhost/' . ($meta['projectName'] ?? '') . '/index.php',
        ];
    }
}

// Fallback if config missing but apps/ exists
if (empty($data['project']) && is_dir($appsDir)) {
    $data['project'] = [
        'name'        => basename($rootDir),
        'namespace'   => 'Apps',
        'generatedAt' => '(unknown)',
        'root'        => $rootDir,
        'url'         => 'http://localhost/' . basename($rootDir) . '/index.php',
    ];
}

// ---- Routes ----
$routesFile = $appsDir . '/Routes/web.php';
if (file_exists($routesFile)) {
    $src = file_get_contents($routesFile);

    // use Apps\Controllers\XxxController;
    preg_match_all('/use\s+(Apps\\\\Controllers\\\\[A-Za-z0-9_\\\\]+)\s*;/', $src, $uses);
    $useMap = [];
    foreach ($uses[1] as $fqcn) {
        $short = substr($fqcn, strrpos($fqcn, '\\') + 1);
        $useMap[$short] = $fqcn;
    }

    // 'GET /path' => [Controller::class, 'method'],
    preg_match_all(
        "/['\"]([A-Z]+)\s+([^'\"]+)['\"]\s*=>\s*\[\s*([A-Za-z0-9_]+)::class\s*,\s*['\"]([^'\"]+)['\"]\s*\]/",
        $src,
        $routes,
        PREG_SET_ORDER
    );

    foreach ($routes as $r) {
        $controller = $r[3];
        $data['routes'][] = [
            'method'     => $r[1],
            'path'       => $r[2],
            'controller' => $controller,
            'action'     => $r[4],
            'fqcn'       => $useMap[$controller] ?? '(missing use)',
        ];
    }
}

// ---- Controllers ----
$controllerFiles = glob($appsDir . '/Controllers/*.php') ?: [];
foreach ($controllerFiles as $file) {
    if (strpos($file, DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR) !== false) {
        continue;
    }

    $base  = basename($file);
    $src   = @file_get_contents($file);
    $size  = @filesize($file) ?: 0;
    $mtime = @filemtime($file) ?: 0;

    $view = '(unknown)';
    if ($src !== false && preg_match("/->view\s*\(\s*['\"]([^'\"]+)['\"]/", $src, $m)) {
        $view = $m[1];
    }

    $ns = '(unknown)';
    if ($src !== false && preg_match('/namespace\s+([^;]+);/', $src, $m)) {
        $ns = trim($m[1]);
    }

    $data['controllers'][] = [
        'file'  => $base,
        'class' => basename($file, '.php'),
        'ns'    => $ns,
        'view'  => $view,
        'size'  => $size,
        'mtime' => $mtime,
    ];
}

// ---- Views ----
$viewFiles = glob($appsDir . '/Views/*.php') ?: [];
foreach ($viewFiles as $file) {
    if (strpos($file, DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR) !== false) {
        continue;
    }
    $data['views'][] = [
        'file'  => basename($file),
        'size'  => @filesize($file) ?: 0,
        'mtime' => @filemtime($file) ?: 0,
    ];
}

// ---- Models (optional) ----
$modelFiles = glob($appsDir . '/Models/*.php') ?: [];
foreach ($modelFiles as $file) {
    if (strpos($file, DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR) !== false) {
        continue;
    }
    $data['models'][] = [
        'file'  => basename($file),
        'size'  => @filesize($file) ?: 0,
        'mtime' => @filemtime($file) ?: 0,
    ];
}

// ---- Backups ----
if (is_dir($logsDir)) {
    $backups = glob($logsDir . '/apps_backup_*') ?: [];
    rsort($backups);
    foreach (array_slice($backups, 0, 10) as $b) {
        $data['backups'][] = [
            'name' => basename($b),
            'when' => @filemtime($b) ?: 0,
            'size' => Info::dirSize($b),
        ];
    }
}

// ---- Stats ----
$data['stats'] = [
    'appsSize'     => Info::dirSize($appsDir),
    'totalRoutes'  => count($data['routes']),
    'totalCtrl'    => count($data['controllers']),
    'totalViews'   => count($data['views']),
    'totalModels'  => count($data['models']),
    'totalBackups' => count($data['backups']),
];

// ------------------------------------------------------------
// Warnings (health checks)
// ------------------------------------------------------------
foreach ($data['controllers'] as $c) {
    if ($c['view'] !== '(unknown)') {
        $expected = $appsDir . '/Views/' . $c['view'] . '.php';
        if (!file_exists($expected)) {
            $data['warnings'][] = "Controller {$c['file']} → missing view '{$c['view']}.php'";
        }
    }
}

$controllerViews = array_column($data['controllers'], 'view');
foreach ($data['views'] as $v) {
    $viewName = basename($v['file'], '.php');
    if (!in_array($viewName, $controllerViews, true)) {
        $data['warnings'][] = "View {$v['file']} has no matching controller";
    }
}

if (!file_exists($appsDir . '/Controllers/default/Controller.php')) {
    $data['warnings'][] = "Missing template: apps/Controllers/default/Controller.php";
}
if (!file_exists($appsDir . '/Views/default/view.php') &&
    !file_exists($rootDir . '/views/default/view.php')) {
    $data['warnings'][] = "Missing template: apps/Views/default/view.php";
}

// ------------------------------------------------------------
// JSON output mode
// ------------------------------------------------------------
if ($asJson) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}

// ------------------------------------------------------------
// Human-readable output
// ------------------------------------------------------------
$show = function (string $s) use ($only): bool {
    return $only === null || $only === $s;
};

echo "\n";
echo "📋 PROJECT INFO\n";
echo str_repeat('═', 52) . "\n";

if ($show('project')) {
    Info::section('Project');
    Info::line('Name',      $data['project']['name']);
    Info::line('Namespace', $data['project']['namespace']);
    Info::line('Generated', $data['project']['generatedAt']);
    Info::line('Root',      $data['project']['root']);
    Info::line('URL',       $data['project']['url']);
}

if ($show('routes')) {
    Info::section('Routes (' . $data['stats']['totalRoutes'] . ')');
    if (empty($data['routes'])) {
        echo "  (no routes defined)\n";
    } else {
        foreach ($data['routes'] as $r) {
            printf(
                "  %-6s %-22s → %s::%s()\n",
                $r['method'],
                $r['path'],
                $r['controller'],
                $r['action']
            );
        }
    }
}

if ($show('controllers')) {
    Info::section('Controllers (' . $data['stats']['totalCtrl'] . ')');
    if (empty($data['controllers'])) {
        echo "  (none)\n";
    } else {
        foreach ($data['controllers'] as $c) {
            printf(
                "  • %-28s view=%-12s %s\n",
                $c['file'],
                $c['view'],
                Info::humanBytes($c['size'])
            );
        }
    }
}

if ($show('views')) {
    Info::section('Views (' . $data['stats']['totalViews'] . ')');
    if (empty($data['views'])) {
        echo "  (none)\n";
    } else {
        foreach ($data['views'] as $v) {
            printf(
                "  • %-28s %s   (modified %s)\n",
                $v['file'],
                Info::humanBytes($v['size']),
                $v['mtime'] ? date('Y-m-d H:i', $v['mtime']) : 'n/a'
            );
        }
    }
}

if ($show('models') && !empty($data['models'])) {
    Info::section('Models (' . $data['stats']['totalModels'] . ')');
    foreach ($data['models'] as $m) {
        printf("  • %-28s %s\n", $m['file'], Info::humanBytes($m['size']));
    }
}

if ($show('logs')) {
    Info::section('Recent Backups (' . $data['stats']['totalBackups'] . ')');
    if (empty($data['backups'])) {
        echo "  (no backups)\n";
    } else {
        foreach ($data['backups'] as $b) {
            printf(
                "  • %-32s %s   (%s)\n",
                $b['name'],
                Info::humanBytes($b['size']),
                $b['when'] ? date('Y-m-d H:i', $b['when']) : 'n/a'
            );
        }
    }
}

if ($show('project')) {
    Info::section('Disk Usage');
    Info::line('apps/', Info::humanBytes($data['stats']['appsSize']));
}

if ($show('project')) {
    Info::section('Warnings (' . count($data['warnings']) . ')');
    if (empty($data['warnings'])) {
        echo "  ✓ No issues detected.\n";
    } else {
        foreach ($data['warnings'] as $w) {
            echo "  ⚠  {$w}\n";
        }
    }
}

echo "\n";