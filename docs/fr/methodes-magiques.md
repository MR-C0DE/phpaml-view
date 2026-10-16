# Méthodes magiques dans les événements frontend

View et Engine prennent en charge dix méthodes magiques sur les classes de
service chargées avant le rendu. Elles exécutent du code compilé dans le
navigateur, avec les mêmes limites d'appels, types et traces que les méthodes
ordinaires.

| Méthode | Déclenchement |
| --- | --- |
| `__get($nom)` | Lecture d'une propriété absente ou inaccessible. |
| `__set($nom, $valeur)` | Écriture d'une propriété absente ou inaccessible. |
| `__isset($nom)` | Vérification par `isset`, `empty`, `??` ou `??=`. |
| `__unset($nom)` | Suppression d'une propriété absente ou inaccessible. |
| `__call($nom, $arguments)` | Appel d'une méthode d'instance absente ou inaccessible. |
| `__callStatic($nom, $arguments)` | Appel d'une méthode statique absente ou inaccessible. |
| `__invoke(...)` | Appel d'un objet comme fonction ou utilisation comme `callable`. |
| `__toString()` | Conversion explicite ou implicite en chaîne. |
| `__clone()` | Personnalisation de la copie créée par `clone`. |

L'[exemple complet](../../examples/methodes-magiques/page.php) utilise les neuf
méthodes dans un [service Profil](../../examples/methodes-magiques/service.php).

```php
$profil = Profil::creer('André');       // __callStatic
$profil->points = 10;                  // __set
$points = $profil->points;             // __get
$profil(5);                           // __invoke
$copie = clone $profil;                // __clone
$this->message = $profil->resume() . ' | ' . $copie;
```

`message` est un `#[State]` chaîne affiché par `Text`. Les objets et leurs
méthodes restent dans les événements ; publier leur résultat scalaire dans le
State pour actualiser la page. `echo` reste dans la console du navigateur.
Une propriété statique de service peut conserver un objet entre les événements
du même composant, selon le [cycle de vie des services](objets.md).

## Propriétés virtuelles

Une propriété déclarée et accessible utilise normalement son propre champ.
Les méthodes magiques interviennent pour les accès absents ou inaccessibles,
ou après la suppression explicite d'une propriété. Une propriété typée jamais
initialisée continue de lever une erreur à la lecture ; `__get` ne masque pas
cette erreur. Après un `unset`, la valeur renvoyée par `__get` doit respecter le
type de la propriété déclarée.

`isset($objet->nom)` consulte `__isset` sans appeler `__get`. `empty`, `??` et
`??=` consultent ensuite `__get` lorsque `__isset` répond vrai. Les gardes de
récursion empêchent la réentrée automatique du même accesseur pour la même
propriété. Les appels vers d'autres propriétés restent possibles.

`__get` retourne une valeur, pas une référence. Pour un tableau virtuel,
`$objet->items[] = 3`, `sort($objet->items)` ou un passage par référence produit
un avertissement de modification indirecte et ne réécrit pas le tableau via
`__set`. Utiliser une copie explicite :

```php
$items = $objet->items;
$items[] = 3;
$objet->items = $items;
```

Un objet retourné par `__get` conserve en revanche son identité : modifier ses
champs accessibles est possible.

## Appels, textes et copies

`__call` et `__callStatic` reçoivent les arguments positionnels et nommés, avec
leurs clés et types. La propagation `...` et les callables de première classe
comme `$objet->inconnue(...)` sont disponibles. L'héritage et `static` conservent
la classe appelée. Les propriétés statiques ne déclenchent pas les accesseurs
de propriétés d'instance.

Un objet avec `__invoke` peut être passé à une fonction acceptant `callable`, à
`array_map` ou aux autres fonctions natives acceptant des callbacks. Ses
paramètres suivent les règles ordinaires, y compris les références et les types.

Un objet avec `__toString` satisfait le contrat `Stringable`. Il fonctionne avec
`(string)`, la concaténation, `echo` et les fonctions natives qui convertissent
leurs valeurs en texte. Un paramètre déclaré `string` continue de refuser un
objet lorsque `strict_types=1` ; en mode faible, la conversion est permise.
Le résultat de `__toString` doit respecter son contrat de retour `string`.

`clone` copie les champs, puis appelle `__clone` sur la copie. Les sous-objets
restent partagés sauf si le service les clone explicitement. La visibilité de
`__clone` est vérifiée. Depuis PHP 8.3, la réinitialisation unique des champs
`readonly` pendant le clonage est prise en charge selon la version PHP du
compilateur ; une seconde écriture reste une erreur.

Les exceptions des méthodes magiques sont interceptables avec `try/catch`.
Les appels implicites consomment le budget d'appels habituel, y compris les
récursions de `__call`. Une erreur non interceptée annule la transaction du
State ; les sorties console et les écritures statiques déjà réalisées restent
conservées. Le même support est disponible dans `eval()` pour les classes déjà
compilées.

## Limites explicites

- Les méthodes magiques prises en charge doivent être publiques, sauf `__clone`
  qui peut restreindre la copie par sa visibilité.
- Les retours par référence, notamment `&__get`, suivent le contrat du
  [guide SPL et références](spl-references.md).
- Les noms de méthodes et de propriétés d'instance calculés peuvent déclencher
  les méthodes magiques ; voir les [fonctions avancées](fonctions-avancees.md).
- La création brute de propriétés dynamiques des services reste exclue (hors `stdClass`). Utiliser un tableau
  déclaré dans la classe comme stockage des propriétés virtuelles.
- `__sleep`, `__wakeup`, `__serialize` et `__unserialize` sont pris en charge
  pour les objets compilés : voir les [extensions du langage](extensions-langage.md).
  `__destruct` et `__set_state` restent exclus ; aucune destruction automatique
  observable des services n’est reproduite.

Les comparaisons de comportement utilisent PHP natif. Les références sont les
manuels sur la [surcharge](https://www.php.net/manual/en/language.oop5.overloading.php),
les [méthodes magiques](https://www.php.net/manual/en/language.oop5.magic.php) et le
[clonage](https://www.php.net/manual/en/language.oop5.cloning.php).

## Diagnostic des objets

La dixième méthode, `__debugInfo()`, personnalise `var_dump()` dans la console du navigateur. Voir le [guide d’introspection](introspection.md) pour les tableaux retournés, les références circulaires et les erreurs de hook.
