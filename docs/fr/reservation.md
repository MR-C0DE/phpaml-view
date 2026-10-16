# Exemple : réservation, dates et JSON

La [page](../../examples/reservation/page.php) appelle un
[service séparé](../../examples/reservation/service.php) depuis un événement
frontend. Elle demande une date d'arrivée, un nombre de nuits et une adresse
courriel. Le clic prépare le séjour dans le navigateur et affiche son résumé
ainsi qu'un export JSON.

## Validation et calcul

`filter_var()` valide l'adresse et le nombre entier de nuits, limité ici à 1–20.
`DateTimeImmutable::createFromFormat('!Y-m-d', ...)` lit la date dans le fuseau
`America/Toronto`. `getLastErrors()` permet de refuser aussi une date que PHP
normaliserait avec un avertissement, comme le 30 février.

`DateInterval('P3D')` représente trois jours calendaires. L'ajout et le parcours
par `DatePeriod` conservent les dates du séjour lors du passage à l'heure d'été.
Le calcul ne remplace pas chaque journée par une durée fixe de 86 400 secondes.
Le tarif est calculé en centimes entiers, via une méthode transmise à
`call_user_func_array()` avec des paramètres nommés.

## Export et affichage

L'objet `Reservation` implémente `JsonSerializable`. Sa méthode `jsonSerialize()`
fournit les dates, les nuits du séjour et le montant en centimes :

```php
$reservation = preparer($this->date, $this->nuits, $this->email);
$this->export = json_encode(
    $reservation,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
);
```

L'objet reste local à l'événement. Les composants `Text` reçoivent les chaînes
de résumé, d'erreur et de JSON au moyen de leurs `State`. `echo` écrit uniquement
dans la console du navigateur. Une saisie invalide affiche son erreur et permet
à l'utilisateur de corriger le formulaire sans recréer le composant.

## Vérification

Les tests `reservation-program.php` et `.mjs` comparent PHP, ViewTest et Engine
pour un séjour ordinaire, une année bissextile, un changement d'heure et plusieurs
erreurs de saisie. Le test navigateur `reservation-program.spec.mjs` utilise la
page complète, modifie les champs et vérifie la récupération après une erreur.
