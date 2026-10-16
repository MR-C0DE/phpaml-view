<?php
declare(strict_types=1);

namespace Exemples\ProfilUnicode\Services;

final class Contact
{
    public function __construct(public string $label) {}
}

final class Detail
{
    public function __construct(public Contact $contact) {}
}

class Presentation
{
    public function titre(string $nom): string
    {
        return 'Bonjour ' . $nom;
    }

    public function detail(string $email): ?Detail
    {
        return $email === '' ? null : new Detail(new Contact($email));
    }
}

final class PresentationGroupe extends Presentation
{
    public function titre(string $nom): string
    {
        return 'Bienvenue à toute l’équipe de ' . $nom;
    }
}
