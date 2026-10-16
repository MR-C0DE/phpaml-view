# Enums dans les événements frontend

Les enums PHP déjà chargées peuvent être utilisées dans les fonctions et services compilés pour Engine. Le compilateur lit leurs déclarations et leurs méthodes ; il n’exécute pas ces méthodes côté PHP.

```php
enum Statut: string
{
    case Brouillon = 'draft';
    case Payee = 'paid';

    public function libelle(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::Payee => 'Payée',
        };
    }
}

// Dans un événement :
$statut = Statut::from('paid');
$this->message = $statut->libelle();
$this->code = $statut->value;
```

## Prise en charge

- Enums sans valeur associée et enums adossées à `int` ou `string`.
- Cas, constantes et alias de cas ; `cases()`, `from()` et `tryFrom()`.
- Propriétés `name` et `value` en lecture seule ; identité de chaque cas avec `===`.
- Méthodes utilisateur statiques ou d’instance, traits, interfaces et contrats `UnitEnum`/`BackedEnum`.
- Cas utilisés comme valeurs par défaut de paramètres ou de propriétés, dans des tableaux locaux, avec `match`, dans des callbacks et dans les propriétés statiques des services.
- Appels avec paramètres nommés ou propagation, références de méthodes `Statut::from(...)` et `$statut->libelle(...)`.
- Introspection des membres et `var_dump()` dans la console.
- Utilisation des enums déjà compilées depuis `eval()`.

Les conversions de `from()`/`tryFrom()` suivent le mode strict du fichier appelant. Une valeur absente provoque `ValueError` pour `from()` et retourne `null` pour `tryFrom()`. La création avec `new`, le clonage et la modification des propriétés en lecture seule provoquent une erreur.

## Limites explicites

La déclaration de nouvelles enums dans `eval()` reste refusée, comme les autres déclarations de classes nommées. Une enum utilisée dans le navigateur doit faire partie du catalogue compilé ; Engine ne charge pas de fichiers PHP.

Les cas restent des objets locaux au navigateur. Ils ne peuvent pas être placés dans un `State`, même au sein d’un tableau : transmettre `->value` ou `->name`. Le magasin d’identités et les propriétés statiques sont isolés par composant, conservés entre ses événements et recréés avec lui.

Les attributs ne sont pas exposés par réflexion. La sérialisation PHP des enums et la réflexion générale restent hors du périmètre frontend.

## Vérification

`tests/enums.php` compare PHP natif et `TestView`. `tests/enums.mjs` réutilise ces scénarios avec Engine ; `tests/browser/enums.spec.mjs` les exécute dans un navigateur.
