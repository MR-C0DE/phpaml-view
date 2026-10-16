<?php
declare(strict_types=1);
namespace Exemples\Iterateurs\Services;

final class CurseurArticles implements \Iterator
{
    private int $position = 0;
    private array $cles;
    private array $valeurs;

    public function __construct(array $articles)
    {
        $this->cles = array_keys($articles);
        $this->valeurs = array_values($articles);
    }
    public function rewind(): void { $this->position = 0; }
    public function valid(): bool { return $this->position < count($this->valeurs); }
    public function current(): mixed { return $this->valeurs[$this->position]; }
    public function key(): mixed { return $this->cles[$this->position]; }
    public function next(): void { $this->position++; }
}

final class ListeArticles implements \IteratorAggregate, \Countable
{
    public function __construct(private array $articles) {}
    public function count(): int { return count($this->articles); }
    public function getIterator(): \Traversable { return new CurseurArticles($this->articles); }
}
