# eval() dans les événements frontend

`eval()` est disponible dans les événements et les services synchrones compilés.
Le code reste dans le navigateur : aucune requête n'envoie ce texte à PHP pour
l'exécuter. Engine interprète les instructions de son langage frontend.

```php
$x = 4;
$resultat = eval('$x += 3; $nouveau = "André"; return $x * 2;');
$this->message = "$nouveau : $x / $resultat"; // André : 7 / 14
```

Une chaîne connue à la compilation devient directement des instructions.
Une chaîne construite pendant l'événement, notamment un `State`, passe par un
analyseur PHP optionnel, chargé avant le premier événement qui en a besoin :

```php
try {
    $resultat = eval($this->code);
    $this->message = var_export($resultat, true);
} catch (\Throwable $erreur) {
    $this->message = $erreur->getMessage();
}
```

L'[exemple complet](../../examples/eval/page.php) propose un champ de code,
un compteur et un résultat réactif. `Text` échappe le résultat. `echo`,
`var_dump()`, `print_r()` et `var_export()` sans retour restent dans la console.

## Portée et comportement

- Les variables locales existantes, leurs références et les `State` accessibles
  sont partagés avec l'appelant. Les nouvelles variables restent disponibles
  après l'appel. Les paramètres et captures gardent leur portée habituelle.
- `return` termine seulement le code évalué. Sans retour, le résultat est `null`.
  Une chaîne exactement vide produit `false`, comme PHP.
- Les fonctions déclarées sont globales dans le registre du composant monté.
  Elles restent disponibles lors des événements suivants jusqu'au démontage.
  Elles ne sont pas enregistrées dans le stockage persistant. Une redéclaration
  échoue ; une déclaration dans une condition ne devient active que si cette
  branche s'exécute. Les closures peuvent être retournées et utilisées localement.
- Les imports et le namespace du fichier appelant ne sont pas hérités.
  Le code évalué utilise les conversions faibles par défaut ; il peut commencer
  par `declare(strict_types=1);` pour demander les contrats stricts.
- Les erreurs de syntaxe sont des `ParseError`, déclenchées lors de l'appel.
  Les erreurs de type et les exceptions restent interceptables. Les traces
  indiquent le fichier appelant et la ligne dans `eval()'d code`.
- Une erreur non interceptée annule les modifications de `State` de l'événement.
  Les sorties déjà écrites et les fonctions déjà déclarées restent présentes.
  Une erreur interceptée garde les changements réalisés avant le `catch`.

## Langage disponible et limites

Les variables, opérations prises en charge par Engine, chaînes UTF-8, tableaux,
conditions, boucles bornées, fonctions, paramètres typés, valeurs par défaut,
arguments nommés, références de paramètres/captures, closures, récursivité et
`try/catch/finally` utilisent les mêmes instructions que les événements.
Utiliser des accolades pour les branches et les boucles pour rester compatible
avec le compilateur PHP de View et ViewTest.

Les affectations binaires `<<=`, `>>=`, `&=`, `|=` et `^=` sont disponibles
dans le code compilé comme dans les chaînes dynamiques. Elles conservent les
références et évaluent une seule fois la cible (par exemple `$tableau[$i++]`).

Les fonctions natives du catalogue Math/String/Array, Unicode, BCMath et les
sorties console sont accessibles. Une fonction de service déjà compilée peut
être appelée par son nom complet ou par un callable local transmis à `eval()`.
Le code dynamique ne charge pas de nouveau service PHP depuis le disque.

Les constantes dynamiques comprennent `PHP_EOL` et les constantes natives
`M_*`, `PHP_ROUND_*`, `SORT_*`, `CASE_*`, `COUNT_*`, `ARRAY_FILTER_*`, `STR_PAD_*`,
`ENT_*`, `HTML_*`, `MB_CASE_*`, `JSON_*`, `PREG_*`, `DATE_*`, `FILTER_*`.
`RoundingMode` et `__LINE__` sont disponibles.
Pour une constante applicative, transmettre sa valeur dans une variable locale.
Les exceptions natives peuvent être construites et examinées ; une exception
personnalisée déjà compilée peut être transmise par un service/callable.

Les classes concrètes nommées, enums, namespaces, imports et variables variables
sont décrits dans les [extensions du langage](extensions-langage.md).

Ce support ne constitue pas une machine virtuelle PHP générale. Les déclarations
d’interfaces, traits, classes abstraites et `const` au niveau global dans le texte,
les classes externes absentes du catalogue frontend, les globals/superglobales, les inclusions, le système de fichiers du serveur,
les processus et les fibres PHP ne sont pas pris en charge.
Les [générateurs](generateurs.md) sont disponibles à l'intérieur des fonctions ;
le programme évalué lui-même ne peut pas être un générateur. La
[matrice de compatibilité](compatibilite.md) précise les formes de références
et les fonctions disponibles. Les usages non pris en charge produisent un diagnostic explicite.
Les variables non définies restent des erreurs Engine ; `isset()` et `??`
permettent une lecture tolérante.

Le texte source est limité à 64 Kio. L'analyse est bornée (10 000 jetons/nœuds,
profondeur 128, imbrication de compilation des `eval` statiques 32).
Les appels partagent les budgets Engine : profondeur 128, 10 000 appels et
10 000 itérations cumulées, 1 000 itérations par boucle et 256 définitions
par registre. Les limites natives sur les chaînes, tableaux et calculs restent
applicables. Les entiers doivent rester dans la plage exacte du navigateur.

`eval()` est refusé dans un texte réactif calculé : exécuter le code dans
l'événement, ranger son résultat dans un `State`, puis afficher ce `State`.

## Installation de l'analyseur optionnel

Les applications générées ou remises à jour par le CLI servent automatiquement
le fichier versionné `engine-eval-0.1.0-beta.9.min.js` sous `/_aml/`.
Une intégration personnalisée doit servir `EngineRuntime::evalAssetPath()` à
côté du script Engine, sous le nom `EngineRuntime::evalAssetFilename()`.
Pour Engine intégré en ligne, le chemin par défaut est
`/_aml/engine-eval-0.1.0-beta.9.min.js` ; l'attribut `data-aml-eval-src` du script
permet de choisir une autre URL de même origine.

L'analyseur est téléchargé une seule fois par page, avant les mutations de
l'événement. Si son chargement échoue, l'événement échoue sans modifier les
`State` et un nouvel essai est possible. Une page utilisant uniquement des
`eval()` statiques ne télécharge pas ce module. Le module n'utilise ni `eval`
JavaScript ni `new Function` et fonctionne avec une CSP `script-src 'self'`.
Si le script Engine utilise un nonce CSP, ce nonce est transmis au module.
Il contient `php-parser` 3.7.0, distribué sous licence BSD-3-Clause.

### Objets de service

Les [objets de service compilés](objets.md) sont maintenant accessibles depuis `eval()` : propriétés, méthodes, instanciation des classes du catalogue et scope objet. Les déclarations de classes nommées restent exclues. Les [classes anonymes](classes-anonymes-intersections.md) sont prises en charge, avec une nouvelle identité à chaque évaluation.
