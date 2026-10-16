# Compatibilité PHP de View et Engine

Cette page est le point d'entrée du périmètre frontend de **View beta.13** et
**Engine beta.9**, en développement. Les guides liés précisent les formes
reconnues et les erreurs. Une famille disponible ne signifie pas que toutes
les fonctions, extensions ou entrées possibles de PHP sont reproduites.

## Où le code s'exécute

| Emplacement | Exécution |
| --- | --- |
| Code ordinaire de `body()`, avant `return` | PHP au rendu initial, avec les fonctionnalités et extensions du serveur. |
| Événement, effet, fonction ou méthode de service appelé par un événement | Source compilée par View, instructions interprétées par Engine dans le navigateur. |
| Expression d'affichage dépendant d'un `State` | Rendu initial PHP puis recalcul pur dans Engine ; les restrictions des [textes réactifs](textes-reactifs.md) s'appliquent. |
| `eval()` dans un événement | Même interpréteur frontend ; analyseur supplémentaire pour les chaînes dynamiques. |

Le compilateur lit les services déjà chargés sans appeler leurs fonctions ni
leurs constructeurs. Charger un fichier de service avec Composer ou
`require_once` avant le rendu. `AML\View\import()` importe les feuilles de style ;
il ne remplace pas le chargement d'un service PHP.

## Langage et objets

| Famille | Disponible dans les événements | Guide et vérification |
| --- | --- | --- |
| Variables et types | Variables locales, scalaires, `null`, constantes connues, conversions scalaires/`array`/`object`, interpolation, concaténation, caractères échappés, heredoc/nowdoc, inspection | [Niveau 1](niveau-1-variables-types.md), [conversions](fonctions-avancees.md) · `tests/language-level1.php`, `tests/advanced-functions.php` |
| Opérateurs | Calculs, puissance, affectations composées, incréments, comparaisons, courts-circuits, ternaire, `??`, `and/or/xor`, opérations binaires `&`, `\|`, `^`, `~`, `<<`, `>>` | [Niveau 2](niveau-2-operateurs.md), [compléments](fonctions-avancees.md) · `tests/language-level2.php`, `tests/advanced-functions.php` |
| Conditions et boucles | `if/elseif/else`, `switch`, `match`, `isset/empty`, `while`, `do`, `for`, `foreach`, `break/continue` imbriqués | [Niveaux 3–5](niveaux-3-5-conditions-boucles-tableaux.md) · `tests/language-level345.php` |
| Tableaux | Clés PHP et ordre conservés, tableaux imbriqués, copies, déstructuration, propagation, callbacks et tris du catalogue | [Fonctions natives](fonctions-natives.md) · `tests/native-functions.php` |
| Fonctions | Services séparés, paramètres typés/nommés/variadiques, retours, closures, flèches, captures et paramètres par référence, récursivité bornée | [Niveau 6](niveau-6-fonctions.md) · `tests/language-level6.php` |
| Références | Alias `=&`, éléments de tableaux référencés, `foreach` par référence sur les collections reconnues, retours de fonctions/méthodes par référence, `&__get` et `&offsetGet` | [SPL et références](spl-references.md) · `tests/spl-references.php` |
| Fonctions avancées | Variables locales `static`, `func_get_args`/`func_num_args`/`func_get_arg`, méthodes et propriétés d'instance à nom calculé, callbacks tableaux/chaînes de méthodes compilées | [Compléments](fonctions-avancees.md) · `tests/advanced-functions.php` |
| Erreurs | `throw`, `try/catch/finally`, erreurs de type, exceptions natives et héritées, traces PHP, assertions selon la configuration PHP | [Niveau 10](niveau-10-erreurs-exceptions.md) · `tests/language-level10.php` |
| Objets de service | Constructeurs, propriétés typées/promues/readonly, visibilité, héritage, interfaces, traits de classes chargées, clonage, liaison statique tardive | [Objets](objets.md) · `tests/objects.php` |
| Propriétés statiques | Mémoire isolée par composant, conservée entre ses événements, réinitialisée à sa recréation | [Objets](objets.md) · `tests/statics.php` |
| Méthodes magiques | `__get`, `__set`, `__isset`, `__unset`, `__call`, `__callStatic`, `__invoke`, `__toString`, `__clone`, `__debugInfo` | [Méthodes magiques](methodes-magiques.md), [introspection](introspection.md) · `tests/magic.php`, `tests/introspection.php` |
| Classes anonymes et contrats | `new class`, interfaces, intersections `A&B`, unions DNF, identité propre aux classes créées dans `eval` | [Guide](classes-anonymes-intersections.md) · `tests/anonymous.php` |
| Enums | Cas simples ou adossés à `string`/`int`, identité, `name`/`value` readonly, `cases`/`from`/`tryFrom`, méthodes, interfaces, usage dans `eval` | [Enums](enums.md) · `tests/enums.php` |
| Collections et parcours | Services `Countable`, `ArrayAccess`, `Iterator`, `IteratorAggregate`, contrats `Traversable`/`iterable`, parcours paresseux | [Collections](collections.md), [itérateurs](iterateurs.md) · `tests/collection-objects.php`, `tests/iterators.php` |
| SPL | `SplDoublyLinkedList`, `SplStack`, `SplQueue`, `ArrayIterator`, `ArrayObject` avec stockage tableau, offsets, tri, itérateurs partagés ; `iterator_to_array`, `iterator_count`, `iterator_apply` | [SPL et références](spl-references.md) · `tests/spl-references.php` |
| Générateurs | `yield`, `yield from`, suspension d'expressions, `send`, `throw`, `getReturn`, reprise entre événements | [Générateurs](generateurs.md) · `tests/generators.php` |
| Évaluation de code | `eval` statique/dynamique, portée partagée, fonctions déclarées, services déjà compilés, classes anonymes ; affectations binaires `<<=`, `>>=`, `&=`, `\|=`, `^=` | [eval](eval.md) · `tests/eval.php`, `tests/eval-bitwise.php` |

Les tests PHP associés confrontent le compilateur et ViewTest. Les fichiers
`.mjs` correspondants exécutent les instructions dans Engine et les comparent
aux résultats PHP ; les scénarios navigateur vérifient les interactions réelles.
Les guides indiquent les divergences volontaires qui ne sont pas une parité PHP.

## Données, chaînes et nombres

| Famille | Contrat frontend | Guide |
| --- | --- | --- |
| Math, String, Array | Fonctions du catalogue ci-dessous, arguments nommés/dépliés, callbacks et paramètres de sortie par référence | [Fonctions natives](fonctions-natives.md) |
| Unicode | 28 fonctions `mb_*` en UTF-8, dix fonctions `grapheme_*`, normalisation et adaptateur `NumberFormatter` | [Unicode](unicode.md), [affichage et locales](web-unicode-dynamique.md) |
| URL et Base64 | Huit fonctions de parsing/encodage et construction de requête, chaînes UTF-8 | [Guide](web-unicode-dynamique.md) · `tests/web-functions.php` |
| Décimaux exacts | 14 fonctions BCMath, calculs décimaux en chaînes, échelle locale à l'événement, huit cas `RoundingMode` | [BCMath](bcmath.md) |
| JSON | Encode/décode/valide en mémoire, flags et erreurs PHP, distinction int/float, objets `stdClass`, enums, dates et méthodes compilées `JsonSerializable` | [JSON](json-regex-dates.md#json) · `tests/json-regex-dates.php`, `tests/json-serializable.php` |
| Expressions régulières | Dix fonctions `preg_*`, captures/offsets/paramètres de sortie, remplacements et callbacks ; sous-ensemble PCRE borné, refus explicite des constructions exclues | [Expressions régulières](json-regex-dates.md#expressions-régulières) · `tests/json-regex-dates.php` |
| Dates | `DateTime`/`DateTimeImmutable`/`DateTimeZone`, `createFromFormat` et diagnostics, `DateInterval`, `diff`/`add`/`sub`, parcours `DatePeriod`, fonctions procédurales, fuseaux explicites/IANA ; années 0001–9999 | [Dates et fuseaux](json-regex-dates.md#dates-et-fuseaux), [formats et périodes](dates-avancees.md) · `tests/json-regex-dates.php`, `tests/date-advanced.php` |
| Validation et appels | `filter_var` et `filter_var_array` pour les filtres décrits dans le guide, `is_callable`, `function_exists`, `call_user_func` et `call_user_func_array` sur le catalogue compilé | [Utilitaires](utilitaires.md) · `tests/utility-functions.php` |
| Validation et hachage | Onze fonctions `ctype_*` en ASCII, `hash`/`hash_hmac` avec neuf algorithmes, comparaison et catalogue frontend | [Fonctions natives](fonctions-natives.md) |
| Console | `echo`, `var_dump`, `print_r`, `var_export`, `printf`, `vprintf`, façade `Console` ; aucune insertion automatique dans la page | [Sorties](sorties-console.md) |
| Fichiers navigateur | Sélection et lecture avec `FileInput`/`BrowserFiles`, JSON/CSV, téléchargement, persistance locale/session | [Niveau 9](niveau-9-fichiers-donnees.md) |

Un résultat à afficher passe dans un `State` puis un composant tel que `Text`.
`print_r` et `var_export` avec `return: true` produisent des chaînes utilisables
dans un affichage pur. Les objets, closures, exceptions et générateurs restent
locaux ou dans la mémoire statique du composant ; ils ne peuvent pas entrer dans
un `State`, même imbriqués dans un tableau.

## Frontières communes

- PHPAML frontend ne fournit pas PDO, le disque du serveur, les processus,
  `STDIN`/`STDOUT`/`STDERR`, les arguments CLI ou les extensions serveur arbitraires.
  Les opérations serveur passent par une API explicite.
- Les [sept extensions du langage](extensions-langage.md) couvrent les nouveaux
  cas de syntaxe, fonctions, regex, dates/Intl, références, objets et `eval`.
  Ce guide détaille aussi les formes encore refusées.
- Les classes et fonctions externes doivent appartenir au catalogue compilé.
  Les classes et enums déclarés par `eval` restent dans ce catalogue frontend.
  Aucun autoload PHP, accès aux superglobales ou `global` n'est ajouté.
- Les destructeurs et la finalisation automatique des générateurs abandonnés ne
  sont pas reproduits. `finally` fonctionne lors d'une sortie explicite du corps.
- PCRE et ICU restent des adaptateurs bornés. La réflexion est limitée aux classes,
  méthodes et propriétés compilées ; la sérialisation refuse les classes inconnues,
  les cellules de références de tableaux et les objets internes non adaptés.
- Les références cycliques sont locales. Les State restent sérialisables et
  refusent les objets et cycles. Les propriétés State entières ne peuvent pas
  être liées à gauche de `=&` ou insérées comme cellules de référence dans un tableau.
- Les chaînes restent UTF-8 ; les opérations par octets qui produiraient du
  UTF-8 invalide sont refusées. Les entiers exacts sont limités à
  ±9 007 199 254 740 991 ; les nombres non finis ne peuvent pas entrer dans State.
- Les textes réactifs restent purs : pas de sortie console, mutation de State,
  hasard, construction libre d'objet, `eval` ou parcours avec effets de bord.
  Les projections de constructeurs utilisent un catalogue fermé d'objets temporaires isolés.

| Limite | Valeur |
| --- | --- |
| Boucles | 1 000 tours par boucle, 10 000 cumulés par événement |
| Fonctions | 128 appels imbriqués, 10 000 appels par événement |
| Catalogue du composant | 256 fonctions/méthodes, 64 classes |
| Allocations | 10 000 objets par événement |
| Valeurs | Chaîne de 1 Mio, tableau de 10 000 entrées, profondeur 64 |
| Travail des fonctions natives | Budget partagé d'un million d'étapes |
| Source `eval` | 64 Kio, profondeur d'analyse 128 |

Une erreur synchrone non interceptée restaure les State et leurs types
numériques. Les sorties console et les effets sur les services statiques déjà
réalisés persistent. Une exception interceptée conserve les changements qui la
précèdent. Les diagnostics gardent le fichier et la ligne PHP.

## Programme combiné

La [page du catalogue](../../examples/compatibilite/page.php) utilise un
[service séparé](../../examples/compatibilite/service.php). Le clic décode du
JSON, nettoie et valide les articles, traduit leur statut enum, parcourt une
collection SPL et affiche un total en centimes avec une date explicite.
Les erreurs sont affichées dans un State ; les sorties `echo` restent dans
la console. `tests/compatibility-program.php` et `.mjs`, puis le test navigateur
correspondant, vérifient les interactions entre ces familles.

Le [programme de réservation](reservation.md) ajoute un formulaire validé,
trois nuits calendaires, un calcul en centimes et un export `JsonSerializable`.
Ses tests PHP, Engine et navigateur couvrent aussi le 29 février, le changement
d'heure à Toronto et la correction d'une saisie invalide.

## Catalogue natif

Cette liste exhaustive est générée depuis le registre Engine avec
`composer docs:compatibility`. `composer docs:check` vérifie sa synchronisation.
Les constructions du langage (`isset`, `empty`, `echo`, `eval`, etc.) ne sont
pas des fonctions de ce registre. Les guides de famille précisent les restrictions
d'arguments, de types et d'utilisation dans les textes réactifs. Une fonction
introduite après PHP 8.2 exige aussi la version PHP correspondante au rendu initial.

<!-- native-catalogue:start -->

**404 fonctions et alias** déclarés dans `AML\Engine\BuiltinFunctions::NAMES`.

`abs()`, `acos()`, `acosh()`, `addcslashes()`, `addslashes()`, `array_all()`, `array_any()`, `array_change_key_case()`,
`array_chunk()`, `array_column()`, `array_combine()`, `array_count_values()`, `array_diff()`, `array_diff_assoc()`, `array_diff_key()`, `array_diff_uassoc()`,
`array_diff_ukey()`, `array_fill()`, `array_fill_keys()`, `array_filter()`, `array_find()`, `array_find_key()`, `array_flip()`, `array_intersect()`,
`array_intersect_assoc()`, `array_intersect_key()`, `array_intersect_uassoc()`, `array_intersect_ukey()`, `array_is_list()`, `array_key_exists()`, `array_key_first()`, `array_key_last()`,
`array_keys()`, `array_map()`, `array_merge()`, `array_merge_recursive()`, `array_multisort()`, `array_pad()`, `array_pop()`, `array_product()`,
`array_push()`, `array_rand()`, `array_reduce()`, `array_replace()`, `array_replace_recursive()`, `array_reverse()`, `array_search()`, `array_shift()`,
`array_slice()`, `array_splice()`, `array_sum()`, `array_udiff()`, `array_udiff_assoc()`, `array_udiff_uassoc()`, `array_uintersect()`, `array_uintersect_assoc()`,
`array_uintersect_uassoc()`, `array_unique()`, `array_unshift()`, `array_values()`, `array_walk()`, `array_walk_recursive()`, `arsort()`, `asin()`,
`asinh()`, `asort()`, `atan()`, `atan2()`, `atanh()`, `base64_decode()`, `base64_encode()`, `base_convert()`,
`bcadd()`, `bcceil()`, `bccomp()`, `bcdiv()`, `bcdivmod()`, `bcfloor()`, `bcmod()`, `bcmul()`,
`bcpow()`, `bcpowmod()`, `bcround()`, `bcscale()`, `bcsqrt()`, `bcsub()`, `bin2hex()`, `bindec()`,
`boolval()`, `call_user_func()`, `call_user_func_array()`, `ceil()`, `checkdate()`, `chop()`, `chr()`, `chunk_split()`,
`class_exists()`, `compact()`, `convert_uudecode()`, `convert_uuencode()`, `cos()`, `cosh()`, `count()`, `count_chars()`,
`crc32()`, `ctype_alnum()`, `ctype_alpha()`, `ctype_cntrl()`, `ctype_digit()`, `ctype_graph()`, `ctype_lower()`, `ctype_print()`,
`ctype_punct()`, `ctype_space()`, `ctype_upper()`, `ctype_xdigit()`, `current()`, `date()`, `date_add()`, `date_create()`,
`date_create_from_format()`, `date_create_immutable()`, `date_create_immutable_from_format()`, `date_default_timezone_get()`, `date_default_timezone_set()`, `date_diff()`, `date_format()`, `date_get_last_errors()`,
`date_interval_format()`, `date_modify()`, `date_sub()`, `date_timestamp_get()`, `date_timestamp_set()`, `date_timezone_get()`, `date_timezone_set()`, `decbin()`,
`dechex()`, `decoct()`, `deg2rad()`, `doubleval()`, `end()`, `enum_exists()`, `exp()`, `explode()`,
`expm1()`, `extract()`, `fdiv()`, `filter_var()`, `filter_var_array()`, `floatval()`, `floor()`, `fmod()`,
`fpow()`, `func_get_arg()`, `func_get_args()`, `func_num_args()`, `function_exists()`, `get_called_class()`, `get_class()`, `get_class_methods()`,
`get_debug_type()`, `get_defined_vars()`, `get_html_translation_table()`, `get_object_vars()`, `get_parent_class()`, `getdate()`, `getrandmax()`, `gettype()`,
`gmdate()`, `gmmktime()`, `grapheme_extract()`, `grapheme_str_split()`, `grapheme_stripos()`, `grapheme_stristr()`, `grapheme_strlen()`, `grapheme_strpos()`,
`grapheme_strripos()`, `grapheme_strrpos()`, `grapheme_strstr()`, `grapheme_substr()`, `hash()`, `hash_algos()`, `hash_equals()`, `hash_hmac()`,
`hash_hmac_algos()`, `hex2bin()`, `hexdec()`, `html_entity_decode()`, `htmlentities()`, `htmlspecialchars()`, `htmlspecialchars_decode()`, `http_build_query()`,
`hypot()`, `implode()`, `in_array()`, `intdiv()`, `interface_exists()`, `intval()`, `is_a()`, `is_array()`,
`is_bool()`, `is_callable()`, `is_countable()`, `is_double()`, `is_finite()`, `is_float()`, `is_infinite()`, `is_int()`,
`is_integer()`, `is_iterable()`, `is_long()`, `is_nan()`, `is_null()`, `is_numeric()`, `is_object()`, `is_scalar()`,
`is_string()`, `is_subclass_of()`, `iterator_apply()`, `iterator_count()`, `iterator_to_array()`, `join()`, `json_decode()`, `json_encode()`,
`json_last_error()`, `json_last_error_msg()`, `json_validate()`, `key()`, `key_exists()`, `krsort()`, `ksort()`, `lcfirst()`,
`levenshtein()`, `log()`, `log10()`, `log1p()`, `ltrim()`, `max()`, `mb_check_encoding()`, `mb_chr()`,
`mb_convert_case()`, `mb_lcfirst()`, `mb_ltrim()`, `mb_ord()`, `mb_rtrim()`, `mb_scrub()`, `mb_str_pad()`, `mb_str_split()`,
`mb_strcut()`, `mb_strimwidth()`, `mb_stripos()`, `mb_stristr()`, `mb_strlen()`, `mb_strpos()`, `mb_strrchr()`, `mb_strrichr()`,
`mb_strripos()`, `mb_strrpos()`, `mb_strstr()`, `mb_strtolower()`, `mb_strtoupper()`, `mb_strwidth()`, `mb_substr()`, `mb_substr_count()`,
`mb_trim()`, `mb_ucfirst()`, `md5()`, `metaphone()`, `method_exists()`, `microtime()`, `min()`, `mktime()`,
`mt_getrandmax()`, `mt_rand()`, `mt_srand()`, `natcasesort()`, `natsort()`, `next()`, `nl2br()`, `normalizer_get_raw_decomposition()`,
`normalizer_is_normalized()`, `normalizer_normalize()`, `number_format()`, `numfmt_create()`, `numfmt_format()`, `numfmt_format_currency()`, `numfmt_get_attribute()`, `numfmt_get_text_attribute()`,
`numfmt_set_attribute()`, `numfmt_set_text_attribute()`, `octdec()`, `ord()`, `parse_str()`, `parse_url()`, `pi()`, `pos()`,
`pow()`, `preg_filter()`, `preg_grep()`, `preg_last_error()`, `preg_last_error_msg()`, `preg_match()`, `preg_match_all()`, `preg_quote()`,
`preg_replace()`, `preg_replace_callback()`, `preg_replace_callback_array()`, `preg_split()`, `prev()`, `print_r()`, `printf()`, `property_exists()`,
`quoted_printable_decode()`, `quoted_printable_encode()`, `quotemeta()`, `rad2deg()`, `rand()`, `random_int()`, `range()`, `rawurldecode()`,
`rawurlencode()`, `reset()`, `round()`, `rsort()`, `rtrim()`, `serialize()`, `settype()`, `sha1()`,
`shuffle()`, `similar_text()`, `sin()`, `sinh()`, `sizeof()`, `sort()`, `soundex()`, `sprintf()`,
`sqrt()`, `srand()`, `sscanf()`, `str_contains()`, `str_decrement()`, `str_ends_with()`, `str_getcsv()`, `str_increment()`,
`str_ireplace()`, `str_pad()`, `str_repeat()`, `str_replace()`, `str_rot13()`, `str_shuffle()`, `str_split()`, `str_starts_with()`,
`str_word_count()`, `strcasecmp()`, `strchr()`, `strcmp()`, `strcspn()`, `strip_tags()`, `stripcslashes()`, `stripos()`,
`stripslashes()`, `stristr()`, `strlen()`, `strnatcasecmp()`, `strnatcmp()`, `strncasecmp()`, `strncmp()`, `strpbrk()`,
`strpos()`, `strrchr()`, `strrev()`, `strripos()`, `strrpos()`, `strspn()`, `strstr()`, `strtok()`,
`strtolower()`, `strtotime()`, `strtoupper()`, `strtr()`, `strval()`, `substr()`, `substr_compare()`, `substr_count()`,
`substr_replace()`, `tan()`, `tanh()`, `time()`, `timezone_name_get()`, `timezone_offset_get()`, `timezone_open()`, `trim()`,
`uasort()`, `ucfirst()`, `ucwords()`, `uksort()`, `unserialize()`, `urldecode()`, `urlencode()`, `usort()`,
`var_export()`, `vprintf()`, `vsprintf()`, `wordwrap()`.

<!-- native-catalogue:end -->

### Composition réactive

Les entrées de composants créés avec `Component()` ou un `new` identifiable suivent les dépendances de
leur parent, y compris à travers des enfants imbriqués et des calculs locaux du
constructeur, y compris héritage, méthodes, retours anticipés et hooks. Les champs
dont la valeur est recopiée directement depuis un State peuvent écrire dans le
parent ; les valeurs transformées restent en lecture seule. Les fonctions auxiliaires conservent les dépendances des arguments
dépliés, nommés et variadiques. Les règles et limites sont détaillées dans le
[guide des textes réactifs](textes-reactifs.md) ;
`tests/reactive-composition.php`, `tests/component-forms.php` et leurs tests navigateur vérifient la
propagation, les instances isolées, les types et la reprise après erreur.
