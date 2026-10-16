<?php
declare(strict_types=1);

namespace Exemples\ProfilUnicode\Pages;

require_once __DIR__ . '/service.php';

use AML\View\{Page, State, View};
use Exemples\ProfilUnicode\Services\{Presentation, PresentationGroupe};
use function AML\View\{Button, Heading, Input, Text, VStack};

final class ProfilUnicodePage extends Page
{
    #[State] public string $nom = "Andre\u{301} 👨‍👩‍👧‍👦";
    #[State] public string $email = 'andre@example.com';
    #[State] public string $quantite = '2';
    #[State] public string $message = 'Prépare ton profil.';
    #[State] public string $montant = '';
    #[State] public string $lien = '';
    #[State] public string $export = '';
    #[State] public string $erreur = '';

    public function preparer(): void
    {
        try {
            $valeurs = filter_var_array(
                ['quantite' => $this->quantite, 'email' => $this->email],
                [
                    'quantite' => [
                        'filter' => FILTER_VALIDATE_INT,
                        'options' => ['min_range' => 1, 'max_range' => 20],
                    ],
                    'email' => FILTER_VALIDATE_EMAIL,
                ],
            );
            if ($valeurs['quantite'] === false) {
                throw new \InvalidArgumentException('Choisis une quantité de 1 à 20.');
            }
            if ($this->email !== '' && $valeurs['email'] === false) {
                throw new \InvalidArgumentException('Courriel invalide.');
            }
            $nom = \Normalizer::normalize(trim($this->nom));
            if (grapheme_strlen($nom) === 0) {
                throw new \InvalidArgumentException('Le nom est obligatoire.');
            }

            $services = ['simple' => Presentation::class, 'groupe' => PresentationGroupe::class];
            $classe = $services[$valeurs['quantite'] > 2 ? 'groupe' : 'simple'];
            $service = new $classe();
            $contact = $service->detail($this->email)?->contact->label ?? 'Sans courriel';
            $this->message = $service->titre($nom) . ' · ' . $contact;

            $totalCentimes = 1250 * $valeurs['quantite'];
            $format = new \NumberFormatter('fr_CA', \NumberFormatter::CURRENCY);
            $this->montant = $format->formatCurrency($totalCentimes / 100, 'CAD');
            $this->lien = '/profil?' . http_build_query(
                ['nom' => $nom, 'quantite' => $valeurs['quantite']],
                encoding_type: PHP_QUERY_RFC3986,
            );
            $this->export = base64_encode(json_encode(
                ['nom' => $nom, 'totalCentimes' => $totalCentimes],
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ));
            $this->erreur = '';
            echo 'Profil préparé' . PHP_EOL;
        } catch (\Throwable $erreur) {
            $this->erreur = $erreur->getMessage();
        }
    }

    public function body(): View
    {
        return VStack(
            Heading('Un profil qui respecte les caractères visibles'),
            Input('nom')->value($this->nom)->attribute('aria-label', 'Nom'),
            Input('email')->value($this->email)->attribute('aria-label', 'Courriel'),
            Input('quantite')->value($this->quantite)->attribute('aria-label', 'Quantité'),
            Text(grapheme_substr($this->nom, 0, 8))->id('apercu-profil'),
            Text('Caractères visibles : ' . grapheme_strlen($this->nom))->id('taille-profil'),
            Button('Préparer')->onClick($this->preparer(...)),
            Text($this->message)->id('message-profil'),
            Text($this->montant)->id('montant-profil'),
            Text($this->lien)->id('lien-profil'),
            Text($this->export)->id('export-profil'),
            Text($this->erreur)->id('erreur-profil'),
        )->gap(12);
    }
}
