# Icônes locales avec Lucide

`Icon()` appartient à View. Le catalogue de dessins est optionnel et s’installe
dans le dossier de l’application :

```bash
aml install lucide
```

Le CLI utilise les SVG du paquet officiel [lucide-static](https://lucide.dev/guide/static),
version `1.61.0`, après vérification de son empreinte SHA-512. Il conserve les
licences ISC et MIT fournies par Lucide. Node.js, npm et un CDN ne sont pas
nécessaires. Le catalogue contient 2 171 noms, y compris les alias amont.

```php
use function AML\View\Icon;

Icon('heart')->size(24)->color('#7047eb')->strokeWidth(2);
Icon('arrow-right')->size('1.5em');
Icon('settings')->label('Paramètres');
```

Le premier SVG est inclus dans le HTML. Une icône est décorative par défaut
(`aria-hidden="true"`, non focalisable). `label('Paramètres')` lui donne un nom
accessible et le rôle `img`. `label(null)` la rend à nouveau décorative.
Pour un bouton contenant uniquement une icône, donner le nom accessible au bouton :

```php
Button(Icon('settings'))
    ->attribute('aria-label', 'Ouvrir les paramètres')
    ->onClick(function (): void { $this->ouvert = true; });
```

## State et Style

Les propriétés `#[State]`, `StateRef`, les expressions et les closures de lecture
fonctionnent comme dans les autres composants :

```php
Icon($this->icone)
    ->size($this->taille)
    ->color($this->couleur)
    ->strokeWidth($this->epaisseur)
    ->label($this->description);

Icon('{icone}');
Icon(fn () => $this->favori ? 'heart' : 'user');
Icon($this->direction . '-right');

$style = Style::create()
    ->color($this->couleur)
    ->width($this->taille)
    ->height($this->taille)
    ->property('stroke-width', 2);

Icon('heart')->style($style);
Icon('heart')->style(Style::create('home.style')->icon);
```

`size()` applique les propriétés CSS `width` et `height`. Les nombres sont des
pixels ; les chaînes permettent les unités CSS. `strokeWidth()` utilise la
propriété SVG/CSS `stroke-width`. La couleur passe par `currentColor` : elle peut
aussi être héritée du parent. Les autres méthodes communes d’Element et les
conditions, pseudo-classes et media queries de Style restent disponibles.

`size()` et `strokeWidth()` refusent les nombres et longueurs CSS littérales
négatifs ; zéro reste autorisé. Une valeur initiale invalide lève une exception.
Une mise à jour réactive invalide conserve la dernière dimension valide et
signale `aml:render-error`. Les expressions CSS (`calc()`, `var()`) restent
évaluées par le navigateur, comme dans Style.

Composant fonctionnel :

```php
use AML\View\View;
use function AML\View\{Button, HStack, Icon, State};

function Favori(): View
{
    $icone = State('heart');
    return HStack(
        Icon($icone)->size(28),
        Button('Changer')->onClick(function () use ($icone): void {
            $icone = 'settings';
        }),
    );
}
```

Pour une liste réactive, `Icon($item->value('icone'))` dans `renderItem()` suit
le champ de la ligne, y compris après ajout ou déplacement.
Si la liste commence vide, donner une valeur initiale valide au modèle de ligne :
`Icon($item->value('icone', 'heart'))`.

## Conditions réactives

Une closure de lecture peut contenir des `if`, `elseif` et `else` imbriqués.
Dans une page à classes dont `mode` est un `#[State]` :

```php
Icon(function (): string {
    if ($this->mode >= 2) {
        if ($this->mode === 2) { return 'settings'; }
        return 'search';
    } elseif ($this->mode === 1) {
        return 'user';
    }
    return 'heart';
});
```

Avec un State local, capturer sa référence avec `function () use ($mode)`.
Ces closures restent en lecture seule ; les événements modifient le State.

`When($this->state('visible'), Icon($this->icone), Icon('heart'))` affiche la
valeur actuelle lorsque sa branche devient active, y compris avec des `When()`
imbriqués. `Icon($this->icone)->showWhen($this->state('visible'))` conserve le
SVG et ses styles pendant qu’il est masqué, puis réaffiche sa valeur actuelle.
Un `if` PHP directement dans `body()` choisit toujours la vue initiale : utiliser
ces liaisons pour réagir ensuite aux changements de State dans le navigateur.

## Chargement, erreurs et déploiement

Le catalogue est enregistré dans `phpaml.json`, sous `modules.lucide`.
Les fichiers publics se trouvent dans `public/assets/phpaml/lucide/1.61.0/`.
Ils doivent être déployés avec l’application et servis comme fichiers statiques.
Une fois installé, `aml install` restaure aussi ce catalogue ; `--offline`
nécessite l’archive déjà présente dans le cache local du CLI.

Une icône statique ne déclenche aucune requête. Lors d’un changement de nom,
Engine récupère seulement la géométrie JSON de l’icône demandée, sur la même
origine, avec un cache partagé limité à 256 entrées. Une connexion au site est
nécessaire pour une icône qui n’a pas encore été chargée. Aucune requête ne part
vers Lucide. Les réponses arrivées après un nouveau choix ou le retrait du
composant sont ignorées.

Après une panne réseau ou une réponse temporaire (408, 429, 5xx), Engine garde
le dernier SVG et effectue au maximum trois nouvelles tentatives, après
0,5, 1,5 puis 4 secondes. Le retour de la connexion (`online`) relance les
icônes en attente, sans devoir modifier leur State. Les requêtes identiques
sont partagées et le démontage du composant annule ses reprises. Les erreurs
permanentes (nom inconnu, réponse 404 ou géométrie invalide) ne déclenchent
pas cette boucle de reprise.

Les noms sont ceux du catalogue, en minuscules avec tirets. Un catalogue absent
ou une icône inconnue au premier rendu provoque une exception explicite. Dans le
navigateur, un nom invalide, un fichier absent ou une réponse malformée conserve
le dernier SVG valide, émet `aml:render-error` et écrit le diagnostic dans la
console du navigateur. Seules les formes SVG inertes
et leurs attributs géométriques autorisés sont acceptés.

Pour une application hébergée sous un préfixe, définir par exemple
`modules.lucide.base_url` à `/mon-app` dans `phpaml.json`.

## Désinstallation

```bash
aml uninstall lucide
```

Le CLI signale les références PHP à `Icon` dans `src/` et `app/` et refuse le
retrait tant qu’elles subsistent. Cette recherche est conservatrice et ne peut
pas prouver l’absence d’appels construits dynamiquement. Pour retirer malgré
ces références :

```bash
aml uninstall lucide --force
```

Seuls les fichiers gérés et inchangés du catalogue sont supprimés. Les fichiers
personnels et modifiés sont conservés, ainsi que les autres modules du projet.
Une réinstallation répare les fichiers manquants, mais refuse d’écraser une
modification locale. Les imports de fichiers SVG personnels ne font pas partie
de cette API.
