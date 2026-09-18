<?php

namespace Core;

class Includes
{
    protected string $includeRoot;
    protected array $allowedExtensions;

    /**
     * @param string $includeRoot        Absolute path to the includes folder.
     * @param array  $allowedExtensions  File types this instance may serve.
     *                                   Defaults to php, js, css, html.
     */
    public function __construct(
        string $includeRoot,
        array $allowedExtensions = ['php', 'js', 'css', 'html']
    ) {
        $this->includeRoot      = rtrim($includeRoot, '/\\');
        $this->allowedExtensions = array_map('strtolower', $allowedExtensions);
    }

    /**
     * Resolve a relative include name to its full filesystem path.
     */
    public function resolve(string $file): string
    {
        $file = ltrim($file, '/');

        return $this->includeRoot . '/' . $file;
    }

    /**
     * Get the lowercase extension of a file (php, js, css, html...).
     */
    public function extension(string $file): string
    {
        return strtolower(pathinfo($file, PATHINFO_EXTENSION));
    }

    /**
     * Whether a given path's extension is allowed for this instance.
     */
    public function isAllowed(string $path): bool
    {
        return in_array($this->extension($path), $this->allowedExtensions, true);
    }

    /**
     * Check whether an include exists and is an allowed file type.
     */
    public function exists(string $file): bool
    {
        $fullPath = $this->resolve($file);

        return is_file($fullPath) && $this->isAllowed($fullPath);
    }

    /**
     * Render a single include file.
     *
     * PHP files are require()'d (so they can contain logic/templating).
     * js/css/html (and any other non-php allowed type) are streamed out
     * as raw file contents.
     *
     * Note: when called from here, $this inside a required .php file
     * refers to this Includes instance, not a Controller. If a partial
     * needs $this->asset()/$this->assets(), require it directly from
     * Controller::include() instead (see Controller.php) so that $this
     * resolves to the controller.
     *
     * Example:
     * $this->include('header.php');
     * $this->include('styles.css');
     */
    public function include(string $file): void
    {
        $fullPath = $this->resolve($file);

        if (!is_file($fullPath)) {
            throw new \RuntimeException(
                "Include file not found: '{$fullPath}'"
            );
        }

        if (!$this->isAllowed($fullPath)) {
            throw new \RuntimeException(
                "File type not allowed for include: '{$fullPath}'"
            );
        }

        if ($this->extension($fullPath) === 'php') {
            require $fullPath;
        } else {
            readfile($fullPath);
        }
    }

    /**
     * List all allowed include files in the include root.
     *
     * Example:
     * $this->files();
     */
    public function files(): array
    {
        if (!is_dir($this->includeRoot)) {
            return [];
        }

        return array_values(
            array_filter(
                scandir($this->includeRoot),
                function ($file) {
                    return $file !== '.'
                        && $file !== '..'
                        && $file[0] !== '.'
                        && is_file($this->includeRoot . '/' . $file)
                        && $this->isAllowed($this->includeRoot . '/' . $file);
                }
            )
        );
    }
}