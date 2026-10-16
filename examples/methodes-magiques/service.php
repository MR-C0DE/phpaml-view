<?php
declare(strict_types=1);
namespace Exemples\Magie\Services;

final class Profil
{
    private array $valeurs = ['nom' => '', 'points' => 0];
    public function __construct(string $nom) { $this->valeurs['nom'] = $nom; }
    public function __get(string $nom): mixed { return $this->valeurs[$nom] ?? null; }
    public function __set(string $nom, mixed $valeur): void { $this->valeurs[$nom] = $valeur; }
    public function __isset(string $nom): bool { return isset($this->valeurs[$nom]); }
    public function __unset(string $nom): void { unset($this->valeurs[$nom]); }
    public function __invoke(int $bonus): int { $this->valeurs['points'] += $bonus; return $this->valeurs['points']; }
    public function __toString(): string { return $this->valeurs['nom'] . ' — ' . $this->valeurs['points'] . ' points'; }
    public function __clone(): void { $this->valeurs['nom'] .= ' (copie)'; }
    public function __call(string $nom, array $arguments): mixed
    {
        return match ($nom) {
            'resume' => (string) $this,
            default => throw new \BadMethodCallException('Méthode inconnue : ' . $nom),
        };
    }
    public static function __callStatic(string $nom, array $arguments): mixed
    {
        if ($nom === 'creer') { return new self($arguments[0] ?? 'Invité'); }
        throw new \BadMethodCallException('Méthode inconnue : ' . $nom);
    }
}
