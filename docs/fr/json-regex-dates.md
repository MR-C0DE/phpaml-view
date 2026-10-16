# JSON, expressions régulières et dates dans les événements

Ces fonctions s’exécutent dans le navigateur. Elles ne lisent aucun fichier et ne font aucun appel serveur. Les objets restent dans les variables locales ou les propriétés statiques du composant ; placer leur résultat scalaire ou un tableau dans un `State` pour l’affichage.

```php
public function traiter(): void
{
    $commande = json_decode($this->json, associative: true, flags: JSON_THROW_ON_ERROR);
    $code = $commande['code'];

    if (!preg_match('/^[A-Z]{2}[0-9]{3}$/', $code)) {
        throw new InvalidArgumentException('Code invalide');
    }

    $date = new DateTimeImmutable($commande['date'], new DateTimeZone('UTC'));
    $libelle = preg_replace('/\s+/u', ' ', trim($commande['libelle']));
    $this->message = $code . ' · ' . $libelle . ' · ' . $date->format('d/m/Y');
}
```

## JSON

Disponibles : `json_encode()`, `json_decode()`, `json_last_error()`, `json_last_error_msg()` et `json_validate()` lorsque PHP fournit cette fonction (PHP 8.3+ pour ViewTest).

- `json_decode()` conserve la distinction `int`/`float`, les tableaux, les clés, les booléens et `null`. Sans `associative: true`, les objets deviennent de vrais objets frontend `stdClass`, avec lecture, écriture, `isset()`, `empty()` et `unset()` des propriétés. Ils ne sont pas des tableaux déguisés.
- `json_encode()` gère les tableaux indexés/associatifs, les propriétés publiques des objets compilés, les `stdClass`, les dates et les enums. Une enum adossée donne sa valeur ; une enum sans valeur donne `JSON_ERROR_NON_BACKED_ENUM`.
- Une classe ou une enum compilée peut implémenter `JsonSerializable`. Engine interprète sa méthode `jsonSerialize()` et encode son retour, y compris un scalaire, `null`, un tableau ou un autre objet. Les exceptions de la méthode se propagent ; les effets déjà réalisés suivent les règles habituelles des événements.
- Si la méthode renvoie `$this`, l'encodage continue avec ses propriétés publiques, comme PHP. Les cycles d'objets suivent `JSON_ERROR_RECURSION` et les options d'erreur. Ces méthodes s'utilisent dans un événement ; elles ne peuvent pas introduire des effets de bord dans un texte réactif.
- Les options d’échappement `JSON_HEX_*`, Unicode/slashes non échappés, `JSON_PRETTY_PRINT`, `JSON_FORCE_OBJECT`, `JSON_NUMERIC_CHECK`, `JSON_PRESERVE_ZERO_FRACTION`, `JSON_PARTIAL_OUTPUT_ON_ERROR`, `JSON_OBJECT_AS_ARRAY`, `JSON_BIGINT_AS_STRING` et `JSON_THROW_ON_ERROR` sont prises en compte.
- `JSON_THROW_ON_ERROR` lance une `JsonException` et conserve la dernière erreur précédente. Sans ce flag, les erreurs de JSON mettent à jour `json_last_error()` ; l’état d’erreur est propre à l’événement. `JSON_PARTIAL_OUTPUT_ON_ERROR` a priorité sur le lancement d’exception.
- `json_validate()` vérifie la syntaxe, y compris un entier que le runtime ne pourrait pas représenter exactement. Il ne transforme pas ce nombre en `State`.

Les entiers PHP compris entre `2^53` et la borne des entiers PHP 64 bits sont refusés au décodage pour éviter une perte silencieuse de précision. Les grands identifiants doivent être des chaînes JSON. `JSON_BIGINT_AS_STRING` suit PHP et transforme en chaîne les entiers dépassant la plage PHP 64 bits, pas tous les entiers dépassant `2^53`.

Les limites frontend restent applicables : chaînes/résultat JSON de 1 Mio, profondeur 64, nombre de valeurs borné. Les chaînes doivent être UTF-8 valides. L’encodage exige `serialize_precision=-1`. Les clés de tableaux réservées au runtime sont refusées lors du décodage associatif. Les appels de `jsonSerialize()` partagent les budgets des fonctions ; une chaîne de sérialiseurs ne peut pas dépasser 128 objets.

```php
final class Total implements JsonSerializable
{
    public function __construct(private int $centimes) {}

    public function jsonSerialize(): array
    {
        return ['centimes' => $this->centimes, 'devise' => 'CAD'];
    }
}

// Dans un événement : l'objet reste local, le State reçoit la chaîne.
$this->export = json_encode(new Total(3750), JSON_THROW_ON_ERROR);
```

Voir le [service de réservation](../../examples/reservation/service.php) pour
un export combinant ces méthodes avec des dates et un calendrier de nuits.

## Expressions régulières

Disponibles : `preg_match()`, `preg_match_all()`, `preg_replace()`, `preg_replace_callback()`, `preg_filter()`, `preg_split()`, `preg_grep()`, `preg_quote()`, `preg_last_error()` et `preg_last_error_msg()`.

Les captures numériques et nommées, les paramètres de sortie par référence, les offsets **en octets**, les groupes optionnels, les flags `PREG_*`, les tableaux de motifs/sujets, les limites de remplacement et les callbacks compilés sont pris en charge. Les modificateurs disponibles sont `i`, `m`, `s`, `u`, `x`, `A` et `D`. En mode `u`, les classes Unicode et les frontières de mots sont traduites selon PHP. Une opération par octets qui produit une chaîne UTF-8 invalide est refusée.

Il s’agit d’un sous-ensemble borné de PCRE, pas du moteur PCRE complet. Pour préserver le navigateur, les motifs sont limités à 4 096 octets, les sujets à 16 384 octets, les captures à 32 et les correspondances à 10 000. Un motif peut contenir une seule répétition non bornée. Les répétitions bornées, options et alternatives ont également un budget.

Les groupes répétés, backreferences, lookarounds, groupes atomiques, quantificateurs
possessifs et `preg_replace_callback_array()` sont disponibles dans un moteur
borné. Récursion, conditions, modes inline, classes POSIX et certaines séquences
PCRE restent refusés. Les limites et la gestion des erreurs sont précisées dans
les [extensions du langage](extensions-langage.md).

## Dates et fuseaux

Classes disponibles : `DateTime`, `DateTimeImmutable`, `DateTimeZone`, `DateInterval` et `DatePeriod`, avec contrats `DateTimeInterface`. Les instances sont opaques et clonables. L’héritage de ces classes internes par une classe utilisateur est explicitement refusé. Les paramètres nommés, les références de méthodes (`$date->format(...)`), les méthodes via callable et les opérations dans `eval()` utilisent le même interpréteur.

Méthodes disponibles : constructeur, `format()`, `getTimestamp()`, `getOffset()`, `getTimezone()`, `setTimestamp()`, `setTimezone()`, `modify()`, `setDate()`, `setTime()`, `createFromInterface()`, `createFromMutable()` et `createFromImmutable()` selon la classe. `DateTimeZone` expose son constructeur, `getName()` et `getOffset()`. Les constantes de format comme `DateTime::ATOM` sont disponibles.

`DateTime` modifie son instance ; `DateTimeImmutable` renvoie une nouvelle instance. `var_dump()` affiche la date, le type du fuseau et son nom dans la console. `get_object_vars()` ne révèle pas ces données internes, conformément à PHP.

Fonctions disponibles : `time()`, `microtime()`, `date()`, `gmdate()`, `strtotime()`, `mktime()`, `gmmktime()`, `checkdate()`, `getdate()`, `date_default_timezone_get/set()`, `date_create()`, `date_create_immutable()`, `date_format()`, `date_timestamp_get/set()`, `date_modify()`, `date_timezone_get/set()`, `timezone_open()`, `timezone_name_get()` et `timezone_offset_get()`.

Le fuseau PHP est capturé lors de la compilation. `date_default_timezone_set()` le change pour l’événement courant. `time()`, `microtime()` et les dates « maintenant » lisent l’horloge du navigateur ; leur précision effective dépend de celui-ci. Les identifiants IANA tels que `America/Toronto` utilisent les données de fuseaux du navigateur, y compris les changements d’heure. Les résultats historiques peuvent dépendre de la version de cette base.

Formats de saisie acceptés : `YYYY-MM-DD`, date/heure ISO avec secondes et jusqu’à six chiffres de microsecondes, décalage numérique ou fuseau IANA, `@timestamp`, `now`, `today`, `midnight`, `noon`, `tomorrow`, `yesterday` et ajustements signés en années, mois, semaines, jours, heures, minutes ou secondes. Les années calendaires vont de 0001 à 9999. Pour une heure ambiguë lors d’un changement de fuseau, préférer un décalage explicite dans la saisie.

Les tokens usuels de `format()` sont disponibles, notamment `Y-m-d H:i:s.u`, jours/mois, semaine ISO, `c`, `r`, `U`, `e`, `O`, `P` et `I`. Le token `T` est limité à UTC/GMT, aux décalages numériques et aux fuseaux Toronto, New York, Chicago, Denver, Los Angeles, Halifax, Anchorage, Honolulu, Winnipeg et Vancouver ; utiliser `e` ou `P` pour les autres fuseaux. Les abréviations de fuseau en entrée, hors UTC/GMT/Z, sont refusées : utiliser un identifiant IANA ou un décalage numérique.

Le [guide des dates avancées](dates-avancees.md) complète ces fonctions avec
`createFromFormat()`, `getLastErrors()`, `DateInterval`, `DatePeriod`, `diff()`,
`add()/sub()` et leurs équivalents procéduraux. Il précise les formats et les
opérations calendaires reconnus.

Restent à ajouter le parsing libre complet de timelib, les fonctions calendaires
sans adaptateur et Intl. Une méthode absente ne se transforme pas en appel PHP
serveur. Les fonctions de date doivent être calculées dans un événement puis
exposées via `State`, pas exécutées dans une expression d’affichage réactive.

## Vérification

`tests/json-regex-dates.php` compare les cas pris en charge à PHP natif, puis les rejoue avec `ViewTest`. `tests/json-regex-dates.mjs` exécute les mêmes instructions dans Engine. `phpaml-engine/tests/browser/json-regex-dates.spec.mjs` rejoue ces cas dans Chrome sous une politique CSP interdisant l’évaluation de code JavaScript.

Les suites `json-serializable` et `date-advanced` complètent ces comparaisons
PHP/ViewTest/Engine et navigateur ; `reservation-program` teste leur intégration
dans une page avec des champs et des sorties réactives.

Les signatures des fonctions viennent de `phpaml-engine/scripts/export-builtins.php`. Les descripteurs des classes de dates sont régénérés avec `phpaml-engine/scripts/export-date-definitions.php` ; les deux scripts inspectent uniquement les métadonnées PHP internes.
