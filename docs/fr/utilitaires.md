# Callbacks, vérification des fonctions et filtres

Ces fonctions sont disponibles dans les événements frontend, leurs services compilés et `eval()` : `is_callable()`, `function_exists()`, `call_user_func()`, `call_user_func_array()` et `filter_var()`. Les constantes `FILTER_*` sont aussi disponibles dans le compilateur dynamique.

## Catalogue des fonctions

`function_exists($nom)` interroge les fonctions natives implémentées par Engine et les fonctions du programme présentes dans le manifeste compilé. Une déclaration conditionnelle devient visible après son exécution. La casse et le préfixe `\` suivent PHP.

Un nom littéral peut faire entrer une fonction applicative déjà chargée dans ce manifeste. Un nom construit à l’exécution ne déclenche aucun chargement supplémentaire. Par exemple, `function_exists('file_get_contents')` vaut `false` dans Engine ; l’existence de cette fonction sur le serveur ne rend pas son système de fichiers accessible au navigateur. Aucun autoload applicatif n’est exécuté par ces vérifications.

`is_callable($valeur, $syntax_only, $nom)` accepte les closures, fonctions compilées, noms de fonctions natives disponibles, objets avec `__invoke`, tableaux `[$objet, 'methode']`, `[Classe::class, 'methode']` et chaînes `'Namespace\\Classe::methode'`. Les règles de visibilité et `__call`/`__callStatic` sont prises en compte. Le troisième argument reçoit le nom par référence. Avec `syntax_only: true`, une chaîne ou un tableau de forme valide peut être accepté même si la fonction ou la classe n’existe pas.

## Appels et références

```php
$calculer = fn (int $quantite, int $prix): int => $quantite * $prix;
$total = call_user_func_array($calculer, [
    'prix' => 12,
    'quantite' => 3,
]);

$augmenter = function (int &$valeur): void {
    $valeur += 2;
};
$stock = 10;
call_user_func_array($augmenter, [&$stock]); // $stock vaut 12.
```

Les arguments nommés suivent PHP : aucun argument positionnel après un argument nommé, aucun doublon, paramètres inconnus refusés sauf collecte variadique. `call_user_func_array()` conserve les véritables références contenues dans le tableau ; `call_user_func()` transmet ses arguments par valeur. Une référence manquante produit un avertissement et le callback travaille sur une cellule détachée, sans modifier la variable d’origine.

Le mode de typage dépend de la forme d’appel PHP. Un appel directement résolu, par exemple `\call_user_func($fonction, '12')`, respecte `strict_types` du fichier appelant : un paramètre `int` refuse ici la chaîne en mode strict. Un appel indirect, une référence de première classe du builtin ou un appel non qualifié résolu par repli global dans un namespace utilise la conversion faible du callback. `FILTER_CALLBACK` et les callbacks internes tels que ceux de `array_map()` utilisent également la conversion faible. Ces distinctions sont comparées à PHP natif dans les tests.

Un appel directement résolu à `\call_user_func()` ou `\call_user_func_array()` peut transmettre la référence retournée par une fonction déclarée avec `&`. Les formes indirectes renvoient une valeur ; une affectation `=&` produit alors la notice PHP et une cellule détachée.

Les callbacks applicatifs passent toujours par l’interpréteur frontend, y compris dans `TestView`. Les erreurs de paramètres, exceptions et limites utilisent les diagnostics habituels et annulent les changements du `State` si l’événement échoue.

## Validation et nettoyage

```php
$email = filter_var($saisieEmail, FILTER_VALIDATE_EMAIL);
$quantite = filter_var($saisieQuantite, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 100],
]);

if ($email === false || $quantite === false) {
    $this->message = 'Vérifiez les valeurs saisies.';
} else {
    $this->message = $email . ' : ' . $quantite;
}
```

| Filtre | Options ou drapeaux spécifiques disponibles |
| --- | --- |
| `FILTER_VALIDATE_INT` | `min_range`, `max_range`, `ALLOW_OCTAL`, `ALLOW_HEX` |
| `FILTER_VALIDATE_FLOAT` | `min_range`, `max_range`, `decimal` sur un octet, `ALLOW_THOUSAND` |
| `FILTER_VALIDATE_BOOL` / `FILTER_VALIDATE_BOOLEAN` | Valeurs PHP telles que `true`, `false`, `yes`, `no`, `on`, `off`, `1`, `0` |
| `FILTER_VALIDATE_EMAIL` | Adresses ASCII usuelles ; domaine international à encoder au préalable |
| `FILTER_VALIDATE_URL` | URL ASCII avec `scheme://` ; `PATH_REQUIRED`, `QUERY_REQUIRED` |
| `FILTER_VALIDATE_IP` | IPv4, IPv6, `IPV4`, `IPV6`, `NO_PRIV_RANGE`, `NO_RES_RANGE` |
| `FILTER_VALIDATE_MAC` | Formes avec `:`, `-` ou groupes Cisco séparés par `.` |
| `FILTER_VALIDATE_DOMAIN` | `HOSTNAME` pour imposer la syntaxe de nom d’hôte |
| `FILTER_VALIDATE_REGEXP` | `options['regexp']`, avec le même [sous-ensemble PCRE](json-regex-dates.md) que `preg_match()` |
| `FILTER_CALLBACK` | `options` contient un callback compilé, appliqué aux valeurs converties en chaînes |
| `FILTER_SANITIZE_EMAIL`, `FILTER_SANITIZE_URL` | Nettoyage des caractères selon PHP |
| `FILTER_SANITIZE_NUMBER_INT` | Chiffres, signes `+` et `-` |
| `FILTER_SANITIZE_NUMBER_FLOAT` | `ALLOW_FRACTION`, `ALLOW_THOUSAND`, `ALLOW_SCIENTIFIC` |
| `FILTER_SANITIZE_ENCODED`, `FILTER_SANITIZE_SPECIAL_CHARS` | `STRIP_LOW`, `STRIP_HIGH`, `ENCODE_LOW`, `ENCODE_HIGH` |
| `FILTER_SANITIZE_FULL_SPECIAL_CHARS` | Entités HTML, dont les caractères accentués ; `NO_ENCODE_QUOTES` |
| `FILTER_SANITIZE_ADD_SLASHES` | Échappement PHP de `\`, des guillemets et du caractère NUL |
| `FILTER_UNSAFE_RAW` / `FILTER_DEFAULT` | `STRIP_LOW`, `STRIP_HIGH`, `ENCODE_LOW`, `ENCODE_HIGH`, `ENCODE_AMP`, `EMPTY_STRING_NULL` |

Dans le tableau, les noms abrégés de drapeaux portent le préfixe `FILTER_FLAG_`. Les options générales `FILTER_REQUIRE_SCALAR`, `FILTER_REQUIRE_ARRAY`, `FILTER_FORCE_ARRAY` et `FILTER_NULL_ON_FAILURE` sont disponibles. Les tableaux sont parcourus récursivement en conservant leurs clés ; `options['default']` remplace une validation en échec. Sans valeur par défaut, l’échec renvoie `false`, ou `null` avec `FILTER_NULL_ON_FAILURE`. Un booléen validé égal à `false` reste une réussite.

Les objets convertibles en chaîne utilisent leur `__toString()` compilé ; une exception lancée pendant cette conversion se propage. Les objets sans conversion provoquent un échec du filtre. Un résultat objet ou closure reste local et ne peut pas être placé dans un `State`.

## Limites explicites

Les filtres inconnus ou dépréciés, les drapeaux non implémentés et les séparateurs de milliers personnalisés gardent leurs limites explicites. `FILTER_FLAG_EMAIL_UNICODE`, les parties locales entre guillemets et les URL `mailto:` sont couverts par les [extensions du langage](extensions-langage.md). Les validations URL suivent les restrictions ASCII de PHP ; elles ne convertissent pas automatiquement les domaines internationaux en punycode.

Les drapeaux dans un tableau d’options doivent être des entiers ; `min_range` et `max_range` doivent être numériques. Les entiers produits et les bornes doivent rester entre `-9007199254740991` et `9007199254740991`. La validation des entiers plus larges est explicitement interrompue, sans arrondi silencieux.

Une chaîne d’entrée est limitée à 1 Mio, un tableau à 10 000 entrées par niveau et 64 niveaux. Les budgets généraux d’Engine s’appliquent aussi aux résultats et aux callbacks. Pour `FILTER_VALIDATE_REGEXP`, les plafonds du moteur regex s’ajoutent : motif de 4 096 octets, sujet de 16 384 octets et constructions PCRE limitées. Les limites de ressources produisent `PHP_BUILTIN_LIMIT` dans Engine et interrompent l’événement ; `TestView` signale ces bornes par `RangeException`.

`filter_var()` n’ajoute ni lecture de requête HTTP ni accès serveur. `filter_input()` et `filter_input_array()` restent exclus. La validation de tableaux avec `filter_var_array()` est décrite dans le [guide complémentaire](web-unicode-dynamique.md). Les règles générales PHP sont décrites dans les manuels de [filter_var](https://www.php.net/manual/fr/function.filter-var.php), [is_callable](https://www.php.net/manual/fr/function.is-callable.php) et [call_user_func_array](https://www.php.net/manual/fr/function.call-user-func-array.php).

## Vérification

`tests/utility-functions.php` compare les résultats, erreurs et avertissements de PHP natif avec `TestView`. `tests/utility-functions.mjs` utilise les mêmes scénarios dans Engine, dont les appels dynamiques par `eval()`, les références, les types numériques, les méthodes privées et magiques et le retour arrière du State. Le paquet Engine fournit `tests/browser/utility-functions.spec.mjs` pour la validation sous CSP dans le navigateur.
