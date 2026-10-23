# Migration vers View 0.1.0-beta.14

Les appels existants aux composants et les classes qui étendent `Page` ou
`Component` restent pris en charge. Engine `^0.1.0-beta.10` est requis.

## Sous-classes personnalisées d'Element

Les paramètres de plusieurs méthodes publiques acceptent désormais les styles
structurés ou des valeurs réactives. PHP impose qu'une méthode redéfinie par une
sous-classe accepte au moins les mêmes types que sa méthode parente. Une ancienne
redéfinition plus restrictive provoque donc une erreur de déclaration PHP lors
du chargement de cette sous-classe. Cela constitue une migration de signature
pour les extensions directes d'`Element` qui redéfinissent ces méthodes.

| Méthodes redéfinies | Signature de paramètre à reprendre |
|---|---|
| `style()` | `string\|Style\|array $name, mixed $value = null` |
| `padding()`, `spacing()`, `gap()`, `fontSize()`, `letterSpacing()` | `mixed $pixels` |
| `alignment()`, `textAlign()` | `mixed $alignment` |
| `fontWeight()` | `mixed $weight` |
| `lineHeight()` | `mixed $height` |
| `textColor()` | `mixed $color` |
| `updates()` | `string\|StateRef $property, int\|float $by = 1` |
| `increments()`, `decrements()` | `string\|StateRef $state, int\|float $by = 1` |
| `sets()` | `string\|StateRef $state, mixed $value` |
| `toggles()` | `string\|StateRef $state` |

Importer `AML\View\Style` et `AML\Engine\StateRef` si ces noms sont utilisés dans
la signature. Conserver le retour `static` et les noms des paramètres pour les
appels nommés. Une redéfinition de `style()` doit également accepter son second
argument facultatif. Les nouvelles valeurs peuvent être transmises à la méthode
parente après les traitements propres à la sous-classe.

```php
public function padding(mixed $pixels): static
{
    return parent::padding($pixels);
}
```

Les méthodes utilisées sans redéfinition conservent leurs anciens appels. Les
classes applicatives habituelles qui étendent `Page` ou `Component` et renvoient
des composants natifs n'ont pas ces signatures à adapter.

Le contrôle automatique d'API conserve une exception exacte pour chacun de ces
élargissements, le numéro de version et deux paramètres facultatifs d'un trait
interne. Toute autre différence reste bloquante.
