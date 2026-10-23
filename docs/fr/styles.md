# Styles PHP et modules CSS locaux

Disponible dans le candidat View `0.1.0-beta.14` pour CLI `1.7.0-beta.46`.

Les modificateurs existants restent utilisables :

```php
Text('Bonjour André')
    ->fontSize(24)
    ->fontWeight(700)
    ->textColor('#7047eb')
    ->padding(16);
```

## Un style réutilisable

`Style::make()` et `Style::create()` sans argument créent le même style vide.
Chaque méthode retourne une nouvelle valeur : modifier une variante ne modifie
pas les boutons qui utilisent le style original.

```php
use AML\View\Style;

$primary = Style::make()
    ->background('#7047eb')
    ->color('#ffffff')
    ->padding(12)
    ->borderRadius(10)
    ->hover(Style::create()->background('#5632bd'));

return VStack(
    Button('Enregistrer')->style($primary),
    Button('Continuer')->style($primary),
    Text('Bonjour')->style(Style::create()->fontSize(24)->fontWeight(700)),
);
```

Les variantes acceptent un Style ou un tableau de propriétés.
`media('(max-width: 640px)', $style)` ajoute une adaptation CSS.
Sur les éléments, `hover()`, `focus()` et `focusVisible()`
sont aussi des raccourcis directs. Les règles sont de vraies règles CSS ; une
couleur de base n'est pas figée dans un attribut inline qui bloquerait `hover`.

Les méthodes courantes comprennent `background()`, `backgroundColor()`,
`color()` / `textColor()`, `padding()`, `margin()`, `gap()` / `spacing()`,
`border()`, `borderRadius()`, `width()`, `height()`, `fontSize()`, `fontWeight()`,
`lineHeight()`, `letterSpacing()`, `textAlign()`, `opacity()` et `display()`.
`property('max-width', '40rem')` permet de déclarer une autre propriété CSS.
Les chaînes conservent leur unité. Les dimensions numériques usuelles sont
converties en pixels ; `fontWeight`, `lineHeight`, `opacity`, `flex` et `zIndex`
restent sans unité. Les valeurs non finies et les caractères qui permettraient
de sortir d'une déclaration sont refusés.

## Conditions, états et pseudo-éléments

Les méthodes suivantes sont disponibles sur `Style` :

| Méthode | Règle CSS | Usage |
| --- | --- | --- |
| `hover($style)`, `active($style)` | `:hover`, `:active` | Survol et activation |
| `focus($style)`, `focusVisible($style)` | `:focus`, `:focus-visible` | Focus et indication du focus |
| `focusWithin($style)` | `:focus-within` | Focus sur l'élément ou un descendant |
| `disabled($style)`, `checked($style)` | `:disabled`, `:checked` | État natif d'un contrôle |
| `valid($style)`, `invalid($style)` | `:valid`, `:invalid` | Validité HTML native |
| `firstChild($style)`, `lastChild($style)` | `:first-child`, `:last-child` | Position parmi les enfants |
| `nthChild('2n', $style)` | `:nth-child(2n)` | Entier, `odd`, `even` ou expression An+B |
| `before($style)`, `after($style)` | `::before`, `::after` | Contenu décoratif |
| `placeholder($style)` | `::placeholder` | Indication d'un champ vide |
| `media($query, $style)` | `@media` | Fenêtre, impression, préférences utilisateur |
| `supports($condition, $style)` | `@supports` | Fonctionnalité CSS reconnue par le navigateur |
| `container($query, $style)` | `@container` | Taille d'un conteneur ancêtre |

Ces méthodes décrivent l'apparence. `Style::checked()` ne coche pas une case,
et `Style::invalid()` ne remplace pas la validation du formulaire. Sur un
élément, les méthodes existantes `checked()` et `disabled()` conservent leur
rôle HTML. Appliquer les variantes avec `->style($style)`.

```php
$field = Style::make()
    ->border('1px solid #999')
    ->focusVisible(['outline' => '3px solid #7047eb'])
    ->invalid(['borderColor' => '#dc2626'])
    ->placeholder(['color' => $this->accent]);

$title = Style::make()
    ->fontSize(20)
    ->supports('(text-wrap: balance)', ['textWrap' => 'balance'])
    ->media('(600px < width < 1200px)', ['fontSize' => 24])
    ->before(['content' => '"→ "', 'color' => $this->accent]);
```

`content` reçoit une valeur CSS : les guillemets autour du texte font partie
de la valeur. Les pseudo-éléments servent à la décoration ; le contenu essentiel
reste dans `Text`, `Label`, etc.

Une requête de conteneur exige un **ancêtre** déclaré comme conteneur :

```php
$card = Style::make()
    ->property('container-type', 'inline-size')
    ->property('container-name', 'card');

$title = Style::make()->fontSize(20)
    ->container('card (min-width: 500px)', [
        'fontSize' => 28,
        'color' => $this->accent,
    ]);

return VStack(Text('Ma carte')->style($title))->style($card);
```

Ici la largeur de la carte décide, indépendamment de la largeur de la fenêtre.
Le nom est facultatif : `container('(min-width: 500px)', $style)` utilise le
conteneur éligible le plus proche. View conserve les conditions CSS et le
navigateur les évalue selon ses capacités. Il n'ajoute pas de polyfill.

Les variantes peuvent s'imbriquer, par exemple
`supports('(display: grid)', Style::make()->media('(min-width: 600px)', ['display' => 'grid']))`.
Les valeurs suivent le State même lorsque la condition est temporairement
inactive. Les sélecteurs et les chaînes de condition restent fixes après le
rendu. `nthChild()` ne prend pas en charge la syntaxe `of <sélecteur>` ; elle
peut être écrite explicitement dans un fichier CSS local.

## Animations locales en PHP

```php
$motion = Style::make()
    ->keyframes('appear', [
        'from' => ['opacity' => 0, 'transform' => 'translateY(8px)'],
        'to' => Style::make()->opacity($this->opacity)
            ->property('transform', 'translateY(0)'),
    ])
    ->property('animation', 'appear 300ms ease-out both')
    ->media('(prefers-reduced-motion: reduce)', ['animation' => 'none']);

Text('Bienvenue')->style($motion);
```

`keyframes($name, $frames)` accepte `from`, `to` ou des pourcentages de `0%`
à `100%`, y compris plusieurs étapes dans une clé (`'0%, 50%'`). Chaque étape
contient un Style de déclarations ou un tableau de propriétés. Les sélecteurs,
conditions, modules et animations imbriquées sont refusés dans une étape.

Le nom est isolé avec le Style compilé : deux styles distincts peuvent définir
`appear` sans se remplacer. Les propriétés `animation` et `animation-name`
sont réécrites avec le nom généré. Composer deux définitions du même nom dans
un seul Style garde la dernière définition complète. Les variantes d'un même
Style partagent ses noms ; une redéfinition dans `media()` ou `supports()` suit
la condition et l'ordre des règles CSS.

Écrire un nom ASCII ordinaire, sans mot-clé CSS. Le nom d'animation est statique,
sans référence indirecte `var()` dans `animation` ou `animation-name` lorsque
des keyframes locaux sont définis. Les valeurs des étapes et les propriétés
séparées restent réactives, par exemple
`->property('animation-duration', $this->duration . 'ms')`.
Un changement de valeur n'implique pas un redémarrage de l'animation.

## Styles nommés et composition

```php
$styles = Style::create([
    'title' => ['fontSize' => 28, 'fontWeight' => 700],
    'muted' => Style::create()->opacity(0.6),
]);

Text('Titre')->style([$styles['title'], ['color' => '#7047eb']]);
Text('Note')->style($styles->muted);
```

`Style::create([...])` reçoit des styles **nommés**. `->style([...])` accepte
une carte de propriétés ou une liste de styles. Dans les cartes PHP composées,
la dernière déclaration d'une propriété gagne ; `false` et `null` sont ignorés
dans les listes. Les propriétés camelCase et kebab-case sont acceptées. Les
classes CSS conservent les règles habituelles de spécificité et de cascade.
L'accès à un nom inconnu lève une erreur. Les collections sont immuables.

L'API existante `->style('color', 'red')` reste compatible, y compris ses
paramètres nommés `name:` et `value:`. Elle conserve ses valeurs brutes ; elle
ne rajoute pas d'unité à un ancien appel numérique.

## Un fichier local

À côté de `page.php`, créer `home.style.css` :

```css
.container { padding: 24px; }
.title { font-size: 28px; color: #222; }
.button { background: #7047eb; color: white; }
.button:hover { background: #5632bd; }
@media (max-width: 640px) { .container { padding: 12px; } }
```

```php
public function body(): View
{
    $style = Style::create('home.style');
    return VStack(
        Text('Bienvenue')->style($style['title']),
        Button('Continuer')->style($style['button']),
    )->style($style['container']);
}
```

Le chemin est relatif au **fichier PHP qui appelle `Style::create()`**, même
dans une fonction d'aide. L'extension `.css` peut être omise après `.style` ou
`.module`. Les noms `.style.css` et `.module.css` ont le même comportement.
Un chemin partagé, par exemple `../../components/shared.style.css`, est permis
tant que le fichier réel reste dans `src/views` (ou le projet Composer pour un
usage indépendant). Les URL et chemins absolus sont refusés, ainsi que les
liens symboliques sortant de cette racine. Un fichier manquant est signalé.

Chaque fichier possède ses noms de classes et d'animations. Deux fichiers
différents peuvent avoir une classe `.title`. Charger un module ne rend pas
`.title` globale : il faut appliquer le Style exporté. Réutiliser le même
module partage les mêmes classes. La police et les couleurs peuvent toujours
être héritées du CSS global ; il ne s'agit pas d'une frontière Shadow DOM.

Les sélecteurs descendants, pseudo-classes, pseudo-éléments, règles `@media`,
`@supports`, `@container` et `@keyframes` sont pris en charge. Chaque branche
d'un sélecteur doit contenir une classe locale hors d'une fonction comme
`:not()` ou `:is()`. Utiliser des noms de classe ordinaires ASCII, sans
échappements. Écrire les sélecteurs complets : le nesting CSS, `:global`,
`:local`, `@import`, `@font-face` et les autres déclarations globales sont
refusés dans les modules. Les noms d'animation locaux doivent être explicites,
sans mot-clé CSS ni référence indirecte par `var()`.

Les images utilisent une URL publique (`/assets/image.png`, `https://…`, une
data URL ou un fragment), puisque la feuille est rendue avec la page. Une URL
relative ambiguë est refusée. Les messages précisent le module concerné.

## Rendu et portée

View génère les classes et rassemble leurs feuilles une seule fois par rendu,
y compris pour les branches conditionnelles et les modèles de liste. Les
styles accompagnent le HTML de la page et sont remplacés avec elle pendant la
navigation Engine, dans les deux runtimes. Ils fonctionnent dès le premier
HTML, sans JavaScript supplémentaire. Aucun processus Node n'est nécessaire
pour charger un module en PHP.

`Renderer` inclut les feuilles collectées. Pour un rendu manuel avec
`$view->render($context)`, récupérer ensuite `$context->styles()` et le placer
avant le fragment. Comme les modificateurs inline historiques, ce rendu exige
une politique CSP autorisant les styles inline ; il n'ajoute pas de dérogation
à la politique du site.

Les imports CSS existants, `stylesheets/base.css` et les thèmes gardent leur
fonctionnement global. Les feuilles locales sont incluses uniquement quand
leur Style est appliqué, même si `FileApplication::styles()` est appelé avant
le rendu. Elles sont aussi présentes dans le HTML produit en production.

## Liaison automatique au State

Les valeurs de style suivent le `State` dans les modificateurs directs,
`Style::make()`, `Style::create()`, les tableaux nommés et les compositions.
Les calculs et les fonctions de lecture utilisent le même compilateur que le
texte réactif. Les propriétés transmises à un composant enfant gardent leur liaison.

```php
#[State] private string $accent = '#7047eb';
#[State] private int $size = 24;

public function body(): View
{
    $button = Style::make()
        ->background($this->accent)
        ->padding($this->size / 2)
        ->hover(Style::create()->background($this->accent));

    $styles = Style::create([
        'title' => ['fontSize' => $this->size, 'color' => $this->accent],
    ]);

    return VStack(
        Text('Direct')->fontSize($this->size)->textColor($this->accent),
        Text('Tableau')->style($styles['title']),
        Text('Calcul')->style(Style::create()->width(($this->size * 2) . 'px')),
        Text('Interpolation')->style(Style::make()->property('border', '1px solid {accent}')),
        Button('Modifier')->style($button)->onClick(function (): void {
            $this->accent = '#087f5b';
            $this->size = 32;
        }),
    );
}
```

Les écritures `$this->accent`, `"{$this->size}px"`, `'{size}px'`, les
concaténations, les variables locales calculées et les fonctions fléchées de
lecture sont prises en charge. `StateRef`, `StateExpression` et les expressions
conditionnelles explicites sont aussi acceptés comme valeurs.
Une valeur de State contenant des accolades reste une donnée : elle n'est pas
réinterprétée comme un autre template.

La liaison porte sur les **valeurs**. Les noms de propriétés, les classes
sélectionnées dans un module, les sélecteurs et la structure des compositions
sont déterminés au rendu. Pour choisir une valeur, placer le ternaire dans
la propriété : `->color($this->active ? 'green' : 'gray')`.
Les mêmes règles de lecture seule et d'ambiguïté du code source que pour le
texte s'appliquent ; mettre les appels de branches différentes sur des lignes
séparées. Un Style construit hors du rendu d'un composant reste statique,
sauf s'il contient une référence explicite.

### State dans un fichier CSS local

Le fichier conserve du CSS ordinaire. Relier ses variables CSS au State lors
de l'application de la classe :

```css
/* home.style.css */
.title { color: var(--accent); font-size: var(--size); }
.title:hover { border-color: var(--accent); }
```

```php
$styles = Style::create('home.style');

Text('Bonjour André')->style([
    $styles['title'],
    ['--accent' => $this->accent, '--size' => $this->size . 'px'],
]);
```

Cela fonctionne aussi avec `$styles['title']->property('--accent', $this->accent)`.
Le chargement du fichier ne devine pas une correspondance entre ses noms de
variables CSS et les propriétés PHP. Les variables CSS personnalisées restent
sans unité automatique : ajouter `px`, `%`, etc. lorsque la valeur le demande.

### Mise à jour et erreurs

Le HTML contient les valeurs initiales. Engine met ensuite à jour les variables
CSS de chaque élément dans les runtimes complet et modulaire, sans recréer
le DOM ni ajouter de feuilles à chaque changement. Les états CSS, conditions,
pseudo-éléments, étapes d'animation et éléments révélés par `When` utilisent
les valeurs courantes.
Les instances indépendantes restent isolées et la navigation retire les styles
avec leur page.

Les valeurs sont contrôlées au rendu PHP et lors des mises à jour. Une valeur
réactive invalide déclenche `aml:render-error` et un diagnostic dans la console,
conserve le dernier style valide et ne bloque pas les autres liaisons. Une
valeur corrigée reprend automatiquement la mise à jour.

### Valeurs vides, priorités et tests PHP

Dans un Style structuré, une valeur vide pour une propriété ordinaire désactive
sa déclaration : les autres règles CSS peuvent à nouveau s'appliquer. Une
variable CSS personnalisée vide reste définie. Les méthodes directes typées
gardent leurs validations historiques, notamment `textColor()` qui refuse une
couleur vide.

`!important` appartient à la déclaration CSS, même lorsque la valeur vient du
State. Ajouter ou retirer cette priorité ne recrée ni l'élément ni la feuille.
Elle fonctionne aussi dans les variantes et pseudo-éléments. Dans une
composition PHP, la dernière définition de la même propriété remplace la
précédente ; entre les règles résultantes et les classes externes, les règles
CSS de priorité et de spécificité s'appliquent. Dans les keyframes, les
déclarations importantes sont ignorées conformément au comportement CSS.
Une chaîne citée comme `content: '"!important"'` reste du texte.

Les mots-clés CSS `initial`, `inherit`, `unset`, `revert` et `revert-layer`
s'appliquent à la propriété ciblée, y compris lorsqu'ils viennent du State et
portent `!important`. Dans les keyframes, écrire ces mots-clés statiquement :
les étapes réactives attendent une valeur concrète et signalent cette limite
au lieu de produire un résultat différent entre PHP et le navigateur.

`ViewTest` met à jour les valeurs et priorités des styles après `click()` ou
`fill()`. Une erreur conserve la dernière valeur valide, apparaît dans
`renderErrors()` et n'empêche pas les autres propriétés d'évoluer. Ce simulateur
vérifie les liaisons ; les couleurs calculées, la cascade et la mise en page
sont vérifiées dans un navigateur.
