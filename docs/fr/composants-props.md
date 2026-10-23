# Propriétés de composants

Pour les composants de `src/views/components/`, `FileApplication` génère
automatiquement une fonction du même nom dans le même espace de noms.
`aml make:view-component Carte` crée uniquement la classe : le constructeur
définit les paramètres, leurs types et leurs valeurs par défaut.

```php
namespace App\Views\Components;

use AML\View\{Component, View};
use function AML\View\{Button, Text, VStack};

final class Carte extends Component
{
    public function __construct(private string $a = '') {}

    public function body(): View
    {
        return VStack(
            Text($this->a),
            Button('Modifier')->onClick(function (): void {
                $this->a = 'Bonjour André';
            }),
        );
    }
}
```

Dans le `body()` d'une page dont `$a` est un `#[State]` :

```php
// En haut du fichier de la page :
use function App\Views\Components\Carte;

// Dans body() :
return VStack(
    Carte($this->a),
    Text('Page : ' . $this->a),
    Carte('Valeur locale'),
    Carte(),
    Carte(a: 'Argument nommé'),
);
```

- `Carte($this->a)` conserve la liaison au State de la page. Le clic modifie la
  page et tous les affichages qui dépendent de ce même State.
- `Carte('Valeur locale')` conserve une valeur propre à cette instance.
- `Carte()` utilise la valeur par défaut, elle aussi locale à l'instance.

La valeur locale automatique concerne les propriétés promues mutables du
constructeur, comme `private string $a` dans l'exemple. Les propriétés déclarées
avec `#[State]` gardent leur identité indépendante et ne deviennent pas des
alias du parent. Les props contenant des objets PHP ne deviennent pas des
objets frontend par ce mécanisme.

Un enfant peut transmettre sa prop à un autre composant par
`Apercu($this->a)`. Si celui-ci reçoit directement cette valeur dans une
propriété mutable, une affectation depuis son événement rejoint également
le State d'origine. Les lectures, champs de formulaire, affectations combinées
et modifications de tableaux suivent cette liaison.

Les types de la propriété et du State d'origine restent vérifiés. Une erreur
non interceptée annule les changements de l'événement. Une propriété
`readonly` refuse l'affectation directe. Les entrées calculées, comme
`Carte(strtoupper($this->a))`, restent en lecture seule : un composant qui
tente de les modifier signale une erreur. Les propriétés avec hooks ne sont
pas des cibles de cette écriture automatique.

Ces affectations s'exécutent dans les événements et effets frontend compilés.
Une affectation exécutée directement par PHP pendant `body()` reste une
opération du rendu initial ; ce n'est pas une référence PHP vers le parent.

Dans un autre espace de noms, importer la fonction avec
`use function App\Views\Components\Carte;`. Importer uniquement la classe ne
rend pas la fonction disponible.

La génération reprend également les constructeurs hérités, les types nullables,
unions et intersections, les paramètres variadiques et les références PHP.
Le type de retour est la classe concrète du composant. Les valeurs objet
créées par défaut avec `new` sont construites à l'appel, pas à la découverte
des fichiers. Les références PHP restent soumises aux limites existantes des
paramètres réactifs ; elles ne remplacent pas la liaison au State.

Les fonctions déjà déclarées à la main sont conservées, y compris dans un
autre fichier du dossier des composants. Les classes abstraites, les
constructeurs non publics, les pages, les layouts et les classes utilitaires
ne reçoivent pas de fonction automatique.

Les fonctions sont définies en mémoire avant le chargement des pages et layouts ;
aucun fichier du projet ni cache sur disque n'est modifié. Le dossier des
composants doit contenir les déclarations, sans appeler ces fonctions au niveau
global pendant leur chargement. Un changement de constructeur est pris en
compte au prochain chargement de l'application (redémarrer un worker PHP
persistant, comme pour un changement de classe). Hors `FileApplication`,
`new Carte(...)`, `Component(Carte::class, ...)` et les fonctions manuelles
restent disponibles. La génération en mémoire ne fournit pas de fichier
de déclarations pour l'autocomplétion des IDE.

## Pages et composants fonctionnels

Les classes peuvent cohabiter avec des fonctions. Dans les fichiers découverts
par `FileApplication`, une fonction dont le nom commence par une majuscule et
dont le type de retour est `View` constitue un composant fonctionnel.

Par exemple, dans `src/views/pages/home/page.php` :

```php
<?php
declare(strict_types=1);

namespace App\Views\Pages\Home;

use AML\View\View;
use function AML\View\{body, Button, State, Text};

function Home(): View
{
    $name = State('André');

    return body(
        Text($name),
        Button('Modifier')->sets($name, 'Marie'),
    );
}
```

`body()` est facultatif : on peut retourner directement `VStack(...)`,
`MainContent(...)` ou toute autre vue. Il regroupe les enfants sans ajouter de
balise HTML. Il ne produit donc pas une seconde balise `<body>`.

Dans `src/views/components/Carte.php` :

```php
<?php
declare(strict_types=1);

namespace App\Views\Components;

use AML\Engine\StateRef;
use AML\View\View;
use function AML\View\{Button, Heading, State, Text, VStack};

function Carte(StateRef $name, string $titre = 'Carte'): View
{
    $clics = State(0);

    return VStack(
        Heading($titre),
        Text($name),
        Text($clics),
        Button('Modifier')->sets($name, 'Marie'),
        Button('Compter')->increments($clics),
    );
}
```

Une page peut ensuite appeler `Carte($name)` et `Carte($name, titre: 'Autre')`.
Les deux cartes partagent la référence `$name`, mais possèdent chacune leur
propre `$clics`. Les types, valeurs par défaut, arguments nommés et paramètres
variadiques restent vérifiés par PHP. Les props scalaires issues d'un State
parent conservent leurs liaisons d'affichage et de formulaire, y compris lors
du passage entre fonctions et classes. Une affectation PHP ordinaire à un
paramètre ne constitue pas une modification d'état dans le navigateur.

Les appels créent des vues différées. Leur contenu s'exécute au rendu, dans un
contexte propre à chaque instance. Les valeurs des `State()` restent disponibles
entre les événements, puis sont réinitialisées lorsque la navigation recrée la
page. `Effect()` suit ce même cycle de vie et ses identifiants sont isolés par
instance, même sans dépendance. Les erreurs de rendu libèrent les contextes ;
une récursion excessive entre vues produit une erreur explicite.

Le nom de la fonction d'une page suit celui du dossier : `Home()` pour `home`,
`Settings()` pour `settings`. La variante `HomePage()` est également acceptée.
Une classe `HomePage extends Page` garde la priorité si elle existe. Les layouts
en classe restent utilisables. Pour une route dynamique, les paramètres portant
le nom d'un segment reçoivent sa chaîne ; `array $params`, `array $query` et
`?Throwable $error` peuvent recevoir le contexte de route. Les pages fonctionnelles
utilisent les métadonnées par défaut ; conserver une classe pour personnaliser
`metadata()`.

Le chargement par `FileApplication` est nécessaire : un simple `require` PHP ne
prépare pas les frontières des composants fonctionnels. Le chargeur travaille
en mémoire et conserve les fichiers, lignes, imports et chemins CSS d'origine.
Aucun fichier source ni cache sur disque n'est généré. Les fonctions auxiliaires
en minuscules, méthodes, closures et fonctions qui ne déclarent pas exactement
un retour `View` conservent leur comportement PHP habituel. Les workers persistants
doivent être redémarrés après une modification des déclarations.

## StateRef dans les closures frontend

Les closures d’événements et d’effets acceptent directement les handles renvoyés
par `State()` ou `$this->state(...)`, y compris lorsqu’ils viennent d’un parent :

```php
Button('Renommer')->onClick(function () use ($name): void {
    $name = 'André';
});

Effect(function () use ($items, $message): void {
    $message = 'Éléments : ' . count($items);
}, [$items]);
```

`use ($name)` conserve le lien vers le State propriétaire. Les affectations,
incréments, écritures de tableau et closures imbriquées suivent ce lien et
respectent le type du State. Un paramètre de fonction ordinaire passé par valeur
ou une variable locale copiée conserve les règles de valeur de PHP. Les captures
PHP ordinaires restent des captures par valeur ; `use (&$variable)` créé côté
serveur reste refusé.

Pour un calcul d’affichage, par exemple `Text(fn () => strtoupper($name))` ou
`IList(fn () => array_filter($items, ...))`, le StateRef se lit de la même façon,
mais les mutations sont interdites. Hors de ces closures compilées, un StateRef
reste un objet PHP : `array_filter($items, ...)` directement dans `body()` ne
convertit pas automatiquement ce handle en tableau.
