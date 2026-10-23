# Niveaux 3 à 5 — Conditions, boucles et tableaux

Disponibles dans les sources **View 0.1.0-beta.13 + Engine 0.1.0-beta.9**, à
utiliser ensemble. Ces versions sont en préparation. Dans `body()`, avant
`return`, le code s’exécute directement en PHP au rendu. Dans un événement ou un
effet compilé, Engine l’exécute dans le navigateur avec les valeurs courantes.

## Niveau 3 — Conditions

| Points | Prise en charge |
| --- | --- |
| 38–41 | `if`, `else`, `elseif`, `else if`, branches vides et conditions imbriquées. |
| 42 | `switch`, comparaison souple, `case`, `default`, passage au cas suivant en l’absence de `break`. |
| 43 | `match`, comparaison stricte, plusieurs valeurs par branche, `default`, expressions imbriquées. |
| 44 | Conversion booléenne PHP : `null`, `false`, `0`, `0.0`, `''`, `'0'` et `[]` sont faux. |
| 45 | `isset()` avec un ou plusieurs arguments et court-circuit ; `empty()` sur une valeur ou un élément absent. |

Le sujet de `switch` ou `match` est évalué une seule fois. Les expressions des
branches inutilisées de `match` ne sont pas évaluées. Sans correspondance et
sans `default`, `match` produit une `UnhandledMatchError` avec sa ligne PHP.
`switch` peut simplement ne sélectionner aucun cas.

```php
Button('Vérifier')->onClick(function (): void {
    if ($this->age >= 18) {
        echo 'Majeur' . PHP_EOL;
    } else {
        echo 'Mineur' . PHP_EOL;
    }

    $this->message = match ($this->age) {
        18, 19 => 'Jeune adulte',
        default => 'Autre âge',
    };
});
```

Dans les événements et effets compilés, **`echo` écrit uniquement dans la console
du navigateur**. Les arguments sont convertis et concaténés selon les règles
PHP ; `PHP_EOL` et les retours à la ligne sont conservés. Aucun élément visuel ni
HTML n’est ajouté à la page. Pour afficher un résultat, affecter une propriété
`#[State]` liée à `Text($this->message)`.

## Niveau 4 — Boucles

| Points | Prise en charge |
| --- | --- |
| 46–48 | `while`, `do...while`, `for`, conditions dépendant des états ; clauses de `for` vides ou séparées par des virgules. |
| 49–50 | `foreach` sur un tableau local, un état ou une expression, valeurs et clés. |
| 51–52 | `break`, `continue`, niveaux explicites tels que `break 2` et `continue 2`. |
| 53 | Imbrication des boucles, avec conditions et `switch` dans leur corps. |
| 54 | Arrêt après 1 000 itérations par boucle et 10 000 itérations cumulées par invocation. |

Ces protections s'appliquent aussi aux boucles dans les fonctions et générateurs.
L'erreur `PHP_LOOP_LIMIT` distingue la limite d'une boucle (`limitKind: "loop"`)
du budget partagé (`limitKind: "execution"`). Son message indique le nombre
autorisé et le fichier PHP avec sa ligne. L'événement restaure le State après
une erreur non interceptée, et les événements suivants restent utilisables.

Les tests permanents vérifient notamment 128 `if` imbriqués, 64 boucles imbriquées,
les mélanges de boucles, `break 2`, `continue 3` et `try`/`finally`. La profondeur
n'est pas illimitée : le format compilé garde une limite de 512 conteneurs JSON
imbriqués. Ce nombre ne correspond pas à un nombre fixe de `if`. Une structure
trop profonde est refusée à la compilation avec son emplacement PHP.

`composer test` et `composer test:language-runtime` exécutent ces comparaisons
avec PHP natif. Pour mesurer la compilation seule (avec sérialisation finale,
sans rendu HTML ni exécution), lancer depuis le dépôt View :

```sh
php tests/nested-control-flow.php --case=if-depth-128-function --benchmark --json
```

Le résultat contient trois mesures après un passage de chauffe et leur médiane.
Les blocs déjà compilés sont conservés sous leur forme sérialisée pendant cette
compilation, sans cache global partagé entre les composants ou leurs State.

```php
Button('Compter')->onClick(function (): void {
    for ($number = 1; $number <= 10; $number++) {
        echo $number . PHP_EOL;
    }

    while ($this->counter < $this->limit) {
        $this->counter++;
    }
});
```

Les boucles sont compilées une seule fois, puis exécutées à chaque événement.
`continue` exécute la mise à jour du `for` ou évalue la condition finale du
`do...while`, comme en PHP. Dans un `switch`, `continue` seul équivaut à `break`
et émet un avertissement ; `continue 2` atteint la boucle extérieure.

Un `foreach` par valeur conserve la structure initiale du tableau parcouru :
ajouter un élément au tableau source ne rajoute pas d’itérations. Les références
déjà présentes dans les éléments gardent leur sémantique PHP. Les variables de
clé et de valeur conservent la dernière valeur parcourue après la boucle.
Le parcours `foreach ($tableau as &$valeur)` et ses différences sont décrits dans
le [guide SPL et références](spl-references.md). Les variables locales restent
privées à l’invocation.

Une limite dépassée produit `PHP_LOOP_LIMIT`, avec fichier et ligne PHP. Une
séquence synchrone restaure les états et leurs types, puis les événements
suivants restent utilisables. Les messages déjà émis dans la console restent
visibles. Les effets externes d’une action asynchrone ne sont pas annulés.

## Niveau 5 — Tableaux

| Points | Prise en charge |
| --- | --- |
| 55–57 | Tableaux indexés, associatifs, mixtes et multidimensionnels avec `[]` ou `array()`. |
| 58 | Lecture et modification, clés calculées, affectations combinées et incréments d’éléments. |
| 59 | Ajout avec `[]`, `unset()`, `array_push()`, `array_pop()`, `array_shift()`, `array_unshift()`. |
| 60 | Parcours avec `foreach`, y compris les tableaux imbriqués. |
| 61 | `count()` sur les tableaux, avec erreur de type pour une valeur incompatible. |
| 62 | `in_array()` souple par défaut, strict avec son troisième argument `true`. |
| 63 | `array_key_exists()` distingue une clé contenant `null` d’une clé absente. |
| 64 | `sort`, `rsort`, `asort`, `arsort`, `ksort`, `krsort` et `usort`. |
| 65–67 | `array_map`, `array_filter`, `array_reduce` avec callables compilés ; plusieurs tableaux pour `array_map`, modes de filtre et valeur initiale de réduction. |
| 68 | Déstructuration avec `[]` ou `list()`, éléments ignorés, clés explicites, motifs imbriqués et déstructuration dans `foreach`. |
| 69 | Propagation `...` dans les littéraux : indices numériques réindexés et clés textuelles remplacées selon PHP. |

```php
Button('Préparer')->onClick(function (): void {
    $user = ['name' => 'André', 'age' => 30];
    echo $user['name'] . PHP_EOL;

    $copy = $user;
    $copy['age']++;
    ['name' => $name, 'age' => $age] = $copy;

    $numbers = [1, 2, 3];
    $doubled = array_map(fn ($value) => $value * 2, $numbers);
    $filtered = array_filter($doubled, fn ($value) => $value > 2);
    $this->total = array_reduce($filtered, fn ($sum, $value) => $sum + $value, 0);
    $this->user = [...$user, 'age' => $age];
});
```

L’ordre d’insertion, le type des clés et le prochain indice d’ajout sont
conservés. `unset()` ne réindexe pas le tableau. `array_filter()` conserve les
clés ; `array_map()` avec un tableau conserve également ses clés. `array_keys()`
et `array_values()` permettent de les examiner ou de réindexer explicitement.
Les affectations copient les tableaux : modifier une copie, même imbriquée, ne
modifie pas l’original. Les types numériques des éléments sont conservés, y
compris après une copie ou une sauvegarde/restauration d’état persisté.

Les fonctions fléchées capturent leurs variables locales par valeur. Une closure
peut capturer par référence avec `use (&$valeur)` ; les paramètres par référence
suivent la signature de la fonction. Les comparaisons strictes distinguent aussi les entiers et flottants
contenus dans les tableaux.

## Périmètre et diagnostics

Cette prise en charge couvre le sous-ensemble PHP décrit ici, sans exécuter de
code PHP arbitraire dans le navigateur. Les blocs des conditions et boucles
utilisent des accolades. Les [fonctions et références](niveau-6-fonctions.md),
[générateurs](generateurs.md) et [objets Traversable](iterateurs.md) prolongent
ce niveau. Les [fonctions natives](fonctions-natives.md) détaillent les callbacks,
les modes de filtrage et les tris, dont les tris naturels. Les opérations `&`,
`|` et `^` peuvent composer les drapeaux. Les tris dépendants de la locale restent
exclus. Consulter la [matrice de compatibilité](compatibilite.md) pour le périmètre actuel.

Les limites numériques et UTF-8 du niveau 1 restent applicables, ainsi qu’aux
clés numériques. Les noms `__proto__`, `prototype` et `constructor` restent
réservés. Une clé absente lue normalement produit un avertissement et `null` ;
`isset`, `empty` et `??` permettent de la tester sans avertissement. Un type de
clé invalide, une écriture dans une valeur scalaire ou un `count()` invalide
produit une erreur explicite. Les états doivent rester sérialisables, sans
valeurs numériques infinies ou hors de la précision entière prise en charge.

`composer test` vérifie le compilateur et le simulateur View.
`composer test:language-runtime` compare les résultats, types, sorties console,
avertissements et erreurs à PHP natif. Les tests Engine vérifient aussi la
persistance, les limites de boucle et les interactions dans le navigateur.

Références PHP : [switch](https://www.php.net/manual/en/control-structures.switch.php),
[match](https://www.php.net/manual/en/control-structures.match.php),
[continue](https://www.php.net/manual/en/control-structures.continue.php),
[tableaux](https://www.php.net/manual/en/language.types.array.php).
