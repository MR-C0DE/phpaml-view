# Niveau 6 — Fonctions

Les fonctions appelées dans `body()`, avant `return`, s'exécutent normalement en
PHP. Dans un événement, View compile les fonctions utilisées et Engine les
exécute dans le navigateur. Le compilateur lit leur source sans les appeler.

Cette prise en charge appartient à View `0.1.0-beta.13` et Engine
`0.1.0-beta.9`, en développement.

## Un fichier `service.php`

Charger le fichier avant de rendre la page, avec `require_once` ou l'autoload
`files` de Composer. Le navigateur reçoit les instructions compilées des
fonctions utilisées ; il ne charge pas le fichier PHP à chaque clic.

```php
// service.php
namespace App\Services;

function add(int $a, int $b = 5): int
{
    return $a + $b;
}

function total(int $number): int
{
    if ($number <= 0) {
        return 0;
    }

    return add($number, total($number - 1));
}
```

```php
// page.php, placé à côté de service.php pour cet exemple
namespace App\Pages;

use AML\View\{Page, State, View};
use function AML\View\{Button, Text, VStack};
use function App\Services\total;

require_once __DIR__ . '/service.php';

final class ExamplePage extends Page
{
    #[State] public int $result = 0;

    public function body(): View
    {
        return VStack(
            Button('Calculer')->onClick(function (): void {
                $this->result = total(5);
                echo $this->result . PHP_EOL;
            }),
            Text($this->result),
        );
    }
}
```

Le clic affiche `15` dans le texte et écrit `15` dans la console du navigateur.
`echo` n'ajoute aucun élément à l'interface.

## Couverture des points 70 à 85

| Point | Prise en charge dans les événements |
|---|---|
| 70 | Fonctions utilisateur chargées, appels entre fichiers, imports `use function`, alias et imports groupés ; méthodes du composant et méthodes statiques de classes déjà chargées. |
| 71 | Paramètres positionnels, évalués une seule fois dans l'ordre des arguments. |
| 72 | Valeurs par défaut ; chaque appel reçoit ses propres valeurs. |
| 73 | Types scalaires, tableaux, `iterable` (tableaux et `Traversable`), `callable`, `Closure`, classes/interfaces compilées, unions, intersections, DNF et types nullables. |
| 74 | Contrôle des types de retour, y compris `void` et `never`. |
| 75 | `return` avec ou sans valeur, y compris depuis une condition, une boucle ou un `switch`. |
| 76 | Une portée locale par appel ; captures explicites des fonctions anonymes et captures par valeur des flèches. |
| 77 | Passage par valeur et copie des tableaux ; une modification locale ne modifie pas l'argument d'origine. |
| 78 | Paramètres `&$value`, captures `use (&$value)`, références vers une variable, un élément de tableau ou une propriété `#[State]`. |
| 79 | Paramètres variadiques, y compris par référence, dépliage `...` et conservation des clés nommées. |
| 80 | Paramètres nommés, ordre libre et contrôle des doublons ou noms inconnus. |
| 81 | Fonctions anonymes avec plusieurs instructions, captures et retour de closure. |
| 82 | Fonctions fléchées, fonctions fléchées imbriquées et invocation immédiate. |
| 83 | Invocation `$callback(...)`, paramètres et retours `callable`, syntaxe `functionName(...)` pour créer un callable, callbacks des opérations de tableau prises en charge. |
| 84 | Récursivité directe, mutuelle et closure récursive capturée par référence. |
| 85 | Fonctions pures ; effets explicites sur les références, les états du composant et la console. |

`fn` contient une expression : `fn (int $a): int => add($a, 5)`.
Pour un corps avec `if` et plusieurs `return`, utiliser `function (...) { ... }`.

Les noms de fonctions sous forme de chaînes désignent les fonctions utilisateur
présentes dans la table compilée. Un nom littéral connu lors de la compilation
peut y ajouter la fonction correspondante. Pour un choix dynamique, privilégier
des callables explicites (`add(...)`, une closure ou une flèche) ; Engine n'appelle
pas une fonction PHP arbitraire à partir d'un nom reçu du réseau.

Les [compléments de fonctions](fonctions-avancees.md) couvrent les variables
locales `static`, `func_get_args()`/`func_num_args()`/`func_get_arg()`, les noms
de méthodes calculés et les callables sous forme de tableau ou de chaîne.

## Références et closures

```php
function increment(int &$value, int $by = 1): void
{
    $value += $by;
}

// Dans un événement :
$count = 2;
increment($count, by: 3); // $count vaut 5

$next = function () use (&$count): int {
    return ++$count;
};

$double = fn (int $value): int => $value * 2;
echo $double($next()); // 12, dans la console
```

Une closure créée dans `body()` peut être capturée par valeur dans un événement.
Ses captures scalaires ou tableaux sont alors des instantanés du rendu serveur.
Une référence PHP du serveur ne peut pas devenir une référence mémoire du
navigateur : créer les closures avec captures par référence dans l'événement.

Les closures restent dans les variables locales ou sont passées à d'autres
fonctions. Elles ne peuvent pas être enregistrées dans un état, la persistance
ou un manifeste JSON. Une closure retournée conserve les références locales
qu'elle capture, même après le retour de sa fonction d'origine.

## Erreurs et limites

Le contrôle des paramètres respecte `strict_types` du fichier appelant. Celui du
retour respecte le fichier où la fonction est définie. Les callbacks appelés par
les opérations de tableau suivent le mode faible des fonctions internes PHP.
Les affectations aux propriétés typées conservent leur propre contrôle.

Engine distingue notamment :

- `ArgumentCountError` pour un argument obligatoire absent ;
- `TypeError` pour un paramètre, un retour ou un callable incompatible ;
- les paramètres nommés inconnus ou fournis deux fois ;
- un argument non assignable passé par référence ;
- une fonction non déclarée ou une déclaration exécutée deux fois ;
- `PHP_CALL_LIMIT` pour plus de 128 appels imbriqués ou 10 000 appels cumulés par événement ;
- les erreurs des niveaux précédents, avec leur emplacement dans le service.

Les erreurs conservent le fichier et la ligne PHP. Les erreurs à l'intérieur des
fonctions portent aussi une pile d'appels `phpStack`. Une erreur annule les
modifications d'état de l'événement synchrone ; les sorties console déjà émises
restent visibles. Les clics suivants restent disponibles.

Une fonction nommée déclarée dans un événement devient disponible lorsque sa
déclaration est exécutée, dans le registre du composant. Réexécuter sa déclaration
produit une erreur. Déclarer les fonctions réutilisables dans `service.php` évite
ce problème.

Le corps des fonctions utilise le sous-ensemble PHP décrit dans les niveaux 1 à 5.
Les entiers exacts, les limites de boucles et les contraintes de tableaux de ces
niveaux restent applicables. Le graphe compilé est limité à 256 fonctions.
Les [objets de service](objets.md), les [générateurs](generateurs.md) et les
[itérateurs](iterateurs.md) sont disponibles. La [matrice de compatibilité](compatibilite.md)
précise les formes de références, de déclarations et de fonctions natives reconnues.
Les fonctions système arbitraires, les accès fichiers du serveur, les bases de
données et `global` ne sont pas transposés dans Engine.
Les opérations serveur restent dans PHP, accessibles aux événements par les API
déjà proposées par PHPAML. Une fonction appelée dans une expression est synchrone.

## Vérification

`composer test` vérifie le compilateur et le simulateur ViewTest.
`composer test:language-runtime` compare les valeurs, types, sorties console,
avertissements et erreurs avec PHP natif, en incluant les fonctions de services
séparés. Les tests Engine couvrent les budgets d'appels, les captures, les erreurs,
l'absence d'exécution de JavaScript arbitraire et les interactions dans Chrome.

Références de langage : [paramètres PHP](https://www.php.net/manual/fr/functions.arguments.php),
[fonctions anonymes](https://www.php.net/manual/fr/functions.anonymous.php) et
[valeurs de retour](https://www.php.net/manual/fr/functions.returning-values.php).
