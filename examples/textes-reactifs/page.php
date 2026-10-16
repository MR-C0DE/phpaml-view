<?php

declare(strict_types=1);
namespace App\Examples\TextesReactifs;

use AML\View\{Page, State, View};
use function AML\View\{VStack, Heading, Text, Paragraph, Strong, Button, Label, Input, CodeBlock, Link};

final class TextesPage extends Page
{
    #[State] public string $message = 'André';
    #[State] public int $quantite = 2;
    #[State] public bool $actif = true;
    #[State] public array $profil = ['ville' => 'Montréal'];

    public function body(): View
    {
        $prefixe = 'Bonjour';

        return VStack(
            Heading('Les textes réactifs', 1),
            Label('Votre message', 'message'),
            Input('text', 'message')->id('message')->value($this->message),

            Text($this->message),
            Paragraph('Texte : ' . $this->message),
            Heading('{message} et aussi {message}', 2),
            Text("$prefixe {$this->message} !"),
            Paragraph('Bienvenue ', Strong($this->message), ' !'),
            Text('Ville : {profil.ville}'),
            Text($this->actif ? 'Actif : ' . $this->message : 'Inactif'),
            Text('Quantité : ' . $this->quantite),
            Text('Total : ' . number_format($this->quantite * 12.50, 2, ',', ' ') . ' $'),
            Text(fn (): string => $prefixe . ' ' . strtoupper($this->message)),
            Link('Profil de ' . $this->message, '#profil'),
            Text('Accolades littérales : {{message}}'),
            CodeBlock("Message : {$this->message}\nQuantité : {$this->quantite}"),

            Button('Ajouter une unité')->onClick(function (): void { $this->quantite++; }),
            Button('Changer le message')->onClick(function (): void {
                $this->message = 'Bonjour tout le monde';
                $this->profil['ville'] = 'Québec';
            }),
            Button('Activer / désactiver')->onClick(function (): void { $this->actif = !$this->actif; }),
        )->gap(12)->padding(24);
    }
}
