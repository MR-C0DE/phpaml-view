<?php

declare(strict_types=1);
namespace Exemples\Pages;

use AML\View\{Page, State, View};
use function AML\View\{Button, Input, Select, Text, VStack};
use function Exemples\Saisie\{centimes, montant};

require_once __DIR__ . '/service.php';

final class SaisiePage extends Page
{
    #[State] public string $nom = 'André';
    #[State] public string $prix = '12,50';
    #[State] public string $quantite = '3';
    #[State] public string $choix = 'total';
    #[State] public string $resultat = 'Choisis une action puis clique sur Valider.';

    public function body(): View
    {
        return VStack(
            Text('Niveaux 7 et 8 — Saisie et calculs'),
            Text('Nom'), Input('nom')->value($this->nom)->attribute('aria-label', 'Nom'),
            Text('Prix unitaire (€)'), Input('prix')->value($this->prix)->inputMode('decimal')->attribute('aria-label', 'Prix'),
            Text('Quantité (1 à 100)'), Input('quantite')->value($this->quantite)->inputMode('numeric')->attribute('aria-label', 'Quantité'),
            Select('choix', ['bonjour' => 'Dire bonjour', 'total' => 'Calculer le total', 'tirage' => 'Tirer un nombre'])
                ->value($this->choix)->attribute('aria-label', 'Action'),
            Button('Valider')->onClick($this->valider(...)),
            Text($this->resultat),
        );
    }

    public function valider(): void
    {
        $nom = trim($this->nom);
        if ($nom === '') { $this->resultat = 'Entre ton nom.'; return; }

        switch ($this->choix) {
            case 'bonjour':
                $this->resultat = "Bonjour {$nom}";
                break;
            case 'total':
                $prix = centimes($this->prix);
                $saisie = trim($this->quantite);
                if ($prix === null || !ctype_digit($saisie) || strlen($saisie) > 3) {
                    $this->resultat = 'Entre un prix positif avec au plus deux décimales et une quantité entière.';
                    return;
                }
                $quantite = (int) $saisie;
                if ($quantite < 1 || $quantite > 100) { $this->resultat = 'La quantité doit être entre 1 et 100.'; return; }
                $this->resultat = $nom . ' : ' . montant($prix * $quantite);
                break;
            case 'tirage':
                $this->resultat = $nom . ', ton nombre : ' . random_int(1, 100);
                break;
            default:
                $this->resultat = 'Choix inconnu.';
                return;
        }
        echo $this->resultat . PHP_EOL;
    }
}
