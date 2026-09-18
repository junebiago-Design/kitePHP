<?php

// ------------------------------------------------------------
// Usage:
//   php console make:backup                  → backup apps/ with auto timestamp
//   php console make:backup {label}          → backup with custom label
//   php console make:backup --list           → list existing backups
//   php console make:backup --restore        → interactive restore (arrow keys)
//   php console make:backup --restore {name} → restore specific backup
//   php console make:backup --prune {N}      → keep only newest N backups
//   php console make:backup --help
// ------------------------------------------------------------

$args = array_slice($argv, 1);

// ============================================================
// Helpers (wrapped in class — no global redeclare risk)
// ============================================================
final class Backup
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
        if (!is_dir($dir)) return 0;
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

    public static function zipDir(string $src, ZipArchive $zip, string $prefix = ''): int
    {
        $count = 0;
        $items = scandir($src);
        if ($items === false) return 0;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;

            $path  = $src . DIRECTORY_SEPARATOR . $item;
            $local = ($prefix === '' ? $item : $prefix . '/' . $item);

            if (is_dir($path)) {
                $zip->addEmptyDir($local);
                $count += self::zipDir($path, $zip, $local);
            } else {
                $zip->addFile($path, $local);
                $count++;
            }
        }
        return $count;
    }

    public static function copyDir(string $src, string $dst): int
    {
        $count = 0;
        if (!is_dir($dst)) {
            if (!mkdir($dst, 0755, true) && !is_dir($dst)) {
                throw new RuntimeException("Cannot create: {$dst}");
            }
        }
        $items = scandir($src);
        if ($items === false) return 0;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;

            $s = $src . DIRECTORY_SEPARATOR . $item;
            $d = $dst . DIRECTORY_SEPARATOR . $item;

            if (is_dir($s)) {
                $count += self::copyDir($s, $d);
            } else {
                if (!@copy($s, $d)) {
                    throw new RuntimeException("Cannot copy: {$s}");
                }
                $count++;
            }
        }
        return $count;
    }

    /** Recursively delete a folder. */
    public static function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }

    /** Get backup info (name, size, type, mtime). */
    public static function info(string $path): array
    {
        $isZip = is_file($path);
        return [
            'path' => $path,
            'name' => basename($path),
            'size' => $isZip ? (filesize($path) ?: 0) : self::dirSize($path),
            'type' => $isZip ? 'ZIP' : 'DIR',
            'when' => filemtime($path) ?: 0,
        ];
    }
}

// ============================================================
// Arrow-key selector (ANSI, no external deps)
// ============================================================
final class Selector
{
    /**
     * Show an interactive list. Returns the selected index, or -1 on cancel.
     *
     * @param array $items  List of strings to display
     * @param string $title Header line
     */
    public static function choose(array $items, string $title = 'Select'): int
    {
        if (empty($items)) return -1;

        // Non-TTY fallback → numbered input
        if (!self::isTty()) {
            return self::numbered($items, $title);
        }

        // Windows: enable VT100 processing
        self::enableWindowsAnsi();

        $count   = count($items);
        $cursor  = 0;
        $hidden  = false;

        // Save cursor
        fwrite(STDOUT, "\033[?25l"); // hide cursor

        $render = function () use ($items, $count, $cursor, $title) {
            // Move up to overwrite the previous render
            // header (1) + items (count) = count + 1 lines
            fwrite(STDOUT, "\033[" . ($count + 1) . "A");

            // Header
            fwrite(STDOUT, "\r\033[K" . $title . "\n");
            foreach ($items as $i => $label) {
                $pointer = ($i === $cursor) ? '▶ ' : '  ';
                $color   = ($i === $cursor) ? "\033[7m" : '';  // reverse video
                $reset   = ($i === $cursor) ? "\033[0m" : '';
                fwrite(STDOUT, "\r\033[K{$color}{$pointer}{$label}{$reset}\n");
            }
        };

        // Initial render: print header + blanks to reserve space
        fwrite(STDOUT, $title . "\n");
        foreach ($items as $_) fwrite(STDOUT, "\n");
        $render();

        // Read keys
        while (true) {
            $key = self::readKey();

            if ($key === 'UP') {
                $cursor = ($cursor - 1 + $count) % $count;
                $render();
            } elseif ($key === 'DOWN') {
                $cursor = ($cursor + 1) % $count;
                $render();
            } elseif ($key === 'ENTER') {
                fwrite(STDOUT, "\033[?25h"); // show cursor
                return $cursor;
            } elseif ($key === 'QUIT' || $key === 'ESC' || $key === 'CTRL_C') {
                fwrite(STDOUT, "\033[?25h");
                return -1;
            }
        }
    }

    private static function isTty(): bool
    {
        if (function_exists('stream_isatty')) {
            return @stream_isatty(STDIN);
        }
        if (function_exists('posix_isatty')) {
            return @posix_isatty(STDIN);
        }
        return false;
    }

    /** Enable ANSI escape processing on Windows 10+. */
    private static function enableWindowsAnsi(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') return;

        // Try `sapi_windows_vt100_support` (PHP 7.2+)
        if (function_exists('sapi_windows_vt100_support')) {
            @sapi_windows_vt100_support(STDOUT, true);
            @sapi_windows_vt100_support(STDIN, true);
            return;
        }

        // Fallback: `mode con` — best effort
        @shell_exec('mode con: cols=100 lines=50 > NUL 2>&1');
    }

    /**
     * Read one key. Returns UP/DOWN/ENTER/QUIT/ESC/CTRL_C or the raw char.
     */
    private static function readKey(): string
    {
        // Windows supports non-blocking read via stream_set_blocking + fread(1)
        stream_set_blocking(STDIN, true);
        $c = fread(STDIN, 1);

        if ($c === false || $c === '') return 'QUIT';

        // Ctrl+C
        if ($c === "\x03") return 'CTRL_C';

        // Escape sequence
        if ($c === "\x1b") {
            // Try to read 2 more bytes for arrow keys: ESC [ A / B
            stream_set_blocking(STDIN, false);
            $seq = '';
            $t0  = microtime(true);

            // Wait up to 30 ms for the rest of the sequence
            while ((microtime(true) - $t0) < 0.03) {
                $chunk = fread(STDIN, 1);
                if ($chunk !== false && $chunk !== '') {
                    $seq .= $chunk;
                    if (strlen($seq) >= 2) break;
                }
                usleep(1000);
            }
            stream_set_blocking(STDIN, true);

            if ($seq === '[A') return 'UP';
            if ($seq === '[B') return 'DOWN';
            if ($seq === '[C') return 'RIGHT';
            if ($seq === '[D') return 'LEFT';
            return 'ESC';
        }

        // Enter
        if ($c === "\r" || $c === "\n") return 'ENTER';

        // q / Q → quit
        if ($c === 'q' || $c === 'Q') return 'QUIT';

        // Windows arrow keys (when VT100 is off) come as two bytes: 0xE0 0x48/0x50
        if ($c === "\xE0" || $c === "\x00") {
            $c2 = fread(STDIN, 1);
            if ($c2 === 'H') return 'UP';
            if ($c2 === 'P') return 'DOWN';
            if ($c2 === 'K') return 'LEFT';
            if ($c2 === 'M') return 'RIGHT';
            return 'ESC';
        }

        return $c;
    }

    /** Non-interactive fallback: numbered menu. */
    private static function numbered(array $items, string $title): int
    {
        echo "\n{$title}\n";
        echo str_repeat('-', 60) . "\n";
        foreach ($items as $i => $label) {
            printf("  [%2d] %s\n", $i + 1, $label);
        }
        echo "  [ 0] Cancel\n\n";
        echo "Enter number: ";

        $line = trim((string) fgets(STDIN));
        if ($line === '' || $line === '0') return -1;

        $n = (int) $line;
        if ($n < 1 || $n > count($items)) return -1;

        return $n - 1;
    }
}

// ------------------------------------------------------------
// Paths
// ------------------------------------------------------------
$rootDir = dirname(__DIR__);
$appsDir = $rootDir . '/apps';
$logsDir = $rootDir . '/logs';

// ============================================================
// --help
// ============================================================
if (in_array('--help', $args, true) || in_array('-h', $args, true)) {
    echo "Usage:\n";
    echo "  php console make:backup                       Backup apps/ with auto timestamp\n";
    echo "  php console make:backup {label}               Backup with a custom label\n";
    echo "  php console make:backup --list                List existing backups\n";
    echo "  php console make:backup --restore             Restore (arrow-key menu)\n";
    echo "  php console make:backup --restore {name}      Restore a specific backup\n";
    echo "  php console make:backup --prune {N}           Keep only newest N backups\n";
    echo "  php console make:backup --help\n";
    exit(0);
}

// ============================================================
// --list
// ============================================================
if (in_array('--list', $args, true)) {
    if (!is_dir($logsDir)) {
        echo "No logs/ folder yet — nothing to list.\n";
        exit(0);
    }

    $backups = glob($logsDir . '/apps_backup_*') ?: [];
    rsort($backups);

    if (empty($backups)) {
        echo "No backups found in logs/.\n";
        exit(0);
    }

    echo "\n📦 Backups (" . count($backups) . ")\n";
    echo str_repeat('─', 72) . "\n";

    foreach ($backups as $b) {
        $info = Backup::info($b);
        printf(
            "  %-44s %-10s %-4s %s\n",
            $info['name'],
            Backup::humanBytes($info['size']),
            $info['type'],
            $info['when'] ? date('Y-m-d H:i', $info['when']) : 'n/a'
        );
    }
    echo "\n";
    exit(0);
}

// ============================================================
// --prune {N}
// ============================================================
$pruneIndex = array_search('--prune', $args, true);
if ($pruneIndex !== false) {
    $keep = isset($args[$pruneIndex + 1]) ? (int) $args[$pruneIndex + 1] : 5;

    if ($keep < 1) {
        echo "Error: --prune expects a positive integer.\n";
        exit(1);
    }

    if (!is_dir($logsDir)) {
        echo "No logs/ folder — nothing to prune.\n";
        exit(0);
    }

    $backups = glob($logsDir . '/apps_backup_*') ?: [];
    rsort($backups);

    if (count($backups) <= $keep) {
        echo "Nothing to prune (only " . count($backups) . " backup(s), keeping {$keep}).\n";
        exit(0);
    }

    $toDelete = array_slice($backups, $keep);
    echo "🧹 Pruning " . count($toDelete) . " old backup(s), keeping newest {$keep}...\n";

    foreach ($toDelete as $path) {
        if (is_file($path)) {
            @unlink($path);
        } elseif (is_dir($path)) {
            Backup::rrmdir($path);
        }
        echo "   ✗ " . basename($path) . "\n";
    }
    echo "✅ Done.\n\n";
    exit(0);
}

// ============================================================
// --restore
// ============================================================
$restoreIndex = array_search('--restore', $args, true);
if ($restoreIndex !== false) {

    if (!is_dir($logsDir)) {
        echo "Error: logs/ folder not found — no backups to restore.\n";
        exit(1);
    }

    // Collect backups (files + folders)
    $backups = glob($logsDir . '/apps_backup_*') ?: [];
    rsort($backups);

    if (empty($backups)) {
        echo "Error: no backups found in logs/.\n";
        exit(1);
    }

    // Optional explicit name after --restore
    $explicitName = $args[$restoreIndex + 1] ?? null;
    if ($explicitName !== null && $explicitName !== '' && $explicitName[0] === '-') {
        $explicitName = null;
    }

    // -------- Resolve which backup to restore --------
    $chosen = null;

    if ($explicitName !== null) {
        // Try exact match, then partial match
        foreach ($backups as $b) {
            if (basename($b) === $explicitName) { $chosen = $b; break; }
        }
        if ($chosen === null) {
            foreach ($backups as $b) {
                if (strpos(basename($b), $explicitName) !== false) { $chosen = $b; break; }
            }
        }
        if ($chosen === null) {
            echo "Error: no backup matches '{$explicitName}'.\n";
            exit(1);
        }
    } else {
        // Interactive arrow-key selection
        $labels = [];
        foreach ($backups as $b) {
            $info     = Backup::info($b);
            $labels[] = sprintf(
                '%-44s %-10s %-4s %s',
                $info['name'],
                Backup::humanBytes($info['size']),
                $info['type'],
                $info['when'] ? date('Y-m-d H:i', $info['when']) : 'n/a'
            );
        }

        echo "\n";
        $idx = Selector::choose($labels, '📦 Select a backup to restore (↑/↓ + Enter, q to cancel):');

        if ($idx < 0) {
            echo "\nCancelled.\n";
            exit(0);
        }
        $chosen = $backups[$idx];
    }

    $chosenInfo = Backup::info($chosen);

    // -------- Confirm --------
    echo "\n";
    echo "⚠️  You are about to restore:\n";
    echo "     " . $chosenInfo['name'] . "\n";
    echo "     into apps/ — the current apps/ will be replaced.\n\n";

    // Ensure a safety backup first
    if (is_dir($appsDir)) {
        $entries = array_values(array_filter(
            scandir($appsDir),
            function ($i) { return $i !== '.' && $i !== '..'; }
        ));
        if (!empty($entries)) {
            $safetyName = 'apps_backup_' . date('Y_m_d_His') . '_pre-restore';
            echo "🛟 Creating safety backup: {$safetyName}\n";

            if (class_exists('ZipArchive')) {
                $safetyPath = $logsDir . '/' . $safetyName . '.zip';
                $zip = new ZipArchive();
                if ($zip->open($safetyPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                    Backup::zipDir($appsDir, $zip, '');
                    $zip->close();
                    echo "   ✔ saved to logs/" . basename($safetyPath) . "\n";
                } else {
                    echo "   ⚠  could not create zip — skipping safety backup\n";
                }
            } else {
                $safetyPath = $logsDir . '/' . $safetyName;
                try {
                    Backup::copyDir($appsDir, $safetyPath);
                    echo "   ✔ saved to logs/" . basename($safetyPath) . "\n";
                } catch (RuntimeException $e) {
                    echo "   ⚠  " . $e->getMessage() . "\n";
                }
            }
        }
    }

    // -------- Wipe apps/ --------
    if (is_dir($appsDir)) {
        Backup::rrmdir($appsDir);
    }
    if (!mkdir($appsDir, 0755, true) && !is_dir($appsDir)) {
        echo "Error: cannot recreate apps/.\n";
        exit(1);
    }

    // -------- Restore --------
    echo "\n♻️  Restoring from " . $chosenInfo['name'] . "...\n";

    $restoredFiles = 0;

    if (is_file($chosen)) {
        // ZIP restore
        if (!class_exists('ZipArchive')) {
            echo "Error: ZipArchive is not available — cannot unzip {$chosenInfo['name']}.\n";
            exit(1);
        }
        $zip = new ZipArchive();
        if ($zip->open($chosen) !== true) {
            echo "Error: cannot open zip: {$chosen}\n";
            exit(1);
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            // Zip slip protection
            $target = $appsDir . DIRECTORY_SEPARATOR . $name;
            $realBase = realpath($appsDir);
            $realTarget = realpath(dirname($target));
            if ($realBase !== false && $realTarget !== false && strpos($realTarget, $realBase) !== 0) {
                echo "   ⚠  skipping suspicious entry: {$name}\n";
                continue;
            }

            if (substr($name, -1) === '/') {
                @mkdir($target, 0755, true);
                continue;
            }

            $dir = dirname($target);
            if (!is_dir($dir)) @mkdir($dir, 0755, true);

            $fp = $zip->getStream($name);
            if ($fp === false) continue;
            $out = @fopen($target, 'wb');
            if ($out === false) { fclose($fp); continue; }
            while (!feof($fp)) {
                fwrite($out, fread($fp, 8192));
            }
            fclose($out);
            fclose($fp);
            $restoredFiles++;
        }
        $zip->close();

    } elseif (is_dir($chosen)) {
        // Directory restore
        try {
            $restoredFiles = Backup::copyDir($chosen, $appsDir);
        } catch (RuntimeException $e) {
            echo "Error: " . $e->getMessage() . "\n";
            exit(1);
        }
    } else {
        echo "Error: backup path is neither file nor directory: {$chosen}\n";
        exit(1);
    }

    echo "\n✅ Restore complete\n";
    echo str_repeat('─', 52) . "\n";
    echo "  Source:     " . $chosenInfo['name'] . "\n";
    echo "  Files:      {$restoredFiles}\n";
    echo "  Target:     apps/\n\n";
    exit(0);
}

// ============================================================
// Default: create a backup
// ============================================================
if (!is_dir($appsDir)) {
    echo "Error: apps/ folder not found. Run: php console make:apps {ProjectName}\n";
    exit(1);
}

$entries = array_values(array_filter(
    scandir($appsDir),
    function ($i) { return $i !== '.' && $i !== '..'; }
));

if (empty($entries)) {
    echo "Error: apps/ is empty — nothing to back up.\n";
    exit(1);
}

if (!is_dir($logsDir)) {
    if (!mkdir($logsDir, 0755, true) && !is_dir($logsDir)) {
        echo "Error: cannot create logs/ folder.\n";
        exit(1);
    }
}

// Build a backup label
$label = null;
foreach ($args as $a) {
    if ($a !== '' && $a[0] !== '-') {
        $label = preg_replace('/[^A-Za-z0-9_-]/', '_', $a);
        break;
    }
}

$timestamp = date('Y_m_d_His');
$baseName  = 'apps_backup_' . $timestamp;
if ($label !== null && $label !== '') {
    $baseName .= '_' . $label;
}

// Try ZIP first, fall back to folder copy
$usedZip = class_exists('ZipArchive');

if ($usedZip) {
    $zipPath = $logsDir . '/' . $baseName . '.zip';
    $zip = new ZipArchive();

    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        echo "Warning: Could not create zip, falling back to folder copy.\n";
        $usedZip = false;
    } else {
        $fileCount = Backup::zipDir($appsDir, $zip, '');
        $zip->close();

        $size = filesize($zipPath) ?: 0;

        echo "\n✅ Backup created\n";
        echo str_repeat('─', 52) . "\n";
        echo "  File:       logs/" . basename($zipPath) . "\n";
        echo "  Type:       ZIP\n";
        echo "  Files:      {$fileCount}\n";
        echo "  Size:       " . Backup::humanBytes($size) . "\n";
        echo "  Label:      " . ($label ?: '(none)') . "\n";
        echo "  Timestamp:  {$timestamp}\n\n";
        exit(0);
    }
}

// Folder copy fallback
$dirPath = $logsDir . '/' . $baseName;
try {
    $fileCount = Backup::copyDir($appsDir, $dirPath);
} catch (RuntimeException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

$size = Backup::dirSize($dirPath);

echo "\n✅ Backup created\n";
echo str_repeat('─', 52) . "\n";
echo "  Folder:     logs/" . basename($dirPath) . "\n";
echo "  Type:       DIRECTORY (ZipArchive not available)\n";
echo "  Files:      {$fileCount}\n";
echo "  Size:       " . Backup::humanBytes($size) . "\n";
echo "  Label:      " . ($label ?: '(none)') . "\n";
echo "  Timestamp:  {$timestamp}\n\n";