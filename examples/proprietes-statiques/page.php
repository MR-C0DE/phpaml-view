<?php
declare(strict_types=1);
namespace Exemples\Statiques\Pages;
use AML\View\{Component, Page, State, View};
use Exemples\Statiques\Services\Compteur;
use function AML\View\{Button, Heading, Text, VStack};
require_once __DIR__ . '/service.php';

final class CompteurPanel extends Component
{
    #[State] public int $valeur = 0;
    public function __construct(private string $titre) {}
    public function body(): View
    {
        return VStack(
            Heading($this->titre, 2),
            Text('Valeur : {valeur}'),
            Button('Incrémenter ' . $this->titre)->onClick(function (): void {
                $this->valeur = Compteur::suivant();
            }),
            Button('Relire ' . $this->titre)->onClick(function (): void {
                $this->valeur = Compteur::lire();
            }),
            Button('Réinitialiser ' . $this->titre)->onClick(function (): void {
                Compteur::remettreAZero();
                $this->valeur = Compteur::lire();
            }),
        )->gap(8);
    }
}
final class StatiquesPage extends Page
{
    public function body(): View
    {
        return VStack(
            Heading('Deux compteurs indépendants', 1),
            Text('Chaque composant conserve son propre compteur entre les clics.'),
            new CompteurPanel('A'),
            new CompteurPanel('B'),
        )->gap(20)->padding(24);
    }
}
