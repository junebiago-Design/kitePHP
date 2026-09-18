<?php

$host = '127.0.0.1';
$port = 8000;

// Parse optional --port flag (e.g., php console serve --port=8080)
foreach ($argv as $arg) {
    if (strpos($arg, '--port=') === 0) {
        $port = (int) substr($arg, 7);
    }
}

$rootDir   = dirname(__DIR__);          // project root
$publicDir = $rootDir . '/public';      // ← document root
$routerFile = $publicDir . '/index.php';// ← front controller inside public
$logDir = $rootDir . '/logs';
$logFile = $logDir . '/server.log';

// 1. Auto-create /logs directory if missing
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

// Ensure the front controller exists
if (!is_file($routerFile)) {
    echo "Error: 'index.php' not found at {$routerFile}\n";
    exit(1);
}

// 2. Port Availability Check
while ($fp = @fsockopen($host, $port, $errno, $errstr, 1)) {
    fclose($fp);
    echo "Port {$port} is in use. Trying port " . ($port + 1) . "...\n";
    $port++;
}

echo "===================================================\n";
echo " KitePHP Development Server Started\n";
echo " Listening on: http://{$host}:{$port}\n";
echo " Document Root: {$publicDir}\n";
echo " Server Logs: {$logFile}\n";
echo " Press Ctrl+C to stop the server.\n";
echo "===================================================\n\n";

// Write session startup header to log file
$timestamp = date('Y-m-d H:i:s');
file_put_contents($logFile, "[{$timestamp}] --- Server started on http://{$host}:{$port} ---\n", FILE_APPEND);

// 3. Build process execution command
// Directs traffic to root index.php front controller
$command = sprintf(
    '%s -S %s:%d -t %s %s 2>&1',
    PHP_BINARY,
    $host,
    $port,
    escapeshellarg($publicDir),
    escapeshellarg($routerFile)
);

// 4. Open process descriptor stream to capture & format CLI logs
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w']
];

$process = proc_open($command, $descriptors, $pipes);

if (is_resource($process)) {
    while (!feof($pipes[1])) {
        $line = fgets($pipes[1]);
        if ($line === false) continue;

        // Log everything to file
        file_put_contents($logFile, $line, FILE_APPEND);

        // Highlight Errors & Standard Activity in Terminal
        if (preg_match('/(Fatal error|Parse error|Uncaught Exception|Warning|Notice|500 Internal Server Error)/i', $line)) {
            // Red output for errors
            echo "\033[31m[ERROR] " . trim($line) . "\033[0m\n";
        } elseif (preg_match('/200 OK/', $line)) {
            // Green output for successful requests
            echo "\033[32m[200] " . trim($line) . "\033[0m\n";
        } elseif (preg_match('/404 Not Found/', $line)) {
            // Yellow output for missing resources
            echo "\033[33m[404] " . trim($line) . "\033[0m\n";
        } else {
            // Standard console line
            echo trim($line) . "\n";
        }
    }

    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
}