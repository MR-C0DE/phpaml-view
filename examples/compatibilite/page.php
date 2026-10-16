<?php

declare(strict_types=1);

namespace Exemples\Compatibilite\Pages;

require_once __DIR__ . '/service.php';

use AML\View\{Page, State, View};
use function AML\View\{Button, Heading, Text, TextArea, VStack};
use function Exemples\Compatibilite\Services\preparerCatalogue;

final class CompatibilitePage extends Page
{
    #[State] public string $source = '{"date":"2026-10-15T12:00:00+00:00","articles":[{"code":"AB123","nom":"  Café   moulu  ","prixCentimes":1250,"quantite":2,"statut":"disponible"},{"code":"CD456","nom":"Thé","prixCentimes":700,"quantite":1,"statut":"reserve"},{"code":"EF789","nom":"Tasse","prixCentimes":500,"quantite":3,"statut":"disponible"}]}';
    #[State] public string $message = 'Prêt à préparer le catalogue.';
    #[State] public string $export = '';
    #[State] public string $erreur = '';
    #[State] public int $preparations = 0;

    public function preparer(): void
    {
        try {
            $catalogue = preparerCatalogue($this->source);
            $this->export = json_encode($catalogue, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $this->message = count($catalogue['articles']) . ' articles · '
                . number_format($catalogue['totalCentimes'] / 100, 2, ',', ' ')
                . ' $ · ' . $catalogue['date'];
            $this->erreur = '';
            $this->preparations++;
            echo 'Catalogue préparé : ' . $this->preparations . PHP_EOL;
        } catch (\Throwable $erreur) {
            $this->erreur = get_class($erreur) . ' : ' . $erreur->getMessage();
        }
    }

    public function body(): View
    {
        return VStack(
            Heading('Catalogue interactif'),
            TextArea('source')->id('source-catalogue')->value($this->source)->attribute('rows', '8'),
            Button('Préparer le catalogue')->onClick($this->preparer(...)),
            Text($this->message)->id('resume-catalogue'),
            Text($this->erreur)->id('erreur-catalogue'),
            Text($this->export)->id('export-catalogue'),
        )->gap(12);
    }
}
