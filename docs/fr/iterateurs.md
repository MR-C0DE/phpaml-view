# Parcourir les collections avec Iterator et IteratorAggregate

ViewTest et Engine acceptent `foreach` sur les services compilés qui implémentent
`Iterator` ou `IteratorAggregate`. Les méthodes sont interprétées dans les
événements frontend, les fonctions de service, les callbacks et `eval()`.
Les contrats `iterable`, `Traversable`, `Iterator` et `IteratorAggregate`, ainsi
que `is_iterable()`, reconnaissent ces objets. Voir le
[catalogue natif complet](compatibilite.md#catalogue-natif).

```php
$collection = new ListeArticles(['pomme' => 'Pomme', 'orange' => 'Orange']);
$lignes = [];
foreach ($collection as $cle => $nom) {
    $lignes[] = $cle . '=' . $nom;
}
$this->message = implode(', ', $lignes);
```

La [page complète](../../examples/iterateurs/page.php) utilise un
[service](../../examples/iterateurs/service.php) qui fournit son propre itérateur.
Le tableau d'articles et le résultat affiché sont des State ; les objets restent
locaux à l'événement. Chaque clic actualise le texte. `echo` reste dans la console.

## Choisir l'interface

`Iterator` porte la position courante et les cinq méthodes du parcours :

| Méthode | Rôle |
| --- | --- |
| `rewind(): void` | Revenir au début |
| `valid(): bool` | Indiquer si la position existe |
| `current(): mixed` | Lire la valeur courante |
| `key(): mixed` | Lire la clé courante |
| `next(): void` | Avancer d'une position |

`IteratorAggregate` fournit `getIterator(): Traversable`. Retourner un nouvel
itérateur permet des parcours imbriqués indépendants. Une même instance
`Iterator` partage sa position entre ses parcours, comme en PHP. Un agrégat peut
également déléguer à un autre agrégat ; retourner directement `$this` est une erreur.

`Traversable` est leur interface commune. Une classe concrète doit implémenter
`Iterator` ou `IteratorAggregate` pour devenir parcourable ; elle ne peut pas
implémenter les deux simultanément. Les signatures des classes anonymes sont
vérifiées, y compris celles déclarées dans `eval()`.

## Déroulement et contrôles

Chaque `foreach` appelle `rewind()`, puis `valid()` avant chaque tour. Si la
position existe, `current()` fournit la valeur et `key()` fournit la clé seulement
si la boucle demande `$cle => $valeur`. `next()` est appelé après le corps de la
boucle, y compris après un `continue` qui reste dans cette boucle. Un `break`, un
`return` ou une exception sort sans appeler ce `next()`.

Les clés conservent leur type : une clé flottante n'est pas transformée en entier
par `foreach`. La déstructuration, les boucles imbriquées, `break 2`, `continue 2`,
`try/catch/finally` et les paramètres/retours `iterable` utilisent les mêmes règles.
`is_iterable()` vérifie le type sans appeler `getIterator()` ni déplacer l'itérateur.

Ces comportements sont comparés au PHP natif dans les tests. Références :
[Iterator](https://www.php.net/manual/en/class.iterator.php),
[IteratorAggregate](https://www.php.net/manual/en/class.iteratoraggregate.php) et
[Traversable](https://www.php.net/manual/en/class.traversable.php).

## Erreurs et limites

Les exceptions des méthodes et les erreurs de type sont interceptables dans
`try/catch`. Un `getIterator()` sans type qui renvoie un tableau ou un objet non
parcourable produit une `Exception` ; un retour contraire à un type explicite
produit une `TypeError`. Les erreurs conservent leur origine PHP dans Engine.
Une erreur synchrone non interceptée restaure les State de l'événement ; les
sorties console déjà émises et les effets sur les services statiques persistent.

Les limites existantes s'appliquent : 1 000 tours par boucle et 10 000 tours cumulés
par événement, ainsi que les limites d'appels et d'allocations. Une chaîne de
`getIterator()` est limitée à 128 délégations. Les itérateurs ne sont pas matérialisés
en entier avant le parcours : un `break` arrête réellement leur consommation.

Ce guide décrit le parcours par valeur des services `Iterator`/`IteratorAggregate`.
Le [guide SPL et références](spl-references.md) complète ce contrat avec
`ArrayIterator`, `ArrayObject`, `iterator_to_array()`, `iterator_count()`,
`iterator_apply()`, la propagation d'un `Traversable` avec `...` et les formes
de références reconnues. Le parcours automatique des propriétés d'un objet sans
ces interfaces reste hors périmètre.

`Countable` et `ArrayAccess` restent indépendants : ils permettent respectivement
le comptage et l'accès `[]`, sans rendre à eux seuls un service parcourable.
Voir le [guide des collections](collections.md).

Les [générateurs `yield` et `yield from`](generateurs.md) sont également disponibles.
