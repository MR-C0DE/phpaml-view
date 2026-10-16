# Introspection des objets frontend

View et Engine prennent en charge les fonctions d'introspection suivantes dans les
événements et les fonctions de service synchrones. Elles lisent les définitions
compilées et les objets locaux du navigateur. Elles fonctionnent aussi avec les
paramètres nommés, la propagation d'arguments, les callbacks et `eval()`.

| Fonction | Résultat |
| --- | --- |
| `is_object($valeur)` | Indique si la valeur est un objet PHP frontend. |
| `get_class($objet)` | Nom de sa classe. |
| `get_parent_class($objetOuClasse)` | Classe parente, ou `false`. |
| `get_called_class()` | Classe de l'appel courant, y compris la liaison statique tardive. |
| `class_exists($classe, $autoload = true)` | Présence d'une classe dans le catalogue compilé. |
| `enum_exists($enum, $autoload = true)` | Présence d'une enum dans le catalogue compilé. |
| `interface_exists($interface, $autoload = true)` | Présence d'une interface dans ce catalogue. |
| `method_exists($objetOuClasse, $methode)` | Présence d'une méthode déclarée, sans appeler `__call()`. |
| `property_exists($objetOuClasse, $propriete)` | Présence d'une propriété déclarée, même non initialisée ou statique. |
| `is_a($objetOuClasse, $classe, $allow_string = false)` | Correspondance avec une classe ou une interface. |
| `is_subclass_of($objetOuClasse, $classe, $allow_string = true)` | Héritage ou implémentation, en excluant la classe elle-même. |
| `get_object_vars($objet)` | Propriétés non statiques, initialisées et accessibles dans la portée courante. |
| `get_class_methods($objetOuClasse)` | Noms des méthodes accessibles, avec leur casse et leur ordre PHP. |

Les noms des classes et méthodes sont insensibles à la casse ; les noms des
propriétés sont sensibles à la casse. Les propriétés virtuelles de `__get()` ne
sont pas ajoutées aux résultats. Une propriété non initialisée ou retirée par
`unset()` reste déclarée pour `property_exists()`, mais n'apparaît pas dans
`get_object_vars()`.

La visibilité est celle de la fonction appelante. Depuis une méthode du parent,
sa propriété privée conserve sa propre valeur même si l'enfant porte une
propriété publique de même nom. `method_exists()` retourne `false` pour une
méthode privée héritée lorsqu'on lui passe un nom de classe ; avec une instance,
il peut retourner `true`. Cela ne donne pas le droit d'appeler cette méthode.

## Afficher le résultat

```php
$profil = new Profil('André');
$valeurs = get_object_vars($profil);
$this->message = $profil->etiquette() . ' · ' . count($valeurs) . ' propriétés publiques';
$this->methodes = implode(', ', get_class_methods($profil));
```

L'[exemple complet](../../examples/introspection/page.php) importe un
[service](../../examples/introspection/service.php), inspecte un profil au clic,
puis actualise deux composants `Text` à travers leurs `State`.
L'objet reste local. Un tableau contenant encore un objet ne peut pas être
placé dans un `State` ; il faut en extraire les valeurs affichables.

## Personnaliser var_dump() avec __debugInfo()

```php
public function __debugInfo(): array
{
    return ['profil' => $this->etiquette(), 'points' => $this->points];
}
```

`var_dump($profil)` utilise ce tableau dans la **console du navigateur**.
La page ne reçoit aucune sortie de diagnostic. Le hook s'applique également aux
objets imbriqués, aux méthodes héritées et aux classes anonymes, y compris dans
`eval()`. `get_object_vars()` continue de lire les véritables propriétés.

La méthode doit être publique, non statique et sans paramètres. Un retour non
typé `null` produit une vue vide ; un retour typé peut déclarer `array` ou
`?array`. Les références circulaires sont signalées par `*RECURSION*` et les
limites de profondeur et d'appels restent actives. Le format frontend omet les
identifiants numériques `#123` propres au processus PHP ; les noms des classes
anonymes ont également une identité frontend.

**Différence explicite avec PHP natif :** PHP peut terminer le processus si le
hook échoue pendant `var_dump()`. Engine et ViewTest lèvent une `TypeError` pour
un résultat autre qu'un tableau ou `null`, et propagent l'exception d'un hook
qui échoue. Ces erreurs sont interceptables avec `try/catch`. Une erreur non
interceptée restaure le State de l'événement ; les sorties déjà émises et les
effets sur les propriétés statiques conservent leurs règles habituelles.

## Frontières du navigateur

- Aucun autoload n'est exécuté, même lorsque `$autoload` vaut `true`. Un nom
  littéral de service déjà chargé peut être ajouté au catalogue lors de la
  compilation. Un nom calculé à l'exécution doit désigner une définition déjà
  compilée. `class_exists()` ne liste pas les classes installées sur le serveur.
- L'inspection des membres utilise les définitions compilées des services et
  les adaptateurs internes disponibles ; elle ne reflète pas l'ensemble des
  classes PHP du serveur. `get_class()` et `is_object()` reconnaissent aussi
  les closures, exceptions et valeurs `RoundingMode` déjà prises en charge.
- `get_class()` et `get_parent_class()` sans argument utilisent la portée du
  service ; leur dépréciation depuis PHP 8.3 est signalée. `get_called_class()`
  exige une portée de classe. Le composant View n'est pas un objet de service
  disponible dans le navigateur.
- Utiliser ces fonctions dans un événement et afficher leur résultat via State.
  L’inspection des membres n’est pas autorisée dans une expression de texte
  réactif ; le prédicat pur `is_object()` y reste disponible.
- `enum_exists()` inspecte les [enums compilées](enums.md), sans autoload.
- `get_class_vars()`, `get_mangled_object_vars()`, `trait_exists()`,
  les listes globales de classes, Reflection, les destructeurs et les hooks de
  sérialisation restent hors de ce bloc. `print_r()` et `var_export()` gardent
  leur contrat existant pour les valeurs scalaires, tableaux et `RoundingMode`.

Références PHP : [method_exists](https://www.php.net/manual/en/function.method-exists.php),
[get_object_vars](https://www.php.net/manual/en/function.get-object-vars.php),
[get_class_methods](https://www.php.net/manual/en/function.get-class-methods.php) et
[les méthodes magiques](https://www.php.net/manual/en/language.oop5.magic.php).
