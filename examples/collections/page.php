<?php
declare(strict_types=1);
namespace Exemples\Collections\Pages;

require_once __DIR__ . '/service.php';

use AML\View\{Page, State, View};
use Exemples\Collections\Services\Panier;
use function AML\View\{Button, Text, VStack};

final class CollectionsPage extends Page
{
    #[State] public array $articles = ['Pomme'];
    #[State] public string $message = '1 article : Pomme';

    public function body(): View
    {
        return VStack(
            Text($this->message)->id('resultat-panier'),
            Button('Ajouter une orange')->onClick(function (): void {
                $panier = new Panier($this->articles);
                $panier[] = 'Orange';
                $this->articles = $panier->all();
                $this->message = count($panier) . ' articles : ' . implode(', ', $panier->all());
                echo $this->message . PHP_EOL;
            }),
        );
    }
}
