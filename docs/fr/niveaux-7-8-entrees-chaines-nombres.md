# Niveaux 7 et 8 — Entrées, chaînes et nombres

Ces ajouts appartiennent à View `0.1.0-beta.13` et Engine `0.1.0-beta.9`,
en développement. Le code de `body()` avant `return` s'exécute en PHP sur le
serveur. Le code d'un événement est compilé puis exécuté dans le navigateur.

## Niveau 7 : terminal PHP et navigateur

La console des outils de développement reçoit les sorties d'Engine. Elle ne
constitue pas un terminal PHP et ne fournit pas de flux `STDIN`.

| Point | Terminal PHP | Événement View/Engine |
|---|---|---|
| 86 | `STDIN`, `STDOUT`, `STDERR` sont des flux du processus. | Les flux ne sont pas disponibles ; `echo`, `var_dump()` et `Console` utilisent la console du navigateur. |
| 87 | `fgets(STDIN)` attend une ligne. | Utiliser `Input`, un état et un événement déclenché par l'utilisateur. |
| 88 | `trim()` nettoie la saisie. | `trim()`, `ltrim()` et `rtrim()` sont compilés. |
| 89 | Les conversions PHP sont disponibles. | Convertir après validation, par exemple `(int) $saisie`. |
| 90 | Contrôler la valeur avant de l'utiliser. | `is_numeric()`, `ctype_digit()`, comparaisons et conditions sont disponibles. |
| 91 | Une boucle peut afficher un menu et lire le prochain choix. | Utiliser `Select` ou des boutons ; chaque action déclenche un nouvel événement. |
| 92 | `$argv` et `$argc` décrivent la commande. | Aucun argument de processus ; utiliser les états, formulaires ou paramètres de route. |
| 93 | `exit(0)` signifie succès ; un autre code indique une erreur. | `return` termine un événement ; une erreur produit le diagnostic Engine habituel. |

Dans un événement, les flux standard, `fgets()`, `fwrite()`, `readline()`, les
variables CLI non déclarées, `exit` et `die` sont refusés avec le fichier et la
ligne PHP et une indication de l'alternative navigateur. Un `$argv` local
explicitement fourni reste une variable ordinaire, sans accès au processus.
Ces restrictions ne concernent pas un script PHP lancé dans un terminal.

Deux exemples séparés sont fournis :

- [page.php](../../examples/niveaux-7-8/page.php) : champs, validation, menu,
  calcul en centimes, tirage et sorties console après un clic ;
- [service.php](../../examples/niveaux-7-8/service.php) : conversion d'un montant
  texte vers des centimes sans passer par un flottant ;
- [console.php](../../examples/niveaux-7-8/console.php) : programme terminal
  avec les trois flux, les arguments, un menu et les codes de sortie.

Depuis la racine du paquet View :

```sh
php examples/niveaux-7-8/console.php André
```

Les sorties `echo` de la page restent dans la console du navigateur. L'affichage
du résultat utilise explicitement un état et `Text`. Les ajouts `print`,
`printf`, `vprintf`, `print_r` et `var_export` sont désormais pris en charge ;
voir les [sorties de diagnostic](sorties-console.md).

## Niveau 8 : fonctions compilées

| Point | Fonctions ou pratique prise en charge |
|---|---|
| 94 | `strlen()` |
| 95 | `str_contains()`, `str_starts_with()`, `str_ends_with()` |
| 96 | `substr()` avec offsets et longueurs positifs, négatifs ou longueur `null` |
| 97 | `str_replace()` avec chaînes ou tableaux, y compris `&$count` |
| 98 | `explode()`, limite positive, nulle ou négative |
| 99 | `implode()` ; ordre des éléments conservé |
| 100 | `strtolower()`, `strtoupper()`, `ucfirst()`, `lcfirst()`, `ucwords()` |
| 101 | `trim()`, `ltrim()`, `rtrim()` et masque de caractères |
| 102 | `round()`, `ceil()`, `floor()`, `abs()` |
| 103 | `random_int($min, $max)` avec le générateur cryptographique du navigateur |
| 104 | `number_format()` avec précision et séparateurs personnalisés |
| 105 | Montants en centimes entiers ; `intdiv()` et `%` pour les calculs exacts |

Les arguments positionnels, nommés et dépliés sont évalués une seule fois dans
l'ordre écrit. Les types respectent le `strict_types` du fichier d'appel.
Pour passer une fonction interne comme callback, utiliser une flèche :
`array_map(fn (string $s): string => trim($s), $valeurs)`.

### Octets et accents

Les opérations suivent les chaînes PHP : `strlen('André')` vaut **6 octets**.
`substr('André', 4)` renvoie `é`. En revanche, `substr('é', 0, 1)` coupe le
caractère en deux : PHP peut produire cette chaîne binaire, mais Engine la
refuse avec `PHP_INVALID_UTF8`, car les chaînes frontend doivent rester UTF-8.
Un BOM UTF-8 en début de chaîne est conservé.

La casse suit les fonctions PHP standard depuis PHP 8.2 : elle transforme les
lettres ASCII. `strtoupper('André')` donne `ANDRé`. Les variantes `mb_*`, les
locales et le comptage de graphèmes ne font pas partie de cet ajout.
Le nettoyage par défaut conserve les espaces insécables, comme PHP.

### Arrondis et montants

`round()` accepte les quatre constantes entières `PHP_ROUND_HALF_UP`,
`PHP_ROUND_HALF_DOWN`, `PHP_ROUND_HALF_EVEN`, `PHP_ROUND_HALF_ODD`.
Les objets enum `RoundingMode` ne sont pas transportés dans les événements.
Les arrondis négatifs et les précisions négatives sont pris en charge.
Le manifeste indique la version PHP : Engine adapte les arrondis antérieurs
à PHP 8.4 et les décimales négatives de `number_format()` antérieures à PHP 8.3.

`number_format()` renvoie du texte pour l'affichage. Pour les calculs monétaires,
l'exemple conserve le prix et le total en centimes :

```php
$prix = 10;                  // 0,10 €
$total = $prix * 3;          // 30 centimes exactement
$euros = intdiv($total, 100);
$centimes = $total % 100;
```

Le service valide le montant avant conversion : au plus sept chiffres avant
la virgule, deux après, aucune notation exponentielle. La quantité est limitée
à 1–100. Pour une remise ou une taxe, définir explicitement à quelle étape et
selon quelle règle arrondir les fractions de centime. Les intermédiaires doivent
rester dans l'intervalle des entiers exacts d'Engine, ±9 007 199 254 740 991.

`random_int()` produit un entier dans les bornes incluses. Ses tirages ne
reproduisent pas ceux du serveur. Les générateurs `rand()`/`mt_rand()` avec leur
état et leur initialisation ne sont pas ajoutés ici.

## Erreurs et vérification

Les arguments manquants, inconnus ou incompatibles, un séparateur vide pour
`explode()`, des bornes aléatoires inversées, une division entière par zéro et
un compteur non assignable sont signalés. Engine conserve le fichier et la
ligne de l'appel ; une erreur restaure les états de l'événement synchrone et
les événements suivants restent utilisables.

Les nouvelles opérations limitent une chaîne produite à 1 Mio, un découpage
à 10 000 morceaux et `number_format()` à 1 000 décimales. Un dépassement produit
`PHP_BUILTIN_LIMIT`. Les nombres doivent rester finis. Les limites habituelles
des tableaux, appels et boucles restent applicables.

`composer test` vérifie le compilateur et ViewTest.
`composer test:language-runtime` compare les résultats avec PHP natif, y compris
les types, avertissements, références et erreurs. Il vérifie également les
flux et codes de sortie du programme terminal. Les tests navigateur Engine
vérifient la saisie, la console, les erreurs et, avec le dépôt View voisin,
l'exemple complet compilé.

Références : [chaînes et octets PHP](https://www.php.net/manual/fr/function.strlen.php),
[arrondis](https://www.php.net/manual/fr/function.round.php),
[formatage des nombres](https://www.php.net/manual/fr/function.number-format.php).
