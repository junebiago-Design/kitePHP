<?php
// core/Autoloader.php — opt-in only
namespace Core;

final class Autoloader
{
    public static function register(string $coreDir, string $appsDir): void
    {
        spl_autoload_register(function ($class) use ($coreDir) {
            if (str_starts_with($class, 'Core\\')) {
                $rel = substr($class, 5);
                $file = $coreDir . '/' . str_replace('\\', '/', $rel) . '.php';
                if (is_file($file)) require $file;
            }
        });

        spl_autoload_register(function ($class) use ($appsDir) {
            if (str_starts_with($class, 'Apps\\')) {
                $rel = substr($class, 5);
                $file = $appsDir . '/' . str_replace('\\', '/', $rel) . '.php';
                if (is_file($file)) require $file;
            }
        });
    }
}