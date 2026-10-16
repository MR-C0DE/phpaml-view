<?php

declare(strict_types=1);

namespace App\Examples\Erreurs;

use AML\View\{Page, State, View, Console};
use function AML\View\{VStack, Heading, Paragraph, Input, Label, Text, Button};

// Dans une application, ces classes/fonctions peuvent aussi être chargées par l’autoload.
require_once __DIR__ . '/service.php';

final class ErreursPage extends Page
{
    #[State] public string $saisie = '-1';
    #[State] public string $message = 'Saisissez votre âge.';
    #[State] public int $tentatives = 0;
    #[State] public string $verification = 'Pas encore vérifié.';

    public function body(): View
    {
        return VStack(
            Heading('Erreurs et exceptions', 1),
            Label('Âge', 'age'),
            Input('saisie')->id('age')->value($this->saisie)->inputMode('numeric'),
            Button('Valider')->onClick($this->valider(...)),
            Text($this->message)->id('message')->attribute('role', 'status'),
            Text("Tentatives : {$this->tentatives}")->id('tentatives'),
            Paragraph('Les messages de journalisation sont dans la console du navigateur.'),
            Button('Tester une assertion')->onClick($this->verifier(...)),
            Text($this->verification)->id('verification'),
            Paragraph('Le diagnostic suivant provoque volontairement une erreur non attrapée.'),
            Button('Déclencher le diagnostic')->onClick($this->diagnostic(...)),
        )->gap(12)->padding(24);
    }

    public function valider(): void
    {
        try {
            $age = validerAge($this->saisie);
            $this->message = "Âge accepté : {$age} ans.";
            echo $this->message . PHP_EOL;
        } catch (AgeInvalide $error) {
            $this->message = $error->getMessage();
            Console::warning('Validation', $error->getMessage(), $error->getCode());
        } finally {
            $this->tentatives++;
        }
    }

    public function verifier(): void
    {
        try {
            assert(false, 'Exemple d’assertion de développement.');
            $this->verification = 'Assertions désactivées lors de la compilation.';
        } catch (\AssertionError $error) {
            $this->verification = $error->getMessage();
        }
    }

    public function diagnostic(): void
    {
        $this->tentatives += 100;
        lancerDiagnostic();
    }
}
