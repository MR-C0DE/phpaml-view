# AML View

Declarative frontend interfaces for PHPAML — authored entirely in PHP.

> Status: `0.1.0-beta.5`. The public API is ready for real-world evaluation,
> but backward compatibility is not guaranteed until `1.0.0`.

[Documentation française](docs/fr/README.md) · [API reference](docs/API.md) ·
[Security](SECURITY.md) · [Changelog](CHANGELOG.md)

## Why AML View?

AML View adds an optional declarative UI layer without replacing classic PHP
views. PHP renders the initial semantic HTML, then PHPAML Engine owns ordinary
state, events and navigation directly in the browser.

- PHP 8.2+ with typed `#[State]` properties;
- cached `#[Computed]` methods;
- components, pages, layouts, `RouterView()` and `Slot()`;
- wrapper-free composition with `Group()`;
- frontend-only events by default, with explicit same-origin API actions;
- bound forms and declarative validation;
- no JSX, Node.js runtime or client framework required.

## Install

Install the public beta directly from Packagist:

```bash
composer require phpaml/view:^0.1@beta phpaml/engine:^0.1@beta
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
                ->onClick(ClientAction::increment('count'))
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
    public string $email = '';

    public function body(): View
    {
        return Form(
            Input('email', 'email')
                ->bindClient('email')
                ->required('Your email is required.')
                ->email(),
            Button('Send')->onClick(
                Api::post('/api/contact', ['email' => StateRef::to('email')])
                    ->loadingIn('sending')
                    ->errorIn('contactError')
            ),
        );
    }
}
```

PHPAML Engine validates controls locally and again before form submission.
Invalid controls receive accessible error markup automatically.

## Styling elements

Attach stylesheet classes declaratively:

```php
Section(
    Heading('Welcome')->class('home-title'),
)->class('home-hero', 'featured');
```

Keep global selectors in `src/views/stylesheets/base.css`. Stylesheets for
pages, components, layouts, and states should use class-based selectors.

## File-based application

Projects created with `aml create-view-app` use a source-first structure:

```text
src/
├── views/
│   ├── pages/
│   │   ├── home/page.php
│   │   ├── about/page.php
│   │   └── users/[id]/page.php
│   ├── components/
│   │   └── Navigation.php
│   ├── layouts/
│   │   └── AppLayout.php
│   ├── states/
│   │   ├── Loading.php
│   │   ├── Error.php
│   │   └── NotFound.php
│   ├── stylesheets/
│       ├── base.css
│       ├── pages/home.css
│       ├── components/navigation.css
│       ├── layouts/app.css
│       └── states/route-states.css
│   └── themes/
│       ├── light/tokens.css
│       └── dark/tokens.css
├── controllers/
├── models/
├── middleware/ (optional)
└── services/ (optional)
public/
├── index.php
├── favicon.svg
├── phpaml-logo-violet-lime.png
└── assets/
```

Folders under `src/views/pages` become URLs automatically:
`src/views/pages/home/page.php` handles `/`, while
`src/views/pages/about/page.php` handles `/about`. Layouts and route states are
kept in their own explicit directories. `public/index.php` remains the only
HTML document shell.

Dynamic folders use brackets. For example, `src/views/pages/users/[id]/page.php`
matches `/users/42`, and the page reads the value with `$this->param('id')`.
`states/Loading.php`, `states/Error.php`, and `states/NotFound.php` provide declarative route
states. Components stay on the view side. Server-only controllers, models,
middleware, services, and API code live directly in `src/`. Classic PHP templates
are intentionally absent from AML View applications.

`src/views`, `src/models`, and `src/controllers` are required in every AML
View application. Middleware and service directories are optional.

Themes are native to AML View:

```php
return ThemeProvider(
    default: 'system',
    content: Group(Navigation(), Slot()),
    themes: ['light', 'dark'],
);
```

Place `ThemeSwitcher('light', 'dark', 'system')` in any component. AML View
applies the selected theme to `<html>`, observes the system preference, and
persists the choice without user-authored JavaScript. Theme CSS lives under
`src/views/themes/{theme}` and is bundled automatically.

SEO metadata is declarative and scoped to each page:

```php
public function metadata(): PageMetadata
{
    return (new PageMetadata())
        ->title('User ' . $this->param('id'))
        ->description('Public user profile')
        ->canonical('https://example.com/users/' . $this->param('id'))
        ->openGraph(image: 'https://example.com/assets/profile.png')
        ->twitter(image: 'https://example.com/assets/profile.png');
}
```

Call `->noIndex()` for a page that search engines must not index.
`FileApplication::head()` safely renders the title, description, canonical,
robots, Open Graph and Twitter tags for the active route.

AML View contains no automatic server-interaction transport. Applications use
client actions for local behavior and an explicit `Api::*()` action when the
backend must be contacted.

## Client-side engine

`phpaml/engine` executes ordinary interface state in the browser. A local
action does not call PHP or the AML interaction endpoint:

```php
#[State]
public int $count = 0;

return VStack(
    Text(StateRef::to('count', $this->count)),
    Button('Add one')->onClick(ClientAction::increment('count')),
);
```

The initial HTML and state still come from PHP. After mounting, PHPAML Engine
updates every element bound to `count` locally. Server communication remains an
explicit concern for future API actions, not the default behavior of a click.

State can be shared across routed pages or persisted by the browser:

```php
#[State, Shared('cart.count'), Persisted('local', 'shop.cart.count', version: 2, expiresAfter: 86400)]
public int $cartCount = 0;

#[State, Persisted('session')]
public int $checkoutStep = 1;
```

`#[Shared]` keeps every mounted property using the same key synchronized and
survives client-side navigation. `#[Persisted]` restores JSON-safe values from
`localStorage` or `sessionStorage`. Both may be combined on one property.
Every reusable component instance receives an isolated state namespace.

Increment `version` when a persisted schema changes. Incompatible data emits
`aml:storage-migration-required`; expired data emits `aml:storage-expired` and
is removed. `AMLEngine.clearPersisted(key)` explicitly resets a local value.
Local-storage changes are synchronized across browser tabs. Never persist
passwords, access tokens or other secrets.

Form state is also local:

```php
Input('name', value: $this->name)->bindClient('name');
Text(StateRef::to('name', $this->name));
```

Typing updates the bound text immediately without registering a server event.

Validation is also frontend-native and remains declarative:

```php
Input('email')
    ->bindClient('email')
    ->required('Email is required.')
    ->email()
    ->minLength(6);
```

PHPAML Engine validates during input/change and before form submission. It
sets `aria-invalid`, connects an accessible error with `aria-describedby`, and
focuses the first invalid control. API routes must still validate all received
data independently.

For availability or uniqueness checks, declare the API explicitly:

```php
Input('name')
    ->bindClient('name')
    ->validateWith(
        Api::get('/api/validate-name', ['name' => StateRef::to('name')]),
        debounce: 300,
    );
```

The endpoint returns `{"valid": true}` or
`{"valid": false, "message": "Already reserved."}`. The engine debounces
input, cancels stale requests, restricts validation to same-origin URLs and
rechecks remote rules before submission.

Same-origin links use PHPAML Engine navigation automatically. The engine keeps
the document runtime mounted, updates `history.pushState`, supports browser
back/forward and marks the matching link with `aria-current="page"`. Use
`->nativeNavigation()` when a link must perform a traditional document load.

Backend calls must be declared explicitly:

```php
Button('Save')->onClick(
    Api::post('/api/profile', ['name' => StateRef::to('name')])
        ->storeIn('profile')
        ->errorIn('apiError')
        ->loadingIn('apiLoading')
);
```

API actions use same-origin requests with JSON payloads and browser credentials.
The response, error and loading states remain available through `StateRef`.

Use `->component('profile-card')` to expose a named frontend lifecycle
boundary. PHPAML Engine emits mount, update and unmount events for the root and
these boundaries, cleans registered handlers and aborts pending API requests
when a page is removed.

Actions can be composed without JavaScript:

```php
Button('Update')->onClick(Actions::sequence(
    ClientAction::increment('count'),
    Actions::when(
        'count', 'gte', 10,
        ClientAction::set('message', 'Goal reached'),
        ClientAction::set('message', 'Keep going'),
    ),
));
```

Sequences preserve their order and can include explicit API actions.
Purely local sequences are automatically batched into one render. Use
`Actions::transaction()` when rollback semantics must also be explicit.

Presentation can depend directly on frontend state:

```php
Button('Toggle')
    ->onClick(ClientAction::toggle('open'))
    ->classWhen(StateRef::to('open', $this->open), 'is-active');

Panel(...)->showWhen(StateRef::to('open', $this->open));
Button('Save')->disabledWhen(StateRef::to('loading', $this->loading));

When(
    StateRef::to('ready', $this->ready),
    Text('Ready'),
    Text('Waiting'),
);
```

The initial HTML already reflects a supplied `StateRef` value, preventing a
visible flash before PHPAML Engine mounts.

The first reactive collection API uses `Each()` (`foreach` is reserved by PHP):

```php
Each(StateRef::to('tasks', $this->tasks), label: 'title', key: 'id');

Button('Add')->onClick(
    ClientAction::append('tasks', [
        'id' => StateRef::to('newId'),
        'title' => StateRef::to('newTitle'),
    ])
);
```

`prepend()`, `removeAt()`, `removeBy()`, `updateBy()`, `filterBy()`, `sortBy()`,
`move()`, `reverse()`, `merge()` and `clear()` are also available. Labels and keys may use nested paths such as
`profile.name`. Initial items are server rendered; subsequent collection
updates run locally and escape labels.
Nodes are reconciled by stable key. Reordering therefore preserves native DOM
state and AML component state. Components cloned for newly appended items
receive a key-derived isolated state namespace.

`#[Computed]` without dependencies remains a PHP-only value cached for one
server render. With explicit dependencies, AML View emits a safe frontend
calculation using `concat`, `sum`, `count`, `all` or `any`:

```php
#[Computed(dependencies: ['first', 'last'], operation: 'concat', separator: ' ')]
protected function fullName(): string
{
    return $this->first . ' ' . $this->last;
}
```

## Declarative effects

`#[Effect]` connects browser-side work to state dependencies without sending a
click or a state update back to PHP. An effect method returns a declarative AML
instruction or an `EffectPlan`; PHP closures are never translated into unsafe
JavaScript.

```php
use AML\Engine\ClientAction;
use AML\Engine\Effects;
use AML\Engine\StateRef;
use AML\View\Effect;

#[Effect(dependencies: ['search'], runOnMount: false, debounce: 250, throttle: 500, concurrency: 'latest')]
protected function synchronizeSearch(): \AML\Engine\EffectPlan
{
    return Effects::run(ClientAction::set('query', StateRef::to('search')));
}

#[Effect]
protected function clock(): \AML\Engine\EffectPlan
{
    return Effects::interval(1000, ClientAction::increment('seconds'));
}

#[Effect]
protected function selection(): \AML\Engine\EffectPlan
{
    return Effects::onDocument(
        'app:selection',
        ClientAction::set('selectedId', \AML\Engine\EventRef::to('detail.id')),
    )->withCleanup(ClientAction::set('selectedId', null));
}
```

Available plans are `Effects::run()`, `timeout()`, `interval()`, `onWindow()`
and `onDocument()`. AML Engine cleans timers, listeners and active API requests
before an effect runs again and when its component is unmounted. It emits
`aml:effect-run`, `aml:effect-cleanup`, `aml:effect-error` and
`aml:effect-cycle` for diagnostics.

Concurrency strategies are `latest`, `exhaust`, `queue`, and `parallel`.
Dynamic collection components own isolated effects, stale async results are
discarded, and mount effects wait for IndexedDB restoration.

Development tools can inspect and control an effect without exposing mutable
runtime internals: `AMLEngine.effects(root)`, `pauseEffect()`, `resumeEffect()`
and `runEffect()`.

## Rich components and collections

AML View now provides declarative, accessible building blocks without requiring
application JavaScript:

```php
return VStack(
    Button('Open profile')->onClick(ClientAction::set('profileOpen', true)),
    Modal(StateRef::to('profileOpen'), 'Profile', new ProfileCard()),
    Tabs(StateRef::to('section'), [
        'Overview' => new OverviewPanel(),
        'Activity' => new ActivityPanel(),
    ]),
    Accordion(StateRef::to('expanded'), [
        'details' => Text('Account details'),
    ]),
);
```

`DataTable()` sorts rows locally, `VirtualList()` renders only the visible
window of a large collection, `SortableEach()` supports local drag and drop,
and `DynamicForm()` keeps generated fields keyed and reactive.
`AsyncBoundary()` selects loading, success, empty, or error content from one
state value. Modal focus, tab arrows, accordion state, sortable table headers,
ARIA attributes, and transitions are handled by PHPAML Engine.

Virtual lists include an initial server-rendered window, then react to scrolling
and resizing in the browser. Sortable collections support pointer drag and drop
as well as `Alt+ArrowUp` and `Alt+ArrowDown`; moves never cross collection state
boundaries. Transitions automatically honor reduced-motion preferences.

No PHP closure or arbitrary expression is evaluated in the browser.

Several related mutations can be applied atomically with one render:

```php
Button('Save')->onClick(Actions::transaction(
    ClientAction::set('profile.name', StateRef::to('draft.name')),
    ClientAction::set('profile.saved', true),
    ClientAction::sortBy('tasks', 'priority'),
));
```

Styles mirror the view tree. `FileApplication::styles()` bundles every CSS file
under `src/views/stylesheets` in deterministic order. The application exposes
that bundle at `/_aml/styles.css`, keeping `public/` limited to the entry point
and static assets. Application images and media belong in `public/assets`.
Documents that require a direct top-level URL—such as the logo, favicon,
`robots.txt`, and `sitemap.xml`—stay at the root of `public`.

```php
$app = new FileApplication(__DIR__ . '/../src/views');

$result = $app->mount($_SERVER['REQUEST_URI'] ?? '/');
```

## Explicit routes and layouts

```php
$layout = static fn (): Layout => new DashboardLayout();

$router = (new Router())
    ->get('/', static fn (): View => new HomePage(), $layout)
    ->get('/users/{id}', static fn (array $params): View => new UserPage($params['id']), $layout);

return RouterView($router);
```

Inside the layout, `Slot()` renders the active page.

## Context and advanced navigation

Contexts carry configuration through layouts and components without global PHP
variables. Values may be static or backed by client state, and may opt into
local persistence:

```php
return ContextProvider('locale', StateRef::to('locale', 'en'),
    ThemeProvider('system', VStack(
        ContextText('locale'),
        ThemeSwitcher('light', 'dark', 'system'),
        Slot(),
    )),
    persist: true,
);
```

The built-in `theme` and `locale` contexts update the document theme and
language. `Page::query()` and `queries()` expose URL query parameters.
`Navigate('/account')` is a client instruction, while `Redirect('/login')`
performs a declarative replacement navigation.

Wrap routed content with `NavigationBoundary()` to keep loading, error, and
not-found states inside the current layout. Successful navigation updates
history, active links, title, managed SEO metadata, scroll position, focus, and
a reduced-motion-aware page transition without a full browser reload. Rapid
navigation cancels stale requests. Route states are loaded lazily and mounted
as interactive AML View trees. Browser tools can inspect
`AMLEngine.context(root, name)` and `AMLEngine.route()`.

## Accessible UI and advanced forms

AML View includes declarative `Toast()`, `Dropdown()`, `MenuItem()`,
`Tooltip()`, and `Popover()` primitives. PHPAML Engine manages their focus,
Escape handling, outside-click dismissal, live regions, ARIA state, and menu
keyboard navigation.

Forms support `FileInput()` with `UploadForm()`, `ConditionalField()`, and
`MultiStepForm()`. Add `->preserve('contact.draft')` to retain ordinary values
across navigation and server errors. File contents are never persisted in
browser storage. After confirmed success, call
`AMLEngine.clearFormDraft('contact.draft')`. Always validate file size, type,
and content again on the server.

## Demo and tests

## Construction-free view trees

Application code does not need to expose object construction. Generic elements
use the `Element()` factory, and a custom component may declare a same-name
factory next to its class:

```php
final class Navigation extends Component
{
    public function body(): View
    {
        return Element('nav', Link('Tasks', '/tasks'));
    }
}

function Navigation(mixed ...$arguments): Navigation
{
    return new Navigation(...$arguments);
}
```

Pages and layouts can then use `Navigation()` directly. `FileApplication`
preloads component files before pages and layouts, while
`aml make:view-component Navigation` generates the factory automatically.
`Component(Navigation::class, ...)` remains the generic fallback, and ordinary
`new Navigation()` stays fully compatible.

Use the built-in test API for fast component and page tests:

```php
ViewTest::render(new CounterPage())
    ->assertSee('Counter')
    ->assertState('count', 0)
    ->click('Add')
    ->assertState('count', 1);
```

It can inspect components and state, fill controls, simulate local AML actions,
verify navigation, and assert exceptions without a browser or DOM extension.

```bash
composer test
php -S 127.0.0.1:8080 -t examples/aml-tasks/public examples/aml-tasks/public/router.php
```

Then open `http://127.0.0.1:8080`. **AML Tasks** is the complete reference
application: dashboard, reactive task collection, dynamic task route,
preferences, persistent themes, route states, responsive styles, controllers,
models, metadata and client-side navigation.

## License

AML View is released under the MIT License.
