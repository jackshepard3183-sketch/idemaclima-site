<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<string, callable> */
    private array $routes = [];

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
        $this->routes[strtoupper($method) . ' ' . $path] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $key = strtoupper($method) . ' ' . $path;

        if (!isset($this->routes[$key])) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Pagina non trovata';
            return;
        }

        ($this->routes[$key])();
    }
}
