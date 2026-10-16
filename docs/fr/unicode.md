# Unicode dans les composants et les événements

View et Engine prennent en charge **28 fonctions `mb_*` en UTF-8**, en plus des
232 fonctions Math, String et Array et des [14 fonctions BCMath](bcmath.md). Elles fonctionnent dans les
textes réactifs, les événements, les fonctions de service compilées et les
callbacks. PHP calcule le rendu initial ; Engine recalcule dans le navigateur
quand un `State` change.

L'extension PHP `mbstring` doit être installée pour le rendu initial et ViewTest.
Le navigateur utilise le module Unicode d'Engine, sans extension ni requête
serveur. Voir [la page complète à essayer](../../examples/unicode/page.php).

## Afficher un State

```php
#[State] public string $message = 'André 🌍';

public function body(): View
{
    return VStack(
        Input('message')->value($this->message),
        Text('Majuscules : ' . mb_strtoupper($this->message)),
        Text('Début : ' . mb_substr($this->message, 0, 5)),
        Text('Longueur : ' . mb_strlen($this->message)),
    );
}
```

Les mêmes expressions sont utilisables dans les composants acceptant déjà un
[texte réactif](textes-reactifs.md). Elles acceptent aussi une closure pure :

```php
Text(fn () => mb_convert_case($this->message, MB_CASE_TITLE)),
```

Les arguments nommés, les valeurs par défaut, `...`, les callables natifs et les
fonctions de service suivent les règles des autres fonctions compilées :

```php
$majuscule = mb_strtoupper(...);
$noms = array_map($majuscule, ['André', 'Émilie']);
$fin = mb_substr(length: 2, string: 'été🌍', start: -2); // é🌍
$this->message = implode(', ', $noms);
```

## Fonctions disponibles

| Usage | Fonctions |
| --- | --- |
| Longueur et extraction | `mb_strlen`, `mb_substr`, `mb_str_split`, `mb_strcut` |
| Recherche et comptage | `mb_strpos`, `mb_strrpos`, `mb_stripos`, `mb_strripos`, `mb_strstr`, `mb_stristr`, `mb_strrchr`, `mb_strrichr`, `mb_substr_count` |
| Casse | `mb_strtolower`, `mb_strtoupper`, `mb_convert_case`, `mb_ucfirst`, `mb_lcfirst` |
| Nettoyage et remplissage | `mb_trim`, `mb_ltrim`, `mb_rtrim`, `mb_str_pad` |
| Largeur conventionnelle | `mb_strwidth`, `mb_strimwidth` |
| Points de code et validation | `mb_ord`, `mb_chr`, `mb_check_encoding`, `mb_scrub` |

`mb_convert_case()` accepte les huit modes `MB_CASE_*`, complets et simples.
La recherche sans casse utilise le pliage simple de PHP : elle ne transforme
pas `ß` en deux caractères `ss`. Les conversions complètes peuvent agrandir
une chaîne, par exemple `mb_strtoupper('Straße')` donne `STRASSE`.

`mb_str_pad` nécessite PHP 8.3 ; `mb_ucfirst`, `mb_lcfirst`, `mb_trim`, `mb_ltrim`
et `mb_rtrim` nécessitent PHP 8.4. Une fonction absente du PHP exécutant View
produit un diagnostic lors de la compilation de son appel direct.

## Octets, points de code et caractères visibles

| Expression | Résultat |
| --- | --- |
| `strlen('André 🌍')` | 11 octets |
| `mb_strlen('André 🌍')` | 7 points de code |
| `mb_strlen("e\u{301}")` | 2 points de code, pour un `é` décomposé |
| `mb_strlen('👨‍👩‍👧‍👦')` | 7 points de code, pour un emoji composé |
| `mb_strwidth('漢字')` | 4 unités de largeur conventionnelle |

`mb_*` ne segmente pas les graphèmes. Une extraction peut donc séparer un accent
combiné ou les éléments d'un emoji composé. `mb_strcut` utilise des octets et
ajuste les limites pour conserver des caractères UTF-8 entiers. `mb_strwidth`
et `mb_strimwidth` suivent les tables de largeur de PHP ; ils ne mesurent ni
les pixels ni la police d'un composant. Les fonctions `grapheme_*`, `Normalizer`
et le formatage localisé sont décrits dans le [guide d'affichage Unicode](web-unicode-dynamique.md).

## Encodage, erreurs et limites

- Les encodages acceptés sont `UTF-8` et son alias `UTF8`, sans distinction de
  casse. Sans argument, Engine utilise l'encodage interne capturé lors de la
  compilation. Si celui-ci n'est pas UTF-8, préciser `encoding: 'UTF-8'` ; les
  autres encodages produisent une `ValueError` explicite dans Engine et ViewTest.
- Les configurations globales `mb_internal_encoding` et
  `mb_substitute_character`, les conversions d'encodage, les fonctions HTTP/mail
  et les expressions régulières `mb_ereg*` ne sont pas compilées. Les anciens
  encodages doivent être convertis avant de transmettre les données à View.
- `mb_check_encoding` attend une chaîne ou un tableau, dont il vérifie aussi
  les clés. L'appel sans valeur ou avec `null`, qui inspecte la requête PHP,
  est refusé. Les chaînes du frontend sont déjà des textes UTF-8 valides :
  `mb_scrub` n'ajoute pas de prise en charge des données binaires. Il peut
  remplacer un surrogate UTF-16 isolé venant du navigateur.
- Les erreurs de type, d'arguments et de valeurs portent leur origine PHP et
  peuvent être interceptées par `try/catch`. Une erreur non interceptée annule
  les mutations de State de l'événement. `echo` reste dans la console.
- Les résultats restent limités à 1 Mio par chaîne et 10 000 éléments par
  tableau. Les opérations partagent le budget de calcul de l'événement.

Les tables de casse, de largeur et de nettoyage sont générées avec PHP 8.4.11
et livrées avec Engine. Elles ne dépendent pas des tables Unicode du navigateur.
La règle du sigma final tient compte de la version PHP compilant l'expression
(introduite dans PHP 8.3). La référence de compatibilité Unicode exhaustive est
PHP 8.4.11 ; des versions PHP utilisant d'autres tables Unicode peuvent différer
sur les caractères ajoutés récemment.

Références PHP : [fonctions mbstring](https://www.php.net/manual/fr/ref.mbstring.php),
[modes de casse](https://www.php.net/manual/fr/function.mb-convert-case.php),
[découpe en octets](https://www.php.net/manual/fr/function.mb-strcut.php).
