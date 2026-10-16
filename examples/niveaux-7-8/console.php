<?php

declare(strict_types=1);

// Exécuter avec PHP dans un terminal. Ce fichier ne fait pas partie de la page.
$nom = $argv[1] ?? null;
if ($nom === null) {
    fwrite(STDOUT, 'Votre nom : ');
    $ligne = fgets(STDIN);
    if ($ligne === false) { fwrite(STDERR, "Aucune saisie reçue.\n"); exit(1); }
    $nom = trim($ligne);
}
if (trim($nom) === '') { fwrite(STDERR, "Le nom est obligatoire.\n"); exit(2); }

while (true) {
    fwrite(STDOUT, "1. Dire bonjour\n2. Doubler un entier\n0. Quitter\nVotre choix : ");
    $ligne = fgets(STDIN);
    if ($ligne === false) exit(0);
    $choix = trim($ligne);
    switch ($choix) {
        case '1':
            echo 'Bonjour ' . trim($nom) . PHP_EOL;
            break;
        case '2':
            fwrite(STDOUT, 'Entier entre 0 et 1000 : ');
            $ligne = fgets(STDIN);
            if ($ligne === false) exit(1);
            $saisie = trim($ligne);
            if (!ctype_digit($saisie) || strlen($saisie) > 4 || (int) $saisie > 1000) {
                fwrite(STDERR, "Saisie invalide.\n");
                break;
            }
            $nombre = (int) $saisie;
            echo $nombre * 2 . PHP_EOL;
            break;
        case '0': exit(0);
        default: fwrite(STDERR, "Choix inconnu.\n");
    }
}
