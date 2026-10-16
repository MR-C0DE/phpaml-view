<?php
declare(strict_types=1);
namespace Exemples\Magie\Pages;
use AML\View\{Page, State, View};
use Exemples\Magie\Services\Profil;
use function AML\View\{Button, Heading, Text, VStack};
require_once __DIR__ . '/service.php';

final class MagiePage extends Page
{
    #[State] public string $message = 'Crée un profil et sa copie.';
    public function body(): View
    {
        return VStack(
            Heading('Un profil et sa copie', 1),
            Button('Créer les profils')->onClick(function (): void {
                $profil = Profil::creer('André');             // __callStatic
                $profil->points = 10;                        // __set
                $points = $profil->points;                   // __get
                $profil(5);                                  // __invoke
                $profil->surnom = 'Andy';
                $present = isset($profil->surnom);            // __isset
                unset($profil->surnom);                       // __unset
                $copie = clone $profil;                      // __clone
                $copie->points = 99;
                $this->message = $profil->resume() . ' | ' . $copie; // __call et __toString
                echo $this->message;
                var_dump([$points, $present, isset($profil->surnom)]);
            }),
            Text($this->message)->id('resultat-magie')->attribute('role', 'status'),
        )->gap(12)->padding(24);
    }
}
