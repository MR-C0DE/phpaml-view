<?php
declare(strict_types=1);
namespace Exemples\ClassesAnonymes\Pages;

require_once __DIR__ . '/service.php';

use AML\View\{Component, Page, State, View};
use Exemples\ClassesAnonymes\Services\{Nommable, Evaluable};
use function Exemples\ClassesAnonymes\Services\presenter;
use function AML\View\{Button, Text, VStack};

final class ProfilAnonyme extends Component
{
    #[State] public string $message = 'Créer un profil pour commencer.';

    public function body(): View
    {
        return VStack(
            Text($this->message)->id('resultat-anonyme'),
            Button('Créer un profil')->onClick(function (): void {
                $profil = new class('André', 15) implements Nommable, Evaluable {
                    private static int $creations = 0;
                    private int $numero;

                    public function __construct(
                        private readonly string $nom,
                        private readonly int $points,
                    ) {
                        $this->numero = ++self::$creations;
                    }

                    public function nom(): string { return $this->nom; }
                    public function points(): int { return $this->points; }
                    public function numero(): int { return $this->numero; }
                };

                $this->message = presenter($profil) . ' · création ' . $profil->numero();
                echo $this->message . PHP_EOL;
            }),
        );
    }
}

final class ClassesAnonymesPage extends Page
{
    public function body(): View { return VStack(new ProfilAnonyme()); }
}
