<?php
declare(strict_types=1);
namespace Exemples\Pages;
use AML\View\{Page, State, View};
use function AML\View\{Button, Text, VStack};

final class SortiesConsolePage extends Page
{
    #[State] public array $commande = ['nom' => 'André', 'quantite' => 1, 'prix' => '12.50'];
    public function body(): View
    {
        return VStack(
            Text('Commande'),
            Text(print_r($this->commande, true)),
            Text(var_export($this->commande, true)),
            Button('Ajouter et examiner')->onClick(function (): void {
                $this->commande['quantite']++;
                print_r($this->commande);
                var_export($this->commande);
            }),
        );
    }
}
