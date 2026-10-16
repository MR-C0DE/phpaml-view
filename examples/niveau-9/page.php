<?php

declare(strict_types=1);

namespace App\Examples\Fichiers;

use AML\Engine\BrowserFiles;
use AML\View\{Page, Persisted, State, View};
use function AML\View\{VStack, Heading, Paragraph, Label, FileInput, Button, Text, CodeBlock};

final class FichiersPage extends Page
{
    #[State] public string $texte = '';
    #[State, Persisted(storage: 'local', key: 'niveau9.profil')]
    public array $profil = ['name' => 'André', 'age' => 30];
    #[State] public array $lignes = [];
    #[State] public array $fichiers = [];
    #[State] public string $erreur = '';
    #[State] public bool $lecture = false;

    public function body(): View
    {
        return VStack(
            Heading('Fichiers et données dans le navigateur', 1),
            Paragraph('Choisissez un fichier UTF-8. Son contenu est traité sur cette page.'),
            Label('Document texte', 'texte-file'),
            FileInput('texte', ['.txt'], files: $this->state('fichiers'))->id('texte-file')
                ->onChange(BrowserFiles::readText()->storeIn('texte')->errorIn('erreur')->loadingIn('lecture')),
            CodeBlock($this->texte)->id('texte-affiche'),
            Button('Ajouter une ligne')->onClick(function (): void { $this->texte .= "\nUne nouvelle ligne."; }),
            Button('Télécharger le texte')->onClick(BrowserFiles::downloadText($this->state('texte'), 'notes.txt')->errorIn('erreur')),

            Label('Profil JSON', 'profil-file'),
            FileInput('profil', ['.json'])->id('profil-file'),
            Button('Importer le JSON')->onClick(BrowserFiles::readJson('profil')->storeIn('profil')->errorIn('erreur')->loadingIn('lecture')),
            Text(fn (): string => 'Nom : ' . ($this->profil['name'] ?? 'Non renseigné'))->id('nom-affiche'),
            Button('Passer le nom en majuscules')->onClick(function (): void {
                if (isset($this->profil['name']) && gettype($this->profil['name']) === 'string') {
                    $this->profil['name'] = strtoupper($this->profil['name']);
                }
            }),
            Button('Télécharger le JSON')->onClick(BrowserFiles::downloadJson($this->state('profil'), 'profil.json')->errorIn('erreur')),

            Label('Tableau CSV', 'csv-file'),
            FileInput('csv', ['.csv'])->id('csv-file')
                ->onChange(BrowserFiles::readCsv()->storeIn('lignes')->errorIn('erreur')->loadingIn('lecture')),
            Text(fn (): string => 'Lignes CSV : ' . count($this->lignes))->id('csv-compte'),
            Button('Ajouter une ligne CSV')->onClick(function (): void { $this->lignes[] = ['André', 'Montréal']; }),
            Button('Télécharger le CSV')->onClick(BrowserFiles::downloadCsv($this->state('lignes'), 'tableau.csv')->errorIn('erreur')),
            Text(fn (): string => $this->lecture ? 'Lecture en cours…' : 'Prêt')->id('lecture-etat')->attribute('role', 'status'),
            Text($this->erreur)->id('erreur-affiche')->attribute('role', 'alert'),
        )->gap(12)->padding(24);
    }
}
