# Collections : Countable et ArrayAccess

Un service peut implémenter `Countable` pour répondre à `count()` et `sizeof()`,
et `ArrayAccess` pour offrir des accès avec `[]` dans les événements frontend.
Les méthodes sont exécutées par ViewTest et Engine, dans le navigateur pour ce dernier.
La compilation lit les déclarations sans exécuter ces méthodes côté serveur.

```php
$panier = new Panier(['Pomme']);
$panier[] = 'Orange';          // offsetSet(null, 'Orange')
$panier[0] = 'Poire';         // offsetSet(0, 'Poire')
$premier = $panier[0];        // offsetGet(0)
$present = isset($panier[1]); // offsetExists(1)
unset($panier[0]);            // offsetUnset(0)
$total = count($panier);      // count()
$valide = is_countable($panier);
```

L'[exemple complet](../../examples/collections/page.php) utilise un
[service Panier](../../examples/collections/service.php) et conserve son tableau
`all()` dans un State. L'objet reste local à l'événement. Le bouton actualise le
texte affiché et `echo` écrit uniquement dans la console du navigateur.

## Accès et contrats

| Expression | Méthodes appelées |
| --- | --- |
| `$objet[$cle]` | `offsetGet($cle)` |
| `$objet[$cle] = $valeur` | `offsetSet($cle, $valeur)` |
| `$objet[] = $valeur` | `offsetSet(null, $valeur)` |
| `isset($objet[$cle])` | `offsetExists($cle)` |
| `empty($objet[$cle])` | `offsetExists()`, puis `offsetGet()` si présent |
| `$objet[$cle] ?? $defaut` | `offsetExists()`, puis `offsetGet()` si présent |
| `$objet[$cle] ??= $defaut` | Même lecture, puis `offsetSet()` si absent ou null |
| `$objet[$cle] += 1` | `offsetGet()`, puis `offsetSet()` |
| `unset($objet[$cle])` | `offsetUnset($cle)` |

Les clés sont transmises telles quelles à ces méthodes : les conversions de clés
s'appliquent ensuite si le service utilise lui-même un tableau PHP. Un
`offsetExists()` fondé sur `array_key_exists()` peut donc rendre `isset()` vrai
même quand `offsetGet()` renvoie `null`.

L'héritage, les classes anonymes, les paramètres `Countable`/`ArrayAccess`, leur
intersection, les arguments nommés, les callbacks et `eval()` utilisent ces mêmes
contrats. La déstructuration lit les éléments avec `offsetGet()`.

`count($objet, COUNT_RECURSIVE)` appelle une fois la méthode `count()` de l'objet ;
il ne parcourt pas ses propriétés. `is_countable()` accepte les tableaux et les
objets `Countable`, mais pas un objet qui implémente seulement `ArrayAccess`.
La liste exhaustive figure dans le [catalogue natif](compatibilite.md#catalogue-natif).

## Modifications indirectes

Avec un `offsetGet()` qui retourne par valeur, PHP ne transmet pas les modifications
indirectes au service. Engine et ViewTest reproduisent ce comportement :

```php
$panier['quantite'] += 1;    // appelle offsetSet(), modification conservée
$panier['quantite']++;      // valeur temporaire, notice, pas de réécriture
$panier['liste'][] = 'A';   // tableau temporaire, notice, pas de réécriture
```

Pour modifier un tableau contenu dans la collection, lire sa valeur, la modifier,
puis la réaffecter :

```php
$liste = $panier['liste'];
$liste[] = 'A';
$panier['liste'] = $liste;
```

Une méthode `&offsetGet()` peut retourner une référence : les modifications
indirectes portent alors sur l'emplacement référencé. Voir le
[guide SPL et références](spl-references.md) pour les formes acceptées.
Un objet renvoyé par `offsetGet()` conserve son identité : modifier ses propriétés
modifie cet objet. Déballer un tableau avec `...$panier['liste']` copie sa structure
selon les règles PHP des tableaux et des références.

## Erreurs et limites

Un accès `[]` sur un objet sans `ArrayAccess` produit une `Error` ; `count()` sur
un objet sans `Countable` produit une `TypeError`. Un mode de comptage invalide
produit une `ValueError`. Les méthodes manquantes, les signatures incompatibles,
les erreurs de type et les exceptions du service sont contrôlées. Une exception
peut être interceptée avec `try/catch`. Une erreur synchrone non interceptée restaure
les State de l'événement ; les sorties console déjà émises restent visibles.
Les appels récursifs conservent les limites d'appels et de profondeur d'Engine.

Ces interfaces ne transforment pas l'objet en tableau : `array_map()` et
`array_key_exists()` continuent d'exiger un tableau. Pour ces fonctions, utiliser
une méthode comme `all()`. Les objets de service ne peuvent pas être stockés dans
un State, même à l'intérieur d'un tableau.

Le [guide des itérateurs](iterateurs.md) décrit le parcours personnalisé avec
`Iterator`, `IteratorAggregate` et `foreach($objet)`, désormais pris en charge.
