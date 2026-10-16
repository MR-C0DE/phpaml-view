# Classes anonymes et types intersection

Les événements View et les fonctions de service peuvent créer un objet avec
`new class(...) { ... }`. View analyse sa déclaration sans l'instancier ni exécuter
son constructeur. Engine construit l'objet dans le navigateur, au moment de l'appel.
ViewTest interprète les mêmes instructions.

```php
interface Nommable { public function nom(): string; }
interface Evaluable { public function points(): int; }

function presenter(Nommable&Evaluable $profil): string
{
    return $profil->nom() . ' — ' . $profil->points() . ' points';
}
```

Dans un événement :

```php
$profil = new class('André', 15) implements Nommable, Evaluable {
    public function __construct(
        private readonly string $nom,
        private readonly int $points,
    ) {}

    public function nom(): string { return $this->nom; }
    public function points(): int { return $this->points; }
};

$this->message = presenter($profil);
echo $this->message . PHP_EOL;
```

L'objet reste local au service ou à l'événement. La chaîne publiée dans un `State`
s'affiche normalement avec `Text($this->message)`. `echo` reste dans la console du
navigateur. Les objets, y compris dans un tableau, ne peuvent pas entrer dans `State`.

## Déclarations prises en charge

- Constructeurs, paramètres par défaut, nommés, variadiques et par référence.
- Propriétés déclarées, typées, promues, privées, protégées, publiques et `readonly`.
- Méthodes d'instance et statiques, constantes de classe non typées, `self`, `parent`
  et `static`, héritage d'un service chargé et implémentation d'interfaces chargées.
- Classes imbriquées, closures liées à l'objet et callables de première classe.
- Les dix [méthodes magiques prises en charge](methodes-magiques.md).
- Les types `A&B` sur les paramètres, les retours et les propriétés, ainsi que les
  unions en forme normale disjonctive : `(A&B)|null`, `(A&B)|C`.

`A&B` exige **tous** les types du groupe. `(A&B)|C` accepte soit les deux premiers,
soit `C`. `?A&B` n'est pas une syntaxe PHP valide ; écrire `(A&B)|null`.
Les erreurs de contrat produisent des `TypeError` interceptables avec `catch`.
Les méthodes doivent respecter les signatures des interfaces et méthodes héritées,
avec contravariance des paramètres et covariance du retour.

## Identité et durée de vie

Deux objets créés par la même déclaration appartiennent à la même classe. Deux
écritures `new class {}` distinctes produisent des classes distinctes, même sur une
seule ligne. Le nom renvoyé par `get_class()` est un identifiant interne frontend ;
ne pas dépendre de sa forme ni le comparer à celui du moteur PHP serveur.

Les propriétés statiques suivent la durée de vie choisie pour les services : elles
sont partagées entre les objets de cette classe **dans un même composant**, conservées
entre ses clics et réinitialisées à sa recréation. Deux composants restent isolés.
Les arguments du constructeur sont évalués dans la portée appelante ; les méthodes
ne capturent pas les variables locales environnantes. Les passer au constructeur.

Les classes anonymes fonctionnent aussi dans `eval()` littéral et dynamique.
Chaque évaluation déclare de nouvelles classes ; un objet renvoyé par `eval()` reste
utilisable ensuite. L'évaluation dynamique utilise l'[asset d'analyse optionnel](eval.md).
Ses parents et interfaces doivent déjà appartenir au catalogue frontend compilé.
Les limites de 64 classes et de 256 fonctions/méthodes continuent de s'appliquer,
y compris aux nouvelles déclarations issues d'évaluations répétées.

## Limites de ce bloc

Les classes anonymes ne prennent pas encore en charge `use Trait`, les attributs,
les constantes typées ni les hooks de propriétés. Pour réutiliser un trait, étendre
un service nommé qui l'utilise. Les valeurs par défaut doivent être des expressions
constantes prises en charge (littéraux, tableaux, opérations arithmétiques,
concaténation et constantes de classe), sans construction d'objet ni appel de fonction.
Les autres [limites des objets frontend](objets.md#limites-explicites) s'appliquent.
L'[introspection et `__debugInfo`](introspection.md) fonctionnent aussi sur les classes anonymes.

L'[exemple complet](../../examples/classes-anonymes/page.php) associe une classe
anonyme, deux interfaces, une fonction de service et un compteur statique.

Références PHP : [classes anonymes](https://www.php.net/manual/en/language.oop5.anonymous.php)
et [déclarations de types](https://www.php.net/manual/en/language.types.declarations.php).
