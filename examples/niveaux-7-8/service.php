<?php

declare(strict_types=1);
namespace Exemples\Saisie;

/** Montant positif avec au plus deux décimales, converti sans calcul flottant. */
function centimes(string $saisie): ?int
{
    $saisie = trim(str_replace(',', '.', $saisie));
    if ($saisie === '') { return null; }
    $parties = explode('.', $saisie);
    if (count($parties) > 2) { return null; }
    $entier = $parties[0];
    $fraction = $parties[1] ?? '';
    if ($entier === '' && $fraction === '') { return null; }
    if ($entier === '') { $entier = '0'; }
    if (!ctype_digit($entier) || strlen($entier) > 7) { return null; }
    if ($fraction !== '' && (!ctype_digit($fraction) || strlen($fraction) > 2)) { return null; }
    if (strlen($fraction) === 1) { $fraction .= '0'; }
    return (int) $entier * 100 + (int) $fraction;
}

function montant(int $centimes): string
{
    $fraction = $centimes % 100;
    return intdiv($centimes, 100) . ',' . ($fraction < 10 ? '0' : '') . $fraction . ' €';
}
