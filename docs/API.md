# API reference

## Base types

- `View`: renderable contract.
- `Component`: base class for reusable declarative components.
- `Page`: routable component.
- `Layout`: component that renders the active page through `Slot()`.
- `Renderer`: initial HTML rendering entry point.

## State and validation

- `#[State]`: exposes a typed initial value to PHPAML Engine.
- `#[Shared('key')]`: synchronizes state across mounted pages/components.
- `#[Persisted(storage, key, version, expiresAfter, migrations)]`: restores versioned,
  optionally expiring browser state.
- `#[Computed]`: caches a parameterless method for one server render. Supplying
  `dependencies`, `operation` and optionally `separator` also declares its safe
  frontend recalculation.
- `bindClient('property')`: binds a control to frontend state.
- `required()`, `email()`, `minLength()`: synchronous frontend validation.
- `validateWith(ApiAction $request, ...)`: explicit asynchronous validation.

## Components

- Layout: `VStack`, `HStack`, `Column`, `Row`, `Grid`, `ZStack`, `Spacer`.
- Content: `Heading`, `Text`, `Image`, `Link`, `Section`, `MainContent`.
- Actions: `Button`, `Action`, `Alert`.
- Forms: `Form`, `Input`, `TextArea`, `Select`, `Checkbox`.
- Composition: `Group`, `RouterView`, `Slot`, `Content`, `ThemeProvider`, `ThemeSwitcher`.

`Group(...$children)` renders its children as siblings without producing an
extra HTML element. Use it when a component needs one `View` return value but
does not need a semantic or styled container.

## Modifiers

- Layout: `gap()`, `spacing()`, `padding()`, `center()`.
- HTML/CSS: `class(...$names)`, `attribute()`, `style()`.
- Events: `onClick(ClientInstruction)` and its alias `click()`.
- Actions: `disabled()`, `status()`, `loadingLabel()`.

## Routing

`Router::get($pattern, $page, $layout)` registers a GET route. Parameters use
`{name}` segments and are passed to the page factory as an associative array.

## Frontend engine

AML View does not expose an automatic interaction endpoint. PHPAML Engine owns
state and ordinary events in the browser. Backend communication must use an
explicit `Api::*()` client instruction.

Local frontend state is executed by `phpaml/engine`. Bind a rendered value with
`StateRef::to('property', $initialValue)` and attach `ClientAction::increment()`,
`decrement()`, `set()` or `toggle()` to `onClick()`. These actions never use the
server interaction endpoint.

Use `Element::bindClient('property')` on an input, textarea, select or checkbox
for bidirectional local state. The engine updates the state on browser input and
refreshes other `StateRef` bindings while preserving the active control.

Internal `Link()` destinations are handled by the PHPAML Engine router. Active
links receive `aria-current="page"`; browser back and forward use `popstate`.
Call `->nativeNavigation()` to opt a link out of client routing.

## Explicit API client

`Api::get()`, `post()`, `put()`, `patch()` and `delete()` create intentional
same-origin backend requests. Use `storeIn()`, `errorIn()` and `loadingIn()` to
connect the request lifecycle to frontend state. Request data may contain
`StateRef` values, which the engine resolves immediately before sending JSON.

## Frontend lifecycle

`Element::component('name')` marks a lifecycle boundary. PHPAML Engine emits
`aml:mount`, `aml:update` and `aml:unmount` events. `AMLEngine.on()` registers a
handler and returns a disposer; registered disposers and in-flight API requests
are cleaned automatically during unmount.

Every component instance owns a generated state namespace. Identical property
names in sibling components therefore remain independent. State paths reject
the reserved JavaScript segments `__proto__`, `prototype` and `constructor`.

## Composed actions

`Actions::sequence(...$actions)` executes local and API instructions in order.
`Actions::transaction(...$actions)` applies related state mutations with a
single render, rolls back on failure and emits `aml:transaction` or
`aml:transaction-error`. Local-only sequences are batched automatically.
`Actions::when($state, $operator, $value, $then, $otherwise)` selects a branch
from current frontend state. Supported operators are `eq`, `neq`, `gt`, `gte`,
`lt`, `lte`, `truthy` and `falsy`.

## Reactive presentation

`showWhen()`, `classWhen()` and `disabledWhen()` bind an element’s visibility,
CSS class or disabled state to client state. Pass a `StateRef` when the initial
server-rendered HTML must reflect the initial value immediately.
`When($state, $then, $otherwise, $equals)` swaps complete declarative branches
while keeping their templates inert until selected.

## Reactive collections

`Each(StateRef $items, string $label = 'label', string $key = 'id',
?Closure $render = null)` renders a collection with stable keys. A custom
renderer may return any AML View and bind item fields with
`CollectionItem::text('profile.name')`.

Collections support `append`, `prepend`, `removeAt`, `removeBy`, `updateBy`,
`filterBy`, `sortBy`, `move`, `reverse` and `clear`. `merge` updates an object
while preserving its other properties. Values may contain `StateRef` instances
resolved at interaction time.
Stable keys drive DOM reconciliation. Reordering preserves an existing node;
new stateful item components receive isolated key-derived state targets.

State paths may be nested (`profile.address.city`, `tasks.0.title`). For
development diagnostics, `AMLEngine.inspect(root)` returns a snapshot,
`AMLEngine.history(root)` returns up to 100 snapshots and
`AMLEngine.restore(root, index)` restores one locally.
History is disabled by default. Enable it only for development through
`PageResult::rootHtml(diagnostics: true)` or a bounded `data-aml-history` value.

Persisted state supports `local`, `session` and `indexeddb`. Version upgrades
use declarative migrations:

```php
#[State, Persisted(storage: 'indexeddb', version: 2, migrations: [
    2 => [
        'rename' => ['name' => 'profile.name'],
        'defaults' => ['active' => true],
        'remove' => ['legacyToken'],
    ],
])]
public array $account = [];
```

Use `->class('home-hero', 'featured')` to attach one or more CSS classes. AML
View merges them with existing classes and removes duplicates. Global selectors
belong in `stylesheets/base.css`; page, component, layout, and state styles
should use class-based selectors.

## Themes

`ThemeProvider(default: 'system', content: ..., themes: ['light', 'dark'])`
declares themes without adding a content wrapper. `ThemeSwitcher()` renders an
accessible selector. The runtime applies `data-aml-theme` to `<html>`, follows
the operating-system preference and persists explicit choices locally.

## Page metadata and SEO

Override `Page::metadata()` to declare the document title, description,
canonical URL, indexing policy and social previews without writing HTML:

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

Use `->noIndex()` for private or temporary pages. `FileApplication::head()`
generates escaped `<title>`, description, canonical, robots, Open Graph and
Twitter tags for the active file-based route.

## Effects

- `#[Effect(dependencies: [], runOnMount: true, debounce: 0, throttle: 0, concurrency: 'latest')]` marks a
  parameterless component method returning `ClientInstruction` or `EffectPlan`.
- `Effects::run($action)` executes once.
- `Effects::timeout($milliseconds, $action)` executes once after a delay.
- `Effects::interval($milliseconds, $action)` repeats until cleanup.
- `Effects::onWindow($event, $action)` and `onDocument()` attach browser
  listeners.

Dependencies and action targets are automatically scoped to reusable component
instances. Reruns and unmounts abort effect API requests and remove timers and
listeners. Rapid cycles above 25 self-triggered runs and sustained cycles above
60 self-triggered runs per minute are disabled and reported.

Concurrency accepts `latest` (cancel the previous execution), `exhaust` (ignore
a trigger while running), `queue` (run once more after completion), or
`parallel` (allow overlap). An effect with `runOnMount: false` must declare at
least one dependency. Cycle diagnostics distinguish rapid loops from sustained
self-triggering loops.

`EventRef::to('detail.id')` reads a safe event snapshot inside listener actions.
Supported roots are `type`, `detail`, `key`, `code`, `repeat`, `button`,
`clientX`, `clientY`, `value`, and `checked`. Attach a local cleanup action with
`$plan->withCleanup($action)`. Cleanup actions cannot call an API.

`AMLEngine.effects(root)` returns immutable diagnostic summaries.
`pauseEffect(root, id)`, `resumeEffect(root, id, runNow)` and
`runEffect(root, id)` support development tools and controlled manual execution.

## Rich UI and collections

- `Modal($open, $title, $content, $close = null)` renders an accessible dialog,
  traps focus while open, closes with Escape, and restores reactive state.
- `Tabs($selected, $tabs, $label = 'Tabs')` implements the ARIA tab pattern and
  keyboard navigation with arrows, Home, and End.
- `Accordion($expanded, $items)` links each trigger to its panel and maintains
  `aria-expanded`.
- `AsyncBoundary($status, $success, $loading, $error, $empty)` selects a branch
  declaratively.
- `DataTable($rows, $columns, $key = 'id', $sortable = true)` renders keyed rows
  and accessible local sorting.
- `VirtualList($items, $render, $key = 'id', $rowHeight = 48, $height = 320,
  $overscan = 4)` keeps DOM size bounded for large collections.
- `SortableEach($items, $label, $key, $render)` adds pointer-based local
  reordering while preserving keyed identity.
- `DynamicForm($fields, $render, $key = 'id', ...$actions)` renders keyed,
  state-driven fields and optional form actions.

Use `->transition('fade'|'slide'|'scale', $milliseconds)` on an element for a
browser-native entrance transition. Durations are restricted to 0–10,000 ms.
The engine honors `prefers-reduced-motion`. `VirtualList()` renders its first
window on the server and observes viewport resizing. `SortableEach()` can be
reordered with a pointer or with `Alt+ArrowUp` / `Alt+ArrowDown`.

## Context and navigation

- `ContextProvider($name, $value, $content, $persist = false, $storageKey = null)`
  provides a nested static or `StateRef` value. Persistent providers receive
  an isolated deterministic key unless an explicit key is supplied.
- `ContextText($name, $fallback = '')` renders and updates the nearest matching
  context.
- `ThemeProvider()` is backed by the persistent `theme` context.
- `Navigate($destination, $replace = false)` returns a client instruction.
- `Redirect($destination, $replace = true)` renders a declarative redirect.
- `NavigationBoundary($content, $loading, $error, $notFound)` preserves route
  states inside the active layout.
- `Page::query()` and `Page::queries()` read sanitized query parameters.

Navigation accepts relative, HTTP, and HTTPS destinations only. Same-origin
destinations use the frontend router; external HTTP(S) destinations use native
navigation. New navigation cancels an older request for the same AML root.
Route changes also synchronize the managed SEO metadata. Loading, error, and
not-found pages are loaded only when needed and mounted by the client engine.

## Accessible interface components

- `Toast($visible, $message, $tone, $duration)` creates an automatically dismissed live-region notification.
- `Dropdown($open, $label, ...$items)` and `MenuItem()` implement an accessible keyboard menu.
- `Tooltip($label, $content)` supports pointer and keyboard focus.
- `Popover($open, $label, $content)` creates a non-modal disclosure dialog.

Menus and popovers close on outside click or Escape. Menu items support arrow keys, Home, and End.

## Advanced forms

- `FileInput($name, $accept, $multiple, $files = null)` validates accepted file descriptors and only binds to array state.
- `UploadForm(...$children)` emits native multipart form encoding.
- `ConditionalField($state, $field, $equals)` makes inactive controls inert.
- `MultiStepForm($step, $steps, ...$actions)` provides bounded, accessible steps.
- `Form(...)->preserve('draft-key')` restores non-file values after navigation or a server error.

Call `AMLEngine.clearFormDraft('draft-key')` after confirmed server success.
Browser `accept` filters are only hints: applications must validate file size,
extension, MIME type, and content on the server.

## Declarative factories

- `Element(string $tag, View ...$children)` creates a generic element without
  `new` in the view tree.
- `Component(class-string $class, mixed ...$arguments)` instantiates a custom
  component through the generic factory.
- A component file can expose a same-name function such as `Navigation()`.
  `FileApplication` loads component files before pages and layouts so these
  named factories are immediately available.
- `aml make:view-component <name>` generates both the component class and its
  same-name factory.

Direct construction remains supported for backward compatibility.

## Testing API

```php
use AML\View\Testing\ViewTest;

ViewTest::render(new CounterPage())
    ->assertSee('Counter')
    ->assertComponent('CounterCard')
    ->assertState('count', 0)
    ->click('Add')
    ->assertState('count', 1)
    ->fill('name', 'AML')
    ->click('Account')
    ->assertRedirect('/account');
```

`ViewTest::assertThrows()` verifies rendering and component exceptions. The
test runtime is self-contained and does not require a browser, server, DOM
extension, or JavaScript. It simulates deterministic local actions; explicit
API requests and full browser behavior remain integration-test concerns.
