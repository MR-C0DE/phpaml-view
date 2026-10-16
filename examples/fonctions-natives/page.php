<?php

declare(strict_types=1);
namespace Exemples\Pages;

use AML\View\{Page, State, View};
use function AML\View\{Button, Input, Text, VStack};

final class FonctionsNativesPage extends Page
{
    #[State] public string $saisie = '3, 4, 12, 3';
    #[State] public array $valeurs = [3.0, 4.0, 12.0];
    #[State] public string $erreur = '';

    public function body(): View
    {
        return VStack(
            Text('Math, String et Array'),
            Input('saisie')->value($this->saisie)->attribute('aria-label', 'Nombres séparés par des virgules'),
            Button('Analyser')->onClick($this->analyser(...)),
            Text('Valeurs : ' . implode(', ', array_map('strval', $this->valeurs))),
            Text(sprintf('Moyenne : %.2f', array_sum($this->valeurs) / count($this->valeurs))),
            Text('Norme : ' . number_format(sqrt(array_sum(array_map(fn (float $x): float => pow($x, 2), $this->valeurs))), 2, ',', ' ')),
            Text($this->erreur),
        );
    }

    public function analyser(): void
    {
        try {
            $morceaux = array_map('trim', explode(',', $this->saisie));
            if (count($morceaux) > 100 || array_filter($morceaux, fn (string $valeur): bool => !is_numeric($valeur))) {
                throw new \InvalidArgumentException('Entre de 1 à 100 nombres séparés par des virgules.');
            }
            $nombres = array_map('floatval', $morceaux);
            if (array_filter($nombres, fn (float $valeur): bool => !is_finite($valeur) || abs($valeur) > 1000000)) {
                throw new \InvalidArgumentException('Chaque nombre doit être compris entre -1 000 000 et 1 000 000.');
            }
            $nombres = array_values(array_unique($nombres, SORT_REGULAR));
            usort($nombres, fn (float $a, float $b): int => $a <=> $b);
            $this->valeurs = $nombres;
            $this->erreur = '';
            printf("Calcul terminé : %d valeurs\n", count($nombres));
        } catch (\InvalidArgumentException $erreur) {
            $this->erreur = $erreur->getMessage();
        }
    }
}
