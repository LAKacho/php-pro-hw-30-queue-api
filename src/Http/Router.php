<?php
declare(strict_types=1);

namespace App\Http;





final class Router
{
     
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = ['method' => $method, 'pattern' => $pattern, 'handler' => $handler];
    }

    


    public function match(string $method, string $path): ?array
    {
        foreach ($this->routes as $route) {
            if (strcasecmp($route['method'], $method) !== 0) {
                continue;
            }
            $regex = '#^' . preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $route['pattern']) . '$#';
            if (preg_match($regex, $path, $matches) === 1) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                return ['handler' => $route['handler'], 'params' => $params];
            }
        }
        return null;
    }

    




    public function allowedMethods(string $path): array
    {
        $methods = [];
        foreach ($this->routes as $route) {
            $regex = '#^' . preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $route['pattern']) . '$#';
            if (preg_match($regex, $path) === 1) {
                $methods[] = strtoupper($route['method']);
            }
        }
        return array_unique($methods);
    }
}
