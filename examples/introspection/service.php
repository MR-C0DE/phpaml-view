<?php
declare(strict_types=1);
namespace Exemples\Introspection\Services;

interface Identifiable { public function etiquette(): string; }

final class Profil implements Identifiable
{
    private int $points = 15;
    public string $ville = 'Montréal';

    public function __construct(public readonly string $nom) {}
    public function etiquette(): string { return $this->nom . ' — ' . $this->ville; }

    public function __debugInfo(): array
    {
        return ['profil' => $this->etiquette(), 'points' => $this->points];
    }
}
