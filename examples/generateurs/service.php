<?php
declare(strict_types=1);
namespace Exemples\Generateurs\Services;

function articlesDisponibles(array $articles): \Generator
{
    $total = 0;
    foreach ($articles as $article) {
        if ($article['stock'] <= 0) { continue; }
        $total++;
        yield $article['nom'];
    }
    return $total;
}

function catalogue(array $articles): \Generator
{
    return yield from articlesDisponibles($articles);
}
