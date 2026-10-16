# SPL pratique et références

Les événements compilés par View, le simulateur `TestView` et le moteur du navigateur partagent les opérations décrites ci-dessous. Les mêmes constructions sont disponibles dans l’`eval()` interprété. Les objets restent locaux ou dans le stockage statique du composant ; un `State` reçoit uniquement des valeurs compatibles avec JSON.

## ArrayIterator et ArrayObject

```php
$articles = new ArrayObject(['pomme' => 3, 'poire' => 2]);
$articles['orange'] = 4;

$iterator = $articles->getIterator();
foreach ($iterator as $nom => $quantite) {
    echo "$nom : $quantite\n";
}

$articles->ksort();
$this->resultat = iterator_to_array($articles);
```

Les deux adaptateurs comprennent les offsets, `append()`, `count()`, `getArrayCopy()`, `getFlags()`, `setFlags()`, `asort()`, `ksort()`, `natsort()`, `natcasesort()`, `uasort()` et `uksort()`. `ArrayIterator` comprend aussi `current()`, `key()`, `next()`, `rewind()`, `valid()` et `seek()`. `ArrayObject` comprend `exchangeArray()`, `getIterator()`, `getIteratorClass()` et `setIteratorClass()`.

L’itérateur renvoyé par `ArrayObject::getIterator()` partage les éléments de la collection. Les copies de tableaux et les clones suivent les comportements de ces adaptateurs PHP : cloner un `ArrayIterator` partage son stockage et réinitialise son curseur ; cloner un `ArrayObject` copie son tableau. Une mutation de la collection depuis un callback de tri lève une erreur.

`iterator_to_array()`, `iterator_count()` et `iterator_apply()` sont disponibles. Le callback d’`iterator_apply()` reçoit ses arguments explicites ; il peut capturer l’itérateur pour lire l’élément courant. Les tableaux, `Iterator`, `IteratorAggregate` et générateurs pris en charge peuvent être dépliés avec `...` dans les tableaux et les appels. Les clés doivent être des entiers ou des chaînes. Les règles des paramètres nommés continuent de s’appliquer.

Ces adaptateurs ont des limites explicites : stockage initial et `exchangeArray()` exigent un tableau, les flags acceptés valent `0`, et la classe d’itérateur d’`ArrayObject` est `ArrayIterator`. L’héritage de ces classes internes, y compris par une classe anonyme, est refusé ; utiliser la composition. Le reste de SPL et les méthodes de sérialisation de ces adaptateurs ne sont pas implémentés.

## Alias et copies

```php
$prix = 10;
$alias =& $prix;
$alias = 12;               // $prix vaut 12

$lignes = [&$prix, &$prix];
$copie = $lignes;
$prix = 15;               // les quatre éléments valent 15

foreach ($lignes as &$ligne) {
    $ligne += 1;
}
unset($ligne);            // termine l’alias conservé après foreach
```

Les affectations `=&` peuvent lier une variable locale, un offset ou un nouvel élément de tableau à une autre référence. Les littéraux `[&$variable]`, les captures `use (&$variable)`, les paramètres par référence et les fonctions ou méthodes déclarées avec `function &` conservent les cellules partagées. Une affectation normale du résultat d’une fonction qui retourne une référence reste une affectation par valeur.

Les copies de tableaux partagent les éléments réellement référencés, tout en séparant leurs éléments ordinaires. Après disparition du dernier alias externe, une copie ordinaire peut à nouveau détacher l’élément, comme PHP. Les fonctions qui conservent des éléments (`array_filter`, `array_values`, `array_slice`, `array_chunk`, fusions, différences, intersections, tris, etc.) conservent leurs références ; un callback de transformation produit sa nouvelle valeur. Les règles de clés et de types numériques restent attachées aux éléments.

`foreach (... as &$valeur)` fonctionne avec les tableaux locaux, les tableaux de `State`, `ArrayIterator` et `ArrayObject`. Les ajouts et suppressions pendant le parcours sont pris en compte, avec les limites de boucle habituelles. `&__get()` et `&offsetGet()` peuvent fournir une référence pour une modification indirecte ou un alias local. Une méthode qui renvoie par valeur conserve le comportement de modification indirecte sans effet et son avertissement.

Un alias local vers une propriété déclarée, statique ou `State` passe par ses contrôles de type lors de l’affectation. Les propriétés readonly refusent l’acquisition d’une référence. Les références internes d’un tableau de `State` sont conservées entre les clics. Si un événement échoue, le snapshot restaure les valeurs **et les liens internes** entre ses éléments, tout en les détachant des cellules extérieures qui ont été modifiées pendant l’événement.

La déstructuration avec `&`, les générateurs par référence, les liaisons aux
propriétés de services et les cycles locaux sont disponibles : voir les
[extensions du langage](extensions-langage.md).

## Frontières des références

Les cas suivants sont refusés explicitement :

- `foreach` par référence sur un `Iterator` utilisateur ordinaire ;
- liaison d’une propriété `State` entière à gauche de `=&` ;
- insertion d’une référence à un `State` entier dans un tableau ;
- stockage d’un cycle ou d’un objet de service dans `State` ;
- dépliage d’un `Traversable` vers un paramètre par référence. Déplier un tableau assignable conserve les règles déjà disponibles.

Ces refus évitent d’afficher un résultat plausible avec des alias perdus ou des contrôles de type contournés. Ils ne signifient pas que ces constructions sont interdites en PHP natif.

Dans la console du navigateur, `var_dump()` affiche les valeurs et leurs types,
mais ne reproduit pas les marqueurs `&` du diagnostic PHP natif pour les éléments
référencés. Les alias continuent de fonctionner ; cet affichage ne permet pas
d'inspecter leur identité.

## Vérification

`tests/spl-references.php` compare les cas pris en charge à PHP natif et à `TestView`. `tests/spl-references.mjs` exécute le même manifeste dans Engine. La suite navigateur `tests/browser/spl-references.spec.mjs` reprend ces scénarios sous une politique CSP sans `unsafe-eval`, ainsi que le parcours à trois clics qui vérifie les alias après un rollback. Le contrôle PHP natif de ce dernier parcours saute l’événement qui échoue : les transactions automatiques appartiennent à View/Engine, pas à PHP.
