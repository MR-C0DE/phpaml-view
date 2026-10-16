<?php
declare(strict_types=1);
namespace Exemples\ClassesAnonymes\Services;

interface Nommable { public function nom(): string; }
interface Evaluable { public function points(): int; }

// L'objet doit respecter les deux interfaces en même temps.
function presenter(Nommable&Evaluable $profil): string
{
    return $profil->nom() . ' — ' . $profil->points() . ' points';
}
