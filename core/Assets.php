<?php

namespace Core;

class Assets
{
    protected string $publicRoot;
    protected string $baseUrl;

    public function __construct(string $publicRoot, string $baseUrl = '')
    {
        $this->publicRoot = rtrim($publicRoot, '/\\');
        $this->baseUrl    = rtrim($baseUrl, '/');
    }

    /**
     * Resolve a single asset URL with cache-busting.
     *
     * Example:
     * asset('css/style.css')
     *
     * Returns:
     * /prj/assets/css/style.css?v=1234567890
     */
    public function asset(string $path): string
    {
        $path = ltrim($path, '/');

        $fullPath = $this->publicRoot . '/assets/' . $path;

        $version = is_file($fullPath)
            ? filemtime($fullPath)
            : time();

        return $this->baseUrl . '/assets/' . $path . '?v=' . $version;
    }

    /**
     * Read a directory of assets and generate HTML tags.
     *
     * Examples:
     *
     * assets('css')
     * assets('js')
     */
    public function assets(string $type): string
    {
        $type = trim($type, '/');

        $dir = $this->publicRoot . '/assets/' . $type;

        if (!is_dir($dir)) {
            return '';
        }

        $files = array_values(
            array_filter(
                scandir($dir),
                function ($file) use ($dir) {
                    return $file !== '.'
                        && $file !== '..'
                        && $file[0] !== '.'
                        && is_file($dir . '/' . $file);
                }
            )
        );

        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        $tags = '';

        foreach ($files as $file) {
            $extension = strtolower(
                pathinfo($file, PATHINFO_EXTENSION)
            );

            $url = htmlspecialchars(
                $this->asset($type . '/' . $file),
                ENT_QUOTES,
                'UTF-8'
            );

            if ($extension === 'css') {
                $tags .= '<link rel="stylesheet" href="' . $url . '">' . PHP_EOL;
            }

            elseif ($extension === 'js') {
                $tags .= '<script src="' . $url . '" defer></script>' . PHP_EOL;
            }
        }

        return $tags;
    }
}