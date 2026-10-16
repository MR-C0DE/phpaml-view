# Niveau 9 — Fichiers et données dans le navigateur

View décrit l’interface en PHP ; PHP prépare le HTML et les instructions.
Engine exécute les interactions dans le navigateur. Les actions ci-dessous
lisent uniquement les fichiers sélectionnés par l’utilisateur et produisent
des téléchargements locaux, sans requête HTTP ni écriture sur le serveur.

Disponible avec View `0.1.0-beta.13` et Engine `0.1.0-beta.9`.

## Importer dans un State et télécharger

```php
use AML\Engine\BrowserFiles;
use AML\View\{Page, State, View};
use function AML\View\{VStack, FileInput, Text, Button};

final class ProfilPage extends Page
{
    #[State] public array $profil = ['name' => 'André'];
    #[State] public string $erreur = '';
    #[State] public bool $lecture = false;

    public function body(): View
    {
        return VStack(
            FileInput('profil', ['.json'])->onChange(
                BrowserFiles::readJson()
                    ->storeIn('profil')
                    ->errorIn('erreur')
                    ->loadingIn('lecture')
            ),
            Text(fn (): string => 'Nom : ' . ($this->profil['name'] ?? '')),
            Button('Télécharger')->onClick(
                BrowserFiles::downloadJson($this->state('profil'), 'profil.json')
                    ->errorIn('erreur')
            ),
            Text($this->erreur),
        );
    }
}
```

`readJson()` sans nom utilise le `FileInput` qui déclenche `onChange`.
Pour un bouton distinct, utilisez `readJson('profil')`, où `profil` est le
nom du champ. Les composants répétés ont chacun leur espace de noms ; deux
champs de même nom dans **un même composant** rendent la source ambiguë.
Les actions se passent directement à `onClick` / `onChange`, pas dans le corps
d’une closure compilée. Pour enchaîner une lecture et d’autres actions,
utilisez `Actions::sequence(...)`. Une erreur arrête cette séquence.
Les actions de fichiers sont refusées dans `Actions::transaction(...)`.

`$this->state('profil')` fournit la valeur actuelle au clic. Passer un tableau
PHP ordinaire à `downloadJson()` fournit sa valeur au rendu initial. Les
tableaux imbriqués peuvent aussi contenir des `StateRef` et expressions.

## Texte et CSV

```php
FileInput('notes', ['.txt'])->onChange(
    BrowserFiles::readText()->storeIn('texte')->errorIn('erreur')
);

FileInput('tableau', ['.csv'])->onChange(
    BrowserFiles::readCsv(separator: ';')->storeIn('lignes')->errorIn('erreur')
);

Button('Texte')->onClick(BrowserFiles::downloadText($this->state('texte'), 'notes.txt'));
Button('CSV')->onClick(BrowserFiles::downloadCsv($this->state('lignes'), 'tableau.csv', ';'));
```

Déclarez les états `texte` en `string`, `lignes` en `array`, `erreur` en
`string` et `lecture` en `bool`. Le CSV donne une liste de lignes, chacune
étant une liste de chaînes. La première ligne reste une ligne normale ; elle
n’est pas automatiquement transformée en noms de colonnes. Les délimiteurs
dans les champs entre guillemets, les guillemets doublés, les retours à la
ligne et le BOM UTF-8 sont pris en charge. Une syntaxe de guillemets invalide
est refusée. Les colonnes peuvent avoir des nombres de cellules différents.

L’export CSV utilise des guillemets et des fins de ligne CRLF. Les cellules
`null` et `false` deviennent vides, `true` devient `1`. Par défaut, les chaînes
qui pourraient devenir des formules de tableur sont précédées d’une apostrophe.
Pour un échange CSV brut dont les valeurs doivent rester identiques, ajoutez
`->spreadsheetSafe(false)` ; cette option conserve aussi les éventuelles formules.

Le JSON accepte les valeurs scalaires, listes et objets simples. Les objets
deviennent des tableaux associatifs côté logique Engine. Le type du State
doit correspondre au résultat : `null` ne peut pas remplacer un State `array`.
L’export JSON est indenté ; `->pretty(false)` produit une forme compacte.
Les nombres suivent la représentation numérique du navigateur : les entiers
hors de sa précision sûre sont refusés, et la notation `1.0` devient `1`.
Gardez les grands identifiants en chaînes et les montants en centimes entiers.
Les clés réservées `__proto__`, `prototype` et `constructor` sont refusées.

## Limites et erreurs

- `->maxBytes(2_097_152)` ajuste la limite d’import ou d’export : 1 Mio par
  défaut, maximum 16 Mio. La taille d’un fichier sélectionné est vérifiée avant
  lecture ; l’export est vérifié avant création du téléchargement.
- Les fichiers doivent être UTF-8. Le CSV est limité à 10 000 lignes et
  100 000 cellules ; les données JSON à 100 000 valeurs et 64 niveaux imbriqués.
- `FileInput(..., multiple: true)` permet plusieurs sélections ; `->fileAt(1)`
  lit le deuxième fichier. L’index par défaut est zéro, maximum 999.
- `->errorIn('erreur')` reçoit le message technique d’échec ; `->loadingIn('lecture')`
  indique le travail en cours. `storeIn()` est obligatoire pour une lecture.
  Les trois chemins ne doivent pas se recouvrir.
- Un fichier absent, trop grand, mal encodé, mal formé ou incompatible avec
  le type du State conserve la donnée précédente. Une nouvelle tentative
  efface l’erreur précédente. Une nouvelle lecture vers le même State annule
  l’ancienne et la suite de sa séquence ; le démontage de la racine annule les lectures en cours.
- `aml:file-success` indique la lecture réussie ou le téléchargement déclenché.
  Il ne garantit pas que l’utilisateur a enregistré le fichier. `aml:file-error`
  et le mécanisme habituel `aml:error` signalent les échecs. Les erreurs restent
  aussi accessibles dans la console ; `echo` et `var_dump` restent dans la console.
- Le filtre `accept` guide le sélecteur, sans prouver le format réel. La lecture
  valide le contenu. La validation du schéma métier reste à écrire dans
  l’application, avec les conditions et fonctions des niveaux précédents.

`FileInput(..., files: $this->state('fichiers'))` conserve seulement les
métadonnées (nom, taille, type, date). Les objets `File` et les chemins du
poste utilisateur ne sont pas sérialisés dans les états. Le contenu lu reste
local, sauf si l’application décide ensuite de l’envoyer avec une action API.

## Correspondance avec les points 106–116

| Point | Dans View / Engine frontend |
| --- | --- |
| 106. Chemins relatifs et absolus | URL pour les ressources publiques ; nom de `FileInput` pour un fichier choisi. Un nom de téléchargement est un simple nom, sans chemin. |
| 107. `__DIR__`, `__FILE__` | Utilisables dans le PHP qui prépare la page, pas dans les événements navigateur. Ils ne désignent pas le disque du visiteur. |
| 108. Existence | Vérifier les métadonnées du champ avec `count()` ; une lecture sans sélection donne une erreur. Pas de test arbitraire du disque. |
| 109. Lire et écrire | `readText/readJson/readCsv`, puis `downloadText/downloadJson/downloadCsv`. Le navigateur choisit la destination. |
| 110. Ajouter | Modifier le State (`.=`, `$lignes[] = ...`), puis télécharger la nouvelle version. Le fichier original n’est pas écrasé. |
| 111. Dossiers | Pas de création arbitraire de dossiers dans cette API. |
| 112. JSON | Import/export avec `BrowserFiles` ; conversion de chaînes en événement avec `json_decode()` et `json_encode()`. |
| 113. CSV | Import, modification des lignes et export CSV. |
| 114. Sérialisation | JSON pour les données ; `#[Persisted]` pour la conservation locale. Sérialisation des objets compilés : voir les [extensions](extensions-langage.md). |
| 115. Permissions | Sélection explicite et téléchargements contrôlés par le navigateur ; pas de `chmod` ou de droits Unix. |
| 116. Chemins sûrs | Sources isolées par composant, noms de téléchargement sans chemin, valeurs et tailles contrôlées. |

`file_get_contents`, `file_put_contents`, `mkdir`, `chmod`, `fgetcsv/fputcsv` et les autres
fonctions de fichiers PHP ne sont pas transposées automatiquement dans les
événements. Le compilateur fournit un diagnostic avec l’emplacement PHP et
l’alternative `BrowserFiles`. Elles restent utilisables dans le PHP exécuté
avant le rendu ; un appel dans `body()` s’exécute alors sur la machine PHP.

`json_encode()` et `json_decode()` sont aussi disponibles dans les événements
pour transformer des valeurs en mémoire. Ils ne lisent ni n'écrivent de fichier.
Voir le [guide JSON, expressions régulières et dates](json-regex-dates.md) pour
les types, les flags et les erreurs ; la conversion des fichiers par
`BrowserFiles` garde le contrat de State décrit plus haut.

Pour conserver volontairement les données importées entre les visites :

```php
#[State, Persisted(storage: 'local', key: 'app.profil')]
public array $profil = ['name' => 'André'];
```

`session` et `indexeddb` sont également disponibles via `Persisted`. Le contenu
est lisible par les scripts de la même origine, soumis aux quotas et effaçable
par le navigateur. Les erreurs de stockage sont signalées par
`aml:storage-error`. Ce stockage ne restaure pas la sélection du champ fichier.

Voir [l’exemple complet](../../examples/niveau-9/page.php) pour les trois formats,
la modification, l’affichage réactif et la persistance. Les imports et
téléchargements sont testés dans Chrome ; `ViewTest` seul ne simule pas le
sélecteur de fichiers ni les téléchargements natifs.

Références navigateur : [File API](https://developer.mozilla.org/en-US/docs/Web/API/File_API/Using_files_from_web_applications),
[FileReader](https://developer.mozilla.org/en-US/docs/Web/API/FileReader/readAsArrayBuffer),
[libération des URL Blob](https://developer.mozilla.org/en-US/docs/Web/API/URL/revokeObjectURL_static).
