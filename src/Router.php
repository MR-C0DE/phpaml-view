<?php

declare(strict_types=1);

namespace AML\View;

use Closure;

final class Router
{
    /** @var list<array{pattern: string, page: Closure, layout: ?Closure}> */
    private array $routes = [];

    /** @param Closure(array<string, string>): View $page @param null|Closure(): Layout $layout */
    public function get(string $pattern, Closure $page, ?Closure $layout = null): self
    {
        $pattern = '/' . trim($pattern, '/');
        $this->routes[] = ['pattern' => $pattern === '/' ? '/' : rtrim($pattern, '/'), 'page' => $page, 'layout' => $layout];
        return $this;
    }

    /** @return array{View, ?Layout} */
    public function resolve(string $path): array
    {
        $query = [];
        parse_str((string) (parse_url($path, PHP_URL_QUERY) ?? ''), $query);
        $path = parse_url($path, PHP_URL_PATH) ?: '/';
        $path = $path === '/' ? '/' : rtrim($path, '/');
        foreach ($this->routes as $route) {
            $names = [];
            $segments = explode('/', trim($route['pattern'], '/'));
            $regexSegments = array_map(static function (string $segment) use (&$names): string {
                if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $segment, $match)) {
                    $names[] = $match[1];
                    return '([^/]+)';
                }
                return preg_quote($segment, '#');
            }, $segments);
            $regex = $route['pattern'] === '/' ? '/' : '/' . implode('/', $regexSegments);
            if (!preg_match('#^' . $regex . '$#', $path, $matches)) {
                continue;
            }
            array_shift($matches);
            $params = [];
            foreach ($names as $index => $name) {
                $params[$name] = rawurldecode($matches[$index] ?? '');
            }
            $reflection = new \ReflectionFunction($route['page']);
            $page = $reflection->getNumberOfParameters() >= 2
                ? ($route['page'])($params, $query)
                : ($route['page'])($params);
            $layout = $route['layout'] === null ? null : ($route['layout'])();
            if (!$page instanceof View || ($layout !== null && !$layout instanceof Layout)) {
                throw new \UnexpectedValueException('AML View routes must return a View and an optional Layout.');
            }
            return [$page, $layout];
        }
        throw new \OutOfBoundsException("No AML View route matches {$path}.");
    }
}
