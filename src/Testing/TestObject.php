<?php
declare(strict_types=1);
namespace AML\View\Testing;

/** @internal Holds interpreted fields; application constructors/methods are never invoked. */
class TestObject
{
    public function __construct(public readonly array $definition, public array $fields = []) {}
    public function is(string $name): bool
    {
        return in_array(strtolower(ltrim($name, '\\')), array_map(strtolower(...), [$this->definition['name'], ...$this->definition['ancestors']]), true);
    }
}
