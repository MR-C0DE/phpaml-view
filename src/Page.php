<?php

declare(strict_types=1);

namespace AML\View;

abstract class Page extends Component
{
    /** @var array<string, string> */
    protected array $routeParameters = [];

    /** @var array<string, string|list<string>> */
    protected array $queryParameters = [];

    protected ?\Throwable $routeError = null;

    /** @internal @param array<string, string> $parameters */
    final public function configureRoute(array $parameters, ?\Throwable $error = null, array $query = []): static
    {
        $this->routeParameters = $parameters;
        $this->routeError = $error;
        $this->queryParameters = $query;
        return $this;
    }

    final public function param(string $name, ?string $default = null): ?string
    {
        return $this->routeParameters[$name] ?? $default;
    }

    /** @return array<string, string> */
    final public function params(): array
    {
        return $this->routeParameters;
    }

    final public function query(string $name, string|array|null $default = null): string|array|null
    {
        return $this->queryParameters[$name] ?? $default;
    }

    /** @return array<string, string|list<string>> */
    final public function queries(): array
    {
        return $this->queryParameters;
    }

    final public function error(): ?\Throwable
    {
        return $this->routeError;
    }

    public function metadata(): PageMetadata
    {
        return new PageMetadata();
    }
}
