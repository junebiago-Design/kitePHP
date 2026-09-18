<?php
/**
 * make:apps
 * ------------------------------------------------------------
 * Deploys scaffolding/project_template/ → apps/
 *
 * Any *.kite file found in the template is copied to apps/
 * with its ".kite" suffix removed, so:
 *
 *     HomeController.php.kite   →   HomeController.php
 *     welcome.php.kite          →   welcome.php
 *     User.php.kite             →   User.php
 *
 * Non-.kite files are copied verbatim.
 * Placeholder substitution runs ONLY on .kite file contents.
 * ------------------------------------------------------------
 */

// ------------------------------------------------------------
// Require project name argument
// ------------------------------------------------------------
if ($argc < 2) {
    echo "Usage: php console make:apps {ProjectName}\n";
    echo "Example: php console make:apps my-blog\n";
    exit(1);
}

$projectName = trim((string) $argv[1]);

if ($projectName === '') {
    echo "Error: Project name cannot be empty.\n";
    exit(1);
}

// ------------------------------------------------------------
// Manual character validation (no regex — bulletproof on Windows)
// ------------------------------------------------------------
$valid = true;
$len   = strlen($projectName);

for ($i = 0; $i < $len; $i++) {
    $c  = $projectName[$i];
    $ok = ($c >= 'a' && $c <= 'z')
       || ($c >= 'A' && $c <= 'Z')
       || ($c >= '0' && $c <= '9')
       || $c === '_'
       || $c === '-';

    if (!$ok) {
        $valid = false;
        break;
    }
}

if (!$valid) {
    echo "Error: Project name may only contain letters, digits, dashes and underscores.\n";
    echo "Received: '{$projectName}' (hex: " . bin2hex($projectName) . ")\n";
    exit(1);
}

// ------------------------------------------------------------
// Resolve paths
// ------------------------------------------------------------
$rootDir  = dirname(__DIR__);
$appsDir  = $rootDir . '/apps';
$template = $rootDir . '/scaffolding/project_template';

if (!is_dir($template)) {
    echo "Error: template not found at scaffolding/project_template\n";
    exit(1);
}

if (!is_dir($appsDir)) {
    mkdir($appsDir, 0755, true);
}

// ------------------------------------------------------------
// Backup apps/ if not empty
// ------------------------------------------------------------
$existing = array_values(array_filter(
    scandir($appsDir),
    function ($i) {
        return $i !== '.' && $i !== '..';
    }
));

if (!empty($existing)) {
    $logsDir = $rootDir . '/logs';
    if (!is_dir($logsDir)) {
        mkdir($logsDir, 0755, true);
    }

    $backup = $logsDir . '/apps_backup_' . date('Y_m_d_His');
    echo "⚠️  apps/ not empty — backing up to logs/" . basename($backup) . "\n";

    if (!rename($appsDir, $backup)) {
        echo "Error: Could not move apps/ to backup location: {$backup}\n";
        exit(1);
    }
    if (!mkdir($appsDir, 0755, true)) {
        echo "Error: Could not recreate apps/ after backup.\n";
        exit(1);
    }
}

$destination = $appsDir;
$namespace   = 'Apps';

// ============================================================
// Template filename mapping
// ============================================================

/**
 * Is this a template file? (ends with ".kite")
 */
function isKiteFile(string $name): bool
{
    return strlen($name) > 5 && substr($name, -5) === '.kite';
}

/**
 * Map a template filename to its deployed filename.
 *
 *   HomeController.php.kite  →  HomeController.php
 *   welcome.php.kite         →  welcome.php
 *   User.php.kite            →  User.php
 *   README.md.kite           →  README.md
 *   plain.txt                →  plain.txt         (unchanged)
 */
function resolveDestinationName(string $sourceName): string
{
    if (isKiteFile($sourceName)) {
        // Strip ONLY the ".kite" suffix, keep the real extension
        return substr($sourceName, 0, -5);
    }
    return $sourceName;
}

// ============================================================
// Recursive copy
//   - .kite files → renamed to their real extension, contents
//                   passed through strtr() for placeholders
//   - other files → copied verbatim, no placeholder substitution
// ============================================================
$copiedDirs   = 0;
$copiedFiles  = 0;
$kiteConverted = 0;
$errors       = [];

/**
 * Recursively deploy a template directory.
 *
 * @param string $src            Source directory (inside scaffolding/project_template)
 * @param string $dst            Destination directory (inside apps/)
 * @param array  $replacements   Placeholder map for filenames + .kite contents
 * @param int    $dirCount       (by ref) directories created
 * @param int    $fileCount      (by ref) files written
 * @param int    $kiteConverted  (by ref) .kite files converted
 * @param array  $errors         (by ref) error messages
 */
function copyTemplate(
    string $src,
    string $dst,
    array  $replacements,
    int   &$dirCount,
    int   &$fileCount,
    int   &$kiteConverted,
    array &$errors
): void {
    if (!is_dir($dst)) {
        if (!mkdir($dst, 0755, true) && !is_dir($dst)) {
            $errors[] = "Failed to create directory: {$dst}";
            return;
        }
    }
    $dirCount++;

    $items = scandir($src);
    if ($items === false) {
        $errors[] = "Failed to read directory: {$src}";
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $srcPath = $src . DIRECTORY_SEPARATOR . $item;

        // --- 1. Resolve destination filename --------------------
        //   a) Apply placeholder substitution to the name itself
        //   b) Strip ".kite" if present, keeping the real extension
        $mappedName = strtr($item, $replacements);
        $dstName    = resolveDestinationName($mappedName);
        $dstPath    = $dst . DIRECTORY_SEPARATOR . $dstName;

        // --- 2. Directories ------------------------------------
        if (is_dir($srcPath)) {
            copyTemplate(
                $srcPath,
                $dstPath,
                $replacements,
                $dirCount,
                $fileCount,
                $kiteConverted,
                $errors
            );
            continue;
        }

        // --- 3. Files ------------------------------------------
        $content = @file_get_contents($srcPath);
        if ($content === false) {
            $errors[] = "Failed to read file: {$srcPath}";
            continue;
        }

        // Placeholder substitution ONLY for .kite files
        if (isKiteFile($item)) {
            $content = strtr($content, $replacements);
            $kiteConverted++;
        }

        if (@file_put_contents($dstPath, $content) === false) {
            $errors[] = "Failed to write file: {$dstPath}";
            continue;
        }

        // Preserve original permissions when possible
        $perms = @fileperms($srcPath);
        @chmod($dstPath, $perms !== false ? ($perms & 0777) : 0644);

        $fileCount++;
    }
}

// ------------------------------------------------------------
// Run it
// ------------------------------------------------------------
copyTemplate(
    $template,
    $destination,
    [
        '{{ projectName }}'  => $projectName,
        '{{ PROJECT_NAME }}' => $projectName,
        '{{ namespace }}'    => $namespace,
        '{{ NAMESPACE }}'    => $namespace,
        '{{ date }}'         => date('Y-m-d'),
    ],
    $copiedDirs,
    $copiedFiles,
    $kiteConverted,
    $errors
);

// ------------------------------------------------------------
// Persist project metadata → apps/config.json
// ------------------------------------------------------------
$metaFile = $appsDir . '/config.json';

$meta = [
    'projectName' => $projectName,
    'namespace'   => $namespace,
    'generatedAt' => date('Y-m-d H:i:s'),
];

$metaJson = json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

if (@file_put_contents($metaFile, $metaJson) === false) {
    echo "⚠️  Warning: could not write {$metaFile}\n";
} else {
    echo "🗂  Metadata saved to apps/config.json\n";
}

// ------------------------------------------------------------
// Report
// ------------------------------------------------------------
echo "\n";
echo "✅ Template deployed to: apps/\n";
echo "📦 Project name:       {$projectName}\n";
echo "📁 Namespace:          {$namespace}\n";
echo "📂 Folders copied:     {$copiedDirs}\n";
echo "📄 Files copied:       {$copiedFiles}\n";
echo "🪁 .kite → .php files: {$kiteConverted}\n";

if (!empty($errors)) {
    echo "\n⚠️  Some items could not be copied:\n";
    foreach ($errors as $e) {
        echo "   - {$e}\n";
    }
    exit(1);
}

echo "\n";
echo "🌐 Visit: http://localhost/{$projectName}/public/index.php\n";