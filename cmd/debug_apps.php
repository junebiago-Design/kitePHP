var_dump([
    'appRoot'   => __DIR__ . '/apps',
    'exists'    => is_dir(__DIR__ . '/apps'),
    'routes'    => __DIR__ . '/apps/Routes/web.php',
    'routesOk'  => file_exists(__DIR__ . '/apps/Routes/web.php'),
]);