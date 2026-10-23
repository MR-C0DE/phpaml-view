# Composants natifs : méthodes et comportement

Les composants comme `Button`, `Input`, `Select`, `Link`, `Text`, `VStack`
et `HStack` retournent des éléments View. Ils partagent les méthodes d’`Element`,
mais une méthode HTML n’a d’effet que sur les éléments auxquels elle s’applique.
Par exemple, `rows()` concerne `TextArea`, `submit()` un bouton et `state()`
un formulaire. `state()` refuse explicitement les autres éléments.

## Boutons

```php
Button('Ajouter un point')
    ->class('primary')
    ->onClick(function (): void {
        $this->name .= '.';
    });
```

| Usage | Méthodes |
| --- | --- |
| Action | `onClick($handler)`, alias `click($handler)` |
| Raccourcis d’action | `increments($state, $by)`, `decrements($state, $by)`, `updates($state, $by)`, `sets($state, $value)`, `toggles($state)` |
| Désactivation | `disabled($enabled = true)`, `disabledWhen($state, $equals = true)` |
| Présentation réactive | `showWhen($state, $equals = true)`, `classWhen($state, $class, $equals = true)` |
| Formulaire | `submit($enabled = true)`, `type('button'|'submit'|'reset')`, `name($name)`, `value($value)` |
| Chargement | `loadingLabel($label)` pendant une opération API ou Files déclenchée par ce bouton |
| Statut initial | `status(ActionStatus::Loading)`, `Idle`, `Success`, `Error`, `Warning` ou `Info` |

`Button` crée un bouton de type `button`. `submit()` le transforme en bouton
d’envoi. `value()` définit sa valeur de formulaire ; son libellé vient de
`Button(...)`. Cela reste vrai lorsque la valeur est liée à un State.

Un événement possède un seul gestionnaire : le dernier `onClick()` ou raccourci
remplace le précédent. Pour plusieurs opérations, utiliser une closure avec
plusieurs instructions, ou `Actions::sequence(...)`.

```php
Button('Enregistrer')
    ->loadingLabel('Enregistrement…')
    ->onClick(Api::post('/api/profile')->errorIn('error'));
```

Cet exemple suppose l’import de `AML\Engine\Api` et un State `error` déclaré.
Le texte de chargement est échappé, le bouton est désactivé pendant l’opération
et son contenu est restauré en cas de succès, d’erreur ou d’annulation. Ses
enfants réactifs continuent à recevoir les mises à jour. Plusieurs opérations
en cours conservent le retour de chargement jusqu’à la dernière terminaison.
Pour `Form(...)->onSubmit(...)`, ce retour s’applique au bouton d’envoi utilisé.
Sans bouton d’envoi identifié, aucun libellé de bouton n’est remplacé.

`status()` configure le rendu PHP ; il ne suit pas automatiquement une requête.
Passer de `Loading` à `Idle` ou `Success` retire les attributs imposés par le
statut précédent et conserve une désactivation explicitement demandée avec
`disabled()`. `loading('lazy')` est une méthode pour les images/iframes, pas un
état de chargement des boutons.

## Listes avec IList

`IList()` retourne une liste HTML `<ul>` par défaut. Les deux écritures suivantes
sont équivalentes ; `ordered()` ou `type('ordered')` produisent un `<ol>`.

```php
use function AML\View\{IList, ListItem, Text};

IList('Pomme', 'Banane')->type('unordered');
IList('Pomme', 'Banane')->unordered();
IList(['Première étape', 'Deuxième étape'])->ordered()->start(3);
IList(Text('Un composant'), ListItem('Une ligne explicite'));
```

Les valeurs et les vues deviennent des `<li>`. Un `ListItem()` déjà fourni n’est
pas enveloppé une seconde fois. Les données d’un tableau sont affichées comme
du texte littéral et échappé, y compris lorsqu’elles contiennent des accolades.

| Usage | Méthodes |
| --- | --- |
| Type de liste | `unordered()`, `ordered()`, `type('unordered'|'ordered')` |
| Numérotation | `start($entier)`, `reversed($enabled = true)` |
| Marqueurs | `marker('disc'|'circle'|'square'|'decimal'|'upper-roman'|...)`, `plain()` pour aucun marqueur |
| Position du marqueur | `markerPosition('inside'|'outside')` |
| Source | `IList($tableauOuState)`, `items($tableauOuState)`, `IList(fn () => ...)`, `items(fn () => ...)` |
| Enregistrements | `label('name')`, `key('id')` ; les chemins imbriqués sont acceptés |
| Contenu de chaque ligne | `renderItem(fn (CollectionItem $item): View => ...)` |
| Liste vide | `empty('Aucun élément')` ou `empty($vue)` |
| Réorganisation du State | `sortable($enabled = true)` : glisser-déposer ou `Alt+ArrowUp` / `Alt+ArrowDown` |
| Présentation | `id()`, `class()`, `style()`, `padding()`, `textColor()`, attributs et événements habituels |

`start()` accepte aussi zéro et les entiers négatifs. `reversed()` inverse la
numérotation native, sans réordonner les données. Ces deux options n’émettent
des attributs que sur une liste ordonnée ; passer à `unordered()` les masque,
puis revenir à `ordered()` les restaure. Le dernier choix de type s’applique.
Un marqueur choisi explicitement reste indépendant du type de liste.

Les marqueurs intégrés sont `disc`, `circle`, `square`, `none`, `decimal`,
`decimal-leading-zero`, `lower-alpha`, `upper-alpha`, `lower-latin`, `upper-latin`,
`lower-roman`, `upper-roman`, `lower-greek`, `armenian` et `georgian`. Les styles CSS
peuvent définir d’autres présentations. La sémantique `role="list"` reste présente,
y compris avec `plain()`. `gap()` exige une mise en page appropriée, comme sur
les autres éléments ; il ne transforme pas automatiquement la liste en grille.

Une liste réactive utilise une référence au tableau complet :

```php
use AML\Engine\ClientAction;
use AML\View\{CollectionItem, View};
use function AML\View\{Button, HStack, IList, State, Text, VStack};

function Fruits(): View
{
    $fruits = State(['Pomme', 'Banane']);

    return VStack(
        IList($fruits)->ordered()->empty('Aucun fruit'),
        Button('Ajouter')->onClick(ClientAction::append($fruits, 'Orange')),
        Button('Vider')->onClick(ClientAction::clear($fruits)),
    );
}
```

Dans une classe, `IList($this->fruits)` et `IList()->items($this->fruits)`
conservent la liaison directe à `#[State]`. La référence explicite
`$this->state('fruits')` fonctionne aussi, notamment pour transmettre la liste à
un composant enfant. Les tableaux calculés à partir de ces propriétés sont aussi
réactifs : filtres, transformations, tranches, fonctions auxiliaires et calculs
locaux purs.

```php
IList(array_filter($this->utilisateurs, fn (array $user): bool => $user['actif']));
IList()->items(array_reverse($this->utilisateurs))->key('id')->label('name');
```

Avec `State()` local, utiliser une closure pour le calcul. Le handle `StateRef`
est lu comme sa **valeur courante** par le compilateur frontend :

```php
use AML\View\View;
use function AML\View\{Button, IList, State, TextField, VStack};

function FruitsFiltres(): View
{
    $fruits = State(['Pomme', 'Banane']);
    $recherche = State('');

    return VStack(
        TextField('recherche', $recherche),
        IList(fn (): array => array_filter(
            $fruits,
            fn (string $fruit): bool => str_contains(strtolower($fruit), strtolower($recherche)),
        ))->empty('Aucun résultat'),
        Button('Ajouter')->onClick(function () use ($fruits): void {
            $fruits[] = 'Orange';
        }),
    );
}
```

La valeur initiale est rendue en PHP, sans attendre JavaScript. Engine suit les
States utilisés dans le calcul et les fonctions appelées ; il évite de recalculer
la liste lors des mises à jour ciblées sans rapport. Les clés PHP préservées par un filtre
sont normalisées en positions d’affichage. `key('id')` conserve l’identité des
lignes qui restent présentes.

Les closures d’affichage sont en lecture seule et doivent retourner un tableau.
Les écritures dans un State, sorties et opérations impures sont refusées ; les
limites d’exécution du compilateur restent applicables. Une erreur de calcul,
un doublon de clé ou un libellé invalide conserve le dernier contenu valide,
signale `aml:render-error` avec son origine PHP et laisse les listes voisines
se mettre à jour. Un prochain calcul valide rétablit le rendu.

`sortable()` exige une liaison directe au tableau State : une projection
filtrée ou transformée n’a pas d’ordre d’écriture implicite vers la source.

Pour des enregistrements ayant un identifiant stable :

```php
IList($utilisateurs)
    ->key('id')
    ->renderItem(fn (CollectionItem $personne): View => HStack(
        Text('Utilisateur :'),
        $personne->text('name'),
    ))
    ->sortable()
    ->empty('Aucun utilisateur');
```

`renderItem()` fournit le contenu **à l’intérieur** du `<li>` : ne pas y retourner
un `ListItem()`. Pour des données scalaires, `$item->text('value')` fournit leur
texte réactif. Dans les arguments analysés par View (texte, `Style`, `Link` et
props), `$item->value('champ')` conserve maintenant sa liaison réactive, y compris
avec une concaténation ou une fonction pure. En PHP ordinaire, `value()` reste la
valeur initiale. La closure de construction PHP ne se réexécute pas dans le navigateur :
utiliser `When()` ou `showWhen()` pour les conditions qui doivent changer.

Pour une liaison explicite, `$item->state('champ', $valeurParDéfaut)` retourne un
`StateRef`. Ce même handle fonctionne dans les champs, les styles, les conditions,
les props et les événements. Les chemins imbriqués comme `profil.nom` sont acceptés.
Une valeur par défaut permet de construire le modèle même quand la liste est vide.

```php
IList($utilisateurs)->key('id')->renderItem(function (CollectionItem $item): View {
    $nom = $item->state('name', '');
    return HStack(
        Text('Bonjour ' . $item->value('name', '')),
        TextField('name', $nom),
        Toggle('active', $item->state('active', false)),
        Button('Ajouter !')->onClick(function () use ($nom): void {
            $nom .= '!';
        }),
    );
});
```

L’écriture retrouve l’enregistrement par sa clé actuelle, après un déplacement
ou un filtre. **Les lignes éditables exigent des identifiants stables et uniques** :
les indices de position ne constituent pas une identité d’édition. Une clé absente,
un doublon ou une ligne supprimée bloque l’écriture, sans modifier un autre élément.
Les sous-listes possèdent leurs propres liaisons. Les composants enfants gardent leur
State pendant un déplacement et le libèrent lorsque la ligne disparaît.

Une liste calculée est en lecture seule par défaut. Pour modifier les enregistrements
d’origine, fournir explicitement le tableau State :

```php
IList(fn () => array_filter($utilisateurs, fn ($u) => $u['active']))
    ->key('id')
    ->editable($utilisateurs)
    ->renderItem(fn (CollectionItem $item): View =>
        TextField('name', $item->state('name', ''))
    );
```

Ici `$utilisateurs` est un `StateRef` (par exemple créé par `State([...])`). Dans une
classe, `editable($this->state('utilisateurs'))` fournit le lien explicite.
Le champ édité est écrit au même chemin dans l’enregistrement source : un champ
calculé n’a pas de conversion inverse automatique. `sortable()` reste réservé à
la source directe.

`type`, `start`, `reversed`, `marker` et `markerPosition` acceptent aussi un State,
un `StateRef` ou une closure pure. Exemple :

```php
IList($utilisateurs)->type($type)->start($depart)->reversed($inverse)
    ->marker($marqueur)->markerPosition($position);
```

Les options et toutes les clés sont validées avant la modification de la liste.
Une valeur invalide signale `aml:render-error` et conserve le dernier affichage
valide ; la prochaine valeur valide permet de reprendre. Ce comportement couvre
les sources State directes comme les sources calculées. Le passage entre `ul` et
`ol` conserve les lignes et leur State. La numérotation accepte zéro et les entiers négatifs.

Une source statique ou un State direct doit être un tableau indexé (utiliser
`array_values()` si nécessaire). Les sources calculées affichent les valeurs du
tableau dans leur ordre d’itération.
Les clés explicites sont des chaînes ou entiers uniques ; sans identifiant, les
positions servent de clés. Préférer des identifiants stables pour des lignes
interactives réordonnées. Les configurations invalides et les doublons initiaux
produisent une erreur explicite. `sortable()` exige une source State. Une liste
vide reste une vraie liste et son message est masqué lorsqu’un élément apparaît.
`Each()`, `SortableEach()`, `UnorderedList()` et `OrderedList()` restent disponibles.

## Champs et formulaires

`value($this->state('name'))` relie un champ à un State et fournit sa valeur
initiale. La syntaxe naturelle `value($this->name)` reste disponible dans les
composants dont le code source peut être analysé. `model(...)` et `bindClient(...)`
sont les liaisons explicites existantes.

```php
Input('name')->value($this->state('name'));
TextArea('bio')->value($this->state('bio'))->rows(5);
Checkbox('accepted', $this->state('accepted'));
Select('role', ['member' => 'Membre', 'admin' => 'Administrateur'])
    ->value($this->state('role'));

RadioInput('role', 'member')->model($this->state('role'));
RadioInput('role', 'admin')->model($this->state('role'));

Select('roles', ['member' => 'Membre', 'admin' => 'Administrateur'])
    ->multiple()->model($this->state('roles'));
```

Le State des radios contient la valeur de l’option choisie. Engine modifie
`checked`, sans remplacer les valeurs propres aux options. Le State du select
multiple est un tableau de chaînes. Une sélection scalaire définie avec
`Select(...)->value(...)` est aussi appliquée au HTML initial, avant Engine.
Les fichiers sélectionnés restent la propriété du navigateur ; `FileInput`
expose leurs métadonnées au State, pas un chemin sur le serveur.

`required()`, `minLength()` et `email()` acceptent des messages de validation.
Les contraintes HTML telles que `pattern()`, `min()`, `max()` et `step()` sont
vérifiées avant une action de formulaire ; leurs messages viennent du navigateur.
Les champs désactivés, y compris par un `Fieldset` désactivé, sont exclus de la
validation locale et distante. Un champ requis de type radio accepte une
sélection dans son groupe.

Les messages d’erreur disposent d’identifiants distincts même lorsque plusieurs
champs ont le même nom. Ils complètent `aria-describedby` sans supprimer les
références aux textes d’aide. Corriger l’erreur retire seulement sa référence.

## Textes, liens et conteneurs

`Text`, `Heading`, les textes sémantiques et les libellés réactifs partagent le
[traitement des textes](textes-reactifs.md). Les contenus sont échappés ; les
concaténations, interpolations et fonctions d’affichage autorisées restent
réactives. `Link($label, $href)` conserve sa destination lorsque son libellé
change. `nativeNavigation()` demande une navigation normale du navigateur.

`class(...)`, `style(...)`, `padding()`, `fontSize()`, `fontWeight()`,
`textColor()`, `lineHeight()`, `letterSpacing()` et `textAlign()` configurent
l’élément. `spacing()`/`gap()` et `alignment()`/`center()` s’appliquent selon son
mode de mise en page : `VStack` et `HStack` fournissent déjà un conteneur flex.
`center()` seul ne transforme pas un élément quelconque en conteneur flex.

Les règles `showWhen`, `classWhen` et `disabledWhen` acceptent un nom de State
ou un `StateRef`, avec une comparaison stricte à la valeur attendue. Un champ
masqué par un conteneur reste désactivé même si sa propre règle change.
Réafficher le conteneur ne retire pas les autres causes de désactivation :
autre conteneur masqué, étape inactive, règle réactive ou opération en cours.

## Vérification

`tests/component-contracts.php` vérifie les contrats du rendu PHP et fournit
une page réelle aux tests navigateur d’Engine, dans
`tests/browser/component-contracts.spec.mjs`. Ces tests couvrent les opérations
API/Files, les libellés réactifs, la soumission, les sélections, la visibilité,
la validation accessible, les textes, les liens et les styles de conteneurs.
Ils utilisent la même sélection de runtime complet/modulaire et de navigateur
que les autres tests d’Engine.

`tests/computed-ilists.php` vérifie aussi le rendu initial des projections et les
captures StateRef, avec `tests/browser/computed-ilists.spec.mjs` pour les deux
runtimes : filtres, fonctions, effets, composants enfants, erreurs et reprise.
