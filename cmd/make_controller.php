<?php
/**
 * make:controller
 * ------------------------------------------------------------
 * Generates a Controller + View + Route entry.
 *
 * Template stubs are read from (first match wins):
 *   scaffolding/controller.kite.php       ← preferred
 *   scaffolding/controller.php.kite       ← alternate suffix
 *   scaffolding/controller.kite
 *   scaffolding/project_template/apps/Controllers/default/Controller.kite.php
 *   apps/Controllers/default/Controller.php  (legacy fallback)
 *
 * Same search order for the view stub:
 *   scaffolding/view.kite.php
 *   scaffolding/view.php.kite
 *   scaffolding/view.kite
 *   scaffolding/project_template/apps/Views/default/view.kite.php
 *   apps/Views/default/view.php  (legacy fallback)
 *
 * Stub contents are passed through str_replace() with the
 * {{CLASS_NAME}}, {{VIEW_NAME}}, {{PROJECT_NAME}}, {{NAMESPACE}}
 * placeholders. Output ALWAYS has a real ".php" extension.
 * ------------------------------------------------------------
 */

if ($argc < 2) {
    echo "Usage: php console make:controller {ControllerName}\n";
    exit(1);
}

$rawName = $argv[1];

$baseName  = ucfirst(preg_replace('/Controller$/i', '', $rawName));
$className = $baseName . 'Controller';
$viewName  = strtolower($baseName);
$routeKey  = 'GET /' . $viewName;

// -------------------------------------------------------------
// Resolve absolute project root
// -------------------------------------------------------------
$rootDir = dirname(__DIR__);

$controllerDir  = $rootDir . '/apps/Controllers/';
$controllerFile = $controllerDir . $className . '.php';

$viewDir  = $rootDir . '/apps/Views/';
$viewFile = $viewDir . $viewName . '.php';

$routesDir  = $rootDir . '/apps/Routes/';
$routesFile = $routesDir . 'web.php';

// -------------------------------------------------------------
// Template lookup helpers
// -------------------------------------------------------------

/**
 * Find the first existing file from a list of candidate paths.
 * Returns null if none exist.
 */
function findFirstExisting(array $candidates): ?string
{
    foreach ($candidates as $path) {
        if (is_file($path)) {
            return $path;
        }
    }
    return null;
}

/**
 * All reasonable suffix variants for a stub, in priority order.
 *
 *   "view"  →  view.kite.php, view.php.kite, view.kite, view.php
 *
 * @param string $basePath  Absolute path WITHOUT extension
 *                          e.g. "/project/scaffolding/view"
 * @return string[]         Candidate paths, best first
 */
function stubCandidates(string $basePath): array
{
    return [
        $basePath . '.kite.php',   // ← preferred
        $basePath . '.php.kite',   // alternate suffix
        $basePath . '.kite',       // pure custom extension
        $basePath . '.php',        // legacy / real file
    ];
}

// -------------------------------------------------------------
// Locate the controller stub
// -------------------------------------------------------------
$controllerTemplatePath = findFirstExisting(array_merge(
    stubCandidates($rootDir . '/scaffolding/controller'),
    stubCandidates($rootDir . '/scaffolding/project_template/apps/Controllers/default/Controller'),
    stubCandidates($rootDir . '/apps/Controllers/default/Controller')
));

// -------------------------------------------------------------
// Locate the view stub
// -------------------------------------------------------------
$viewTemplatePath = findFirstExisting(array_merge(
    stubCandidates($rootDir . '/scaffolding/view'),
    stubCandidates($rootDir . '/scaffolding/project_template/apps/Views/default/view'),
    stubCandidates($rootDir . '/views/default/view'),
    stubCandidates($rootDir . '/apps/Views/default/view')
));

// -------------------------------------------------------------
// Project name & namespace — read from apps/config.json if present
// -------------------------------------------------------------
$projectName = basename($rootDir);   // fallback
$namespace   = 'Apps\\Controllers';  // fallback

$metaFile = $rootDir . '/apps/config.json';

if (file_exists($metaFile)) {
    $metaRaw = @file_get_contents($metaFile);
    $meta    = $metaRaw !== false ? json_decode($metaRaw, true) : null;

    if (is_array($meta)) {
        if (!empty($meta['projectName'])) {
            $projectName = (string) $meta['projectName'];
        }
        if (!empty($meta['namespace'])) {
            $namespace = rtrim((string) $meta['namespace'], '\\') . '\\Controllers';
        }
    }
}

// -------------------------------------------------------------
// Prevent overwriting existing controller
// -------------------------------------------------------------
if (file_exists($controllerFile)) {
    echo "Error: Controller '{$className}' already exists at {$controllerFile}\n";
    exit(1);
}

// =============================================================
// 1. Create Controller File
// =============================================================
if ($controllerTemplatePath !== null) {
    $template = file_get_contents($controllerTemplatePath);
} else {
    // Inline fallback — used only if no .kite.php stub exists anywhere
    $template  = "<?php\n\n";
    $template .= "namespace Apps\\Controllers;\n\n";
    $template .= "use Core\\Controller;\n\n";
    $template .= "class {{CLASS_NAME}} extends Controller\n{\n";
    $template .= "    public function index(): string\n";
    $template .= "    {\n";
    $template .= "        return \$this->view('{{VIEW_NAME}}', [\n";
    $template .= "            'projectName' => '{{PROJECT_NAME}}',\n";
    $template .= "            'namespace'   => '{{NAMESPACE}}',\n";
    $template .= "        ]);\n";
    $template .= "    }\n";
    $template .= "}\n";
}

$content = str_replace(
    ['{{CLASS_NAME}}', '{{VIEW_NAME}}', '{{PROJECT_NAME}}', '{{NAMESPACE}}'],
    [$className,      $viewName,      $projectName,     $namespace],
    $template
);

if (!is_dir($controllerDir)) {
    mkdir($controllerDir, 0777, true);
}

if (file_put_contents($controllerFile, $content) !== false) {
    $src = $controllerTemplatePath !== null
        ? 'from ' . basename($controllerTemplatePath)
        : 'from inline fallback';
    echo "Success: Controller '{$className}' created at apps/Controllers/{$className}.php ({$src})\n";
} else {
    echo "Error: Failed to write controller file to {$controllerFile}\n";
    exit(1);
}

// =============================================================
// 2. Create View File
// =============================================================
if (!is_dir($viewDir)) {
    mkdir($viewDir, 0777, true);
}

if (file_exists($viewFile)) {
    echo "Info: View '{$viewName}.php' already exists. Skipping creation.\n";
} else {

    if ($viewTemplatePath !== null) {
        $viewContent = file_get_contents($viewTemplatePath);
    } else {
        // Inline fallback
        $viewContent  = "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n";
        $viewContent .= "    <meta charset=\"UTF-8\">\n";
        $viewContent .= "    <title>{{TITLE}}</title>\n";
        $viewContent .= "    <?= \$this->assets('css'); ?>\n";
        $viewContent .= "</head>\n<body>\n";
        $viewContent .= "    <h1>Welcome to {{VIEW_NAME}} page</h1>\n";
        $viewContent .= "    <?= \$this->assets('js'); ?>\n";
        $viewContent .= "</body>\n</html>\n";
    }

    $displayName = $baseName;

    $replacements = [
        '{{TITLE}}'         => $displayName . ' | ' . $projectName,
        '{{VIEW_NAME}}'     => $viewName,
        '{{CLASS_NAME}}'    => $className,
        '{{DISPLAY_NAME}}'  => $displayName,
        '{{PROJECT_NAME}}'  => $projectName,
        '{{NAMESPACE}}'     => $namespace,
        '{{ projectName }}' => $projectName,
        '{{ namespace }}'   => $namespace,
    ];

    $viewContent = str_replace(
        array_keys($replacements),
        array_values($replacements),
        $viewContent
    );

    // ---- Replace <title> with "{Name} | {Project}" ----
    $openTitle  = '<title>';
    $closeTitle = '</title>';
    $titleStart = strpos($viewContent, $openTitle);
    if ($titleStart !== false) {
        $titleEnd = strpos($viewContent, $closeTitle, $titleStart);
        if ($titleEnd !== false) {
            $newTitle    = '<title>' . $displayName . ' | ' . $projectName . '</title>';
            $viewContent = substr($viewContent, 0, $titleStart)
                         . $newTitle
                         . substr($viewContent, $titleEnd + strlen($closeTitle));
        }
    }

    // ---- Replace the "Welcome <projectName>" <h1> ----
    $h1Open   = '<h1>';
    $h1Close  = '</h1>';
    $h1Needle = 'Welcome <?= htmlspecialchars($projectName) ?>';
    $h1Start  = strpos($viewContent, $h1Open);
    if ($h1Start !== false) {
        $h1End = strpos($viewContent, $h1Close, $h1Start);
        if ($h1End !== false) {
            $inner = substr(
                $viewContent,
                $h1Start + strlen($h1Open),
                $h1End - ($h1Start + strlen($h1Open))
            );

            if (strpos($inner, $h1Needle) !== false) {
                $newH1 = '<h1>Welcome to <?= htmlspecialchars($projectName) ?>'
                       . ' &mdash; ' . $displayName
                       . '</h1>';

                $viewContent = substr($viewContent, 0, $h1Start)
                             . $newH1
                             . substr($viewContent, $h1End + strlen($h1Close));
            }
        }
    }

    // ---- Add a "generated from" marker ----
    $templateSourceName = $viewTemplatePath !== null
        ? basename($viewTemplatePath)
        : 'inline-fallback';

    $marker      = '<!-- View: ' . $className . ' | Generated from ' . $templateSourceName . ' -->' . "\n";
    $viewContent = $marker . $viewContent;

    if (file_put_contents($viewFile, $viewContent) !== false) {
        echo "Success: View '{$viewName}.php' created at apps/Views/{$viewName}.php (from {$templateSourceName})\n";
    } else {
        echo "Warning: Failed to create view file at {$viewFile}\n";
    }
}

// =============================================================
// 3. Inject Route
// =============================================================
if (!is_dir($routesDir)) {
    mkdir($routesDir, 0777, true);
}

if (!file_exists($routesFile)) {
    $initialRoutesContent  = "<?php\n\n";
    $initialRoutesContent .= "\$routes = [\n";
    $initialRoutesContent .= "];\n";
    file_put_contents($routesFile, $initialRoutesContent);
}

$routesContent = file_get_contents($routesFile);
$useStatement  = "use Apps\\Controllers\\{$className};\n";
$newRouteEntry = "    '{$routeKey}' => [{$className}::class, 'index'],\n";

// ---- Insert `use` statement right after `<?php` ----
if (strpos($routesContent, $useStatement) === false) {
    $phpTag = '<?php';
    $tagPos = strpos($routesContent, $phpTag);
    if ($tagPos !== false) {
        $insertAt      = $tagPos + strlen($phpTag);
        $routesContent = substr($routesContent, 0, $insertAt)
                       . "\n\n" . $useStatement
                       . substr($routesContent, $insertAt);
    }
}

// ---- Insert route entry before `];` ----
$arrayOpen  = '$routes = [';
$arrayClose = '];';
$arrayStart = strpos($routesContent, $arrayOpen);

if ($arrayStart !== false) {
    $arrayEnd = strpos($routesContent, $arrayClose, $arrayStart);
    if ($arrayEnd !== false) {
        $routesContent = substr($routesContent, 0, $arrayEnd)
                       . $newRouteEntry
                       . substr($routesContent, $arrayEnd);

        file_put_contents($routesFile, $routesContent);
        echo "Success: Route '{$routeKey}' mapped to {$className}::class in apps/Routes/web.php\n";
    } else {
        echo "Warning: Could not find closing '];' for \$routes array in web.php\n";
    }
} else {
    echo "Warning: \$routes array not found in apps/Routes/web.php\n";
}