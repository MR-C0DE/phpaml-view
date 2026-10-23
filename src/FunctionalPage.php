<?php

declare(strict_types=1);

namespace AML\View;

/** @internal File routes retain the existing Page/layout/navigation contract. */
final class FunctionalPage extends Page
{
    public function __construct(private string $function) {}

    public function functionName(): string { return $this->function; }

    public function body(): View
    {
        $arguments = [];
        foreach ((new \ReflectionFunction($this->function))->getParameters() as $parameter) {
            $name = $parameter->getName();
            if (array_key_exists($name, $this->routeParameters)) $arguments[$name] = $this->routeParameters[$name];
            elseif ($name === 'params') $arguments[$name] = $this->params();
            elseif ($name === 'query') $arguments[$name] = $this->queries();
            elseif ($name === 'error') $arguments[$name] = $this->error();
            elseif (!$parameter->isOptional()) throw new \InvalidArgumentException("Missing route parameter {$name} for functional page {$this->function}.");
        }
        return FunctionalViews::invoke($this->function, $arguments);
    }
}
