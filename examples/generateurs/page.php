<?php
declare(strict_types=1);
namespace Exemples\Generateurs\Pages;

require_once __DIR__ . '/service.php';
use AML\View\{Page, State, View};
use function AML\View\{Button, Text, VStack};
use function Exemples\Generateurs\Services\catalogue;

final class GenerateursPage extends Page
{
    #[State] public array $articles = [
        ['nom' => 'Pomme', 'stock' => 3],
        ['nom' => 'Poire', 'stock' => 0],
        ['nom' => 'Orange', 'stock' => 2],
    ];
    #[State] public string $message = 'Prêt à parcourir le catalogue.';
    #[State] public int $parcours = 0;

    public function body(): View
    {
        return VStack(
            Text($this->message)->id('resultat-generateurs'),
            Button('Parcourir le catalogue')->onClick(function (): void {
                $flux = catalogue($this->articles);
                $noms = [];
                foreach ($flux as $nom) { $noms[] = $nom; }
                $this->parcours++;
                $this->message = 'Parcours ' . $this->parcours . ' · ' . $flux->getReturn() . ' disponibles : ' . implode(', ', $noms);
                echo 'Catalogue parcouru' . PHP_EOL;
            }),
        );
    }
}
