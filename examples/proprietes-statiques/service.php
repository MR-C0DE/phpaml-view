<?php
declare(strict_types=1);
namespace Exemples\Statiques\Services;

final class Compteur
{
    private static int $valeur = 0;
    public static function suivant(): int { return ++self::$valeur; }
    public static function lire(): int { return self::$valeur; }
    public static function remettreAZero(): void { self::$valeur = 0; }
}
