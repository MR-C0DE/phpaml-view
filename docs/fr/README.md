# AML View — documentation française

AML View est la bibliothèque frontend déclarative et optionnelle de PHPAML.
Elle permet de construire des pages interactives en PHP sans JSX et sans
framework JavaScript côté client. PHP produit le premier HTML, puis PHPAML
Engine exécute les états et événements ordinaires dans le navigateur.

## Organisation d’une application

Une application créée avec `aml create-view-app` range son code dans `src/` :

```text
src/
├── views/
│   ├── pages/
│   │   ├── home/page.php
│   │   ├── about/page.php
│   │   └── users/[id]/page.php
│   ├── components/
│   │   └── Navigation.php
│   ├── layouts/
│   │   └── AppLayout.php
│   ├── states/
│   │   ├── Loading.php
│   │   ├── Error.php
│   │   └── NotFound.php
│   ├── stylesheets/
│       ├── base.css
│       ├── pages/home.css
│       ├── components/navigation.css
│       ├── layouts/app.css
│       └── states/route-states.css
│   └── themes/
│       ├── light/tokens.css
│       └── dark/tokens.css
├── controllers/
├── models/
├── middleware/ (optionnel)
└── services/ (optionnel)
public/
├── index.php
├── favicon.svg
├── phpaml-logo-violet-lime.png
└── assets/
```

Les dossiers de `src/views/pages` définissent automatiquement les URL. Ainsi,
`src/views/pages/home/page.php` correspond à `/`, tandis que
`src/views/pages/about/page.php` correspond à `/about`. Les layouts et les
états sont rangés dans leurs dossiers dédiés. La seule coquille HTML impérative
reste `public/index.php`.

Un dossier dynamique utilise des crochets :
`src/views/pages/users/[id]/page.php` correspond notamment à `/users/42`. La page lit
le paramètre avec `$this->param('id')`. Les fichiers `states/Loading.php`,
`states/Error.php` et `states/NotFound.php` décrivent les états de navigation. Le code MVC exécuté
uniquement sur le serveur reste disponible directement dans `src/`. Les composants
restent dans `src/views/components`. Les templates PHP classiques sont
volontairement absents des applications AML View.

`src/views`, `src/models` et `src/controllers` sont obligatoires dans une
application AML View. Les dossiers des middlewares et services sont optionnels.

Les styles se trouvent dans `src/views/stylesheets` et sont rangés par rôle :
`pages/`, `components/`, `layouts/` et `states/`, avec `base.css` pour les
règles globales. Le moteur les regroupe automatiquement et l’application les
expose à `/_aml/styles.css`; aucun CSS applicatif n’est placé dans `public/`.
Les images et médias ajoutés par l’application sont regroupés dans
`public/assets/`. Les documents qui exigent une URL directe — logo, favicon,
`robots.txt` ou `sitemap.xml` — restent à la racine de `public/`.

Les thèmes sont natifs à AML View. `ThemeProvider()` déclare les thèmes au
niveau du layout et `ThemeSwitcher()` peut être placé dans le header ou tout
autre composant. AML View applique le choix sur `<html>`, suit la préférence
système et conserve le choix sans JavaScript écrit par l’utilisateur. Les CSS
des thèmes sont rangés librement sous `src/views/themes/{theme}`.

## Fonctionnalités

Une phrase peut intégrer directement un état réactif :

```php
Text('Compteur : {count}');
Text('Bonjour {profile.name}');
```

Le nom racine doit correspondre à une propriété `#[State]`. Les chemins
imbriqués restent échappés et réactifs. Pour afficher littéralement
`{count}`, utilisez `Text('Exemple : {{count}}')`.

Les mutations frontend simples peuvent utiliser une closure PHP naturelle :

```php
Button('Ajouter')->onClick(function (): void {
    $this->count++;
});

Button('Réinitialiser')->onClick(function (): void {
    $this->count = 0;
});
```

La closure est analysée sans être exécutée, puis convertie vers les actions
déclaratives d'Engine. Le premier sous-ensemble accepte `++`, `--`, `=`, `+=`
et `-=` sur les propriétés `#[State]`, ainsi que les variables locales et les
calculs `+`, `-`, `*`, `/` et `%` :

```php
Button('Calculer')->onClick(function (): void {
    $sousTotal = $this->prix * $this->quantite;
    $this->total = $sousTotal - $this->remise;
});
```

Les priorités PHP et les parenthèses sont respectées. Les appels PHP arbitraires et les
propriétés non réactives sont refusés. Chaque closure frontend doit être écrite
sur ses propres lignes afin que sa source soit identifiable sans ambiguïté.
La négation booléenne, la concaténation et les affectations composées sont aussi
acceptées : `$this->ouvert = !$this->ouvert`, `$this->message = 'Total : ' . $this->total`
et `$this->quantite *= 2`.
Les ternaires PHP sont également réactifs :

```php
$this->remise = $sousTotal >= 100 ? 10 : 0;
$this->libelle = $this->actif ? 'Actif' : 'Inactif';
```

Une interaction plus longue ou réutilisable peut être placée dans une méthode
nommée du composant, puis transmise comme callable PHP de première classe :

```php
private function incrementer(): void
{
    $this->compteur++;
}

// Dans body()
Button('Ajouter')->onClick($this->incrementer(...));
```

La méthode est analysée avec les mêmes règles de sécurité frontend et n’est pas
exécutée pendant le rendu serveur.

Une action composée d’une seule expression peut utiliser la fonction fléchée PHP :

```php
Button('Ajouter')->onClick(fn () => $this->compteur++);
Button('Basculer')->onClick(fn () => $this->actif = !$this->actif);
```

Une closure avec accolades ou une méthode nommée reste préférable pour plusieurs
instructions.

L’affichage réactif reste du PHP ordinaire. Une référence d’état validée indique
les valeurs que le navigateur doit recalculer :

```php
Text('Compteur : {compteur}');
Text($this->state('connecte')->choose('Bienvenue', 'Connexion requise'));
Text($this->state('prix')->times($this->state('quantite')));
Text($this->state('compteur')->prefixedBy('Compteur : '));
Text($this->state('compteur')->concat(' éléments'));
```

`choose()` et les calculs sécurisés actualisent automatiquement leur texte quand
leurs dépendances changent.

Les premières mutations de collections utilisent la syntaxe PHP habituelle dans
les gestionnaires frontend sécurisés :

```php
$this->taches[] = $this->nouvelleTache;
array_pop($this->taches);
array_shift($this->taches);
```

Seules ces opérations explicitement autorisées sont compilées ; les autres appels
de fonctions PHP restent refusés.

Les conditions `if/elseif/else` (y compris `else if`) sont également compilées :

```php
Button('Vérifier')->onClick(function (): void {
    if ($this->count === 0) {
        $this->message = 'Commencez';
    } elseif ($this->count >= 10) {
        $this->message = 'Objectif atteint';
    } else {
        $this->message = 'Continuez';
    }
});
```

Le sous-ensemble accepte les états booléens ainsi que `==`, `===`, `!=`,
`!==`, `>`, `>=`, `<` et `<=` avec une valeur simple, un autre état ou une
expression locale calculée.

- propriétés réactives typées avec `#[State]` ;
- liaison explicite d'un champ avec `value($this->state('propriete'))`, tandis
  qu'une valeur scalaire reste une valeur HTML ordinaire ;
- soumission et événements frontend avec `submit()`, `onSubmit()`, `onInput()`,
  `onChange()`, `onFocus()` et `onBlur()` ;
- état partagé avec `#[Shared]` et persistance locale/session avec
  `#[Persisted]` ;
- propriétés calculées avec `#[Computed]` ;
- calculs frontend à dépendances explicites, batching automatique et transactions ;
- rendu conditionnel avec `When()` et collections réconciliées par clé ;
- pages, composants, layouts, `RouterView()` et `Slot()` ;
- composition sans balise supplémentaire avec `Group()` ;
- formulaires liés à l’état frontend ;
- actions locales et appels API explicitement déclarés ;
- rendu HTML sémantique et accessible.
- métadonnées SEO, URL canonique et aperçus sociaux déclaratifs par page.

## SEO déclaratif

Une page peut décrire son référencement sans écrire de balises HTML :

```php
public function metadata(): PageMetadata
{
    return (new PageMetadata())
        ->title('Profil de ' . $this->param('id'))
        ->description('Profil public')
        ->canonical('https://exemple.com/users/' . $this->param('id'))
        ->openGraph(image: 'https://exemple.com/assets/profil.png')
        ->twitter(image: 'https://exemple.com/assets/profil.png');
}
```

`->noIndex()` protège une page de l’indexation. AML View produit et échappe
automatiquement le titre, la description, l’URL canonique, les directives pour
les robots ainsi que les métadonnées Open Graph et Twitter.

## Appliquer les styles

Les classes CSS sont appliquées avec le modificateur déclaratif :

```php
Section(
    Heading('Bienvenue')->class('home-title'),
)->class('home-hero', 'featured');
```

Les sélecteurs globaux restent dans `stylesheets/base.css`. Les styles des
pages, composants, layouts et états utilisent des sélecteurs fondés sur des
classes.

## Exemple

```php
final class CounterPage extends Page
{
    #[State]
    public int $count = 0;

    #[Computed]
    protected function summary(): string
    {
        return "Valeur actuelle : {$this->count}";
    }

    public function body(): View
    {
        return VStack(
            Heading('AML View')->size(42)->bold(),
            Text($this->summary),
            Button('Ajouter')->onClick(ClientAction::increment('count')),
        )->gap(16)->padding(40);
    }
}
```

`#[Computed]` sans dépendances reste calculé et mis en cache pendant le rendu
PHP. Pour un recalcul automatique dans le navigateur, les dépendances et
l’opération sont déclarées explicitement :

```php
#[Computed(dependencies: ['prenom', 'nom'], operation: 'concat', separator: ' ')]
protected function nomComplet(): string
{
    return $this->prenom . ' ' . $this->nom;
}

When(
    StateRef::to('pret', $this->pret),
    Text('Prêt'),
    Text('En attente'),
);
```

## Effets déclaratifs

`#[Effect]` exécute des actions dans le navigateur lorsque ses dépendances
changent. Aucun clic ni nouvel appel à PHP n’est nécessaire.

```php
#[Effect('recherche', runOnMount: false, debounce: 250)]
private function rechercheModifiee(): void
{
    if ($this->recherche === '') {
        $this->message = 'Saisissez une recherche';
    } else {
        $this->message = 'Recherche : ' . $this->recherche;
    }
}
```

La méthode PHP est analysée sans être exécutée sur le serveur. Plusieurs
dépendances utilisent `#[Effect(['prenom', 'nom'])]`. Pour un petit effet local
à `body()`, la seconde forme est disponible :

```php
return VStack(
    OnChange($this->state('age'), function (): void {
        $this->categorie = $this->age < 18 ? 'mineur' : 'majeur';
    }),
    NumberInput()->name('age')->value($this->state('age')),
    Text('{categorie}'),
);
```

`OnChange()` convient aux petits effets locaux et ne s'exécute pas au montage
par défaut. Les méthodes nommées `#[Effect]` restent recommandées pour les
comportements importants ou réutilisables.

Comme avec un Hook React, `Effect()` peut aussi être enregistré avant le
`return` de `body()` :

```php
public function body(): View
{
    Effect(function (): void {
        $this->message = 'Recherche : ' . $this->recherche;
    }, [$this->state('recherche')], runOnMount: false);

    return Text('{message}');
}
```

Ces appels doivent rester inconditionnels. La classe d'attribut `Effect` et la
fonction `Effect()` peuvent être importées ensemble en PHP.

### État automatique d'une action

`ActionState()` enveloppe une closure frontend ou une instruction client. Il
fournit automatiquement les références réactives `pending`, `success` et
`error`, sans ajouter trois propriétés `#[State]` :

```php
$enregistrement = ActionState(Api::post('/api/profil', [
    'nom' => $this->state('nom'),
]));

return Form(
    TextField()->value($this->state('nom')),
    Button($enregistrement->pending->choose('Enregistrement…', 'Enregistrer'))
        ->onClick($enregistrement)
        ->disabledWhen($enregistrement->pending),
    Text($enregistrement->error),
);
```

À chaque exécution, l'ancienne erreur est effacée, l'action passe en attente,
puis son succès ou son erreur est enregistré.

### État complet d'un formulaire

`FormState()` suit automatiquement le cycle de vie d'un formulaire natif :

```php
$formulaire = FormState();

return Form(
    EmailInput()
        ->value($this->state('email'))
        ->required(),

    Button($formulaire->submitting->choose('Envoi…', 'Envoyer'))
        ->submit()
        ->disabledWhen($formulaire->invalid),

    Button('Réinitialiser')->onClick($formulaire->reset()),
    Text($formulaire->error),
)
    ->state($formulaire)
    ->onSubmit($formulaire->action(
        Api::post('/api/contact', ['email' => $this->state('email')]),
    ));
```

Le handle expose `dirty`, `touched`, `valid`, `invalid`, `submitting`,
`submitted` et `error`. La validation native et distante reste la source de
vérité. La réinitialisation restaure aussi les valeurs réactives liées.

### Reducer, contexte et références

`Reducer()` convient à une valeur possédant plusieurs transitions nommées :

```php
$compteur = Reducer(0, [
    'incrementer' => fn (StateRef $state) => $state->plus(1),
    'decrementer' => fn (StateRef $state) => $state->minus(1),
]);

return VStack(
    Text($compteur->state),
    Button('+')->onClick($compteur->dispatch('incrementer')),
    Button('-')->onClick($compteur->dispatch('decrementer')),
);
```

`Context()` fournit une valeur et `UseContext()` lit la valeur du fournisseur
le plus proche. `Ref()` donne un accès impératif limité à `focus`, `blur`,
`select`, `play` et `pause`. Aucun nom de méthode arbitraire ni JavaScript n'est
exécuté.

La forme avancée reste disponible :

```php
#[Effect(dependencies: ['recherche'], runOnMount: false, debounce: 250)]
protected function synchroniser(): \AML\Engine\EffectPlan
{
    return \AML\Engine\Effects::run(
        \AML\Engine\ClientAction::set('requete', \AML\Engine\StateRef::to('recherche'))
    );
}
```

Les plans disponibles sont `run`, `timeout`, `interval`, `onWindow` et
`onDocument`. AML Engine supprime automatiquement les minuteries et les
écouteurs, puis annule les requêtes API actives lors d’une nouvelle exécution
ou du démontage du composant. Les événements de diagnostic sont
`aml:effect-run`, `aml:effect-cleanup`, `aml:effect-error` et
`aml:effect-cycle`.

La concurrence est contrôlée avec `latest` par défaut, `exhaust` pour ignorer
les déclenchements pendant une exécution, `queue` pour conserver le prochain,
ou `parallel` lorsque les résultats peuvent arriver dans n’importe quel ordre.
Les composants ajoutés dans une collection possèdent leurs propres effets et
leur suppression libère automatiquement toutes leurs ressources.

`throttle` limite la fréquence d’exécution. `EventRef::to('detail.id')` permet
de lire une copie sécurisée des données d’un événement. Un plan peut déclarer
une action locale de nettoyage avec `withCleanup()`. Pour le développement,
`AMLEngine.effects()`, `pauseEffect()`, `resumeEffect()` et `runEffect()`
permettent d’inspecter et de contrôler les effets.

## Composants et collections riches

AML View fournit maintenant `Modal()`, `Tabs()`, `Accordion()` et
`AsyncBoundary()` pour construire les interactions courantes sans écrire de
JavaScript dans l’application. Le moteur gère le focus de la modale, Échap, les
flèches des onglets, les attributs ARIA et les transitions.

Les collections disposent de `DataTable()` pour le tri local accessible,
`VirtualList()` pour limiter le nombre d’éléments présents dans le DOM,
`SortableEach()` pour le glisser-déposer local et `DynamicForm()` pour les
formulaires générés depuis un état réactif. Les éléments restent identifiés par
leur clé pendant les mises à jour.

Une transition d’entrée peut être déclarée avec
`->transition('fade'|'slide'|'scale', $millisecondes)`.

La première fenêtre d’une liste virtualisée est rendue par PHP, puis le moteur
réagit au défilement et au redimensionnement. Une collection triable fonctionne
à la souris ou avec `Alt+Flèche haut` et `Alt+Flèche bas`. Les transitions
respectent automatiquement la préférence de réduction des animations.

## Contexte et navigation avancée

`ContextProvider()` transmet une configuration statique ou réactive aux vues
imbriquées. `ContextText()` affiche la valeur du provider le plus proche. Un
contexte peut être conservé localement, tandis que les contextes `theme` et
`locale` mettent automatiquement à jour le thème et la langue du document.

Les pages lisent la requête avec `query()` et `queries()`. `Navigate()` crée une
navigation frontend déclarative et `Redirect()` un remplacement déclaratif.
`NavigationBoundary()` conserve les vues de chargement, d’erreur et de page
introuvable dans le layout courant. Après navigation, le moteur actualise
l’historique, les liens actifs, les métadonnées SEO, le focus, le défilement et
la transition sans rechargement complet. Une navigation récente annule toute
requête plus ancienne. Les états de route sont chargés uniquement lorsqu’ils
sont nécessaires, puis montés comme des vues AML interactives.

Les opérations disponibles sont `concat`, `sum`, `count`, `all` et `any`.
AML View ne convertit jamais une fonction PHP arbitraire en JavaScript. Les
séquences entièrement locales sont regroupées en un rendu. Une transaction
explicite revient à son état initial lorsqu’une mutation échoue.

## Interface accessible et formulaires avancés

AML View fournit `Toast()`, `Dropdown()`, `MenuItem()`, `Tooltip()` et
`Popover()`. Le moteur gère le focus, Échap, la fermeture au clic extérieur,
les régions live, les attributs ARIA et la navigation clavier des menus.

Les formulaires disposent de `FileInput()` avec `UploadForm()`, de
`ConditionalField()` et de `MultiStepForm()`. `->preserve('contact.draft')`
conserve les valeurs ordinaires après une navigation ou une erreur serveur. Le
contenu des fichiers n’est jamais enregistré dans le stockage du navigateur.
Après confirmation du serveur, utilisez
`AMLEngine.clearFormDraft('contact.draft')`. La taille, le type et le contenu
des fichiers doivent toujours être validés de nouveau côté serveur.

## Formulaire validé

```php
#[State]
public string $email = '';

return Form(
    Input('email', 'email')
        ->bindClient('email')
        ->required('Votre adresse courriel est obligatoire.')
        ->email('Adresse courriel invalide.'),
    Button('Envoyer')->onClick(
        Api::post('/api/contact', ['email' => StateRef::to('email')])
    ),
);
```

AML View empêche automatiquement une soumission invalide et affiche les
messages accessibles sous les champs concernés.

## Arbres déclaratifs sans `new`

Utilisez `Element('section', ...)` pour un élément générique. Les composants
personnalisés peuvent fournir une fonction du même nom que leur classe, puis
être appelés directement avec `Navigation()` ou `TaskCard()`.

La commande `aml make:view-component Navigation` génère automatiquement la
classe et sa factory. `FileApplication` charge les composants avant les pages
et layouts. La forme générique `Component(Navigation::class)` et l’ancienne
forme `new Navigation()` restent compatibles.

## Tester une vue

```php
use AML\View\Testing\ViewTest;

ViewTest::render(new CounterPage())
    ->assertSee('Compteur')
    ->assertState('count', 0)
    ->click('Ajouter')
    ->assertState('count', 1);
```

Cette API recherche du texte et des composants, inspecte l’état, remplit les
champs, simule les actions locales, vérifie les redirections et contrôle les
exceptions. Elle fonctionne sans navigateur, serveur, JavaScript ou extension
DOM. Les appels API réels restent des tests d’intégration.

## Application de démonstration

L’application complète **AML Tasks** montre le tableau de bord, les
collections réactives, les formulaires, une route dynamique, les préférences,
les thèmes persistants et les états de navigation :

```bash
php -S 127.0.0.1:8080 -t examples/aml-tasks/public examples/aml-tasks/public/router.php
```

Ouvrez ensuite `http://127.0.0.1:8080`.

Consultez également la [référence API](../API.md) et le
[guide de sécurité](../../SECURITY.md).
