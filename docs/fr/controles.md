# Picker, Toggle et Calendar

Ajoutés pour la bêta CLI 1.7.0-beta.46 (View 0.1.0-beta.14 et Engine
0.1.0-beta.10). Les API `Select`, `Checkbox` et `DateInput` restent disponibles.

| Composant | Valeur | Rendu |
| --- | --- | --- |
| `Picker($name, $options, $selected = null)` | Chaîne, entier ou `null` | Sélecteur natif, classe `aml-picker` |
| `Toggle($name = '', $checked = false, $value = '1')` | Booléen | Case native avec `role="switch"`, classe `aml-toggle` |
| `Calendar($name = '', $value = null, $locale = 'en', $firstDayOfWeek = 1)` | Date `YYYY-MM-DD`, `''` ou `null` | Champ date natif et grille mensuelle visible |

## Exemple complet

Les propriétés `#[State]` et les références explicites `$this->state('…')`
fonctionnent dans les trois composants. Les valeurs sont synchronisées dans
les deux sens, y compris lorsqu’un composant enfant reçoit le State du parent.

```php
<?php
declare(strict_types=1);

namespace App\Views\Pages\Preferences;

use AML\View\{Page, State, View};
use function AML\View\{Calendar, Label, Picker, Text, Toggle, VStack, import};

import('pages/controls.css');

final class PreferencesPage extends Page
{
    #[State] public string $country = 'CA';
    #[State] public bool $notifications = true;
    #[State] public string $appointment = '2028-02-28';

    public function body(): View
    {
        return VStack(
            Label('Pays', 'country'),
            Picker('country', ['CA' => 'Canada', 'FR' => 'France'], $this->country)
                ->id('country')->required(),
            Label('Recevoir un rappel', 'notifications'),
            Toggle('notifications', $this->notifications)->id('notifications'),
            Label('Rendez-vous', 'appointment'),
            Calendar('appointment', $this->appointment, locale: 'fr-CA')
                ->id('appointment')->min('2028-01-01')->max('2028-12-31')->required(),
            Text('Date : ' . $this->appointment),
        )->class('controls-page');
    }
}
```

Le fichier importé doit exister. La page `/controls` de l’exemple
[AML Tasks](../../examples/aml-tasks/src/views/pages/controls/page.php) inclut
un formulaire complet, la confirmation et un
[CSS réutilisable](../../examples/aml-tasks/src/views/stylesheets/pages/controls.css).
Ce CSS utilise les variables de thème de son `base.css` : adapter les couleurs
à l’application qui le reprend.

## Méthodes et validation

Les méthodes natives restent utilisables : `id`, `class`, `required`, `disabled`,
`disabledWhen`, `showWhen`, `onInput` et `onChange`. `Calendar` accepte aussi
`min`, `max`, `step` et `readOnly`. Les jours non autorisés et les calendriers
désactivés ne peuvent pas modifier le State. Les actions passent par les mêmes
événements et règles de formulaire que les autres champs.

`Picker` conserve le contrat de `Select` pour les options (clé = valeur,
libellé = texte). `Toggle` utilise `checked` pour le booléen ; son argument
`$value` est la valeur envoyée dans un formulaire HTML lorsqu’il est coché.
Un booléen State n’est pas remplacé par cette chaîne.

`Calendar` sélectionne une date civile unique, sans heure ni conversion de
fuseau. Une valeur initiale invalide ou une borne mal formée lève une
`InvalidArgumentException`. `null` et `''` donnent un champ vide ; effacer le
champ renvoie `''`, comme `DateInput`. `required()` interdit alors l’envoi du
formulaire. Les bornes inclusives et le pas reprennent les règles natives du
champ date ; `step('any')` permet tous les jours dans les bornes.

Les langues prises en charge sont `en` et `fr`, avec une région facultative
(`en-US`, `fr-CA`…). Le premier jour va de `0` (dimanche) à `6` (samedi) ;
la valeur par défaut `1` correspond au lundi.

## Clavier et présentation

Le Toggle s’active avec Espace. Son libellé doit rester stable et être associé
par `Label(..., $id)`, comme dans le [modèle d’interrupteur W3C](https://www.w3.org/WAI/ARIA/apg/patterns/switch/).

Dans Calendar, Tab rejoint un seul jour de la grille. Les flèches déplacent
le focus, Début/Fin rejoignent le début/la fin de semaine, Page précédente/
suivante changent de mois et Maj avec ces touches change d’année. Entrée ou
Espace sélectionne le jour. Le focus ne change pas la sélection. Le mois est
annoncé via une région `aria-live`. La navigation suit les principes du
[calendrier W3C](https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/examples/datepicker-dialog/).

View n’impose pas de thème CSS. Le Toggle garde un rendu de case native sans
CSS ; la feuille d’exemple lui donne l’apparence d’un interrupteur. Calendar
reste un champ date natif avant le chargement d’Engine. Sa grille est ensuite
créée sans injection de code ou de HTML depuis ses libellés.

`Calendar()->class(...)`, `id(...)` et les attributs de formulaire concernent
le champ date. Pour le conteneur et la grille, utiliser `.aml-calendar`,
`.aml-calendar-header`, `.aml-calendar-month`, `.aml-calendar-grid`,
`[aria-selected=true]` et `:focus-visible`. Une feuille importée reste globale
à l’application : préfixer les sélecteurs pour limiter leur portée visuelle.

Le calendrier ne gère pas les plages de dates ou les événements d’agenda.
Les vérifications automatisées couvrent les interactions au clavier et les
navigateurs ; elles ne remplacent pas une évaluation avec des lecteurs d’écran.
