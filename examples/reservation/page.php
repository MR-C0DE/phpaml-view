<?php

declare(strict_types=1);

namespace Exemples\Reservation\Pages;

require_once __DIR__ . '/service.php';

use AML\View\{Page, State, View};
use function AML\View\{Button, Heading, Input, Text, VStack};
use function Exemples\Reservation\Services\preparer;

final class ReservationPage extends Page
{
    #[State] public string $date = '2026-10-15';
    #[State] public string $nuits = '3';
    #[State] public string $email = 'andre@example.com';
    #[State] public string $message = 'Prépare ta réservation.';
    #[State] public string $export = '';
    #[State] public string $erreur = '';

    public function valider(): void
    {
        try {
            $reservation = preparer($this->date, $this->nuits, $this->email);
            $this->export = json_encode($reservation, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $this->message = $reservation->arrivee->format('Y-m-d') . ' → '
                . $reservation->depart->format('Y-m-d') . ' · '
                . number_format($reservation->totalCentimes / 100, 2, ',', ' ') . ' $';
            $this->erreur = '';
            echo 'Réservation préparée pour ' . $reservation->email . PHP_EOL;
        } catch (\Throwable $erreur) {
            $this->erreur = $erreur->getMessage();
        }
    }

    public function body(): View
    {
        return VStack(
            Heading('Préparer une réservation'),
            Input('date')->value($this->date)->attribute('aria-label', 'Date d’arrivée'),
            Input('nuits')->value($this->nuits)->inputMode('numeric')->attribute('aria-label', 'Nombre de nuits'),
            Input('email')->value($this->email)->attribute('aria-label', 'Courriel'),
            Button('Préparer la réservation')->onClick($this->valider(...)),
            Text($this->message)->id('resume-reservation'),
            Text($this->erreur)->id('erreur-reservation'),
            Text($this->export)->id('export-reservation'),
        )->gap(12);
    }
}
