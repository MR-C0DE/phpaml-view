<?php
declare(strict_types=1);
namespace Exemples\Objets\Services;

interface Tarif
{
    public function totalCentimes(): int;
}

class Ligne implements Tarif
{
    public function __construct(public readonly string $nom, protected int $prixCentimes, public int $quantite = 1)
    {
        if ($prixCentimes < 0 || $quantite < 1) {
            throw new \InvalidArgumentException('Prix ou quantité invalide.');
        }
    }

    public function totalCentimes(): int
    {
        return $this->prixCentimes * $this->quantite;
    }
}

final class LigneRemisee extends Ligne
{
    public function __construct(string $nom, int $prixCentimes, int $quantite = 1, private int $remise = 10)
    {
        parent::__construct($nom, $prixCentimes, $quantite);
        if ($remise < 0 || $remise > 100) {
            throw new \InvalidArgumentException('La remise doit être comprise entre 0 et 100.');
        }
    }

    public function totalCentimes(): int
    {
        return intdiv(parent::totalCentimes() * (100 - $this->remise), 100);
    }
}

final class Panier
{
    private array $lignes = [];

    public function ajouter(Tarif $ligne): self
    {
        $this->lignes[] = $ligne;
        return $this;
    }

    public function resume(): array
    {
        $total = 0;
        foreach ($this->lignes as $ligne) {
            $total += $ligne->totalCentimes();
        }
        return ['lignes' => count($this->lignes), 'totalCentimes' => $total];
    }
}
