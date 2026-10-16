<?php

declare(strict_types=1);

namespace Exemples\Compatibilite\Services;

enum Disponibilite: string
{
    case Disponible = 'disponible';
    case Reserve = 'reserve';

    public function libelle(): string
    {
        return match ($this) {
            self::Disponible => 'Disponible',
            self::Reserve => 'Réservé',
        };
    }
}

function normaliserArticle(array &$article): void
{
    $article['nom'] = preg_replace('/\s+/u', ' ', trim($article['nom']));
    if (preg_match('/^[A-Z]{2}[0-9]{3}$/', $article['code']) !== 1) {
        throw new \InvalidArgumentException('Code article invalide : ' . $article['code']);
    }
    if ($article['prixCentimes'] < 0 || $article['quantite'] < 1) {
        throw new \InvalidArgumentException('Le prix et la quantité sont invalides.');
    }
}

function preparerCatalogue(string $source): array
{
    $donnees = json_decode($source, associative: true, flags: JSON_THROW_ON_ERROR);
    $date = new \DateTimeImmutable($donnees['date']);
    $collection = new \ArrayObject();
    $totalCentimes = 0;
    foreach ($donnees['articles'] as $article) {
        normaliserArticle($article);
        $statut = Disponibilite::from($article['statut']);
        if ($statut === Disponibilite::Reserve) { continue; }
        $montant = $article['prixCentimes'] * $article['quantite'];
        $totalCentimes += $montant;
        $collection->append([
            'code' => $article['code'],
            'nom' => $article['nom'],
            'statut' => $statut->libelle(),
            'montantCentimes' => $montant,
        ]);
    }
    return [
        'date' => $date->format('Y-m-d'),
        'articles' => iterator_to_array($collection->getIterator()),
        'totalCentimes' => $totalCentimes,
    ];
}
