# Fonctions natives — Math, String et Array

Le [catalogue natif complet](compatibilite.md#catalogue-natif) est généré depuis
le registre Engine. Ce guide décrit les fonctions Math, String, Array et
utilitaires, dont 28 fonctions
Unicode `mb_*` et les 14 fonctions procédurales BCMath. Les fonctions
sont exécutées par PHP lors du rendu initial, puis par Engine dans les événements
et les textes réactifs du navigateur. Aucun appel serveur n'est nécessaire pour
ces calculs. Le catalogue est fermé : le nom d'une fonction arbitraire ne permet
pas d'exécuter du PHP ou du JavaScript.

Ce catalogue étend les niveaux 1 à 10. Il ne représente pas toute la bibliothèque
standard PHP : les limites et les fonctions exclues sont précisées ci-dessous.
Le [guide Unicode](unicode.md) détaille les fonctions `mb_*` en UTF-8 ; le
[guide BCMath](bcmath.md) couvre les décimaux exacts. Les adaptateurs Intl sont décrits dans le guide Unicode et les extensions du langage.

Les [fonctions d’introspection](introspection.md) complètent ce catalogue
pour les événements et services. L’inspection des membres reste hors des textes
réactifs ; le prédicat pur `is_object()` y est également utilisable.
Le prédicat `is_countable()` et le comptage des objets sont détaillés dans le
[guide des collections](collections.md). `is_iterable()` et le parcours des objets
sont décrits dans le [guide des itérateurs](iterateurs.md).

## Exemple utilisable

Voir [la page Math, String et Array](../../examples/fonctions-natives/page.php).
Elle nettoie la saisie, convertit les nombres, retire les doublons, trie, calcule
la moyenne et une norme, puis actualise les composants `Text` depuis les `State`.
Les données invalides sont traitées dans `try/catch`.

```php
// Dans un événement :
$valeurs = array_map('floatval', array_map('trim', explode(',', $this->saisie)));
$valeurs = array_values(array_unique($valeurs, SORT_REGULAR));
usort($valeurs, fn (float $a, float $b): int => $a <=> $b);
$this->valeurs = $valeurs;
printf("Calcul terminé : %d valeurs\n", count($valeurs));

// Dans body(), les expressions contenant un State restent réactives :
Text('Valeurs : ' . implode(', ', array_map('strval', $this->valeurs))),
Text(sprintf('Moyenne : %.2f', array_sum($this->valeurs) / count($this->valeurs))),
```

La page complète valide les données avant ces opérations. `printf()`, `vprintf()`
et `echo` écrivent dans la console du navigateur pendant un événement ;
`sprintf()` et `vsprintf()` renvoient une chaîne à afficher avec un composant.
`print_r()` et `var_export()` suivent la même règle ; avec `return: true`, ils
renvoient une chaîne et peuvent servir dans un texte réactif. Voir les
[sorties de diagnostic](sorties-console.md).

## Appels, callbacks et références

Les appels acceptent les arguments positionnels, nommés, les valeurs par défaut
et `...`. Les noms des paramètres proviennent des signatures natives de PHP 8.4.
Les aliases conservent leurs propres signatures. Les fonctions de service déjà
chargées, les closures et les fonctions natives autorisées peuvent servir de
callbacks ; `strtoupper(...)` peut être conservé dans une variable locale.

```php
$nettoyer = trim(...);
$noms = array_map($nettoyer, [' André ', ' Marie ']);
$noms = array_map('strtoupper', $noms);

$compteur = 0;
$message = str_replace(search: 'a', replace: 'A', subject: 'banana', count: $compteur);

$prix = [12, 4, 8];
sort($prix, SORT_NUMERIC); // modification par référence
array_walk($prix, function (&$prix): void { $prix *= 2; });
```

Sont notamment pris en charge : plusieurs tableaux dans `array_map()`, son
callback `null`, les modes de `array_filter()`, les tris par callback, les familles
`array_diff*`/`array_intersect*`, `array_multisort()`, les fusions récursives et les
paramètres de sortie de `parse_str()`, `sscanf()`, `similar_text()` et des
remplacements. Les comparateurs de tri doivent être cohérents et sans effets
secondaires : leur ordre et leur nombre d'appels ne sont pas un contrat.

## Catalogue

### Math et nombres

`acos()`, `acosh()`, `asin()`, `asinh()`, `atan()`, `atan2()`, `atanh()`, `base_convert()`, `bindec()`, `cos()`, `cosh()`, `decbin()`, `dechex()`, `decoct()`, `deg2rad()`, `exp()`, `expm1()`, `fdiv()`, `fmod()`, `fpow()`, `hexdec()`, `hypot()`, `is_finite()`, `is_infinite()`, `is_nan()`, `log()`, `log10()`, `log1p()`, `max()`, `min()`, `octdec()`, `pi()`, `pow()`, `rad2deg()`, `sin()`, `sinh()`, `sqrt()`, `tan()`, `tanh()`, `abs()`, `ceil()`, `floor()`, `round()`, `intdiv()`, `random_int()`, `number_format()`.

### Chaînes

`strlen()`, `str_contains()`, `str_starts_with()`, `str_ends_with()`, `substr()`, `str_replace()`, `explode()`, `implode()`, `strtolower()`, `strtoupper()`, `ucfirst()`, `lcfirst()`, `ucwords()`, `trim()`, `ltrim()`, `rtrim()`, `addcslashes()`, `addslashes()`, `bin2hex()`, `chop()`, `chr()`, `chunk_split()`, `convert_uudecode()`, `convert_uuencode()`, `count_chars()`, `crc32()`, `get_html_translation_table()`, `hex2bin()`, `html_entity_decode()`, `htmlentities()`, `htmlspecialchars()`, `htmlspecialchars_decode()`, `join()`, `levenshtein()`, `md5()`, `metaphone()`, `nl2br()`, `ord()`, `parse_str()`, `printf()`, `quoted_printable_decode()`, `quoted_printable_encode()`, `quotemeta()`, `sha1()`, `similar_text()`, `soundex()`, `sprintf()`, `sscanf()`, `str_decrement()`, `str_getcsv()`, `str_increment()`, `str_ireplace()`, `str_pad()`, `str_repeat()`, `str_rot13()`, `str_shuffle()`, `str_split()`, `str_word_count()`, `strcasecmp()`, `strchr()`, `strcmp()`, `strcspn()`, `strip_tags()`, `stripcslashes()`, `stripos()`, `stripslashes()`, `stristr()`, `strnatcasecmp()`, `strnatcmp()`, `strncasecmp()`, `strncmp()`, `strpbrk()`, `strpos()`, `strrchr()`, `strrev()`, `strripos()`, `strrpos()`, `strspn()`, `strstr()`, `strtok()`, `strtr()`, `substr_compare()`, `substr_count()`, `substr_replace()`, `vprintf()`, `vsprintf()`, `wordwrap()`.

### Tableaux

`array_all()`, `array_any()`, `array_change_key_case()`, `array_chunk()`, `array_column()`, `array_combine()`, `array_count_values()`, `array_diff()`, `array_diff_assoc()`, `array_diff_key()`, `array_diff_uassoc()`, `array_diff_ukey()`, `array_fill()`, `array_fill_keys()`, `array_filter()`, `array_find()`, `array_find_key()`, `array_flip()`, `array_intersect()`, `array_intersect_assoc()`, `array_intersect_key()`, `array_intersect_uassoc()`, `array_intersect_ukey()`, `array_is_list()`, `array_key_exists()`, `array_key_first()`, `array_key_last()`, `array_keys()`, `array_map()`, `array_merge()`, `array_merge_recursive()`, `array_multisort()`, `array_pad()`, `array_pop()`, `array_product()`, `array_push()`, `array_rand()`, `array_reduce()`, `array_replace()`, `array_replace_recursive()`, `array_reverse()`, `array_search()`, `array_shift()`, `array_slice()`, `array_splice()`, `array_sum()`, `array_udiff()`, `array_udiff_assoc()`, `array_udiff_uassoc()`, `array_uintersect()`, `array_uintersect_assoc()`, `array_uintersect_uassoc()`, `array_unique()`, `array_unshift()`, `array_values()`, `array_walk()`, `array_walk_recursive()`, `arsort()`, `asort()`, `count()`, `current()`, `end()`, `in_array()`, `key()`, `key_exists()`, `krsort()`, `ksort()`, `natcasesort()`, `natsort()`, `next()`, `pos()`, `prev()`, `range()`, `reset()`, `rsort()`, `shuffle()`, `sizeof()`, `sort()`, `uasort()`, `uksort()`, `usort()`.

### Sorties de diagnostic

`print_r()`, `var_export()`.

### Types et conversions utilisables comme callbacks

`is_numeric()`, `ctype_digit()`, `gettype()`, `intval()`, `floatval()`, `doubleval()`, `strval()`, `boolval()`, `is_array()`, `is_bool()`, `is_float()`, `is_double()`, `is_int()`, `is_integer()`, `is_long()`, `is_null()`, `is_scalar()`, `is_string()`.

## Validation de caractères et hachage

Les onze fonctions `ctype_*` sont disponibles, notamment `ctype_alpha`,
`ctype_alnum`, `ctype_space` et `ctype_xdigit`. Elles suivent les classes ASCII
avec locale C, pas les catégories Unicode. Une chaîne vide renvoie `false` ; les
entiers suivent les règles historiques PHP et les arguments non textuels
émettent l'avertissement de dépréciation natif.

`hash`, `hash_hmac`, `hash_equals`, `hash_algos` et `hash_hmac_algos` sont
également disponibles. Le catalogue frontend comprend `md5`, `sha1`, `ripemd160`,
`sha224`, `sha256`, `sha384`, `sha512`, `sha512/224` et `sha512/256`.
Les deux fonctions de catalogue listent uniquement ces algorithmes disponibles
dans Engine. Les calculs synchrones utilisent
[noble-hashes](https://github.com/paulmillr/noble-hashes), inclus dans le runtime
avec sa licence MIT. Les sorties sont hexadécimales ; `binary: true` et les
algorithmes absents sont refusés avec une `ValueError`. Les entrées restent UTF-8.
`hash_equals` compare les octets UTF-8 ; aucune garantie de temps constant n'est
revendiquée pour l'exécution JavaScript optimisée par le navigateur.

Ces fonctions sont pures et utilisables dans les textes réactifs. Les cas valides,
arguments nommés, callbacks, erreurs et dépréciations sont comparés à PHP dans
`tests/common-functions.php` et `.mjs`.

## Erreurs et limites du navigateur

- Les erreurs de type, d'arguments, de références et de valeurs sont remontées
  avec le fichier et la ligne PHP. `try/catch` peut les traiter. Une erreur non
  interceptée restaure les State de l'événement ; les logs déjà émis restent dans
  la console. Les limites propres au navigateur sont des erreurs de plage.
- Les textes réactifs doivent rester purs. Les sorties console, le hasard,
  `strtok()` et les modifications des State par référence sont interdits dans
  ces expressions. Les transformations de tableaux locaux peuvent être faites
  dans un service pur ; les callables natifs par référence y sont refusés.
- Les chaînes sont en UTF-8, avec les positions et longueurs en **octets**, comme
  les fonctions PHP non `mb_*`. Une opération produisant une séquence UTF-8
  invalide est refusée : par exemple `strrev('é')`, `chr(255)` ou certains hashages
  binaires. La casse est ASCII. Les entités HTML utilisent UTF-8.
- Une chaîne produite ne dépasse pas 1 Mio, un tableau 10 000 entrées et un
  résultat 64 niveaux imbriqués. Les opérations coûteuses partagent un budget
  d'un million d'étapes par événement ; les limites de boucles et de fonctions
  s'appliquent également. La précision flottante de `sprintf()` est limitée à
  100 chiffres, celle de `number_format()` à 1 000.
- Les entiers exacts restent dans ±9 007 199 254 740 991. Les conversions de base
  dépassant cette plage sont refusées. Les résultats flottants suivent la
  précision double du navigateur, avec de possibles différences de dernier bit.
  Les valeurs `NAN`/`INF` calculées peuvent être examinées localement avec
  `is_nan()`/`is_infinite()` ; elles ne peuvent pas être stockées dans un State
  ni transmises comme constantes non finies dans un manifeste.
- `strtok()` conserve sa position pendant l'événement courant. Aucun état du
  tokenizer n'est partagé entre événements. Le hasard utilise la source sûre du
  navigateur pour `random_int`. Les générateurs historiques `rand`/`mt_rand`
  et leurs graines sont également adaptés. Les pointeurs internes des tableaux ne sont pas un format de
  persistance ; stocker un index explicite pour conserver une position.
- Le mode de locale est fixe : `SORT_LOCALE_STRING`, `setlocale()`, `localeconv()`,
  `strcoll()` et `nl_langinfo()` ne sont pas émulés. Utiliser un format explicite.
  `hebrev()` et les conversions d'anciens encodages ne sont pas disponibles.
- `compact()`, `extract()`, `get_defined_vars()` et les variables variables sont
  disponibles dans les événements ; voir les [extensions du langage](extensions-langage.md).
- `crypt()`, `md5_file()`, `sha1_file()`, `fprintf()` et `vfprintf()` restent liés
  au serveur ou aux flux PHP. `md5()`/`sha1()` servent à la compatibilité de
  données, pas au stockage de mots de passe. `strip_tags()` ne désinfecte pas du
  HTML arbitraire ; `Text` continue d'échapper le contenu affiché.
- Pour `round()`, les modes disponibles sont les quatre constantes `PHP_ROUND_HALF_*`.
  `bcround()` accepte les huit cas `RoundingMode`, comme valeurs locales opaques.
  Le rendu PHP initial exige la version native appropriée : PHP 8.3 pour
  `str_increment()`/`str_decrement()`, PHP 8.4 pour `fpow()` et
  `array_find()`/`array_find_key()`/`array_any()`/`array_all()`.

## Architecture et vérification

Le compilateur réutilise un registre commun dans Engine et un seul mécanisme
pour les arguments et les références. Les implémentations navigateur sont
séparées en modules Math, String, Array, formats et codecs. Les anciennes
branches de compilation par fonction de tableau ont été retirées.
Les modules sont regroupés dans le runtime unique ; ce découpage ne constitue
pas un chargement à la demande.

`composer test` et `composer test:language-runtime` comparent les valeurs,
les types numériques, les clés, les références, les sorties et les erreurs à
PHP natif. Les tests navigateur vérifient aussi l'hydratation, les mises à jour,
les callbacks et la récupération après erreur. Ce sont des scénarios de
compatibilité ; ils ne prétendent pas prouver une équivalence sur toute entrée
possible de chaque fonction PHP.
