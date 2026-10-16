# Niveau 1 — Variables et types

Implémenté dans les sources d’AML View **0.1.0-beta.13** avec PHPAML Engine **0.1.0-beta.9**.
Ces versions doivent être mises à jour ensemble ; cette préparation ne constitue pas une publication. Cette évolution concerne le
PHP compilé des événements et effets frontend. Le PHP exécuté sur le serveur
conserve son fonctionnement habituel.

| Point | Prise en charge dans les événements frontend |
| --- | --- |
| 11. Variables `$` | Déclaration par affectation, lecture, réaffectation et copie de valeur. |
| 12. Nommage | Règles PHP, casse significative, `_` et noms Unicode ; les variables inconnues sont signalées. |
| 13. `string` | Littéraux, variables locales, propriétés d’état et conversions. |
| 14. `int` | Décimal, hexadécimal, binaire, octal et séparateurs `_`. |
| 15. `float` | Décimaux et notation scientifique ; `1.0` reste un `double` pour `gettype()`. |
| 16. `bool` | `true`, `false` et conversion PHP, notamment `(bool) '0' === false`. |
| 17. `null` | Affectation, copie, conversion et diagnostic. |
| 18. `gettype()` | `string`, `integer`, `double`, `boolean`, `NULL` pour les types scalaires. |
| 19. `var_dump()` | Diagnostic des valeurs scalaires dans la console du navigateur, avec leurs types PHP. |
| 20. Conversions | `(string)`, `(int)`, `(float)`, `(bool)`, leurs alias PHP et les fonctions `strval`, `intval`, `floatval`, `doubleval`, `boolval` à un argument. |
| 21. `const` | Constantes scalaires de fichier/namespace, imports `use const` (alias et groupes), constantes de classes déjà chargées ; visibilité respectée. |
| 22. Guillemets | Différence PHP entre chaînes simples et doubles. |
| 23. Interpolation | `$nom`, `{$nom}` et `{$this->propriete}` dans les chaînes doubles. |
| 24. Concaténation | `.` et `.=` ; `true` devient `"1"`, `false` et `null` deviennent `""`. |
| 25. Échappements | `\n`, `\r`, `\t`, `\v`, `\e`, `\f`, `\\`, `\"`, `\$`, hexadécimal, octal et Unicode. |

## Dans `body()`, avant `return`

Ce code est exécuté par PHP au rendu :

```php
public function body(): View
{
    $nom = 'André';
    $age = 30;
    $prix = 12.50;
    $actif = true;
    $valeur = null;
    $message = "Bonjour $nom";
    $type = gettype($prix);

    return VStack(Text($message), Text($type));
}
```

Il affiche `Bonjour André` puis `double`. Ces variables locales ne deviennent pas
réactives dans le navigateur. Les erreurs de syntaxe, variables inconnues et
incompatibilités de types sont diagnostiquées par PHP, avec son fichier et sa
ligne ; View conserve le gestionnaire d’erreurs de l’application. `var_dump()`
placé ici écrit côté serveur. Dans un événement compilé, il écrit dans la console
au déclenchement de cet événement.

## Erreurs et avertissements des actions frontend

Les propriétés `#[State]` conservent leur contrat de type lors des affectations :
`int`, `float`, `string`, `bool`, `mixed`, types acceptant `null` et unions scalaires.
Le mode `declare(strict_types=1)` du fichier contenant l’affectation est respecté.
Par exemple, une chaîne `"42"` est refusée pour une propriété `int` en mode strict ;
en mode PHP faible, elle est convertie en `42`. L’affectation d’un `int` à une
propriété `float` est autorisée et `gettype()` continue de renvoyer `double`. Cette distinction reste conservée
après une transaction, un partage entre composants et une sauvegarde/restauration
d’état scalaire. Les anciennes sauvegardes sans information de type utilisent
le type déclaré de la propriété, ou le type déductible de leur valeur JSON.

Une conversion explicite valide ne devient pas une erreur : `(int) 'abc'` vaut
`0`, `(bool) '0'` vaut `false`. Une conversion implicite perdant la fraction, par
exemple `12.75` affecté à un `int` en mode faible, émet un avertissement de
précision et conserve le comportement PHP (`12`). Les conversions tableau vers
chaîne émettent aussi un avertissement. Les noms de variables sont lisibles dans
les erreurs ; les identifiants internes ne remplacent plus `$nom`.

- Erreur de compilation : exception PHP indiquant le fichier et la ligne de
  l’instruction concernée, notamment variable/constante inconnue et mauvais
  nombre d’arguments de `gettype()` ou `var_dump()`.
- Erreur d’exécution d’un événement : événement `aml:error` et console navigateur.
  `event.detail.error` contient `message`, `code` et `source` (`file`, `line`,
  `function`, `strictTypes`). Les effets conservent `aml:effect-error` et les
  actions avec gestion d’état conservent `aml:action-error`, avec la même erreur.
- Avertissement : événement `aml:warning` dont `detail` contient `message`, `code`
  et `source`, puis sortie dans la console. Il ne bloque pas l’action.
- Une séquence synchrone est transactionnelle : une erreur annule ses mutations
  d’état et restaure les informations de types numériques. Cela ne peut pas
  annuler un appel réseau ou une sortie console déjà effectués.

Les chemins envoyés au navigateur sont relatifs au dossier de l’application,
ou limités au nom du fichier s’il se trouve à l’extérieur. Aucun panneau d’erreur
n’est ajouté automatiquement à l’interface. Le code applicatif peut écouter les
événements ci-dessus pour afficher son propre message.

Les variables locales inconnues sont refusées avant exécution quand cela est
possible, ou interrompent l’action si leur initialisation dépend d’une branche
non exécutée. Cette détection est volontairement plus stricte que l’avertissement
PHP suivi d’une valeur `null`.

## Exemple complet

```php
use AML\View\{Page, State, View};
use function AML\View\{Button, Text, VStack};

const PREFIXE = 'Bonjour';

final class ExemplePage extends Page
{
    private const PONCTUATION = '!';

    #[State]
    public string $message = '';

    public function body(): View
    {
        return VStack(
            Text($this->state('message')),
            Button('Essayer')->onClick(function (): void {
                $nom = 'André';
                $age = 30;
                $prix = 12.50;
                $actif = true;
                $absence = null;

                $salutation = PREFIXE . " {$nom}" . self::PONCTUATION;
                $this->message = "$salutation\nType du prix : " . gettype($prix);

                var_dump($nom, $age, $prix, $actif, $absence);
            }),
        );
    }
}
```

`var_dump()` n'écrit rien dans le HTML et ne déclenche aucun appel serveur. Il
écrit le diagnostic au moment de l'événement dans la console du navigateur.
La longueur des chaînes affichée dans ce diagnostic est mesurée en octets UTF-8.
Le retour à la ligne existe dans la chaîne ; son affichage HTML dépend de la
règle CSS `white-space` de l'élément.

## Valeurs locales

Chaque invocation possède ses propres variables locales. Elles ne sont ni des
propriétés `#[State]`, ni des valeurs persistées. Une affectation capture la valeur
à cet instant ; elle ne crée pas une formule qui relira l'état plus tard :

```php
$avant = $this->compteur;
$this->compteur++;
$this->copie = $avant;
```

Si le compteur valait `1`, il vaut ensuite `2` et la copie vaut `1`. Une variable
locale peut changer de type lors d'une nouvelle affectation. Les valeurs
scalaires capturées par `use ($nom)` ou automatiquement par une fonction fléchée
sont prises au moment du rendu, puis copiées dans chaque invocation.

Une variable s'écrit avec `$`, commence par une lettre ou `_`, puis peut contenir
des chiffres. PHP accepte aussi des noms comme `$étiquette`. `$nom` et `$Nom`
sont différents. Les superglobales serveur ne sont pas accessibles au navigateur.

## Constantes et chaînes

`const` se déclare au niveau du fichier ou de la classe. PHP interdit cette
syntaxe à l'intérieur d'une fonction ou d'une closure. Les références aux
constantes connues sont résolues lors de la compilation, sans exécuter leur code
applicatif. Les constantes objets ne sont pas sérialisées.

```php
$simple = 'Bonjour $nom\n'; // Dollar et antislash littéraux.
$double = "Bonjour $nom\n"; // Variable interpolée et retour à la ligne.
$dollar = "Prix : \$10";
$unicode = "\u{1F600}";
$inconnu = "\q";           // L'antislash est conservé, comme en PHP.
```

## Limites explicites

- Les entiers frontend exacts sont limités à `±9 007 199 254 740 991`, la plage
  entière sûre de JavaScript. Les littéraux, conversions et calculs entiers hors plage sont
  rejetés ; les grands entiers 64 bits nécessiteront une représentation dédiée.
- Les conversions et diagnostics visent ici les cinq types scalaires listés.
  Cela ne constitue pas une émulation complète des objets, ressources ou tableaux
  PHP. Les nombres flottants conservent les limites usuelles de précision binaire.
- Les chaînes transportées entre PHP et le navigateur sont en UTF-8. Les
  littéraux et les états flottants transportés doivent être finis ; `INF`/`NAN`
  sont refusés pour éviter une conversion silencieuse en `null` dans JSON.
- La conversion des flottants en texte suit la précision PHP usuelle de 14 chiffres.
  Des réglages serveur personnalisés de `precision` ou `serialize_precision` ne
  sont pas transmis au navigateur.
- Les variables variables (`$$nom`), superglobales et appels PHP arbitraires
  restent exclus. Les chaînes heredoc/nowdoc sont disponibles ; les
  [fonctions et références](niveau-6-fonctions.md) et les [objets](objets.md)
  étendent les types de ce niveau. La [matrice de compatibilité](compatibilite.md)
  décrit le périmètre actuel.

## Vérification

`composer test` inclut une comparaison avec l'exécution PHP native des mêmes
instructions. `composer test:language-runtime` exécute également les instructions
compilées avec le véritable moteur JavaScript, puis compare les états et les
sorties de `var_dump()`. Les cas d’erreur couvrent aussi le mode strict/faible,
les types acceptant `null`, les unions, le fichier et la ligne, les avertissements
et la restauration d’état après échec. Cette seconde vérification nécessite Node.js.
