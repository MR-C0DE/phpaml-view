# AML View — documentation française

AML View est la bibliothèque d’interfaces web déclaratives, optionnelle et
orientée serveur de PHPAML. Elle permet de construire des pages interactives en
PHP sans JSX et sans framework JavaScript côté client.

## Fonctionnalités

- propriétés réactives typées avec `#[State]` ;
- propriétés calculées avec `#[Computed]` ;
- pages, composants, layouts, `RouterView()` et `Slot()` ;
- formulaires liés et validation déclarative ;
- interactions signées, expirables et utilisables une seule fois ;
- rendu HTML sémantique et accessible.

## Exemple

```php
final class CounterPage extends Page
{
    #[State]
    public int $count = 0;

    #[Computed]
    protected function summary(): string
    {
        return "Valeur actuelle : {$this->count}";
    }

    public function body(): View
    {
        return VStack(
            Heading('AML View')->size(42)->bold(),
            Text($this->summary),
            Button('Ajouter')->onClick(fn () => $this->count++),
        )->gap(16)->padding(40);
    }
}
```

## Formulaire validé

```php
#[State]
#[Required('Votre adresse courriel est obligatoire.')]
#[Email('Adresse courriel invalide.')]
public string $email = '';

return Form(
    Input('email', 'email')->bind($this, 'email'),
    Button('Envoyer')->loadingLabel('Envoi…'),
)->onSubmit(fn () => $this->send());
```

AML View empêche automatiquement une soumission invalide et affiche les
messages accessibles sous les champs concernés.

Consultez également la [référence API](../API.md) et le
[guide de sécurité](../../SECURITY.md).
