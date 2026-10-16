<?php
declare(strict_types=1);
namespace Exemples\Pages;
use AML\View\{Page, State, View};
use function AML\View\{Button, Heading, Label, Text, TextArea, VStack};

final class EvalPage extends Page
{
    #[State] public int $compteur = 0;
    #[State] public string $code = '$this->compteur++; return "Bonjour André : " . $this->compteur;';
    #[State] public string $message = 'Prêt.';

    public function body(): View
    {
        return VStack(
            Heading('Évaluer du PHP dans le navigateur', 1),
            Label('Code PHP, sans balise <?php', 'code'),
            TextArea('code')->id('code')->value($this->code)->attribute('rows', '5'),
            Button('Exécuter')->onClick($this->executer(...)),
            Button('Ajouter avec du code fixe')->onClick(function (): void {
                eval('$this->compteur += 10;');
            }),
            Text($this->message)->id('message')->attribute('role', 'status'),
            Text("Compteur : {$this->compteur}")->id('compteur'),
        )->gap(12)->padding(24);
    }

    public function executer(): void
    {
        try {
            $resultat = eval($this->code);
            $this->message = is_string($resultat) ? $resultat : var_export($resultat, true);
            echo $this->message . PHP_EOL;
        } catch (\Throwable $erreur) {
            $this->message = $erreur->getMessage();
        }
    }
}
