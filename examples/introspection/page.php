<?php
declare(strict_types=1);
namespace Exemples\Introspection\Pages;

require_once __DIR__ . '/service.php';

use AML\View\{Page, State, View};
use Exemples\Introspection\Services\{Identifiable, Profil};
use function AML\View\{Button, Text, VStack};

final class IntrospectionPage extends Page
{
    #[State] public string $message = 'Inspecter un profil pour commencer.';
    #[State] public string $methodes = '';

    public function body(): View
    {
        return VStack(
            Text($this->message)->id('resultat-introspection'),
            Text($this->methodes)->id('methodes-introspection'),
            Button('Inspecter le profil')->onClick(function (): void {
                $profil = new Profil('André');
                if (is_a($profil, Identifiable::class) && method_exists($profil, 'etiquette')) {
                    $proprietes = get_object_vars($profil);
                    $this->message = $profil->etiquette() . ' · ' . count($proprietes) . ' propriétés publiques';
                    $this->methodes = implode(', ', get_class_methods($profil));
                }
                // Le navigateur affiche le résultat de __debugInfo() dans sa console.
                var_dump($profil);
            }),
        );
    }
}
