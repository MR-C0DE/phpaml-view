# AML View

Declarative frontend interfaces for PHPAML — authored entirely in PHP.

See the [seven language extensions and their exact scope](docs/fr/extensions-langage.md).

> Status: `0.1.0-beta.13`. The public API is ready for real-world evaluation,
> but backward compatibility is not guaranteed until `1.0.0`.

[Documentation française](docs/fr/README.md) · [API reference](docs/API.md) ·
[Security](SECURITY.md) · [Changelog](CHANGELOG.md)

Frontend error handling: [level 10 guide](docs/fr/niveau-10-erreurs-exceptions.md)
and [service exception example](examples/niveau-10/page.php).

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

use AML\View\{Page, State, View};
use function AML\View\{Button, Heading, Text, VStack};

final class CounterPage extends Page
{
    #[State]
    public int $count = 0;

    public function body(): View
    {
        return VStack(
            Heading('AML View')->fontSize(42)->fontWeight('bold'),
            Text($this->count),
            Button('Remove one')
                ->sets('count', $this->state('count')->minus(1)),
            Button('Add one')
                ->sets('count', $this->state('count')->plus(1)),
        )->gap(16)->padding(40);
    }
}
```

The common path intentionally needs neither `StateRef` nor `ClientAction`.
Passing a `#[State]` property to a native text component keeps it reactive. Passing it
directly to a form control's `value()` creates a two-way binding:

```php
Text($this->name);
Input()->value($this->name);
```

Ordinary unmarked PHP properties remain static. The explicit
`$this->state('property')` API remains available for expressions. Safe
`plus()` and `minus()` expressions let `sets()` derive a new value from the
current state without JavaScript or executable serialized callbacks. The
existing `increments()`, `decrements()` and `toggles()` shortcuts remain
available for compatibility.
The lower-level Engine APIs remain available for sequences, transactions and
other advanced behavior.

Reactive state can also be interpolated into ordinary text without splitting a
sentence into several view nodes:

```php
Text('Count: {count}');
Text('Hello {profile.name}');
```

Placeholders must resolve to a property marked with `#[State]`. Nested array
paths are supported and remain HTML-escaped. Use doubled braces when text must
contain a literal placeholder, for example `Text('Example: {{count}}')`.

The same text pipeline handles native headings, paragraphs, emphasis, labels,
buttons, links, overlays and option labels:

```php
Paragraph('Hello ' . $this->name);
Heading("Hello {$this->name}", 2);
Strong('{name}, and again {name}');
Text(number_format($this->price * $this->quantity, 2));
Text(fn () => $prefix . $this->name);
```

Display functions are read-only and use the supported frontend grammar.
See the [reactive text guide](docs/fr/textes-reactifs.md) for the full component
list, local captures, literal braces, diagnostics and compiler boundaries.

Simple frontend mutations can be written as ordinary PHP closures:

```php
Button('Add')->onClick(function (): void {
    $this->count++;
});

Button('Reset')->onClick(function (): void {
    $this->count = 0;
});
```

PHPAML inspects these closures without executing them and compiles the supported
statements into the same declarative Engine actions shown above. The initial
subset supports `++`, `--`, assignment, `+=`, `-=`, local variables and the
arithmetic operators `+`, `-`, `*`, `/` and `%` on `#[State]` properties:

```php
Button('Calculate')->onClick(function (): void {
    $subtotal = $this->price * $this->quantity;
    $this->total = $subtotal - $this->discount;
});
```

Normal PHP precedence and parentheses are preserved. Arbitrary PHP calls and unmarked properties are rejected.
Boolean negation, string concatenation and compound arithmetic are also supported,
for example `$this->open = !$this->open`, `$this->label = 'Total: ' . $this->total`
and `$this->quantity *= 2`.
PHP ternaries remain reactive as well:

```php
$this->discount = $subtotal >= 100 ? 10 : 0;
$this->label = $this->enabled ? 'Active' : 'Inactive';
```

Longer or reusable interactions may be moved into a named component method and
passed as a first-class PHP callable:

```php
private function increment(): void
{
    $this->count++;
}

// Inside body()
Button('Add')->onClick($this->increment(...));
```

The named method is inspected under the same frontend safety rules and is not
executed during server rendering.

A single expression can use PHP's concise arrow-function syntax:

```php
Button('Add')->onClick(fn () => $this->count++);
Button('Toggle')->onClick(fn () => $this->enabled = !$this->enabled);
```

Use a braced closure or a named method when the interaction needs multiple
statements.

Reactive output remains ordinary PHP. Use a validated state reference when the
browser must recalculate a displayed value:

```php
Text('Count: {count}');
Text($this->state('connected')->choose('Welcome', 'Sign in required'));
Text($this->state('price')->times($this->state('quantity')));
Text($this->state('count')->prefixedBy('Count: '));
Text($this->state('count')->concat(' items'));
```

`choose()` and safe calculations update their text automatically when their
state dependencies change.

Basic collection changes use familiar PHP syntax inside safe frontend handlers:

```php
$this->tasks[] = $this->newTask;
array_pop($this->tasks);
array_shift($this->tasks);
```

Collection operations use the closed native catalogue. Loaded user functions
and service methods can also be compiled; see the [compatibility matrix](docs/fr/compatibilite.md).
Keep each frontend closure on its own lines so its source is unambiguous.

Conditions use ordinary PHP syntax and compile to safe Engine branches:

```php
Button('Evaluate')->onClick(function (): void {
    if ($this->count >= 10) {
        $this->message = 'Goal reached';
    } else {
        $this->message = 'Keep going';
    }
});
```

The initial condition grammar supports boolean states and comparisons with
scalar literals, state values or calculated local expressions: `==`, `===`,
`!=`, `!==`, `>`, `>=`, `<` and `<=`.

## Frontend variables and scalar types

Frontend handlers support invocation-local variables, PHP scalar casts,
`gettype()`, browser-console `var_dump()`, file and component constants, and
single/double-quoted strings with interpolation and PHP escapes. Local
assignments snapshot their values instead of retaining live state references.
Use View beta.13 together with Engine beta.9. CLI beta.45 selects these
compatible minimum versions when creating a View application. See the
[Level 1 guide (French)](docs/fr/niveau-1-variables-types.md) for examples,
precise limits, and the native-PHP/runtime parity check.

## Frontend operators

Compiled handlers and effects support arithmetic, power, PHP strict/loose comparisons,
logical short circuits, ternaries, null coalescing and pre/post increments.
See the [French level 2 guide](docs/fr/niveau-2-operateurs.md) for precedence,
assignment expressions, examples and runtime limits.

## Frontend conditions, loops and arrays

Handlers support `switch`, strict `match`, `empty()` and multi-argument `isset()`.
`for`, `while`, `do...while` and `foreach` execute against current event state,
with nested loop controls and runtime iteration limits. PHP arrays preserve keys,
insertion order, numeric kinds and value-copy behavior across local assignments,
callbacks, destructuring and spread expressions. `echo` writes only to the browser
console. See the [levels 3–5 guide (French)](docs/fr/niveaux-3-5-conditions-boucles-tableaux.md)
for examples, diagnostics and the supported subset.

## Functions and services

Frontend events can call user functions loaded from separate PHP service files,
component methods and loaded static methods. Calls have private frames, real
returns, typed/default/named/variadic parameters, references and closures.
Recursion is bounded; synchronous failures restore component state. Function
bodies are compiled without executing application code. See the
[level 6 guide (French)](docs/fr/niveau-6-fonctions.md) for examples and limits.

Selected PHP string and numeric builtins also compile into events, including
trimming, validation, byte lengths, replacement, splitting/joining, rounding,
secure random integers and amount formatting. Terminal streams, command arguments
and process exits remain PHP CLI features; browser input uses controls and state.
See [levels 7–8 (French)](docs/fr/niveaux-7-8-entrees-chaines-nombres.md) and the
[input/menu example](examples/niveaux-7-8/page.php), which calculates prices in cents.

## Bound and validated forms

```php
final class ContactPage extends Page
{
    #[State]
    public string $email = '';

    public function body(): View
    {
        return Form(
            EmailInput()
                ->name('email')
                ->value($this->state('email'))
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

`name()` defines the submitted HTML field name. Passing a state reference to
`value()` creates the two-way reactive binding; passing a scalar instead keeps
the field non-reactive. `model()` remains available as the lower-level explicit
binding API.

PHPAML Engine validates controls locally and again before form submission.
Invalid controls receive accessible error markup automatically.

Submit and field events use the same restricted frontend PHP closures as
`onClick()`:

```php
Form(
    TextField()
        ->name('name')
        ->value($this->state('name'))
        ->onInput(fn () => $this->editing = true)
        ->onBlur(fn () => $this->editing = false),
    Button('Send')->submit(),
)->onSubmit(function (): void {
    $this->submitted = true;
});
```

Available field events are `onInput()`, `onChange()`, `onFocus()` and
`onBlur()`. A frontend `onSubmit()` prevents native navigation after successful
local and remote validation; forms without it retain their normal HTML submit.

Status messages use the singular `Alert` API:

```php
Alert::success('Saved');
Alert::error('Unable to save');
Alert::warning('Review this value');
Alert::info('A new version is available');
Alert::loading('Loading…');
```

Native progress can follow client state without a request or custom script:

```php
use AML\Engine\StateRef;
use function AML\View\Progress;

Progress(StateRef::to('completion', $this->completion), 100)
    ->attribute('aria-label', 'Completion');
```

Changing `completion` through a declarative action updates both the state and
the browser's native progress value.

The original `Alert($message, ActionStatus $status)` function remains available
for compatibility.

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
        ->language('en')->direction('ltr')
        ->favicon('/favicon.svg', 'image/svg+xml')
        ->meta('theme-color', '#ffffff')
        ->openGraph(image: 'https://example.com/assets/profile.png')
        ->twitter(image: 'https://example.com/assets/profile.png');
}
```

Call `->noIndex()` for a page that search engines must not index.
`FileApplication::head()` safely renders the title, description, canonical,
robots, Open Graph, Twitter, favicon, preload and structured-data tags for the
active route. The generated document also receives the declared language and
text direction.

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
click or a state update back to PHP. A `void` method can use the safe natural
frontend PHP subset and is compiled without being executed on the server:

```php
#[Effect('search', runOnMount: false, debounce: 250)]
private function searchChanged(): void
{
    if ($this->search === '') {
        $this->message = 'Enter a search term';
    } else {
        $this->message = 'Searching for ' . $this->search;
    }
}
```

Multiple dependencies use `#[Effect(['first', 'last'])]`. Advanced effects may
still return a declarative AML instruction or an `EffectPlan`; PHP source is
never translated into arbitrary JavaScript.

For a short effect local to `body()`, use `OnChange()` and keep the named
attribute form for larger or reusable behavior:

```php
return VStack(
    OnChange($this->state('age'), function (): void {
        $this->category = $this->age < 18 ? 'minor' : 'adult';
    }),
    NumberInput()->name('age')->value($this->state('age')),
    Text('{category}'),
);
```

`OnChange()` does not run initially by default. Pass `initial: true` when the
inline effect must also run on mount. Multiple dependencies are passed as an
array of state references.

For a React-style hook before the return statement, import the function and
register it while building `body()`:

```php
use AML\View\Effect;
use function AML\View\Effect;

public function body(): View
{
    Effect(function (): void {
        $this->message = 'Search: ' . $this->search;
    }, [$this->state('search')], runOnMount: false);

    return VStack(
        TextField()->name('search')->value($this->state('search')),
        Text('{message}'),
    );
}
```

PHP keeps class and function imports in separate symbol tables, so the
`#[Effect]` attribute and `Effect()` function can coexist in the same file.
As with React Hooks, keep hook-style calls unconditional and before `return`.
The safe closure subset also accepts the browser-console facade directly:

```php
use AML\View\Console;

Effect(function (): void {
    Console::log('New counter value', $this->counter);
}, [$this->state('counter')], runOnMount: false);
```

`Console::log()`, `Console::warning()` and `Console::error()` write to the browser
console. Loaded service static methods can also be compiled when their bodies
use the supported frontend language.

### Action status without manual state

`ActionState()` wraps a frontend closure or client instruction and supplies
reactive `pending`, `success`, and `error` references automatically:

```php
$save = ActionState(Api::post('/api/profile', [
    'name' => $this->state('name'),
]));

return Form(
    TextField()->value($this->state('name')),
    Button($save->pending->choose('Saving...', 'Save'))
        ->onClick($save)
        ->disabledWhen($save->pending),
    Text($save->error),
);
```

Each execution clears the previous error and success state, marks the action as
pending, then records success or the client error message. No extra `#[State]`
properties are required.

### Complete form state

`FormState()` tracks the lifecycle of one native form without manual status
properties:

```php
$form = FormState();

return Form(
    EmailInput()
        ->value($this->state('email'))
        ->required(),

    Button($form->submitting->choose('Sending...', 'Send'))
        ->submit()
        ->disabledWhen($form->invalid),

    Button('Reset')->onClick($form->reset()),
    Text($form->error),
)
    ->state($form)
    ->onSubmit($form->action(
        Api::post('/api/contact', ['email' => $this->state('email')]),
    ));
```

The handle exposes `dirty`, `touched`, `valid`, `invalid`, `submitting`,
`submitted`, and `error`. Native and remote validation remain authoritative.
Resetting the form also restores its bound reactive values.

### Reducers, context and element references

Use `Reducer()` when one value has several named transitions:

```php
$counter = Reducer(0, [
    'increment' => fn (StateRef $state) => $state->plus(1),
    'decrement' => fn (StateRef $state) => $state->minus(1),
]);

return VStack(
    Text($counter->state),
    Button('+')->onClick($counter->dispatch('increment')),
    Button('-')->onClick($counter->dispatch('decrement')),
);
```

`Context()` and `UseContext()` are concise aliases for the existing provider
and reader contract:

```php
Context('locale', 'fr-CA', UseContext('locale'));
```

`Ref()` provides an intentionally small imperative escape hatch. Supported
operations are `focus`, `blur`, `select`, `play`, and `pause`:

```php
$search = Ref();

return Group(
    Input()->ref($search),
    Button('Focus')->onClick($search->focus()),
);
```

Refs never execute arbitrary method names or JavaScript.

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

PHP closure bodies compile into Engine instructions. Explicit `eval()` uses the same instruction set, with an optional PHP parser for dynamic strings; it never invokes JavaScript eval.

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

Declare which stylesheet belongs to a page or component with one top-level
`import()` outside the class. Its path is relative to `src/views/stylesheets`:

```php
use function AML\View\{import, MainContent, Text};

import('pages/home.css');

final class HomePage extends Page
{
    public function body(): View
    {
        return MainContent(Text('Home'))->class('home-page');
    }
}
```

`import()` accepts local `.css` files only, rejects traversal and remote URLs,
and deduplicates repeated declarations. `class()` remains responsible for
applying a stylesheet class to an element. The application keeps one global,
deterministic bundle so navigation never flashes or reloads page styles.

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

Frontend integration is also checked from installed Composer distributions in
Chromium, Firefox and WebKit, with both the complete and modular Engine runtimes.
For a matching sibling Engine checkout, run `npm run test:browser:runtimes` and
`npm run test:packages` from that checkout. The package runner creates candidate
release ZIPs, installs them in isolation, checks their assets and runs the complete
task, reservation and compatibility examples. It does not publish packages.
The `frontend-packages` CI job repeats this on View changes; the repository
variable `PHPAML_ENGINE_TEST_REF` selects the matching Engine revision (default
`main`). See Engine's README for prerequisites and local overrides.

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

## Native PHP functions

View and Engine share a [closed native catalogue](docs/fr/compatibilite.md#catalogue-natif). Math, string,
array and scalar helpers support browser events and pure reactive displays;
object member introspection runs in events. See the
[function catalogue and browser limits](docs/fr/fonctions-natives.md) and the
[working example](examples/fonctions-natives/page.php).

The [Unicode guide](docs/fr/unicode.md) covers 28 UTF-8 `mb_*` functions and a
[reactive example](examples/unicode/page.php). PHP rendering requires ext-mbstring.

The [BCMath guide](docs/fr/bcmath.md) covers 14 exact decimal functions, rounding
and event-local scale. See the [decimal total example](examples/calcul-decimal/page.php).

The [output guide](docs/fr/sorties-console.md) covers `print_r()` and `var_export()`
in events and reactive text, with a [working example](examples/sorties-console/page.php).

The [eval guide](docs/fr/eval.md) covers static and dynamic PHP text in frontend events,
shared local scope, catchable errors, limits and the optional parser asset.

The [service object guide](docs/fr/objets.md) describes frontend constructors, typed properties, inheritance and method calls, with a [complete cart example](examples/objets/page.php). Service static properties have component-local storage across events, including stored objects and closures; publish scalar or array results through State. See the [two-counter example](examples/proprietes-statiques/page.php).

The [magic methods guide](docs/fr/methodes-magiques.md) covers service hooks for properties, methods, invokable objects, strings and cloning. The [introspection guide](docs/fr/introspection.md) adds `__debugInfo()`. See the [profile example](examples/methodes-magiques/page.php).

Anonymous classes and intersection/DNF contracts are supported in frontend events and services. See the [guide](docs/fr/classes-anonymes-intersections.md) and [profile example](examples/classes-anonymes/page.php).

The [object introspection guide](docs/fr/introspection.md) covers native object helpers, compiled enum detection and `__debugInfo()` console output. See the [working example](examples/introspection/page.php).

The [collection objects guide](docs/fr/collections.md) covers `Countable`, `ArrayAccess`, `is_countable()` and direct/indirect offset operations. See the [shopping-cart example](examples/collections/page.php).

The [iterator guide](docs/fr/iterateurs.md) covers `Iterator`, `IteratorAggregate`, `Traversable`, `iterable` and `is_iterable()`, including bounded `foreach` traversal. See the [working collection example](examples/iterateurs/page.php).

The [generator guide](docs/fr/generateurs.md) covers lazy `yield`/`yield from`, suspension inside expressions, `send`, `throw`, `getReturn`, component-local lifetime and explicit cleanup boundaries. See the [catalogue example](examples/generateurs/page.php).

The [compatibility matrix](docs/fr/compatibilite.md) is the entry point for the
supported language and remaining boundaries. Its native function list is generated
from Engine with `composer docs:compatibility` and checked with `composer docs:check`.
The [combined example](examples/compatibilite/page.php) calls a
[service](examples/compatibilite/service.php) using JSON, regular expressions,
dates, SPL collections and enums together.

The [reservation example](docs/fr/reservation.md) combines form validation,
`createFromFormat`, calendar intervals and periods, named callback arguments and
`JsonSerializable` in a separate service. See the [date guide](docs/fr/dates-avancees.md)
and [validation and callable utilities](docs/fr/utilitaires.md) for their exact limits.

The [URL, Unicode and dynamic-class guide](docs/fr/web-unicode-dynamique.md) covers
URL/Base64 helpers, `filter_var_array`, grapheme-safe reactive text, normalization,
localized numbers and currencies, dynamic class targets and complete nullsafe
receiver chains. See the [combined profile example](examples/profil-unicode/page.php).
