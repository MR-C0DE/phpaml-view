# Dates avancées dans le navigateur

Les événements View et Engine peuvent analyser une date de formulaire, calculer une durée et parcourir un calendrier. `DateTime`, `DateTimeImmutable`, `DateInterval` et `DatePeriod` restent des objets locaux ou statiques du composant. Le `State` reçoit leurs chaînes, nombres ou tableaux de résultats ; un objet caché dans un tableau State est également refusé.

## Lire un format explicite

```php
$zone = new DateTimeZone('America/Toronto');
$date = DateTimeImmutable::createFromFormat('!Y-m-d', $this->arrivee, $zone);
$diagnostic = DateTimeImmutable::getLastErrors();

if ($date === false || $diagnostic !== false) {
    $this->message = 'Saisir une date réelle au format AAAA-MM-JJ.';
    return;
}

$this->message = $date->format('d/m/Y');
```

`createFromFormat()` est disponible sur `DateTime` et `DateTimeImmutable`. Les fonctions `date_create_from_format()` et `date_create_immutable_from_format()` donnent les mêmes résultats. `getLastErrors()` et `date_get_last_errors()` donnent `false` sans diagnostic, sinon `warning_count`, `warnings`, `error_count` et `errors`. Les positions sont des offsets UTF-8 en octets. Les diagnostics détaillés sont ceux de la lecture par format ; ce module n’implémente pas tous les diagnostics du parseur libre timelib.

Une date telle que `2026-02-31` peut produire une date normalisée et un avertissement. Pour valider une saisie stricte, vérifier les avertissements aussi bien que les erreurs, comme dans l’exemple.

| Tokens acceptés | Usage |
| --- | --- |
| `Y`, `y` | Année numérique ; `y` suit la fenêtre PHP 1970–2069 |
| `m`, `n`, `d`, `j` | Mois et jour numériques |
| `M`, `F` | Noms anglais de mois, courts ou complets |
| `D`, `l`, `S`, `z` | Jour anglais, suffixe ordinal et jour de l’année après une année |
| `H`, `G`, `h`, `g`, `i`, `s` | Heure, minutes et secondes ; heures sur 12 heures avec `a`/`A` après l’heure |
| `u`, `v` | Microsecondes, millisecondes |
| `e`, `O`, `P`, `p` | Fuseau IANA, UTC/GMT/Z ou décalage numérique |
| `U` | Timestamp en secondes, éventuellement suivi de `.u` |
| `!` | Réinitialiser les champs à la base `1970-01-01 00:00:00` |
| `\|` | Réinitialiser les champs non fournis |
| `+` | Autoriser une fin de saisie supplémentaire avec avertissement |
| espace, `#`, `; : / . , - ( )` | Séparateurs PHP |
| `\` suivi d’un caractère | Littéral, par exemple `\T` dans une date ISO |

Sans `!` ou `|`, les champs absents utilisent la date et l’heure courantes du navigateur. Préférer `!` pour les formulaires. Les abréviations de fuseau telles que `EST`, les jokers `?/*` et les autres tokens non listés restent refusés explicitement. Les formats et saisies sont limités à 4 096 caractères ; les chaînes doivent rester UTF-8 valides. Les années supportées vont de **0001 à 9999**.

## Intervalles, addition et soustraction

```php
$duree = new DateInterval('P3DT2H');
$depart = new DateTimeImmutable('2026-03-07 12:00:00', $zone);
$fin = $depart->add($duree);
$veille = $depart->sub(new DateInterval('P1D'));

$this->message = $fin->format('Y-m-d H:i P');
```

Le constructeur accepte la forme ISO `PnYnMnDTnHnMnS`, ses parties optionnelles, et les semaines `PnW`. Exemples : `P1Y2M`, `P3D`, `PT90M`, `P2W`. Les formes ISO avec séparateurs de dates, les fractions dans la chaîne ISO et `createFromDateString()` ne sont pas ajoutées ici. Une syntaxe ISO malformée lève l’exception native correspondante ; une limite frontend produit une `ValueError` explicite.

Les propriétés `y`, `m`, `d`, `h`, `i`, `s`, `f`, `invert` et `days` sont lisibles. Les composantes entières et `invert` sont modifiables avec un entier entre −1 000 000 et 1 000 000. La fraction `f` se modifie par un nombre de `0` inclus à `1` exclu, à la microseconde. `days` vaut `false` pour un intervalle construit et le total de jours pour un résultat de `diff()` ; une écriture sur `days` est ignorée comme en PHP. Les références vers ces propriétés et leur suppression sont refusées. `from_string` est une métadonnée d’inspection PHP, pas un champ applicatif modifiable.

`format()` comprend `%Y/%y`, `%M/%m`, `%D/%d`, `%H/%h`, `%I/%i`, `%S/%s`, `%F/%f`, `%R/%r`, `%a` et `%%`. `clone`, `get_object_vars()`, la conversion `(array)`, `json_encode()` et `var_dump()` préservent les composantes et la distinction de `f` comme `float`.

`add()` et `sub()` respectent la mutabilité de la date : `DateTime` modifie son instance, `DateTimeImmutable` renvoie une nouvelle date. Les jours/mois/années sont calendaires ; les heures/minutes/secondes sont écoulées. **`P1D` et `PT24H` peuvent donc donner des heures différentes lors d’un changement d’heure.** Les dépassements de mois suivent PHP : ajouter un mois au 31 janvier peut atteindre mars. Les fonctions `date_add()`, `date_sub()` et `date_interval_format()` sont également disponibles.

## Mesurer un écart

```php
$fin = $depart->add(new DateInterval('P3D'));
$ecart = $depart->diff($fin);
$this->nuits = $ecart->days;
$this->message = $ecart->format('%a jours');
```

`diff()` et `date_diff()` conservent les composantes calendaires, les microsecondes, `invert` et le paramètre `absolute`. L’inversion ne se réduit pas à changer un signe : les emprunts de fin de mois suivent PHP dans les deux directions.

Les deux dates doivent utiliser le **même nom de fuseau**. Si leurs offsets diffèrent à cause d’un changement d’heure, leurs heures locales, minutes, secondes et microsecondes doivent être identiques. Les séjours de plusieurs nuits, y compris à Toronto lors du passage à l’heure d’été, sont ainsi couverts. Pour mesurer un écart intrajournalier à travers une transition ou entre deux fuseaux, convertir d’abord les deux dates en UTC avec `setTimezone()` ; cette conversion exprime alors un temps écoulé.

## Comparer des dates

Les comparaisons entre `DateTime` et `DateTimeImmutable` portent sur l'instant,
microsecondes comprises, même entre deux fuseaux ou entre les deux classes.
`===` conserve l'identité de l'objet. Les tableaux de dates peuvent être comparés,
recherchés avec `in_array`/`array_search`, réduits avec `min`/`max` et triés ou
dédupliqués avec `SORT_REGULAR`. Pour `DateInterval`, `DatePeriod`, `DateTimeZone`
et `InternalIterator`, utiliser `===` ou comparer explicitement leurs champs :
les comparaisons non strictes entre objets sont refusées par une `TypeError`.

## Parcourir une période

```php
$fin = $depart->add(new DateInterval('P3D'));
$periode = new DatePeriod($depart, new DateInterval('P1D'), $fin);
$jours = [];

foreach ($periode as $jour) {
    $jours[] = $jour->format('Y-m-d');
}
$this->jours = $jours;
```

Constructeurs reconnus :

```php
new DatePeriod($debut, $intervalle, $fin, $options);
new DatePeriod($debut, $intervalle, $recurrences, $options);
```

Le début est inclus et la fin exclue par défaut. Les flags `DatePeriod::EXCLUDE_START_DATE` et `DatePeriod::INCLUDE_END_DATE` peuvent être combinés. Une récurrence compte les répétitions après le début ; `getRecurrences()` et la propriété interne `recurrences` ont donc les valeurs distinctes prévues par PHP.

`getStartDate()`, `getEndDate()`, `getDateInterval()`, `getRecurrences()`, `getIterator()`, les propriétés publiques en lecture seule, `clone`, JSON et l’inspection sont disponibles. Les dates renvoyées gardent la classe mutable ou immutable du début. Le parcours fonctionne avec `foreach`, `...`, `iterator_count()` et `iterator_to_array()` ; les objets obtenus restent locaux avant conversion pour le State.

`getIterator()` renvoie un `InternalIterator` avec `rewind()`, `valid()`, `current()`, `key()` et `next()`. Comme en PHP, après la fin `valid()` est faux mais `current()` expose la date suivante et `key()` son index. `InternalIterator` ne peut pas être construit ni cloné directement. Les copies renvoyées par les getters ne modifient pas la définition de la période. Les propriétés `start`, `end` et `interval` sont, elles, des objets d’inspection mis en cache comme en PHP : leur identité reste stable. Modifier une variable locale obtenue depuis ces propriétés affecte cette copie d’inspection, sans modifier le calendrier ; une écriture imbriquée directe telle que `$periode->interval->d = 2` est refusée. Une copie `clone` conserve le partage de ces objets d’inspection et dispose de son propre parcours.

L’intervalle doit avancer le temps. Les récurrences et le parcours sont limités à 1 000, en plus des budgets communs de boucles et d’objets ; les options ne permettent pas de dépasser le budget de parcours. Les formes textuelles `R…/…`, `createFromISO8601String()`, les périodes inversées et l’héritage des classes internes restent refusés.

## Vérification

`tests/date-advanced.php` compare PHP natif et ViewTest. `tests/date-advanced.mjs` rejoue les mêmes instructions dans Engine et utilise le compilateur `eval` source. `phpaml-engine/tests/browser/date-advanced.spec.mjs` contient les cas Chrome sous CSP stricte. Les cas couvrent les diagnostics UTF-8, les fins de mois dans les deux directions, les fractions, DST, la copie des périodes, les erreurs et le refus récursif des objets dans State.

Les références sémantiques sont les manuels PHP de [createFromFormat](https://www.php.net/manual/fr/datetimeimmutable.createfromformat.php), [DateInterval](https://www.php.net/manual/fr/dateinterval.construct.php), [diff](https://www.php.net/manual/fr/datetime.diff.php) et [DatePeriod](https://www.php.net/manual/fr/dateperiod.construct.php). Le [programme de réservation](../../examples/reservation/service.php) assemble ces opérations avec les autres fonctions frontend.

Les périodes ISO et les nouveaux formats relatifs sont décrits dans les [extensions du langage](extensions-langage.md).
