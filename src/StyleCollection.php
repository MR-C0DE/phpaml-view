<?php
declare(strict_types=1);
namespace AML\View;

/** @implements \ArrayAccess<string, Style> @implements \IteratorAggregate<string, Style> */
final readonly class StyleCollection implements \ArrayAccess, \IteratorAggregate, \Countable
{
    /** @param array<string, Style> $styles */
    public function __construct(private array $styles, private string $source = 'Style::create()') {}
    public function offsetExists(mixed $offset): bool { return is_string($offset) && isset($this->styles[$offset]); }
    public function offsetGet(mixed $offset): Style
    {
        if (!$this->offsetExists($offset)) throw new \OutOfBoundsException('Unknown style ' . (is_scalar($offset) ? (string) $offset : get_debug_type($offset)) . ' in ' . $this->source);
        return $this->styles[$offset];
    }
    public function __get(string $name): Style { return $this->offsetGet($name); }
    public function __isset(string $name): bool { return $this->offsetExists($name); }
    public function offsetSet(mixed $offset, mixed $value): void { throw new \LogicException('Style collections are immutable.'); }
    public function offsetUnset(mixed $offset): void { throw new \LogicException('Style collections are immutable.'); }
    public function getIterator(): \Traversable { yield from $this->styles; }
    public function count(): int { return count($this->styles); }
}
