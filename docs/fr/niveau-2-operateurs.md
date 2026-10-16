# Niveau 2 — Opérateurs

Implémenté dans les sources d’AML View **0.1.0-beta.13** avec Engine
**0.1.0-beta.9**, à mettre à jour ensemble. Ces versions sont en préparation.
Les opérateurs fonctionnent dans les événements et effets PHP compilés. Dans
`body()`, avant `return`, ils s’exécutent directement en PHP au rendu ; les
variables locales ne deviennent pas automatiquement réactives.

| Point | Prise en charge |
| --- | --- |
| 26 | `+`, `-`, `*`, `/`, signes unaires `+` et `-`. |
| 27 | `%`, signe du dividende, conversion des opérandes en entiers. |
| 28 | `**`, puissances associées à droite et exposants négatifs. |
| 29 | `=`, réaffectations, affectations en chaîne et affectations dans une expression. |
| 30 | `+=`, `-=`, `*=`, `/=`, `.=` ; également `%=` et `**=`. |
| 31 | `++$n`, `$n++`, `--$n`, `$n--`, sur variables locales et propriétés `#[State]`. |
| 32 | `===`, `!==`, `<`, `>`, `<=`, `>=`. |
| 33 | `==`, `!=`, `<>` distincts de `===`, `!==` ; règles de comparaison PHP 8. |
| 34 | `&&`, `||`, `!`, avec conversion booléenne PHP et court-circuit. |
| 35 | Ternaire complet et court `?:`, y compris les formes imbriquées parenthésées. |
| 36 | `??`, chaînes de valeurs de secours, lecture absente sans avertissement ; également `??=`. |
| 37 | Priorités et associativités PHP 8, parenthèses, concaténation moins prioritaire que l’addition. |

## Exemple

Dans `body()`, le code suivant produit `15` et `Accès autorisé` au rendu :

```php
$total = 10 + 5;
$age = 18;
$isActive = true;
$isAllowed = $age >= 18 && $isActive;
$message = $isAllowed ? 'Accès autorisé' : 'Accès refusé';

return VStack(Text($total), Text($message));
```

Dans un événement, les propriétés `#[State]` sont lues au moment du clic :

```php
Button('Vérifier')->onClick(function (): void {
    $age = $this->age;
    $isActive = $this->isActive;
    $isAllowed = $age >= 18 && $isActive;
    $this->message = $isAllowed ? 'Accès autorisé' : 'Accès refusé';
});
```

Les propriétés `age`, `isActive` et `message` doivent être déclarées avec `#[State]`.
Les conversions et vérifications des propriétés typées suivent les règles du
[niveau 1](niveau-1-variables-types.md).

## Valeurs et types

```php
1 == '1';     // true
1 === '1';    // false
1 == 1.0;     // true
1 === 1.0;    // false
0 == 'texte'; // false, selon les règles PHP 8
null == 0;    // true
null == '0';  // false

$n = 1;
$avant = $n++; // avant = 1, n = 2
$apres = ++$n; // apres = 3, n = 3

2 ** 3 ** 2;  // 512
-2 ** 2;      // -4
(-2) ** 2;    // 4
(int) 2.5 ** 2; // 6
'Résultat : ' . 2 + 3 * 4; // 'Résultat : 14'
```

L’inspection du type d’une expression ne la réexécute pas :
`gettype($n++)` incrémente `$n` une seule fois. La division conserve la différence
PHP entre un résultat entier et flottant ; `%` produit un entier et peut émettre
un avertissement si ses opérandes perdent une fraction pendant leur conversion.

## Évaluation conditionnelle

```php
false && (1 / 0);       // false, sans division
true || (1 / 0);        // true, sans division
true ? 42 : (1 / 0);    // 42, sans division
1 ?? (1 / 0);           // 1, sans division
$absent ?? 'secours';   // 'secours', sans lecture invalide
'0' ?: 'secours';       // 'secours'
'0' ?? 'secours';       // '0'
```

`??` ne remplace que `null` ou une valeur absente. Il conserve `false`, `0`,
`'0'` et `''`. Les branches ignorées ne produisent aucune mutation. Une variable
inconnue effectivement lue hors de `??` interrompt l’action, conformément au
choix de diagnostics explicites du niveau 1.

Les affectations imbriquées renvoient la valeur effectivement affectée, y compris
sa conversion éventuelle vers le type d’une propriété. Les erreurs d’exécution
conservent leur fichier et leur ligne PHP, et une séquence synchrone restaure son
état en cas d’échec. Division/modulo par zéro et opérandes incompatibles sont
signalés explicitement.

## Périmètre et vérification

Les [compléments de langage](fonctions-avancees.md) ajoutent `and`, `or`, `xor`,
les décalages `<<`/`>>`, `~` et les affectations correspondantes. Les opérations
bit à bit respectent les limites de précision et de chaînes du navigateur.

Cette étape porte sur les opérateurs appliqués aux types simples du niveau 1.
Les limites de transport restent les mêmes : entiers numériques exacts entre
`-9 007 199 254 740 991` et `9 007 199 254 740 991`, chaînes UTF-8 et états flottants
finis. Les comparaisons de deux chaînes contenant des entiers PHP 64 bits restent
exactes sans convertir ces chaînes en états numériques. L’arithmétique sur les
objets et l’union de tableaux avec `+` ne font pas partie de cette étape.

Éviter les expressions dont PHP ne garantit pas l’ordre d’évaluation, comme
`$n + $n++`. Écrire des instructions séparées rend le résultat déterministe.
Les constructions rejetées par le parseur PHP, notamment certaines chaînes de
comparaisons ou ternaires non parenthésés, sont rejetées avant le rendu.

`composer test` vérifie le comportement PHP et le simulateur View.
`composer test:language-runtime` compare Engine à PHP natif : exemple d’accès,
priorités, mutations, courts-circuits, erreurs et matrice de comparaisons
scalaires. Les tests navigateur vérifient l’interaction avec les événements et
les liaisons de texte.

Références : [priorité des opérateurs](https://www.php.net/manual/en/language.operators.precedence.php),
[comparaisons](https://www.php.net/manual/en/language.operators.comparison.php),
[incrémentation et décrémentation](https://www.php.net/manual/en/language.operators.increment.php).
