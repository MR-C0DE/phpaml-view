# Calculs décimaux exacts avec BCMath

View et Engine prennent en charge les **14 fonctions procédurales BCMath**,
dans les événements, les services compilés, les callbacks et les textes
réactifs. Elles figurent dans le [catalogue natif complet](compatibilite.md#catalogue-natif).
PHP calcule le rendu initial ; Engine effectue ensuite les calculs dans le
navigateur, sans requête serveur et sans convertir les montants en flottants.

L'extension PHP `bcmath` est nécessaire pour le rendu initial et ViewTest.
Dans Engine, les valeurs sont représentées par un entier `BigInt` et une
échelle décimale. Seules les chaînes de résultat entrent dans les `State`.

## Conserver les montants sous forme de chaînes

```php
#[State] public string $prix = '0.10';
#[State] public string $supplement = '0.20';

public function body(): View
{
    return VStack(
        Text('Total : ' . bcadd($this->prix, $this->supplement, 2)),
        Text(fn () => bcmul($this->prix, '3', 2)),
    );
}
```

Les résultats sont respectivement `'0.30'` et `'0.30'`. Une valeur telle que
`'9007199254740993.01'` reste exacte. Écrire les opérandes entre guillemets dès
leur création : transformer en chaîne un calcul déjà fait avec des flottants
ne restaure pas sa précision. Conserver aussi des chaînes dans JSON et les
réponses d'API. Pour comparer deux montants, utiliser `bccomp()`.

`number_format()`, les conversions `(float)` et les opérateurs arithmétiques
ordinaires ne préservent pas ce contrat décimal. Pour l'affichage, concaténer
directement la chaîne obtenue avec le suffixe voulu.

## Fonctions disponibles

| Fonction | Résultat |
| --- | --- |
| `bcadd`, `bcsub`, `bcmul`, `bcdiv` | Addition, soustraction, multiplication, division ; chaîne décimale |
| `bcmod` | Reste signé ; chaîne décimale |
| `bcdivmod` | Tableau `[quotient entier, reste décimal]` |
| `bcpow` | Puissance avec exposant entier, éventuellement négatif |
| `bcpowmod` | Puissance modulaire sur des valeurs entières |
| `bcsqrt` | Racine carrée décimale |
| `bccomp` | Entier `-1`, `0` ou `1` |
| `bcscale` | Lecture ou modification de l'échelle par défaut ; renvoie l'ancienne échelle |
| `bcfloor`, `bcceil` | Arrondi inférieur ou supérieur ; chaîne entière |
| `bcround` | Arrondi décimal explicite ; chaîne |

`bcdivmod`, `bcfloor`, `bcceil` et `bcround` nécessitent PHP 8.4. La classe
`BcMath\Number` reste hors de cette
étape. Les arguments nommés, les valeurs par défaut, `...`, les callables
natifs (`bcadd(...)`) et les fonctions de service déjà chargées sont acceptés.

## Tronquer et arrondir

L'échelle détermine le nombre de décimales du résultat. Les opérations ordinaires
tronquent vers zéro ; pour arrondir, utiliser `bcround()` :

```php
bcadd('1.005', '0', 2);                        // '1.00'
bcround('1.005', 2);                          // '1.01'
bcround('1.005', 2, RoundingMode::HalfEven);   // '1.00'
bcround('-250', -2, RoundingMode::HalfOdd);   // '-300'
```

Les huit cas `RoundingMode` sont acceptés : `HalfAwayFromZero`,
`HalfTowardsZero`, `HalfEven`, `HalfOdd`, `TowardsZero`, `AwayFromZero`,
`NegativeInfinity` et `PositiveInfinity`. Ces valeurs peuvent être conservées
dans des variables et tableaux locaux, capturées par une closure et passées à
un paramètre de service typé `RoundingMode`. Elles gardent leur identité et ne
peuvent pas être stockées dans un `State`. Les constantes entières
`PHP_ROUND_HALF_*` ne sont pas des arguments valides de `bcround()`.

## Échelle par défaut et isolation

```php
$ancienne = bcscale(4);
$quotient = bcdiv('1', '3'); // '0.3333'
bcscale($ancienne);
```

L'échelle PHP au moment de la compilation est conservée dans les expressions.
Dans Engine, `bcscale(4)` s'applique aux appels suivants du même événement, y
compris dans les services et les callbacks. Chaque nouvel événement repart de
l'échelle compilée. Cela évite qu'une action ou un composant modifie les calculs
d'un autre. ViewTest reproduit cette isolation sans changer la configuration
BCMath du processus PHP qui exécute les tests.

Chaque texte réactif a aussi son propre contexte. `bcscale()` et `bcscale(null)`
peuvent lire l'échelle ; changer cette échelle dans un texte réactif est refusé.
Les références à `bcscale` comme callable y sont également refusées. Une échelle
explicite, comme `bcadd($a, $b, 2)`, rend le comportement de l'affichage plus clair.

## Erreurs et limites

Les erreurs natives conservent leur catégorie et leur origine PHP :

```php
try {
    $this->resultat = bcdiv($this->montant, $this->diviseur, 2);
} catch (DivisionByZeroError $error) {
    $this->erreur = 'Le diviseur doit être différent de zéro.';
} catch (ValueError $error) {
    $this->erreur = 'Entre un nombre décimal avec un point.';
}
```

Les espaces dans les nombres, la virgule décimale et la notation exponentielle
ne sont pas des écritures BCMath valides. Les cas particuliers acceptés par PHP,
comme la chaîne vide assimilée à zéro ou la fin de l'opérande au premier octet
NUL, restent acceptés ; une saisie utilisateur doit donc être validée selon les
besoins du formulaire. L'exemple refuse explicitement les octets NUL.

Une erreur non interceptée restaure les `State` de l'événement. Les sorties
`echo` restent dans la console. La différence introduite par PHP 8.4 pour
`bcpow('0', '-1')` est conservée : `DivisionByZeroError` en 8.4, zéro auparavant.

Pour borner les calculs du navigateur :

- 4 096 chiffres par opérande ;
- échelle de 0 à 1 000 et précision de `bcround` de −1 000 à 1 000 ;
- exposant de `bcpow` de −10 000 à 10 000 ; `bcpowmod` accepte un exposant entier
  plus grand sous réserve du budget de calcul ;
- au plus 16 384 chiffres dans les valeurs intermédiaires ;
- budget de calcul partagé avec les autres fonctions natives de l'événement.

Les limites produisent une erreur de plage ; elles ne réduisent jamais
silencieusement la précision demandée. ViewTest vérifie aussi les tailles et
l'échelle avant de lancer l'extension PHP ; le budget fin d'opérations appartient
à Engine. Ces limites peuvent empêcher un calcul même si ses opérandes tiennent
individuellement dans la taille autorisée.

Voir [l'exemple complet de calcul de total](../../examples/calcul-decimal/page.php)
(PHP 8.4). Il arrondit d'abord le sous-total, puis la taxe avec un taux d'exemple
de 15 %, et additionne les montants arrondis. La politique d'arrondi est explicite.

Références PHP : [BCMath](https://www.php.net/manual/fr/ref.bc.php),
[bcround](https://www.php.net/manual/fr/function.bcround.php),
[bcscale](https://www.php.net/manual/fr/function.bcscale.php).
