<?php

declare(strict_types=1);

namespace Exemples\Pages;

use AML\View\{Page, State, View};
use function AML\View\{Button, Text, VStack};
use function Exemples\Services\{ajouterProduit, calculerCommande};

require_once __DIR__ . '/service.php';

final class CommandePage extends Page
{
    #[State] public string $resultat = 'Clique sur Calculer.';

    public function body(): View
    {
        $nom = 'André';

        return VStack(
            Text("Commande de $nom"),
            Text($this->resultat),
            Button('Calculer')->onClick(function () use ($nom): void {
                $vip = true;
                $codePromo = null;
                $panier = [];

                ajouterProduit($panier, nom: 'Café', prix: 12.50, quantite: 2);
                ajouterProduit($panier, nom: 'Tasse', prix: 10.00);

                $commande = calculerCommande($panier, vip: $vip);

                $this->resultat = $commande['quantite'] . ' articles — '
                    . $commande['total'] . ' € — ' . $commande['statut'];

                echo "Bonjour $nom\n";
                echo 'Code promo : ' . ($codePromo ?? 'aucun') . "\n";
                echo 'Type du total : ' . gettype($commande['total']) . "\n";
                var_dump($commande);
            }),
        );
    }
}
