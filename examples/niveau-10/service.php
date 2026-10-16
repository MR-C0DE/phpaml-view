<?php

declare(strict_types=1);

namespace App\Examples\Erreurs;

final class AgeInvalide extends \InvalidArgumentException {}

function validerAge(string $saisie): int
{
    $texte = trim($saisie);
    if ($texte === '' || !ctype_digit($texte) || strlen($texte) > 3) {
        throw new AgeInvalide('Entrez un âge entier entre 0 et 130.', 117);
    }
    $age = (int) $texte;
    if ($age > 130) { throw new AgeInvalide('L’âge doit être compris entre 0 et 130.', 118); }
    return $age;
}

function panneInterne(): never
{
    throw new \RuntimeException('Service indisponible <détail>', 500);
}

function lancerDiagnostic(): void
{
    panneInterne();
}
