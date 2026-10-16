# Classes et objets dans les événements frontend

View `0.1.0-beta.13` et Engine `0.1.0-beta.9` peuvent compiler des classes de
service PHP déjà chargées. Engine crée leurs instances et exécute leurs méthodes
dans le navigateur. La compilation lit le code sans appeler les constructeurs
ni les méthodes de l'application.

Dans `body()`, avant `return`, PHP continue à s'exécuter normalement. Dans un
événement, les objets appartiennent à l'exécution frontend. Les instances créées
avant le rendu ne peuvent pas être capturées dans l'événement.

```php
Button('Calculer')->onClick(function (): void {
    $panier = new Panier();
    $panier->ajouter(new Ligne('Café', 1250, quantite: 2));
    $resume = $panier->resume();
    $this->message = number_format($resume['totalCentimes'] / 100, 2) . ' €';
});
```

L'[exemple complet](../../examples/objets/page.php) charge son
[service](../../examples/objets/service.php), applique une remise par héritage,
calcule en centimes et affiche le résultat avec un `State`.

## Ce qui fonctionne

- Classes nommées chargées avant le rendu, `new`, constructeurs et promotion des propriétés.
- Propriétés publiques, protégées et privées, valeurs initiales, types scalaires,
  tableaux et objets ; propriétés non initialisées et `readonly`.
- Lecture, écriture, affectations combinées, incréments, tableaux dans les
  propriétés, `isset`, `empty`, `??` et `unset`.
- Méthodes d'instance et statiques, arguments nommés, valeurs par défaut,
  propagation, paramètres variadiques, références et types de retour.
- Héritage, redéfinition de méthodes, classes abstraites, contrats d'interface,
  méthodes issues des traits, `self`, `parent` et liaison tardive avec `static`.
- Constantes de classe, `::class`, `instanceof`, `get_class()` et `is_object()`.
- Closures et fonctions fléchées liées à `$this`, méthodes comme callables avec
  `$objet->methode(...)`, appels récursifs et chaînes d'appels.
- Identité partagée lors de l'affectation ou du passage d'un objet en paramètre,
  comparaison `===`, comparaison des champs avec `==` et copie superficielle `clone`.
- Méthodes magiques `__get`, `__set`, `__isset`, `__unset`, `__call`, `__callStatic`,
  `__invoke`, `__toString` et `__clone` : voir le [guide dédié](methodes-magiques.md).
- Chaînes nullsafe avec `?->` : récepteurs, arguments et offsets suivants sont ignorés après un récepteur `null`.

Les erreurs de type, d'accès, de propriété non initialisée, de méthode inconnue
ou d'instanciation abstraite sont interceptables avec `try/catch`. Une erreur
non interceptée annule les modifications du `State` de l'événement. Les sorties
déjà écrites dans la console restent visibles. Les écritures déjà réalisées dans
les propriétés statiques restent également conservées ; seule la transaction du
`State` est annulée.

## Propriétés statiques par composant

Les propriétés statiques des classes de service sont prises en charge avec
`Compteur::$valeur`, `self::$valeur`, `parent::$valeur` et `static::$valeur`.
Les accès utilisent la visibilité et les types déclarés en PHP. Une propriété
héritée partage sa valeur avec le parent ; une propriété redéclarée dans l'enfant
a son propre stockage. La liaison tardive de `static` choisit la classe appelée.

```php
final class Compteur
{
    private static int $valeur = 0;
    public static function suivant(): int { return ++self::$valeur; }
}

// Dans body() : l'événement sera exécuté dans le navigateur.
Button('Ajouter')->onClick(function (): void {
    $this->total = Compteur::suivant();
});
```

**Le stockage frontend est isolé par instance de composant.** Deux composants
utilisant `Compteur` commencent chacun à zéro. Les événements et effets d'une
même instance partagent ses valeurs, conservées jusqu'à son démontage. Une
nouvelle instance, une ligne de liste supprimée puis recréée, une branche `When`
recréée ou un rechargement repart des valeurs déclarées. Cette durée de vie est
un choix de PHPAML : elle diffère du stockage statique global d'une requête PHP.

La compilation lit les valeurs par défaut déclarées, jamais les valeurs
statiques courantes du serveur. Les propriétés non initialisées restent telles
quelles. Leur lecture lève une `Error` ; `isset`, `empty` et `??` permettent de les
tester. Passer une propriété non initialisée et non nullable par référence lève
également une `Error`. Les affectations, incréments, paramètres par référence,
fonctions natives qui modifient un tableau et offsets imbriqués sont disponibles.
`unset(Compteur::$valeur)` lève une `Error`, comme en PHP ; retirer une entrée de
tableau avec `unset(Compteur::$tableau['cle'])` est permis.

Une propriété statique peut conserver un objet de service ou une closure frontend
entre deux événements. Ils restent dans la mémoire du composant et ne peuvent
pas être publiés dans le `State`. Le catalogue retenu est borné à 64 classes et
256 fonctions/méthodes par composant. Les propriétés statiques ne sont pas
réactives : publier le résultat dans un `#[State]` pour actualiser le texte.
Elles ne sont ni persistées dans le stockage du navigateur, ni synchronisées
entre composants, onglets ou utilisateurs.

Le même support existe dans `eval()` pour les classes du catalogue frontend,
avec les mêmes règles de visibilité. Les cibles de classes dynamiques sont disponibles
avec `$classe::$propriete` ; les noms calculés de propriétés statiques restent exclus. Les variables locales `static` sont décrites dans le
[guide des fonctions avancées](fonctions-avancees.md), et les alias dans le
[guide des références](spl-references.md). Les propriétés statiques ici
concernent les classes de service, pas la déclaration de `State` statiques sur
les composants.

L'[exemple des deux compteurs](../../examples/proprietes-statiques/page.php)
utilise le même [service](../../examples/proprietes-statiques/service.php) dans
deux instances indépendantes.

## Chargement et affichage

Charger les services avant le rendu via les fichiers d'autoload Composer, le
chargement explicite du projet ou `require_once`. `use` fournit un nom court ;
il ne charge pas à lui seul un fichier. Le `import()` actuellement fourni par
`AML\View` importe des feuilles de style, pas des classes PHP.

Le catalogue frontend contient les classes utilisées, leurs ancêtres, leurs
propriétés et leurs méthodes. Toutes les méthodes de ces classes doivent être
compatibles avec le langage frontend. Les valeurs par défaut ne déclenchent
aucun autoload supplémentaire pendant leur inspection.

Les objets restent locaux. Un `State`, y compris un tableau imbriqué dans un
`State`, ne peut pas contenir d'objet de service. Fournir une méthode comme
`resume(): array` ou `libelle(): string`, puis placer son résultat dans le
`State` affiché. Créer les objets dans les événements : les formateurs réactifs
de texte n'acceptent pas de construction ou de mutation d'objet.

`echo` écrit dans la console du navigateur. `var_dump($objet)` y affiche le nom
de classe et les champs, avec une protection contre les cycles ; ce diagnostic
n'utilise pas les identifiants d'objets du processus PHP. `print_r` et
`var_export` acceptent toujours les valeurs scalaires et tableaux ; passer le
résultat de `resume()` pour un service.

## Avec eval()

Une chaîne dynamique peut appeler les objets locaux et créer des instances de
classes déjà présentes dans le catalogue compilé de l'événement. Les noms
écrits dans la chaîne doivent être pleinement qualifiés, sauf `self`, `parent`
et `static` dans une méthode. Les imports du fichier appelant ne sont pas repris.
L'eval d'une méthode conserve son objet et sa visibilité.

```php
$produit = new Produit('Café', 1250);
$code = '$produit->quantite++; return $produit->totalCentimes();';
$this->totalCentimes = eval($code);
```

Les déclarations de classes concrètes **nommées** et d’enums dans `eval()` sont disponibles.
Les [classes anonymes](classes-anonymes-intersections.md) sont disponibles. Voir aussi
le [guide eval](eval.md) pour l'asset d'analyse chargé à la demande et les budgets.

## Limites explicites

Ce support couvre les objets de service frontend, pas toutes les fonctionnalités
objet de PHP. Les hooks, la visibilité asymétrique, la réflexion adaptée et la
sérialisation sont détaillés dans les [extensions du langage](extensions-langage.md).
Restent exclus les propriétés dynamiques de services hors `stdClass`, les
 destructeurs, les objets internes sans adaptateur et les valeurs par défaut
qui construisent un objet. Les classes anonymes et les types intersection sont
décrits dans le [guide dédié](classes-anonymes-intersections.md).
Les [fonctions avancées](fonctions-avancees.md) couvrent les noms de méthodes
et de propriétés d'instance calculés ainsi que les callables `[$objet, 'methode']`
ou `'Classe::methode'`.
Les [dates](json-regex-dates.md), les [classes SPL](spl-references.md) et les
[enums](enums.md) disposent d'adaptateurs explicites.

`foreach ($objet as ...)` parcourt les services `Iterator`/`IteratorAggregate`
et les générateurs. Un objet sans contrat `Traversable` n'est pas parcourable.
Ne pas utiliser les conversions d'objet vers nombre. La conversion en chaîne
nécessite `__toString`. Éviter aussi
leur comparaison avec un scalaire. Les cibles de classe dynamiques et les chaînes
nullsafe complètes sont décrites dans le [guide dédié](web-unicode-dynamique.md).

Le catalogue est limité à 64 classes et 256 fonctions/méthodes. Chaque événement
dispose de 10 000 créations ou clones d'objets ; les limites existantes d'appels
et de boucles restent actives. Les champs `private` organisent l'accès du
programme mais leur contenu compilé est livré au navigateur.

Les règles de référence sont décrites dans les manuels PHP sur les
[objets](https://www.php.net/manual/fr/language.oop5.basic.php), les
[propriétés](https://www.php.net/manual/fr/language.oop5.properties.php) et la
[liaison statique tardive](https://www.php.net/manual/fr/language.oop5.late-static-bindings.php).

L’[introspection des services](introspection.md) couvre les membres accessibles, les relations entre classes et `__debugInfo()` dans la console.

Les [collections Countable et ArrayAccess](collections.md) permettent de compter
un service et de lire ou modifier ses éléments avec `[]`.

Le [guide des itérateurs](iterateurs.md) décrit `Iterator`, `IteratorAggregate`
et le parcours des services avec `foreach`.
