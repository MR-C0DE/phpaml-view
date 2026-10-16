# Générateurs : yield et yield from

Une fonction contenant `yield` renvoie un objet `Generator`. Son corps commence
au premier parcours ou au premier appel qui le fait avancer. Chaque `yield`
suspend le corps ; la reprise conserve les variables, les références et la
position dans les boucles et les blocs `try/catch/finally`.

```php
function disponibles(array $articles): \Generator
{
    $total = 0;
    foreach ($articles as $article) {
        if ($article['stock'] <= 0) { continue; }
        $total++;
        yield $article['nom'];
    }
    return $total;
}

// Dans un événement frontend :
$flux = disponibles($this->articles);
$noms = [];
foreach ($flux as $nom) { $noms[] = $nom; }
$this->message = implode(', ', $noms) . ' : ' . $flux->getReturn();
```

Voir la [page complète](../../examples/generateurs/page.php) et son
[service](../../examples/generateurs/service.php). Les services doivent être
chargés pour que View lise leurs déclarations. Le compilateur n'exécute pas
leurs fonctions : Engine interprète les instructions dans le navigateur.

## Syntaxes et parcours

Sont pris en charge `yield`, `yield $valeur`, `yield $cle => $valeur` et
`yield from $source`. La source peut être un tableau, un service `Traversable`
ou un autre générateur. La délégation conserve les clés et transmet les valeurs
envoyées et les exceptions au générateur enfant. Son résultat est la valeur
renvoyée par l'enfant, ou `null` pour un tableau ou un itérateur ordinaire.

Les fonctions nommées, méthodes, fonctions anonymes, fonctions fléchées et
fonctions définies dans `eval()` peuvent être des générateurs. Un
`IteratorAggregate::getIterator()` peut utiliser `yield` directement.
Les paramètres sont validés à l'appel ; un type de retour doit accepter
`Generator`, par exemple `Generator`, `Iterator`, `Traversable` ou `iterable`.
Le `return` du corps est disponible avec `getReturn()` une fois le corps terminé.

`current()`, `key()`, `valid()`, `next()`, `rewind()`, `send()` et `throw()` sont
interprétés. Par exemple :

```php
$dialogue = function (): \Generator {
    $nom = yield 'Votre nom ?';
    yield "Bonjour $nom";
    return 'terminé';
};
$g = $dialogue();
echo $g->current();           // Console du navigateur.
echo $g->send('André');
$g->next();
echo $g->getReturn();
```

Un `break` suspend le parcours sans fermer le générateur conservé dans une
variable. On peut le reprendre manuellement. Un générateur déjà avancé ne se
rembobine pas ; un générateur fermé ne se parcourt pas une seconde fois.
Créer un nouveau générateur pour un nouveau parcours.

## Durée de vie, erreurs et limites

Un générateur reste un objet local et ne peut pas entrer dans `State`, même au
milieu d'un tableau. Une propriété statique de service peut le conserver entre
les clics du même composant ; les composants et leurs recréations sont isolés.
Les budgets du clic courant s'appliquent à chaque reprise. Les boucles restent
limitées à 1 000 passages chacune et 10 000 au total, les appels à 128 niveaux
et 10 000 opérations, les allocations à 10 000 objets. La délégation est bornée.
Les erreurs non interceptées restaurent `State`, avec leur origine PHP.

`finally` s'exécute lors du retour ou de la propagation d'une exception. **La
finalisation automatique d'un générateur abandonné par le ramasse-miettes PHP
n'est pas reproduite dans le navigateur ni dans ViewTest.** Pour des effets de
nettoyage observables, terminer explicitement son parcours ; ne pas dépendre
de la destruction d'une variable ou du composant.

Les fonctions `iterator_*`, les classes `ArrayIterator`/`ArrayObject` et la
propagation d'un `Traversable` dans les arguments ou les tableaux sont décrites
dans le [guide SPL et références](spl-references.md), avec les limites des
parcours par référence. L'événement lui-même ne peut pas être un générateur :
il doit appeler et consommer une fonction génératrice.

Références PHP : [Generator](https://www.php.net/manual/en/class.generator.php)
et [syntaxe des générateurs](https://www.php.net/manual/en/language.generators.syntax.php).
