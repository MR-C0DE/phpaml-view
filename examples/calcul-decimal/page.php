<?php

declare(strict_types=1);
namespace Exemples\Pages;

use AML\View\{Page, State, View};
use function AML\View\{Button, Input, Text, VStack};

// PHP 8.4 + ext-bcmath. All amounts remain decimal strings.
final class CalculDecimalPage extends Page
{
    #[State] public string $prix = '19.995';
    #[State] public string $quantite = '3';
    #[State] public string $sousTotal = '59.99';
    #[State] public string $taxes = '9.00';
    #[State] public string $erreur = '';

    public function body(): View
    {
        return VStack(
            Input('prix')->value($this->prix)->attribute('aria-label', 'Prix unitaire'),
            Input('quantite')->value($this->quantite)->attribute('aria-label', 'Quantité'),
            Button('Calculer')->onClick($this->calculer(...)),
            Text('Sous-total : ' . $this->sousTotal . ' $'),
            Text('Taxes (15 %) : ' . $this->taxes . ' $'),
            Text('Total : ' . bcadd($this->sousTotal, $this->taxes, 2) . ' $'),
            Text($this->erreur),
        );
    }

    public function calculer(): void
    {
        try {
            $prix = trim($this->prix); $quantite = trim($this->quantite);
            if ($prix === '' || str_contains($prix, "\0") || strlen($prix) > 30 || strlen($quantite) > 5 || !ctype_digit($quantite)
                || bccomp($quantite, '1') < 0 || bccomp($quantite, '10000') > 0
                || bccomp($prix, '0', 30) <= 0 || bccomp($prix, '1000000', 30) > 0) {
                throw new \InvalidArgumentException('Entrée invalide');
            }
            // Policy for this example: round the subtotal, then round the tax.
            $sousTotal = bcround(bcmul($prix, $quantite, 30), 2, \RoundingMode::HalfAwayFromZero);
            $taxes = bcround(bcmul($sousTotal, '0.15', 4), 2, \RoundingMode::HalfAwayFromZero);
            $this->sousTotal = $sousTotal; $this->taxes = $taxes; $this->erreur = '';
            echo 'Total calculé : ' . bcadd($sousTotal, $taxes, 2) . "\n";
        } catch (\InvalidArgumentException|\ValueError $error) {
            $this->erreur = 'Entre un prix positif avec un point décimal et une quantité de 1 à 10 000.';
        }
    }
}
