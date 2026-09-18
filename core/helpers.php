<?php

/**
 * Global safe HTML escaper for XSS protection.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Clean URI path by stripping query params and leading/trailing slashes.
 */
function sanitizeUri(string $uri): string
{
    $path = parse_url($uri, PHP_URL_PATH) ?? '/';
    return trim($path, '/');
}

/**
 * Debug utility to dump variables and halt execution.
 */
function dd(...$vars): void
{
    echo '<pre style="background: #1e1e1e; color: #00ff66; padding: 15px; border-radius: 5px; font-family: monospace;">';
    foreach ($vars as $var) {
        var_dump($var);
    }
    echo '</pre>';
    exit(1);
}

/**
 * Helper to generate absolute application URLs.
 */
function url(string $path = ''): string
{
    $baseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
    return $baseUrl . '/' . ltrim($path, '/');
}