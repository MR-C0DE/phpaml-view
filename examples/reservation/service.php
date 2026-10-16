<?php

declare(strict_types=1);

namespace Exemples\Reservation\Services;

final class Tarif
{
    public static function total(int $nuits, int $prixCentimes): int
    {
        return $nuits * $prixCentimes;
    }
}

final class Reservation implements \JsonSerializable
{
    public function __construct(
        public readonly string $email,
        public readonly \DateTimeImmutable $arrivee,
        public readonly \DateTimeImmutable $depart,
        public readonly int $totalCentimes,
    ) {}

    public function jsonSerialize(): array
    {
        $nuits = [];
        foreach (new \DatePeriod($this->arrivee, new \DateInterval('P1D'), $this->depart) as $jour) {
            $nuits[] = $jour->format('Y-m-d');
        }
        return [
            'email' => $this->email,
            'arrivee' => $this->arrivee->format('Y-m-d'),
            'depart' => $this->depart->format('Y-m-d'),
            'derniereNuit' => $this->depart->sub(new \DateInterval('P1D'))->format('Y-m-d'),
            'nuits' => $nuits,
            'nombreNuits' => (int) $this->arrivee->diff($this->depart)->format('%a'),
            'totalCentimes' => $this->totalCentimes,
        ];
    }
}

function preparer(string $date, string $nombreNuits, string $email): Reservation
{
    $email = trim($email);
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new \InvalidArgumentException('Adresse courriel invalide.');
    }
    $nuits = filter_var($nombreNuits, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 20],
    ]);
    if ($nuits === false) {
        throw new \InvalidArgumentException('Choisis entre 1 et 20 nuits.');
    }
    $arrivee = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('America/Toronto'));
    $erreurs = \DateTimeImmutable::getLastErrors();
    if ($arrivee === false || ($erreurs !== false && ($erreurs['warning_count'] > 0 || $erreurs['error_count'] > 0))) {
        throw new \InvalidArgumentException('Date invalide : utilise AAAA-MM-JJ.');
    }
    $depart = $arrivee->add(new \DateInterval('P' . $nuits . 'D'));
    $calculer = Tarif::total(...);
    if (!is_callable($calculer)) {
        throw new \LogicException('Le calcul du tarif est indisponible.');
    }
    $total = call_user_func_array($calculer, ['prixCentimes' => 1250, 'nuits' => $nuits]);
    return new Reservation($email, $arrivee, $depart, $total);
}
