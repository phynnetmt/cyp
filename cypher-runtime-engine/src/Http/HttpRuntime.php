<?php

namespace Cypher\RuntimeEngine\Http;

class HttpRuntime
{
    private array $routes = [];
    private array $middleware = [];
    private array $serverConfig;

    public function __construct(array $config = [])
    {
        $this->serverConfig = array_merge([
            'host' => '0.0.0.0',
            'port' => 8080,
            'workers' => 4,
        ], $config);
    }

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function put(string $path, callable $handler): void
    {
        $this->routes['PUT'][$path] = $handler;
    }

    public function delete(string $path, callable $handler): void
    {
        $this->routes['DELETE'][$path] = $handler;
    }

    public function use(callable $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    public function group(array $attributes, callable $callback): void
    {
        $prefix = $attributes['prefix'] ?? '';
        $routesBefore = $this->routes;
        $callback($this);
        foreach ($this->routes as $method => $paths) {
            foreach ($paths as $path => $handler) {
                if (!isset($routesBefore[$method][$path])) {
                    unset($this->routes[$method][$path]);
                    $this->routes[$method][$prefix . $path] = $handler;
                }
            }
        }
    }

    public function handle(string $method, string $path, array $body = []): HttpResponse
    {
        // Apply middleware
        foreach ($this->middleware as $mw) {
            $result = $mw($method, $path, $body);
            if ($result instanceof HttpResponse) {
                return $result;
            }
        }

        $handler = $this->routes[$method][$path] ?? null;
        if (!$handler) {
            // Try pattern matching
            foreach (($this->routes[$method] ?? []) as $routePath => $routeHandler) {
                $params = $this->matchRoute($routePath, $path);
                if ($params !== null) {
                    try {
                        $result = $routeHandler(array_merge($body, $params));
                        return new HttpResponse(200, json_encode($result), ['Content-Type' => 'application/json']);
                    } catch (\Throwable $e) {
                        return new HttpResponse(500, json_encode(['error' => $e->getMessage()]));
                    }
                }
            }
            return new HttpResponse(404, json_encode(['error' => 'Not found']));
        }

        try {
            $result = $handler($body);
            return new HttpResponse(200, json_encode($result), ['Content-Type' => 'application/json']);
        } catch (\Throwable $e) {
            return new HttpResponse(500, json_encode(['error' => $e->getMessage()]));
        }
    }

    public function start(): void
    {
        $host = $this->serverConfig['host'];
        $port = $this->serverConfig['port'];
        echo "Cypher HTTP Runtime listening on {$host}:{$port}\n";
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    private function matchRoute(string $pattern, string $path): ?array
    {
        $patternParts = explode('/', trim($pattern, '/'));
        $pathParts = explode('/', trim($path, '/'));

        if (count($patternParts) !== count($pathParts)) return null;

        $params = [];
        foreach ($patternParts as $i => $part) {
            if (str_starts_with($part, '{') && str_ends_with($part, '}')) {
                $paramName = trim($part, '{}');
                $params[$paramName] = $pathParts[$i] ?? null;
            } elseif ($part !== ($pathParts[$i] ?? null)) {
                return null;
            }
        }

        return $params;
    }
}
