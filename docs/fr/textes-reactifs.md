# Afficher des textes réactifs

Les composants de texte partagent la même gestion des valeurs. PHP produit le premier texte ; Engine recalcule les expressions dans le navigateur quand un `State` change. La liaison est déterminée à partir du code source, jamais en comparant les valeurs de deux propriétés.

```php
#[State] public string $message = 'André';
#[State] public int $quantite = 2;

public function body(): View
{
    return VStack(
        Text($this->message),
        Paragraph('Texte ' . $this->message),
        Heading('{message} et aussi {message}', 2),
        Strong("Bonjour {$this->message}"),
        Text("{$this->message} : {$this->quantite} articles"),
        Button('Modifier')->onClick(function (): void {
            $this->message = 'Marie';
            $this->quantite++;
        }),
    );
}
```

L'exemple complet se trouve dans [`examples/textes-reactifs/page.php`](../../examples/textes-reactifs/page.php).

## Écritures disponibles

| Écriture | Exemple |
| --- | --- |
| Propriété `State` directe | `Text($this->message)` |
| Concaténation | `Text('Bonjour ' . $this->message . ' !')` |
| Interpolation PHP | `Text("Bonjour {$this->message}")` |
| Placeholder répété | `Text('{message} puis {message}')` |
| Plusieurs états | `Text('{message} : {quantite}')` |
| Tableau imbriqué | `Text($this->profil['ville'])` ou `Text('{profil.ville}')` |
| Expression conditionnelle | `Text($this->actif ? $this->message : 'Inactif')` |
| Valeur de remplacement | `Text($this->message ?? 'Sans message')` |
| Calcul et formatage | `Text(number_format($this->prix * $this->quantite, 2, ',', ' '))` |
| Fonction pure d'un service chargé | `Text(formater($this->message))` |
| Fonction fléchée et captures | `Text(fn () => $prefixe . $this->message)` |
| Composition | `Paragraph('Bonjour ', Strong($this->message), ' !')` |
| Dépliage d'un tableau | `Paragraph(...['Bonjour ', $this->message])` |
| Référence explicite | `Text($this->state('message'))` |
| Accolades littérales | `Text('{{message}}')` affiche `{message}` |

Les chaînes simples, doubles, `heredoc` et `nowdoc` sont prises en charge. `\n` et `\t` suivent les règles PHP : une chaîne entre apostrophes conserve généralement leur écriture littérale. Le navigateur applique ensuite les règles HTML : utiliser `CodeBlock()` ou `->style('white-space', 'pre-wrap')` pour conserver visuellement les retours à la ligne.

Les nombres sont convertis en texte. `true` affiche `1`, `false` et `null` affichent une chaîne vide, comme la conversion PHP en chaîne. Utiliser un ternaire pour des libellés comme « Oui » et « Non ».

Les valeurs sont échappées dans le HTML initial, puis affectées avec `textContent`. Une valeur `<b>Bonjour</b>` reste du texte. Les accolades présentes **dans une valeur d'état** ne déclenchent pas une seconde interpolation. Un placeholder inconnu dans le modèle signale une erreur.

## Composants concernés

- Typographie : `Text`, `Heading`, `Span`, `Paragraph`, `Strong`, `Emphasis`, `SmallText`, `MarkedText`, `DeletedText`, `InsertedText`, `Subscript`, `Superscript`, `InlineCode`, `CodeBlock`, `Quote`, `BlockQuote`, `Abbreviation`, `Time`, `Address`, `KeyboardInput`, `SampleOutput`, `Variable`.
- Libellés : `Button`, `Action`, `MenuItem`, `Link`, `ExternalLink`, `EmailLink`, `PhoneLink`, `DownloadLink`, `Alert`, `Label`, `Legend`, `Output`.
- Structure : `FigCaption`, `Summary`, `ListItem`, `Caption`, `TableHeader`, `TableCell`, et texte de repli de `Canvas`.
- Interfaces composées : titre de `Modal`, message de `Toast`, déclencheur de `Dropdown` et `Popover`, texte de `Tooltip`.
- Sélection : texte de `Option` et libellés du tableau fourni à `Select`. Les valeurs des options et leur nombre restent ceux construits par PHP.
- `NoScript` reçoit le texte initial calculé par PHP ; sans JavaScript, il ne peut pas se mettre à jour.

Les attributs comme les adresses des liens, les identifiants, `datetime` ou la signification d'une abréviation conservent leur API propre. Ce mécanisme concerne le contenu textuel.

## Variables locales, fonctions et limites du compilateur

Une propriété PHP sans `#[State]` reste une valeur du rendu initial. Une propriété `#[Computed]` ayant des dépendances déclarées peut être utilisée directement comme un état.

Les calculs locaux qui alimentent un texte sont suivis : affectations successives,
affectations composées, conditions et boucles complètes exécutées avant le
composant. View sélectionne leurs dépendances et Engine les recalcule en lecture
seule. Les autres instructions de `body()` ne sont pas rejouées.

```php
$libelle = $this->message;
if ($this->actif) {
    $libelle .= ' actif';
} else {
    $libelle .= ' inactif';
}
$total = 0;
foreach ($this->articles as $article) {
    $total += $article['prix'];
}
return Paragraph($libelle, ' : ', $total);
```

Les fonctions et méthodes qui fabriquent des composants conservent maintenant
les dépendances de leurs paramètres scalaires et tableaux, y compris avec des
arguments nommés, des valeurs par défaut et plusieurs fonctions intermédiaires.
Les tableaux sont transmis comme expressions complètes : leur nombre d'éléments
peut changer entre deux mises à jour.

```php
function legende(string $message): View
{
    return Paragraph('Bonjour ' . $message);
}
// Dans body() :
return legende($this->message);
```

Les captures explicites d'une closure gardent le comportement PHP :
`Text(fn () => $prefixe . $this->message)` capture `$prefixe` comme instantané.
La fabrique `Component()` conserve les dépendances transmises au constructeur,
y compris les propriétés promues, privées ou `readonly`, les tableaux et les
calculs locaux purs du constructeur. Les propriétés PHP gardent leur type et
leur valeur initiale ; Engine transporte séparément leur expression réactive.

```php
class Salutation extends Component
{
    public function __construct(private readonly string $message) {}

    public function body(): View
    {
        return Text('Bonjour ' . $this->message);
    }
}
// Dans le body() du parent :
return Component(Salutation::class, message: $this->message);
```

La liaison traverse plusieurs enfants, accepte les modèles `{message}` et peut
être lue dans leurs événements. Elle va du parent vers l'enfant : les `#[State]`
propres à l'enfant restent indépendants, et affecter une propriété d'entrée dans
un événement n'écrit pas dans le parent. Une entrée typée invalide signale une
erreur de rendu et conserve le dernier texte valide jusqu'à correction.
Le constructeur PHP s'exécute une seule fois ; seuls les calculs nécessaires aux
propriétés affichées sont rejoués dans le navigateur.

`new Salutation($this->message)` est également reconnu lorsqu'il est passé
à un conteneur natif, retourné directement par `body()`, conservé dans un alias
local simple ou retourné par une fabrique identifiable à un seul `return`.
Les arguments nommés et dépliés sont compilés sans réexécuter la fabrique PHP.
`Component(...$arguments)` peut aussi recevoir la classe dans le tableau.

Les constructeurs peuvent appeler `parent::__construct()`, utiliser des méthodes
auxiliaires nommées, des propriétés sélectionnées dynamiquement, des retours
anticipés et les hooks de propriétés de PHP 8.4. Engine crée un objet temporaire
isolé pour recalculer leurs résultats scalaires et tableaux. Les règles de type,
visibilité, héritage et `readonly` restent appliquées. L'objet temporaire ne
remplace pas le composant rendu et ne partage pas les services statiques de ses
événements.

Ces calculs doivent rester déterministes : aucune sortie, écriture statique,
mutation de State, allocation de service externe ou lecture du hasard n'est
rejouée. Les méthodes invoquées doivent être identifiables à la compilation.
Les constructeurs complexes recevant des objets PHP non sérialisables, des vues
ou des closures ne sont pas des projections automatiques générales. Les entrées
ne doivent pas être réaffectées après la construction. Pour une construction
dissimulée dans un tableau mutable, une boucle, plusieurs branches de retour ou
un callable impossible à identifier, utiliser `Component()` à l'endroit où le
composant est réellement créé. Les ambiguïtés détectées produisent une erreur
avec l'origine PHP.

## Formulaires entre composants

Une entrée recopiée directement depuis un `#[State]` conserve son adresse.
Un champ enfant peut ainsi écrire dans le State du parent :

```php
class EditeurNom extends Component
{
    public function __construct(private readonly string $nom) {}

    public function body(): View
    {
        return TextField('nom', $this->nom);
    }
}
// Dans le body() du parent, dont $nom est un #[State] :
return VStack(
    Component(EditeurNom::class, $this->nom),
    Text('Bonjour ' . $this->nom),
);
```

La propriété PHP reste une chaîne `readonly` : la saisie écrit dans le State du
parent dans le navigateur. Les copies simples, fonctions auxiliaires et enfants
imbriqués conservent cette liaison. Les arguments nommés, tableaux littéraux
dépliés et dépliages associatifs à clés nommées conservent aussi les adresses
identifiables. Un dépliage positionnel de taille variable ou une collection
variadique ne fournit pas une adresse d'écriture stable : passer une `StateRef`
explicite dans ces cas.

Cela s'applique à `Input`, `TextField`, `EmailInput`, `NumberInput`, `DateInput`,
`SearchInput`, `HiddenInput`, `TextArea`, `Checkbox`, `Select` et `->value()`.
Les valeurs nulles initiales, nombres, booléens et chemins de tableau à clés
littérales simples sont reconnus, par exemple `->value($this->profil['ville'])`.
Les types scalaires des champs de tableau sont conservés. Les clés contenant
un point et les clés calculées demandent une liaison explicite appropriée.
Les radios et fichiers conservent leurs API explicites `model()` et `files:`.

Une valeur calculée comme `strtoupper($this->nom)` ou une propriété transformée
par un constructeur complexe reste en lecture seule. L'utiliser comme valeur
automatique d'un champ signale une erreur ; View ne tente pas d'inventer une
conversion inverse. Les propriétés `#[Computed]` ne sont pas des cibles
d'écriture. Les States propres aux enfants restent isolés, même si deux champs
ou deux instances démarrent avec la même valeur.

Les événements peuvent aussi écrire directement dans une prop mutable liée :
`$this->nom = 'Marie'`. L'adresse du State d'origine est conservée à travers
les composants imbriqués. Une prop promue mutable initialisée par une valeur
littérale ou par défaut garde une valeur locale à son instance. Voir le
[guide des propriétés de composants](composants-props.md) pour un exemple
complet avec `Carte()`, les types et les limites d'écriture.

Les fonctions réactives emploient la grammaire frontend des niveaux 1 à 8 : elles peuvent calculer, utiliser des variables locales, appeler d'autres fonctions chargées et effectuer une récursion bornée. Elles doivent rester en lecture seule. Les écritures d'état, sorties console, références de paramètres/captures et appels non pris en charge sont refusés. Générer une valeur aléatoire dans un événement puis l'enregistrer dans un état. Les instructions uniques sans accolades sont également compilées.

`{message}` est une syntaxe de modèle du composant. Pour fournir une valeur à un service, écrire `formater($this->message)` ou utiliser l'interpolation PHP. Les modèles passés à une fonction utilisateur ne sont pas un remplacement général du langage PHP.

Le fichier source doit être disponible pour les liaisons automatiques. Une fabrique affectée explicitement, par exemple `$texte = Text(...); $texte($this->message)`, est reconnue. Les combinaisons d'arguments ordinaires et de plusieurs tableaux dépliés sont reconnues pour le contenu variadique. Placer sur des lignes séparées les appels d'un même composant dans des branches conditionnelles, ainsi que les appels d'une même fonction auxiliaire recevant des valeurs réactives. Les appels ambigus sont refusés. Les fonctions auxiliaires acceptent les arguments réactifs dépliés, les paramètres variadiques, les clés nommées et les valeurs par défaut ; leur nombre d'arguments peut changer. Les callables affectés explicitement (`$legende = legende(...);`) sont reconnus. Les paramètres réactifs par référence restent refusés. Les boucles PHP qui créent des éléments ne deviennent pas des listes dynamiques : utiliser les primitives de liste de View pour ajouter ou retirer des éléments.

Une expression réactive non compilable signale son origine PHP. Une erreur de calcul lors d'une mise à jour est signalée dans la console et par `aml:render-error`, avec le fichier et la ligne PHP ; elle n'annule pas le changement d'état déjà validé. Le dernier texte valide est conservé et les autres liaisons continuent leur mise à jour. Une mise à jour valide suivante permet de reprendre le rendu. `TestView::renderErrors()` expose les mêmes échecs d'affichage avec l'exception et son origine ; l'état validé n'est pas annulé par une erreur de rendu. Aucun code PHP arbitraire n'est exécuté dans le navigateur.

## Accents et emoji

Les [28 fonctions Unicode `mb_*`](unicode.md) sont utilisables dans les mêmes
expressions, par exemple `Text(mb_strtoupper($this->message))` ou
`Text(mb_substr($this->message, 0, 12))`. Les positions comptent des points de code ;
les emoji composés et les accents décomposés peuvent en contenir plusieurs.

## Vérifications

`tests/reactive-bindings.php` vérifie les calculs locaux, les fonctions auxiliaires,
les copies de tableaux, le premier rendu, les mises à jour et la récupération
après une division par zéro. `reactive-bindings.spec.mjs` vérifie les mêmes
interactions dans le navigateur avec une CSP sans `unsafe-eval`.

`tests/component-forms.php` et `component-forms.spec.mjs` couvrent les écritures
parent/enfant, champs frères, types des tableaux, entrées nulles, constructions
directes, fabriques, héritage, hooks, diagnostics et reprise après erreur de type.
