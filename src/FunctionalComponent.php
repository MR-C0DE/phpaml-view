<?php

declare(strict_types=1);

namespace AML\View;

/** @internal One independently rendered invocation of a function component. */
final class FunctionalComponent extends Component
{
    private string $function;
    private array $arguments;
    private array $bindings;

    public function __construct(string $function, array $arguments, array $bindings)
    {
        $this->function = $function;
        $this->arguments = $arguments;
        $this->bindings = $bindings;
    }

    public function functionName(): string { return $this->function; }
    public function inputBindings(): array { return $this->bindings; }
    public function body(): View { return FunctionalViews::invoke($this->function, $this->arguments); }
}
