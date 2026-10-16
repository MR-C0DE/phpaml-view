# Fonctions, opérateurs et conversions avancés

Ces possibilités fonctionnent dans les événements frontend, les services compilés et les fonctions ou closures appelées par Engine. Les mêmes instructions sont utilisées par `TestView`.

## Variables statiques locales

```php
function prochainNumero(): int
{
    static $numero = 0;
    return ++$numero;
}

$this->message = 'Commande ' . prochainNumero();
```

Une variable statique conserve sa valeur entre les appels. Comme les propriétés statiques des services, son stockage appartient au composant : les clics successifs le partagent et une nouvelle instance du composant repart de ses valeurs initiales. Les méthodes héritées partagent leur déclaration statique ; une méthode redéfinie possède son propre stockage.

Chaque instance de closure a ses propres variables statiques. Copier une closure dans une autre variable conserve son identité et son compteur ; recréer la closure crée un nouveau compteur. Les générateurs conservent également leurs références aux variables statiques lorsqu’ils sont suspendus.

Les initialisations peuvent être des expressions constantes ou, à partir de PHP
8.3, des expressions dynamiques évaluées au premier appel. Les déclarations
statiques en double sont refusées à partir de cette version. Voir les
[extensions du langage](extensions-langage.md).

## Arguments de la fonction courante

`func_num_args()`, `func_get_args()` et `func_get_arg($index)` décrivent les arguments de la fonction en cours. Ils prennent en compte les paramètres nommés, les valeurs intermédiaires par défaut, les paramètres modifiés, les paramètres par référence et les arguments positionnels supplémentaires. Les arguments variadiques nommés ne sont pas ajoutés à la liste positionnelle, conformément à PHP.

Un indice négatif ou absent provoque `ValueError`. Une fonction imbriquée possède son propre contexte ; celui d’un générateur est conservé entre deux reprises. Ces fonctions doivent être utilisées dans un événement, puis leur résultat peut être envoyé à un `State`.

## Noms de méthodes et callbacks

```php
$service = new Calculatrice();
$methode = 'additionner';
$resultat = $service->$methode(10);

$callback = [$service, 'additionner'];
$valeurs = array_map($callback, [1, 2, 3]);

$statique = [Calculatrice::class, 'doubler'];
$valeurs = array_map($statique, $valeurs);
```

Sont pris en charge : `$objet->$methode()`, `$objet->{'préfixe' . $suffixe}()`, `Classe::$methode()`, leurs références de première classe `(...)`, les tableaux `[$objet, 'methode']`/`[Classe::class, 'methode']` et les chaînes pleinement qualifiées `'Namespace\\Classe::methode'`.

Les règles de visibilité, les méthodes statiques et les méthodes magiques sont conservées. Un accès nullsafe ne calcule ni le nom dynamique ni les arguments lorsque le récepteur vaut `null`. Les noms de propriétés dynamiques sont également acceptés ; la création de propriétés concerne `stdClass`, tandis que les services conservent leurs déclarations ou leurs méthodes magiques.

Le catalogue reste fermé : une classe utilisée uniquement sous un nom construit à l’exécution doit déjà être compilée par une utilisation explicite dans le programme. Les cibles dynamiques `$classe::methode()` et `new $classe` sont disponibles dans ce catalogue, ainsi que les [chaînes nullsafe](web-unicode-dynamique.md).

## Opérateurs supplémentaires

`<<`, `>>`, `~`, `xor`, `and` et `or` sont disponibles, ainsi que `<<=`, `>>=`, `&=`, `|=` et `^=`. La priorité des opérateurs suit PHP :

```php
$autorise = true and false; // $autorise reste true.
$masque = 3;
$masque <<= 2;              // 12
```

Les décalages suivent les entiers signés de 64 bits de PHP ; leur résultat doit rester dans la plage exacte du frontend, de `-9007199254740991` à `9007199254740991`. Un décalage négatif provoque `ArithmeticError`. Les opérations binaires sur chaînes doivent produire de l’UTF-8 valide.

## Conversions `(array)` et `(object)`

Les conversions des scalaires, de `null`, des tableaux, de `stdClass` et des services sont disponibles. Un cast objet d’un objet déjà existant conserve son identité. Les conversions de tableaux préservent les clés et copient les valeurs selon les règles PHP ; les objets contenus conservent leur identité.

Les casts vers tableau des services incluent les propriétés initialisées et conservent les noms PHP des propriétés privées ou protégées, avec leurs préfixes NUL. Les enums exposent `name` et, le cas échéant, `value`. `ArrayIterator` et `ArrayObject` exposent leur tableau de stockage.

Les casts vers tableau des exceptions et closures ne dévoilent pas leurs données internes. Les classes de dates demandent une projection explicite avec `format()` et les autres méthodes disponibles. Les objets résultants restent locaux : ils ne peuvent pas être placés dans un `State`.

## Validation et limites restantes

`tests/advanced-functions.php` compare PHP natif et `TestView`. `tests/advanced-functions.mjs` compare Engine avec les mêmes résultats, y compris son compilateur dynamique `eval()`. Les diagnostics sont vérifiés dans `tests/fixtures/advanced-functions-diagnostics.json`, et les scénarios navigateur sont dans `tests/browser/advanced-functions.spec.mjs` du paquet Engine.

Les hooks, la visibilité asymétrique, le clonage des closures et la réflexion adaptée sont décrits dans les [extensions du langage](extensions-langage.md). Les attributs à l’exécution et les destructeurs automatiques restent exclus.
