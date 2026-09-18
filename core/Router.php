<?php

namespace Core;

class Router
{
    /**
     * @var array<int, array{method: string, path: string, pattern: string, action: array}>
     */
    protected array $routes = [];

    public function get(string $path, array $action): void
    {
        $this->add('GET', $path, $action);
    }

    public function post(string $path, array $action): void
    {
        $this->add('POST', $path, $action);
    }

    public function put(string $path, array $action): void
    {
        $this->add('PUT', $path, $action);
    }

    public function delete(string $path, array $action): void
    {
        $this->add('DELETE', $path, $action);
    }

    /**
     * Register a route.
     *
     * @param array $action [ControllerClass::class, 'method']
     */
    public function add(string $method, string $path, array $action): void
    {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'path'    => $path,
            'pattern' => $this->toPattern($path),
            'action'  => $action,
        ];
    }

    /**
     * Convert a route path like /users/edit/{id} into a regex pattern
     * with named capture groups.
     */
    protected function toPattern(string $path): string
    {
        $path = trim($path, '/');

        // {id} -> (?P<id>[^/]+)
        $pattern = preg_replace(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            '(?P<$1>[^/]+)',
            $path
        );

        return '#^' . $pattern . '$#';
    }

    /**
     * Match a request method + URI against the registered routes.
     *
     * Returns [controllerClass, action, params] on match, or null.
     * $params is an ordered list of the matched path parameters, in the
     * order they appear in the route (e.g. /users/edit/{id} -> [$id]).
     */
    public function match(string $method, string $uri): ?array
    {
        $uri    = trim($uri, '/');
        $method = strtoupper($method);

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $uri, $matches)) {
                $params = array_filter(
                    $matches,
                    fn($key) => !is_int($key),
                    ARRAY_FILTER_USE_KEY
                );

                return [
                    $route['action'][0],
                    $route['action'][1],
                    array_values($params),
                ];
            }
        }

        return null;
    }
}