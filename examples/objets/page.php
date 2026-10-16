<?php
declare(strict_types=1);
namespace Exemples\Objets\Pages;

use AML\View\{Page, State, View};
use Exemples\Objets\Services\{Ligne, LigneRemisee, Panier};
use function AML\View\{Button, Heading, Text, VStack};

require_once __DIR__ . '/service.php';

final class ObjetsPage extends Page
{
    #[State] public int $quantite = 2;
    #[State] public string $message = 'Clique sur Calculer.';

    public function body(): View
    {
        return VStack(
            Heading('Un panier avec des objets PHP', 1),
            Text("Quantité de cafés : {$this->quantite}"),
            Button('Ajouter un café')->onClick(function (): void { $this->quantite++; }),
            Button('Calculer')->onClick(function (): void {
                try {
                    $panier = new Panier();
                    $panier
                        ->ajouter(new LigneRemisee('Café', 1250, $this->quantite, remise: 10))
                        ->ajouter(new Ligne('Tasse', 1000));

                    $resume = $panier->resume();
                    $this->message = $resume['lignes'] . ' lignes — '
                        . number_format($resume['totalCentimes'] / 100, 2, ',', ' ') . ' €';
                    echo $this->message . PHP_EOL;
                    var_dump($resume);
                } catch (\Throwable $erreur) {
                    $this->message = $erreur->getMessage();
                }
            }),
            Text($this->message)->id('resultat')->attribute('role', 'status'),
        )->gap(12)->padding(24);
    }
}
