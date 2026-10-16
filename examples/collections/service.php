<?php
declare(strict_types=1);
namespace Exemples\Collections\Services;

final class Panier implements \Countable, \ArrayAccess
{
    public function __construct(private array $articles = []) {}

    public function count(): int { return count($this->articles); }
    public function offsetExists(mixed $position): bool { return isset($this->articles[$position]); }
    public function offsetGet(mixed $position): mixed { return $this->articles[$position] ?? null; }
    public function offsetSet(mixed $position, mixed $article): void
    {
        if (!is_string($article)) { throw new \InvalidArgumentException('Un article doit être une chaîne.'); }
        if ($position === null) { $this->articles[] = $article; }
        else { $this->articles[$position] = $article; }
    }
    public function offsetUnset(mixed $position): void { unset($this->articles[$position]); }
    public function all(): array { return $this->articles; }
}
