<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int, array{method:string,path:string,regex:string,params:array<int,string>,handler:callable}> */
    private array $routes = [];
    private $notFoundHandler = null;

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function add(string $method, string $path, callable $handler): void
    {
        $params = [];
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static function (array $matches) use (&$params): string {
                $params[] = $matches[1];
                return '([^/]+)';
            },
            $path
        );

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'regex' => '#^' . ($regex ?? preg_quote($path, '#')) . '$#',
            'params' => $params,
            'handler' => $handler,
        ];
    }

    public function setNotFoundHandler(callable $handler): void
    {
        $this->notFoundHandler = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = Url::stripBasePath($path);
        $method = strtoupper($method);

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;
            if (!preg_match($route['regex'], $path, $matches)) continue;

            array_shift($matches);
            $arguments = [];
            foreach ($route['params'] as $index => $name) {
                $arguments[$name] = isset($matches[$index]) ? rawurldecode($matches[$index]) : '';
            }

            call_user_func_array($route['handler'], array_values($arguments));
            return;
        }

        if (is_callable($this->notFoundHandler)) {
            call_user_func($this->notFoundHandler, $path, $method);
            return;
        }

        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Pagina non trovata';
    }
}
