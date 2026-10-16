<?php
declare(strict_types=1);
namespace Exemples\Iterateurs\Pages;

require_once __DIR__ . '/service.php';

use AML\View\{Page, State, View};
use Exemples\Iterateurs\Services\ListeArticles;
use function AML\View\{Button, Text, VStack};

final class IterateursPage extends Page
{
    #[State] public array $articles = ['pomme' => 'Pomme', 'orange' => 'Orange'];
    #[State] public int $parcours = 0;
    #[State] public string $message = 'Prêt à parcourir la collection.';

    public function body(): View
    {
        return VStack(
            Text($this->message)->id('resultat-iterateurs'),
            Button('Parcourir la collection')->onClick(function (): void {
                $collection = new ListeArticles($this->articles);
                $lignes = [];
                foreach ($collection as $cle => $nom) {
                    $lignes[] = $cle . '=' . $nom;
                }
                $this->parcours++;
                $this->message = 'Parcours ' . $this->parcours . ' · ' . count($collection) . ' articles : ' . implode(', ', $lignes);
                echo 'Parcours terminé' . PHP_EOL;
            }),
        );
    }
}
