# Niveau 10 — Erreurs et exceptions

Disponible dans View `0.1.0-beta.13` et Engine `0.1.0-beta.9`.

Le PHP de `body()` prépare la page. Les événements et les fonctions de service
qu’ils appellent sont compilés en instructions exécutées par Engine dans le
navigateur. Une erreur de rendu PHP et une exception pendant un clic ont donc
des lieux et des moments de traitement différents.

## Attraper une erreur dans la page

```php
use AML\View\{Page, State, View, Console};
use function AML\View\{VStack, Text, Button};

final class AgePage extends Page
{
    #[State] public int $age = -1;
    #[State] public string $message = '';
    #[State] public int $tentatives = 0;

    public function body(): View
    {
        return VStack(
            Text($this->message),
            Button('Vérifier')->onClick(function (): void {
                try {
                    if ($this->age < 0) {
                        throw new \InvalidArgumentException('Âge invalide');
                    }
                    $this->message = "Âge accepté : {$this->age}";
                } catch (\InvalidArgumentException $error) {
                    $this->message = $error->getMessage();
                    echo $error->getMessage() . PHP_EOL;
                } finally {
                    $this->tentatives++;
                }
            }),
            Text("Tentatives : {$this->tentatives}"),
        );
    }
}
```

`Text` affiche le State. `echo` écrit uniquement dans la console du navigateur.
L’exemple complet avec saisie, service séparé, assertion et diagnostic se trouve
dans [page.php](../../examples/niveau-10/page.php) et
[service.php](../../examples/niveau-10/service.php).

## Couverture 117 à 126

| Nº | Élément | Comportement |
| --- | --- | --- |
| 117 | Syntaxe | PHP refuse le fichier invalide avant son rendu. View indique aussi l’origine PHP d’une instruction frontend non prise en charge. Un `try` exécuté au clic ne peut pas attraper une erreur qui empêche la page de compiler. |
| 118 | Types | Les contrats des State, paramètres, retours et opérations produisent des erreurs attrapables : notamment `TypeError`, `ArgumentCountError` et `ValueError`. |
| 119 | Exceptions | `Exception` et `Error` suivent leurs hiérarchies PHP. `catch (Throwable)` couvre les deux ; `catch (Exception)` ne couvre pas `TypeError`. |
| 120 | `try/catch/finally` | Blocs imbriqués, plusieurs `catch`, types réunis par `\|`, variable de capture facultative, `finally`, `return`, `break` et `continue`. |
| 121 | `throw` | Instruction ou expression, par exemple `$value ?? throw new RuntimeException('Valeur requise')`. Relancer `$error` conserve son identité et son origine. |
| 122 | Exception personnalisée | Classe chargée qui hérite d’une exception standard, sans redéfinir le constructeur, les méthodes ou les propriétés. Héritage, alias, code et `previous` sont conservés. |
| 123 | Journalisation | `echo`, `var_dump` et `Console::log/warning/error` restent dans la console du navigateur. Aucun envoi automatique au serveur. |
| 124 | Développement / production | L’overlay détaillé apparaît seulement avec la métadonnée `aml-debug=true`. Les exceptions restent attrapables dans les deux modes. |
| 125 | Trace | `getFile`, `getLine`, `getTrace`, `getTraceAsString`, `getMessage`, `getCode`, `getPrevious`, `get_class` et `instanceof` sont disponibles. L’overlay ajoute la pile JavaScript. |
| 126 | Assertions | `assert` est compilé selon `zend.assertions` et `assert.active` du PHP qui prépare la page. Un échec actif lance `AssertionError` ou le `Throwable` fourni. |

## Services et exceptions personnalisées

```php
namespace App\Services;

final class AgeInvalide extends \InvalidArgumentException {}

function validerAge(int $age): int
{
    if ($age < 0) { throw new AgeInvalide('Âge invalide', 117); }
    return $age;
}
```

Chargez le service avant le rendu, avec l’autoload de l’application ou
`require_once __DIR__ . '/service.php'` au niveau du fichier. `import()` reste
l’import des feuilles de style. N’ajoutez pas de `require` dans le gestionnaire
frontend. Engine reçoit les fonctions compilées et les métadonnées des classes ;
aucun fichier PHP n’est exécuté dans le navigateur.

Les paramètres et retours peuvent porter `Throwable`, `Exception`, un type
d’exception pris en charge ou une union nullable. `new` accepte un nom de classe
explicite chargé, des paramètres positionnels, nommés ou dépliés avec `...`.
`new ErrorException(...)` conserve aussi la sévérité et les éventuels fichier et
ligne fournis. Les constructeurs et propriétés personnalisés sont refusés avec
un diagnostic : placez la validation dans une fonction de service.

```php
try {
    validerAge($this->age);
} catch (\App\Services\AgeInvalide $error) {
    throw new \RuntimeException('Validation impossible', previous: $error);
}
```

L’exception reste une valeur locale opaque. Elle peut passer à une fonction,
être retournée, capturée dans une closure ou figurer dans un tableau local.
Elle ne peut pas entrer dans un State ou dans la persistance : stockez plutôt
`$error->getMessage()` et `$error->getCode()`. `===` et `!==` comparent l’identité.
Pour comparer deux exceptions distinctes, comparez leurs messages ou leurs
codes ; la comparaison structurelle d’objets avec `==` n’est pas implémentée.
`var_dump($error)` fournit un résumé de l’exception et de sa trace, pas la
représentation interne complète de l’objet PHP.

## Propagation et état

Le premier `catch` compatible traite l’erreur. Une erreur lancée dans ce
`catch` remonte vers un bloc extérieur. `finally` s’exécute aussi en présence
de `return`, `break`, `continue` ou d’une exception. Un `return` dans `finally`
peut remplacer le retour ou supprimer l’exception en attente ; une exception
lancée par `finally` devient prioritaire et conserve l’erreur précédente dans
sa chaîne `previous`, sans créer de cycle.

Si une erreur est attrapée dans l’événement, les changements déjà faits aux
State sont conservés. Si elle sort de l’événement synchrone sans être attrapée,
Engine restaure l’état d’avant l’événement, y compris les modifications de
`finally`. C’est la transaction de l’interface ; PHP natif ne restaure pas
automatiquement ses variables. Les sorties console déjà produites restent.
`aml:error` signale l’échec, et un événement suivant peut toujours fonctionner.

Les actions asynchrones `Api` et `BrowserFiles` gardent leurs propres API
`errorIn` et événements d’erreur. Cette syntaxe `try` concerne le code PHP
compilé des événements et des fonctions synchrones ; elle n’ajoute pas
d’attente de requête dans une expression PHP.

## Logs, mode et traces

```php
Console::log('Validation démarrée');
Console::warning('Saisie invalide', $error->getMessage());
Console::error('Échec', $error->getMessage(), $error->getCode());
```

Ces appels n’écrivent aucun fichier journal. `error_log`, `set_error_handler`,
`set_exception_handler`, `trigger_error`, `error_reporting`, `ini_set` et les
fonctions de restauration des gestionnaires PHP restent des fonctions du
processus PHP ; leur usage dans un événement frontend est refusé.

La CLI PHPAML émet la métadonnée `aml-debug=true` lorsque
`PHPAML_DEV_SERVER=1`. Engine présente alors les erreurs non attrapées dans
l’overlay de développement. Sans cette métadonnée, l’overlay est désactivé ;
les logs et événements de diagnostic restent disponibles. Les assertions ont
leur propre configuration, indépendante de cet affichage.

Une trace frontend contient le fichier, la ligne et le nom des fonctions PHP
compilées, sans leurs arguments. Le fichier est relatif au projet lorsque le
contexte le permet, sinon son nom seul est utilisé. L’origine reste celle de
la création de l’exception ou de l’échec, même après une relance. La trace
n’inclut pas les appels PHP du moteur de rendu serveur et n’est donc pas une
copie exacte de la trace PHP native. `getPrevious()?->getMessage()` permet
d’inspecter une cause facultative.

Le mode production masque l’overlay ; il ne retire pas les métadonnées PHP
des instructions distribuées au navigateur. Les messages et données inclus
dans une page frontend restent accessibles à son utilisateur.

## Assertions de développement

```php
assert($total >= 0, 'Le total calculé doit rester positif.');
assert($age >= 0, new \LogicException('Invariant sur l’âge'));
```

Avec `zend.assertions=1` et `assert.active=1`, condition et description sont
évaluées, et l’échec lance l’exception. Avec `zend.assertions=0` ou `-1`, ou
`assert.active=0`, l’appel donne `true` sans évaluer ses arguments, y compris
leurs effets secondaires. Le résultat est fixé à la compilation de la page ;
changer seulement la métadonnée de debug ne le change pas.

Le mode actif exige `assert.exception=1`, `assert.bail=0` et aucun callback
PHP d’assertion. Les autres configurations sont refusées explicitement. Les
messages automatiques peuvent avoir un espacement différent du PHP natif.
Utilisez toujours `if (...) { throw ...; }` pour une validation utilisateur qui
doit fonctionner en production. Une assertion sert à vérifier un invariant
pendant le développement.

Références PHP : [exceptions et propagation](https://www.php.net/language.exceptions),
[Throwable](https://www.php.net/manual/en/class.throwable.php),
[assertions](https://www.php.net/manual/en/function.assert.php).
