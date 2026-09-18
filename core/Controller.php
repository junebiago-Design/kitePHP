<?php

namespace Core;

abstract class Controller
{
    protected string $projectRoot;
    protected string $appRoot;
    protected string $publicRoot;
    protected string $includesRoot;
    protected string $baseUrl;
    protected Assets $assetsManager;
    protected Includes $includes;

public function __construct(string $baseUrl = '')
{
    $this->projectRoot  = dirname(__DIR__);
    $this->appRoot      = $this->projectRoot . '/apps';
    $this->publicRoot   = $this->projectRoot . '/public';
    $this->includesRoot = $this->appRoot . '/includes';
    $this->baseUrl      = rtrim($baseUrl, '/');

    $this->assetsManager = new Assets(
        $this->publicRoot,
        $this->baseUrl
    );

    $this->includes = new Includes(
        $this->includesRoot,
        ['php', 'js', 'css', 'html']
    );
}

    
    protected function currentUri(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        if ($this->baseUrl && str_starts_with($path, $this->baseUrl)) {
            $path = substr($path, strlen($this->baseUrl));
        }

        return trim($path, '/');
    }

    /**
     * Resolve a single asset URL with cache-busting.
     * Assets live in: public/assets/{path}
     */
    public function asset(string $path): string
    {
        $path     = ltrim($path, '/');
        $fullPath = $this->publicRoot . '/assets/' . $path;

        $version = is_file($fullPath) ? filemtime($fullPath) : time();
        error_log("FILE: /public/assets/{$path}", 4);
        return $this->baseUrl . '/assets/' . $path . '?v=' . $version;
    }

    /**
     * Read a directory of assets and return HTML tags.
     * Directories: public/assets/css, public/assets/js
     */
    public function assets(string $type): string
    {
        $dir = $this->publicRoot . '/assets/' . $type;
        if (!is_dir($dir)) return '';

        $files = array_values(array_filter(
            scandir($dir),
            fn($f) => is_file("$dir/$f") && $f[0] !== '.'
        ));

        $tags = '';
        foreach ($files as $file) {
            $url = $this->asset("$type/$file");
            $tags .= $type === 'css'
                ? '<link rel="stylesheet" href="' . htmlspecialchars($url) . '">' . "\n"
                : '<script src="' . htmlspecialchars($url) . '" defer></script>' . "\n";
        }
        return $tags;
    }

    /**
     * Include a reusable partial from apps/includes.
     *
     * .php files are require()'d in the controller's own scope, so they
     * can call $this->asset(), $this->assets(), $this->include(), etc.
     * Optional $data is extracted into local scope first.
     *
     * .js / .css / .html (or any other allowed non-php type) files are
     * streamed out as raw file contents — no PHP evaluation, no $data.
     *
     * If no extension is given, '.php' is assumed.
     *
     * Examples:
     * $this->include('header');                 // apps/includes/header.php
     * $this->include('header.php', ['title' => $title]);
     * $this->include('vendor/chart.js');
     * $this->include('print.css');
     */
    protected function include(string $file, array $data = []): void
    {
        $file = ltrim($file, '/');

        if ($this->includes->extension($file) === '') {
            $file .= '.php';
        }

        if (!$this->includes->exists($file)) {
            throw new \RuntimeException(
                "Include file not found: '{$this->includesRoot}/{$file}'"
            );
        }

        $fullPath  = $this->includes->resolve($file);
        $extension = $this->includes->extension($file);

        if ($extension === 'php') {
            extract($data, EXTR_SKIP);

            // Required here (not inside Includes::include()) so that $this
            // resolves to the Controller instance, giving the partial
            // access to $this->asset(), $this->assets(), etc.
            require $fullPath;
            return;
        }

        // Non-PHP includes (js/css/html/...) are output as-is.
        readfile($fullPath);
    }

    /**
     * Check whether a given include exists.
     */
    protected function includeExists(string $file): bool
    {
        if ($this->includes->extension($file) === '') {
            $file .= '.php';
        }

        return $this->includes->exists($file);
    }

    protected function view(string $view, array $data = []): string
    {
        $viewFile = $this->appRoot . '/Views/' . ltrim($view, '/') . '.php';

        if (!is_file($viewFile)) {
            throw new \RuntimeException("View template file not found: '{$viewFile}'");
        }

        error_log("FILE: " . str_replace($this->projectRoot, '', $viewFile), 4);

        $data['currentUri'] = $this->currentUri();
        $data['baseUrl']    = $this->baseUrl;

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewFile;
        return ob_get_clean();
    }
}