# AML View

Declarative, server-first web interfaces for PHPAML — written entirely in PHP.

> Status: `0.1.0-beta.1`. The public API is ready for real-world evaluation,
> but backward compatibility is not guaranteed until `1.0.0`.

[Documentation française](docs/fr/README.md) · [API reference](docs/API.md) ·
[Security](SECURITY.md) · [Changelog](CHANGELOG.md)

## Why AML View?

AML View adds an optional declarative UI layer without replacing classic PHP
views. It renders semantic HTML on the server and uses a small progressive
runtime only when a component declares an interaction.

- PHP 8.2+ with typed `#[State]` properties;
- cached `#[Computed]` methods;
- components, pages, layouts, `RouterView()` and `Slot()`;
- signed, expiring, one-time browser interactions;
- bound forms and declarative validation;
- no JSX, Node.js runtime or client framework required.

## Install

During the beta, install the repository as a Composer VCS dependency. A normal
Packagist installation will become available after the package is registered.

```bash
composer require phpaml/view:^0.1@beta
```

## Your first interactive page

```php
<?php

use AML\View\Computed;
use AML\View\Page;
use AML\View\State;
use AML\View\View;
use function AML\View\{Button, Heading, Text, VStack};

final class CounterPage extends Page
{
    #[State]
    public int $count = 0;

    #[Computed]
    protected function summary(): string
    {
        return "Current value: {$this->count}";
    }

    public function body(): View
    {
        return VStack(
            Heading('AML View')->size(42)->bold(),
            Text($this->summary),
            Button('Add one')
                ->onClick(fn () => $this->count++)
                ->loadingLabel('Updating…'),
        )->gap(16)->padding(40);
    }
}
```

## Bound and validated forms

```php
final class ContactPage extends Page
{
    #[State]
    #[Required('Your email is required.')]
    #[Email]
    public string $email = '';

    public function body(): View
    {
        return Form(
            Input('email', 'email')->bind($this, 'email'),
            Button('Send')->loadingLabel('Sending…'),
        )->onSubmit(fn () => $this->send());
    }
}
```

AML View validates every bound control before executing `onSubmit`. Invalid
controls receive accessible error markup automatically.

## Routes and layouts

```php
$layout = static fn (): Layout => new DashboardLayout();

$router = (new Router())
    ->get('/', static fn (): View => new HomePage(), $layout)
    ->get('/users/{id}', static fn (array $params): View => new UserPage($params['id']), $layout);

return RouterView($router);
```

Inside the layout, `Slot()` renders the active page.

## Demo and tests

```bash
composer test
php -S 127.0.0.1:8080 -t examples/demo examples/demo/router.php
```

Then open `http://127.0.0.1:8080`.

## Security requirement

Use a random secret of at least 32 characters and bind the interaction kernel
to the current authenticated session:

```php
$kernel = new InteractionKernel($_ENV['AML_VIEW_SECRET'], null, session_id());
```

Never use the demonstration secret in production. See [SECURITY.md](SECURITY.md).

## License

AML View is released under the MIT License.
