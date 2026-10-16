# Les sept extensions de View et Engine

Les événements et les services synchrones utilisent le même catalogue fermé dans
ViewTest et dans le navigateur. Les ajouts ci-dessous sont vérifiés contre PHP
natif par `tests/language-completion.php`, puis exécutés dans Engine et Chrome.
Les syntaxes PHP 8.3 et 8.4 sont chargées seulement sur une version compatible.

| Volet | Ajouts |
| --- | --- |
| 1. Syntaxe | Instructions sans accolades, `instanceof $classe`, propriétés statiques calculées (`Classe::${$nom}`), initialisations dynamiques de variables locales statiques à partir de PHP 8.3. |
| 2. Fonctions natives | `get_debug_type`, `settype`, `get_defined_vars`, `compact`, `extract` et ses modes dont `EXTR_REFS`, `rand`, `mt_rand`, `srand`, `mt_srand`, `getrandmax`, `mt_getrandmax`. |
| 3. Expressions régulières | `preg_replace_callback_array`, groupes répétés, assertions avant/arrière, références numériques et nommées aux captures, groupes atomiques, quantificateurs possessifs et correspondances vides. |
| 4. Dates, Intl et validation | Dates textuelles usuelles et relatives, formats explicites `D/l/S/z`, intervalles étendus, périodes ISO `R<n>/<début>/<durée>`, différences entre fuseaux, `Collator`, `IntlDateFormatter`, multiplicateur et préfixes/suffixes de `NumberFormatter`, validation d’adresses électroniques Unicode. |
| 5. Références | Déstructuration avec `&`, y compris dans `foreach`, générateurs produisant des références, liaisons aux propriétés de services, références dans leurs tableaux et cycles locaux. |
| 6. Objets | Hooks et visibilité asymétrique des propriétés de services PHP 8.4, clonage des closures, réflexion sur classes/méthodes/propriétés, sérialisation des objets compilés, `SplDoublyLinkedList`, `SplStack`, `SplQueue`. |
| 7. `eval` | Variables variables, déclarations de classes nommées concrètes et d’enums, namespaces successifs ou entre accolades, imports de classes/fonctions et imports groupés. |

## Exemple

```php
public function calculer(): void
{
    $ligne = ['prix' => 12, 'quantite' => 2];
    extract($ligne, EXTR_REFS);
    $quantite = 3;

    $messages = preg_replace_callback_array([
        '/(produit)+/i' => fn (array $m) => strtoupper($m[0]),
    ], 'produit disponible');

    $this->resume = $messages . ' : ' . ($prix * $quantite);
    echo $this->resume; // Console du navigateur.
}
```

Une propriété liée par référence garde son contrat de type. Un alias partagé
avec une propriété `int` refuse une chaîne incompatible. Une propriété readonly
ou un setter inaccessible refuse l’acquisition d’une référence. Les `State`
restent des valeurs sérialisables ; une référence cyclique locale ne peut pas
être enregistrée dans un `State`. Une erreur non interceptée restaure les State.
Les sorties console et les mutations de services déjà effectuées restent des
effets de bord, comme pour les événements existants.

## Périmètre exact

Ces ajouts ne constituent pas une implémentation intégrale de PHP, PCRE ou ICU.

- **Regex** : les motifs restent limités à 4 096 octets, 32 captures et les sujets
  à 16 384 octets. Le moteur à retour arrière est borné à 200 000 étapes par
  recherche, 256 niveaux et 1 000 répétitions. Une limite atteinte produit
  `false`/`null` et met à jour `preg_last_error()`. Récursion PCRE, sous-routines,
  conditions, modes inline, classes POSIX et certaines séquences PCRE restent
  refusés. Les limites ne sont pas celles du JIT PCRE du serveur.
- **Dates** : le parsing reste explicite. Les abréviations arbitraires de fuseaux
  et les différences locales traversant un changement d’offset avec des heures
  différentes restent refusées ; convertir en UTC pour ce calcul. Une période
  doit avancer et ne peut pas dépasser 1 000 dates.
- **Intl** : `Collator` adapte comparaison et tri aux fonctions Intl du navigateur.
  `IntlDateFormatter` couvre le calendrier grégorien et les lettres de motif
  `y M L d E H h m s a G z S`. `getPattern()` nécessite un motif explicite. La version ICU du navigateur peut influencer le
  texte localisé. `NumberFormatter` garde les styles décimal, devise, pourcentage
  et devise comptable ; les motifs ICU arbitraires et les nombres en lettres ne
  sont pas ajoutés.
- **Références** : `yield from` dans un générateur déclaré par référence reste
  refusé, comme en PHP. Les itérateurs utilisateur ordinaires n’offrent pas de
  parcours par référence. Une propriété `State` entière ne peut pas être liée
  à gauche de `=&`, ni intégrée comme cellule de référence dans un tableau.
  Les graphes locaux restent soumis aux budgets de profondeur lors d’une copie,
  d’un affichage ou d’une conversion.
- **Propriétés** : les hooks compilés depuis une classe PHP 8.4 et la visibilité
  en écriture sont disponibles. Les déclarations de hooks dans les classes
  anonymes ou dans `eval`, ainsi que la référence à une propriété virtuelle ou
  munie d’un setter, restent hors du périmètre.
- **Réflexion** : `ReflectionClass`, `ReflectionObject`, `ReflectionMethod` et
  `ReflectionProperty` inspectent uniquement le catalogue du composant. Les
  signatures autorisées figurent dans `reflection-definitions.json`. Ce n’est pas
  une réflexion du serveur ; `ReflectionFunction`, les attributs et les arbres
  complets de types ne sont pas implémentés.
- **Sérialisation** : `serialize` et `unserialize` utilisent le format PHP pour
  scalaires, tableaux sans cellules de référence et objets compilés. Les méthodes
  `__serialize`/`__unserialize` et `__sleep`/`__wakeup`, les champs privés/protégés
  et les identités répétées ou cycliques d’objets sont pris en charge. Les cellules
  `R:` des tableaux, classes anonymes, closures et objets internes adaptés restent
  refusés. Aucune classe inconnue n’est chargée : une classe absente ou interdite
  par `allowed_classes` lève une erreur au lieu de produire un objet incomplet.
  La profondeur est limitée à 64, le total à 10 000 valeurs, et
  `serialize_precision` doit valoir `-1`.
- **`eval`** : les classes externes utilisées par un texte dynamique doivent déjà
  être compilées dans le composant. Aucun autoload, `require`, accès au serveur,
  superglobale ou `global` n’est ajouté. Les interfaces, traits, classes abstraites
  et constantes globales déclarés dans le texte restent hors du périmètre. Les
  classes doivent être déclarées avant leur première utilisation ; les fonctions
  ordinaires au niveau supérieur sont pré-déclarées. Les alias ne transforment
  pas les noms contenus dans des chaînes dynamiques.

Les fonctions qui dépendent d’une portée, du hasard ou d’effets de bord
s’exécutent dans un événement. Un texte réactif affiche le résultat placé dans
un `State`. Les limites communes et le catalogue natif généré sont dans la
[matrice de compatibilité](compatibilite.md).
