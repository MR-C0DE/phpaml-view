<?php

declare(strict_types=1);

namespace Exemples\Services;

const REMISE_VIP = 0.10;

function ajouterProduit(array &$panier, string $nom, float $prix, int $quantite = 1): void
{
    $panier[] = [
        'nom' => $nom,
        'prix' => $prix,
        'quantite' => $quantite,
        'disponible' => true,
    ];
}

function prixLigne(array $produit): float
{
    return $produit['prix'] * $produit['quantite'];
}

// Fonction récursive : elle s'arrête à la fin du tableau.
function additionner(array $montants, int $position = 0): float
{
    if ($position >= count($montants)) {
        return 0.0;
    }

    return $montants[$position] + additionner($montants, $position + 1);
}

function calculerCommande(array $panier, bool $vip = false): array
{
    $disponibles = array_filter(
        $panier,
        fn (array $produit): bool => $produit['disponible'] && $produit['quantite'] > 0,
    );

    $quantite = 0;
    foreach ($disponibles as $produit) {
        $quantite += $produit['quantite'];
    }

    $montants = array_values(array_map(
        fn (array $produit): float => prixLigne($produit),
        $disponibles,
    ));

    $sousTotal = additionner($montants);
    $remise = $vip ? $sousTotal * REMISE_VIP : 0.0;
    $total = $sousTotal - $remise;

    $statut = match (true) {
        $quantite === 0 => 'Panier vide',
        $vip => 'Remise VIP appliquée',
        default => 'Tarif normal',
    };

    return ['quantite' => $quantite, 'total' => $total, 'statut' => $statut];
}
