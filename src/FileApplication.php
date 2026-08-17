<?php

declare(strict_types=1);

namespace AML\View;

final class FileApplication
{
    /** @var array<string, array{page: class-string<Page>, layouts: list<class-string<Layout>>, directory: string}>|null */
    private ?array $routes = null;

    public function __construct(
        private string $source,
        private ?string $secret = null,
        private string $audience = 'public',
        private string $namespace = 'App\\Views',
    ) {
        $this->source = rtrim($this->source, '/\\');
    }

    public function mount(string $path): PageResult
    {
        $requestedState = $_SERVER['HTTP_X_AML_NAVIGATION_STATE'] ?? null;
        if (is_string($requestedState) && in_array($requestedState, ['loading', 'error', 'not-found'], true)) {
            $error = $requestedState === 'error' ? new \RuntimeException('Navigation failed.') : null;
            $state = $this->specialView($requestedState, $path, $error, true)
                ?? match ($requestedState) {
                    'loading' => Text('Loading…'),
                    'error' => Text('An unexpected error occurred.'),
                    default => Text('Page not found.'),
                };
            $markedState = (new Element('div', $state))->attribute('data-aml-navigation-state-content', $requestedState);
            return new PageResult((new Renderer())->render($markedState));
        }
        [$pattern, $parameters] = $this->match($path);
        $definition = $this->discover()[$pattern];
        $content = (new $definition['page']())->configureRoute($parameters, null, $this->queryFrom($path));
        $boundary = new NavigationBoundaryView(
            $content,
            Text('Loading…'),
            Text('An unexpected error occurred.'),
            Text('Page not found.'),
        );
        return new PageResult((new Renderer())->render($this->wrap($boundary, $definition['layouts'])));
    }

    public function metadata(string $path): PageMetadata
    {
        [$pattern, $parameters] = $this->match($path);
        $definition = $this->discover()[$pattern];
        return (new $definition['page']())->configureRoute($parameters, null, $this->queryFrom($path))->metadata();
    }

    public function head(string $path): string
    {
        return $this->metadata($path)->render();
    }

    /** @return list<string> */
    public function paths(): array
    {
        return array_keys($this->discover());
    }

    public function styles(): string
    {
        $files = [];
        foreach ([$this->source . '/stylesheets', $this->source . '/themes'] as $root) {
            if (!is_dir($root)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && strtolower($file->getExtension()) === 'css') {
                    $files[] = $file->getPathname();
                }
            }
        }
        sort($files);
        $styles = [];
        foreach ($files as $file) {
            $relative = ltrim(str_replace('\\', '/', substr($file, strlen($this->source))), '/');
            $content = file_get_contents($file);
            if (is_string($content)) {
                $styles[] = "/* {$relative} */\n" . trim($content);
            }
        }
        return implode("\n\n", $styles) . ($styles === [] ? '' : "\n");
    }

    public function loading(string $path): ?string
    {
        return $this->renderSpecial('loading', $path);
    }

    public function notFound(string $path): string
    {
        return $this->renderSpecial('not-found', $path)
            ?? (new Renderer())->render(Text('Page not found.'));
    }

    public function error(string $path, \Throwable $error): string
    {
        return $this->renderSpecial('error', $path, $error)
            ?? (new Renderer())->render(Text('An unexpected error occurred.'));
    }

    /** @return array{string, array<string, string>} */
    private function match(string $path): array
    {
        $path = parse_url($path, PHP_URL_PATH) ?: '/';
        $path = $path === '/' ? '/' : '/' . trim($path, '/');
        foreach ($this->discover() as $pattern => $_definition) {
            $names = [];
            $segments = explode('/', trim($pattern, '/'));
            $regex = $pattern === '/' ? '/' : '/' . implode('/', array_map(
                static function (string $segment) use (&$names): string {
                    if (preg_match('/^\[(\.\.\.)?([a-zA-Z_][a-zA-Z0-9_]*)\]$/', $segment, $match)) {
                        $names[] = $match[2];
                        return ($match[1] ?? '') === '...' ? '(.+)' : '([^/]+)';
                    }
                    return preg_quote($segment, '#');
                },
                $segments,
            ));
            if (!preg_match('#^' . $regex . '$#', $path, $matches)) {
                continue;
            }
            array_shift($matches);
            $parameters = [];
            foreach ($names as $index => $name) {
                $parameters[$name] = rawurldecode($matches[$index] ?? '');
            }
            return [$pattern, $parameters];
        }
        throw new \OutOfBoundsException("No AML View page matches {$path}.");
    }

    /** @param array{page: class-string<Page>, layouts: list<class-string<Layout>>, directory: string} $definition @param array<string, string> $parameters */
    private function make(array $definition, array $parameters = [], array $query = []): View
    {
        $view = (new $definition['page']())->configureRoute($parameters, null, $query);
        return $this->wrap($view, $definition['layouts']);
    }

    /** @return array<string, string|list<string>> */
    private function queryFrom(string $path): array
    {
        $query = (string) (parse_url($path, PHP_URL_QUERY) ?? '');
        if ($query === '') return [];
        parse_str($query, $values);
        $safe = [];
        foreach ($values as $name => $value) {
            if (!is_string($name) || preg_match('/^[a-zA-Z_][a-zA-Z0-9_.-]*$/', $name) !== 1) continue;
            if (is_scalar($value)) $safe[$name] = (string) $value;
            elseif (is_array($value)) $safe[$name] = array_values(array_map('strval', array_filter($value, 'is_scalar')));
        }
        return $safe;
    }

    /** @param list<class-string<Layout>> $layouts */
    private function wrap(View $view, array $layouts): View
    {
        foreach (array_reverse($layouts) as $layoutClass) {
            $view = new LayoutView(new $layoutClass(), $view);
        }
        return $view;
    }

    private function renderSpecial(string $name, string $path, ?\Throwable $error = null): ?string
    {
        $view = $this->specialView($name, $path, $error);
        return $view === null ? null : (new Renderer())->render($view);
    }

    private function specialView(string $name, string $path, ?\Throwable $error = null, bool $withLayouts = true): ?View
    {
        try {
            [$pattern, $parameters] = $this->match($path);
            $definition = $this->discover()[$pattern];
            $directory = $definition['directory'];
            $layouts = $definition['layouts'];
        } catch (\OutOfBoundsException) {
            $parameters = ['path' => (string) (parse_url($path, PHP_URL_PATH) ?: '/')];
            $directory = '';
            $layouts = $this->layouts([]);
        }

        $segments = $directory === '' ? [] : explode('/', $directory);
        for ($depth = count($segments); $depth >= 0; $depth--) {
            $candidate = array_slice($segments, 0, $depth);
            $stateFile = match ($name) {
                'not-found' => 'NotFound.php',
                'loading' => 'Loading.php',
                'error' => 'Error.php',
            };
            $folder = $this->source . '/states' . ($candidate === [] ? '' : '/' . implode('/', $candidate));
            $file = $folder . '/' . $stateFile;
            if (!is_file($file)) {
                continue;
            }
            require_once $file;
            $class = $this->specialClass($candidate, $name);
            if (!is_subclass_of($class, Page::class)) {
                throw new \UnexpectedValueException("{$file} must declare {$class} as an AML View page.");
            }
            $view = (new $class())->configureRoute($parameters, $error, $this->queryFrom($path));
            return $withLayouts ? $this->wrap($view, $layouts) : $view;
        }
        return null;
    }

    /** @return array<string, array{page: class-string<Page>, layouts: list<class-string<Layout>>, directory: string}> */
    private function discover(): array
    {
        if ($this->routes !== null) {
            return $this->routes;
        }
        $this->loadComponentFactories();
        $pagesRoot = $this->source . '/pages';
        if (!is_dir($pagesRoot)) {
            throw new \RuntimeException("AML View pages directory not found: {$pagesRoot}");
        }

        $routes = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($pagesRoot, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getFilename() !== 'page.php') {
                continue;
            }
            $pageFile = $file->getPathname();
            $directory = trim(str_replace('\\', '/', substr(dirname($pageFile), strlen($pagesRoot))), '/');
            $pattern = $directory === 'home' ? '/' : '/' . $directory;
            require_once $pageFile;
            $pageClass = $this->pageClass($directory);
            if (!is_subclass_of($pageClass, Page::class)) {
                throw new \UnexpectedValueException("{$pageFile} must declare {$pageClass} as an AML View page.");
            }
            $segments = $directory === '' ? [] : explode('/', $directory);
            $routes[$pattern] = [
                'page' => $pageClass,
                'layouts' => $this->layouts($segments),
                'directory' => $directory,
            ];
        }
        uksort($routes, static function (string $left, string $right): int {
            $rank = static fn (string $route): int => str_contains($route, '[...')
                ? 2
                : (str_contains($route, '[') ? 1 : 0);
            $difference = $rank($left) <=> $rank($right);
            return $difference !== 0 ? $difference : strcmp($left, $right);
        });
        return $this->routes = $routes;
    }

    /**
     * Component files may expose a same-name factory beside their class, for
     * example Navigation(). Loading them before pages and layouts makes those
     * factories available without a generated registry.
     */
    private function loadComponentFactories(): void
    {
        $root = $this->source . '/components';
        if (!is_dir($root)) return;
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);
        foreach ($files as $file) require_once $file;
    }

    /** @param list<string> $segments @return list<class-string<Layout>> */
    private function layouts(array $segments): array
    {
        $layouts = [];
        for ($depth = 0; $depth <= count($segments); $depth++) {
            $candidate = array_slice($segments, 0, $depth);
            if ($candidate === []) {
                $file = $this->source . '/layouts/AppLayout.php';
            } else {
                $names = array_map([$this, 'studly'], $candidate);
                $parents = array_slice($candidate, 0, -1);
                $file = $this->source . '/layouts'
                    . ($parents === [] ? '' : '/' . implode('/', $parents))
                    . '/' . end($names) . 'Layout.php';
            }
            if (!is_file($file)) {
                continue;
            }
            require_once $file;
            $class = $this->layoutClass($candidate);
            if (!is_subclass_of($class, Layout::class)) {
                throw new \UnexpectedValueException("{$file} must declare {$class} as an AML View layout.");
            }
            $layouts[] = $class;
        }
        return $layouts;
    }

    private function pageClass(string $directory): string
    {
        $segments = array_map([$this, 'studly'], explode('/', $directory));
        return $this->namespace . '\\Pages\\' . implode('\\', $segments) . '\\' . end($segments) . 'Page';
    }

    /** @param list<string> $segments */
    private function layoutClass(array $segments): string
    {
        if ($segments === []) {
            return $this->namespace . '\\Layouts\\AppLayout';
        }
        $names = array_map([$this, 'studly'], $segments);
        $parents = array_slice($names, 0, -1);
        return $this->namespace . '\\Layouts'
            . ($parents === [] ? '' : '\\' . implode('\\', $parents))
            . '\\' . end($names) . 'Layout';
    }

    /** @param list<string> $segments */
    private function specialClass(array $segments, string $name): string
    {
        $namespace = $this->namespace . '\\States';
        if ($segments !== []) {
            $namespace .= '\\' . implode('\\', array_map([$this, 'studly'], $segments));
        }
        return $namespace . '\\' . match ($name) {
            'not-found' => 'NotFoundPage',
            'loading' => 'LoadingPage',
            'error' => 'ErrorPage',
        };
    }

    private function studly(string $value): string
    {
        $value = ltrim(trim($value, '[]'), '.');
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

}
