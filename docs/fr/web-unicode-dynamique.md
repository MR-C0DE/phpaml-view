# URL, validation, affichage Unicode et classes dynamiques

Ces ajouts fonctionnent dans les événements et services frontend, avec les mêmes
contrats dans ViewTest et Engine. Le [programme complet](../../examples/profil-unicode/page.php)
et son [service](../../examples/profil-unicode/service.php) combinent un formulaire,
la validation, un aperçu Unicode réactif, un montant localisé, une URL et un
service choisi à l'exécution. Aucun de ces calculs ne contacte le serveur.

## URL et Base64

| Usage | Fonctions |
| --- | --- |
| Composants d'une URL | `parse_url` et constantes `PHP_URL_*` |
| Paramètres d'une URL | `http_build_query`, `PHP_QUERY_RFC1738`, `PHP_QUERY_RFC3986` |
| Encodage formulaire | `urlencode`, `urldecode` |
| Encodage RFC 3986 | `rawurlencode`, `rawurldecode` |
| Texte en Base64 | `base64_encode`, `base64_decode` |

```php
$query = http_build_query(['nom' => 'André', 'tags' => ['été', 'café']],
    encoding_type: PHP_QUERY_RFC3986);
$this->lien = 'https://example.test/profil?' . $query;
$this->export = base64_encode(json_encode(['nom' => 'André'], JSON_THROW_ON_ERROR));
```

`parse_url` décompose aussi les chemins relatifs, sans leur ajouter l'adresse de
la page. Il ne valide pas à lui seul une URL. `http_build_query` parcourt les
tableaux imbriqués et les propriétés publiques des services compilés ; les valeurs
`null` sont omises. Son séparateur par défaut est capturé lors de la compilation.
La variante RFC 1738 encode les espaces avec `+`, RFC 3986 avec `%20`.

Le mode `strict: true` de `base64_decode` renvoie `false` pour un encodage invalide.
Les chaînes frontend restent des textes UTF-8 : un décodage Base64 ou URL qui
produirait des octets UTF-8 invalides est refusé. Ces fonctions ne constituent
donc pas une API de manipulation de fichiers binaires.

## Valider plusieurs champs

```php
$donnees = filter_var_array(
    ['age' => $this->age, 'email' => $this->email],
    [
        'age' => ['filter' => FILTER_VALIDATE_INT,
                  'options' => ['min_range' => 0, 'max_range' => 130]],
        'email' => FILTER_VALIDATE_EMAIL,
    ],
);
if ($donnees['age'] === false || $donnees['email'] === false) {
    throw new InvalidArgumentException('Vérifier les champs.');
}
```

`filter_var_array` accepte un filtre commun ou une définition par clé, les
options, valeurs par défaut, callbacks compilés et drapeaux de tableaux de
`filter_var`. Avec `add_empty: true`, une clé demandée mais absente vaut `null` ;
avec `false`, elle est omise. Les filtres et drapeaux disponibles sont exactement
ceux du [guide de validation](utilitaires.md). Une validation dans le navigateur
ne remplace pas celle de l'API recevant les données.

## Caractères visibles et normalisation

| Unité | Exemple |
| --- | --- |
| Octets UTF-8 | `strlen("e\u{301}")` vaut 3 |
| Points de code | `mb_strlen("e\u{301}")` vaut 2 |
| Graphèmes | `grapheme_strlen("e\u{301}")` vaut 1 |
| Emoji composé | `grapheme_strlen('👨‍👩‍👧‍👦')` vaut 1 |

Les fonctions disponibles sont `grapheme_strlen`, `grapheme_substr`,
`grapheme_str_split`, `grapheme_strpos`, `grapheme_stripos`, `grapheme_strrpos`,
`grapheme_strripos`, `grapheme_strstr`, `grapheme_stristr` et `grapheme_extract`.
Les positions suivent le contrat de chaque fonction PHP : les fonctions de
découpe habituelles comptent les graphèmes ; `grapheme_extract` reçoit un décalage
en octets et peut renseigner `$next` par référence. Ses trois modes `GRAPHEME_*`
sont disponibles.

`normalizer_normalize`, `normalizer_is_normalized` et
`normalizer_get_raw_decomposition` sont disponibles, ainsi que leurs équivalents
`Normalizer::normalize`, `isNormalized` et `getRawDecomposition`. Les formes
NFC, NFD, NFKC, NFKD et NFKC_CF suivent les opérations correspondantes.

```php
#[State] public string $nom = "e\u{301} 👨‍👩‍👧‍👦";

public function body(): View
{
    return VStack(
        Input('nom')->value($this->nom),
        Text(grapheme_substr($this->nom, 0, 8)),
        Text('Caractères visibles : ' . grapheme_strlen($this->nom)),
        Text(Normalizer::normalize($this->nom)),
    );
}
```

L'extension PHP `intl` est requise pour le rendu et ViewTest.
`grapheme_str_split` nécessite PHP 8.4. Les calculs du navigateur utilisent
`Intl.Segmenter` et la normalisation Unicode JavaScript ; les tables complémentaires
de décomposition et de pliage sont générées avec ICU 77.1. Les différences de
versions Unicode/ICU peuvent affecter les caractères récemment ajoutés. Le moteur
`mb_*` conserve ses propres [tables et limites](unicode.md).

## Nombres et monnaies localisés

Créer le formateur dans un événement, puis publier la chaîne dans un `State` :

```php
$formateur = new NumberFormatter('fr_CA', NumberFormatter::CURRENCY);
$this->montant = $formateur->formatCurrency($totalCentimes / 100, 'CAD');
```

| Élément | Périmètre disponible |
| --- | --- |
| Construction | `new NumberFormatter`, `NumberFormatter::create`, `clone` |
| Styles | `DECIMAL`, `CURRENCY`, `CURRENCY_ACCOUNTING`, `PERCENT` |
| Formatage | `format`, `formatCurrency` ; types `TYPE_DEFAULT`, `TYPE_INT32`, `TYPE_INT64`, `TYPE_DOUBLE` |
| Attributs | `getAttribute`, `setAttribute` pour `GROUPING_USED`, `MIN_INTEGER_DIGITS`, `MAX_FRACTION_DIGITS`, `MIN_FRACTION_DIGITS`, `FRACTION_DIGITS`, `ROUNDING_MODE`, `SIGNIFICANT_DIGITS_USED`, `MIN_SIGNIFICANT_DIGITS`, `MAX_SIGNIFICANT_DIGITS` |
| Arrondis | `ROUND_CEILING`, `ROUND_FLOOR`, `ROUND_DOWN`, `ROUND_UP`, `ROUND_HALFEVEN`, `ROUND_HALFDOWN`, `ROUND_HALFUP` |
| Devise | `getTextAttribute`, `setTextAttribute` avec `CURRENCY_CODE` |
| API procédurale | `numfmt_create`, `numfmt_format`, `numfmt_format_currency`, `numfmt_get_attribute`, `numfmt_set_attribute`, `numfmt_get_text_attribute`, `numfmt_set_text_attribute` |

Utiliser une locale explicite comme `fr_CA`, `fr_FR` ou `en_US`. Les motifs
personnalisés, locales ICU avec options `@`, `root`/POSIX, styles hors tableau,
parsing et autres attributs/méthodes ne sont pas pris en charge. Les nombres de
chiffres entiers/significatifs acceptés vont de 1 à 21, les décimales de 0 à 100.
Un navigateur disposant des modes d'arrondi récents d'`Intl.NumberFormat` est requis.
La ponctuation, les noms et symboles suivent ses données ICU/CLDR et peuvent varier
entre versions. Le formatage ne rend pas les calculs flottants exacts : conserver
les calculs en centimes ou utiliser [BCMath](bcmath.md) selon le besoin.

L'objet reste local à l'événement ou au stockage statique du composant ; il ne
peut pas entrer dans un `State` ni être construit dans un texte réactif.
Cet adaptateur ne couvre pas toute l'extension Intl (`Collator`, `IntlDateFormatter`, etc.).

## Classes choisies à l'exécution

```php
$classe = $this->groupe ? PresentationGroupe::class : Presentation::class;
$service = new $classe();
$this->message = $service->titre($this->nom);

$classe = Compteur::class;
$suivant = $classe::suivant(...);
$this->total = $suivant();
$limite = $classe::LIMITE;
$classe::$valeur += 1;
```

Les cibles variables, issues d'un tableau ou d'une expression parenthésée sont
acceptées pour `new` et les accès statiques. Un objet peut aussi servir de cible
de classe ; `$objet::class` renvoie son nom. Les arguments nommés, callables de
méthode, constantes, visibilité, types, héritage et stockage statique par composant
conservent leurs règles existantes. Ces formes fonctionnent aussi dans `eval`.

Chaque classe doit déjà appartenir au catalogue compilé. Une référence explicite
comme `Presentation::class` inscrit une classe chargée et compilable ; fabriquer
une chaîne ne charge aucun service. Les classes internes nécessitent un adaptateur.
Les noms calculés de propriétés statiques et `instanceof $classe` restent hors
de ce périmètre.

## Chaînes nullsafe

```php
$this->contact = $service->detail($this->email)?->contact->label ?? 'Sans courriel';
```

Quand le récepteur d'un `?->` vaut `null`, toute la suite de cette chaîne de
récepteurs est ignorée : propriétés, méthodes, offsets et accès statiques. Les
arguments et noms calculés ignorés n'ont pas d'effets de bord. Le récepteur est
évalué une seule fois ; cette règle est aussi conservée dans les générateurs.

Le nullsafe ne transforme pas les autres accès en accès nullables. Si une méthode
appelée normalement renvoie `null`, la suite utilise les règles ordinaires, à moins
d'ajouter un autre `?->`. Les arguments possèdent leurs propres chaînes. Un appel
de callable termine la chaîne : `$objet?->callback()()` tente encore d'appeler le
résultat et échoue si celui-ci vaut `null`. Le nullsafe ne sert pas de cible
d'affectation ou de passage par référence et ne peut pas être combiné avec la
création d'un callable de première classe `methode(...)`.

## Erreurs et vérification

Les erreurs interceptables suivent les règles des événements : origine PHP dans
les diagnostics, `try/catch`, annulation du State en cas d'erreur non interceptée.
`echo` et les diagnostics de sortie restent dans la console.

Les suites `tests/web-functions.php`, `tests/unicode-display.php`,
`tests/dynamic-classes.php` et `tests/frontend-program.php` comparent PHP natif
à ViewTest. Leurs variantes `.mjs` vérifient Engine, et les tests navigateur
contrôlent les événements et textes réactifs sous CSP sans `unsafe-eval`.
