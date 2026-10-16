<?php

declare(strict_types=1);
namespace Exemples\Pages;

use AML\View\{Page, State, View};
use function AML\View\{Button, Input, Text, VStack};

final class UnicodePage extends Page
{
    #[State] public string $message = 'André 🌍';

    public function body(): View
    {
        return VStack(
            Input('message')->value($this->message)->attribute('aria-label', 'Texte Unicode'),
            Text('Original : ' . $this->message),
            Text('Majuscules : ' . mb_strtoupper($this->message)),
            Text('Minuscules : ' . mb_strtolower($this->message)),
            Text('Titre : ' . mb_convert_case($this->message, MB_CASE_TITLE)),
            Text('Début : ' . mb_substr($this->message, 0, 5)),
            Text('Points de code : ' . mb_strlen($this->message)),
            Text('Octets UTF-8 : ' . strlen($this->message)),
            Button('Mettre en majuscules')->onClick($this->majuscules(...)),
        );
    }

    public function majuscules(): void
    {
        $this->message = mb_strtoupper($this->message);
        echo "Normalisé : {$this->message}\n";
    }
}
