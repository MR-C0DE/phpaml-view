# Examiner les valeurs avec print_r et var_export

Les deux fonctions font partie du [catalogue natif](compatibilite.md#catalogue-natif).
Dans les événements et les services compilés, elles écrivent dans la console du
navigateur. Avec `return: true`, elles renvoient une chaîne sans sortie console.

```php
print_r($commande);             // console ; retourne true
var_export($commande);          // console ; retourne null
$texte = print_r($commande, true);
$code = var_export($commande, true);
```

Le comportement des paramètres positionnels, nommés et dépliés, des valeurs par
défaut et des erreurs de types est partagé avec les autres fonctions natives.
Les callbacks, comme `array_map('print_r', $commandes)`, fonctionnent dans les
événements ; leur sortie reste dans la console.

## Affichage réactif

```php
Text(print_r($this->commande, true)),
Text(var_export(value: $this->commande, return: true)),
```

Les textes suivent les changements du State et restent échappés comme les autres
contenus de `Text`. Aucun HTML contenu dans une valeur examinée n'est exécuté.
Pour conserver visuellement l'indentation, appliquer `white-space: pre-wrap`
au composant avec les styles de l'application.

Dans une expression d'affichage, `return: true` doit être explicite et connu à
la compilation. Les appels directs sans ce paramètre, les arguments dépliés et
les références à ces fonctions comme callables sont refusés dans ce contexte.
Une closure ou un service pur qui appelle directement ces fonctions avec `true`
peut préparer le texte. Les callbacks restent disponibles dans les événements.

L'[exemple complet](../../examples/sorties-console/page.php) affiche les deux
représentations et écrit dans la console quand on modifie la quantité.

## Valeurs, précision et limites

- Scalaires, tableaux indexés, associatifs et imbriqués : ordre des clés et
  types numériques conservés, notamment la distinction entre `1` et `1.0`.
- Les chaînes exportées échappent apostrophes, antislashs et octets nuls avec
  la notation de PHP. Les sauts de ligne restent dans le contenu.
- Les cas locaux de `RoundingMode` sont reconnus. Les internes des closures,
  des exceptions, les objets généraux et les ressources PHP ne sont pas
  représentés : ces usages échouent explicitement. Pour une exception, examiner
  son message, son code ou sa trace.
- `print_r` utilise `precision` et `var_export` utilise `serialize_precision`
  capturées lors de la compilation. Les réglages pris en charge sont `-1` et
  les valeurs de 1 à 100. Les valeurs par défaut PHP sont 14 et -1.
- Maximum de 1 Mio par sortie, 10 000 éléments par tableau et 64 niveaux
  d'imbrication ; le budget de calcul natif s'applique aussi. Une sortie trop
  volumineuse est refusée avant son écriture dans la console.
- Les erreurs conservent leur origine PHP et sont interceptables. Une erreur
  non interceptée annule les changements de State de l'événement, mais pas les
  sorties déjà écrites par les appels précédents.

Dans le PHP qui prépare la page, les sorties directes gardent le comportement
PHP natif : seules les instructions des événements sont routées vers la console
du navigateur. Utiliser le paramètre `true` pour préparer du contenu dans `body()`.

`var_export` produit une représentation PHP ; la prise en charge de `eval()`
est un chantier distinct, pas une conséquence de cet ajout.

Références : [print_r](https://www.php.net/manual/fr/function.print-r.php),
[var_export](https://www.php.net/manual/fr/function.var-export.php).
