# API reference

## Base types

- `View`: renderable contract.
- `Component`: base class for reusable declarative components.
- `Page`: routable component.
- `Layout`: component that renders the active page through `Slot()`.
- `Renderer`: initial HTML rendering entry point.

## State and validation

`Text()` recognizes state placeholders while a component is being rendered:

```php
Text('Count: {count}');
Text('User: {profile.name}');
```

The root name must refer to a `#[State]` property. Values are rendered on the
server, escaped, and kept reactive by AML Engine. Doubled braces such as
`{{count}}` render a literal `{count}`.

`onClick()` also accepts a restricted frontend closure:

```php
Button('Update')->onClick(function (): void {
    $this->count += 2;
    $this->message = 'Ready';
});
```

The closure is parsed and never executed by PHP. Version 0.1's initial compiler
accepts `++`, `--`, `=`, `+=` and `-=` on declared `#[State]` properties. Values
may use scalar literals, state properties, local variables, parentheses and
`+`, `-`, `*`, `/` or `%` with normal PHP precedence. Other PHP statements are
rejected rather than serialized or evaluated.
Boolean `!`, string concatenation with `.`, and `*=`, `/=`, `%=` and `.=` are
also accepted.
PHP ternary expressions are accepted when their condition depends on reactive
state; both result branches may contain any supported safe value expression.
`onClick()` also accepts a first-class callable created from a named component
method, such as `$this->increment(...)`. The method body is compiled with the
same restricted grammar and never invoked during rendering.
Single-expression event handlers may use PHP arrow functions, for example
`onClick(fn () => $this->count++)`. Arrow functions remain subject to the same
state-only grammar and never permit arbitrary PHP execution.
`Text()` accepts safe `StateExpression` and `StateConditional` values. A concise
conditional output can use `$this->state('enabled')->choose($then, $otherwise)`;
the browser recalculates it whenever reactive state changes.
`prefixedBy()` places a safe value before a reactive expression, while
`concat()` appends one after it.
Frontend handlers accept `$this->items[] = $value`, `array_pop($this->items)`
and `array_shift($this->items)` for safe state-collection mutations.

Frontend closures also accept `if`/`elseif`/`else` (including `else if`) with a boolean state or a state
comparison using `==`, `===`, `!=`, `!==`, `>`, `>=`, `<` or `<=` and a scalar,
state reference or calculated local expression. Branches may contain the same
supported state mutations.

- `#[State]`: exposes a typed initial value to PHPAML Engine.
- `$this->state('property')`: creates a validated reactive reference without
  importing or manually initializing `StateRef`.
- `value($this->state('property'))`: explicitly connects a form control to
  reactive state; scalar values remain ordinary non-reactive HTML values.
- `name()`, `type()`, `placeholder()`, `autocomplete()`, `autofocus()`,
  `pattern()`, `inputMode()`, `min()`, `max()` and `step()` expose safe native
  field attributes fluently.
- `#[Shared('key')]`: synchronizes state across mounted pages/components.
- `#[Persisted(storage, key, version, expiresAfter, migrations)]`: restores versioned,
  optionally expiring browser state.
- `#[Computed]`: caches a parameterless method for one server render. Supplying
  `dependencies`, `operation` and optionally `separator` also declares its safe
  frontend recalculation.
- `model($this->state('property'))`: lower-level typed control binding.
- `bindClient('property')`: lower-level string-based control binding.
- `required()`, `email()`, `minLength()`: synchronous frontend validation.
- `validateWith(ApiAction $request, ...)`: explicit asynchronous validation.

## Native components

Native status and progress primitives remain declarative and accessible:

```php
use AML\Engine\StateRef;
use AML\View\Alert;

Alert::success('Saved');
Alert::warning('Review this value');

Progress(StateRef::to('completion', $this->completion), 100)
    ->attribute('aria-label', 'Upload progress');
```

`Progress()` accepts a numeric value or a numeric `StateRef`. With a state
reference, PHPAML Engine updates the native `HTMLProgressElement.value`
property rather than replacing its text content. The initial value must remain
between zero and the positive maximum.

- Layout: `VStack`, `HStack`, `Column`, `Row`, `Grid`, `ZStack`, `Spacer`.
- Sections: `Header`, `Navigation`, `Main`, `Section`, `Article`, `Aside`,
  `Footer`, `Search`, `HeadingGroup`, `Details`, `Summary`, `Div`, `Span`,
  `Separator`, `LineBreak`, `NoScript`.
- Text: `Heading`, `Text`, `Paragraph`, `Strong`, `Emphasis`, `SmallText`,
  `MarkedText`, `DeletedText`, `InsertedText`, `Subscript`, `Superscript`,
  `InlineCode`, `CodeBlock`, `Quote`, `BlockQuote`, `Abbreviation`, `Time`,
  `Address`, `KeyboardInput`, `SampleOutput`, `Variable`.
- Lists and tables: `UnorderedList`, `OrderedList`, `ListItem`, `Table`,
  `Caption`, `TableHead`, `TableBody`, `TableFoot`, `TableRow`, `TableHeader`,
  `TableCell`, `ColumnGroup`, `TableColumn`, `DataTable`, `Each`,
  `SortableEach`, `VirtualList`.
- Media: `Image`, `Picture`, `Source`, `Video`, `Audio`, `Track`, `Figure`,
  `FigCaption`, `Iframe`, `Embed`, `ObjectMedia`, `ImageMap`, `Area`, `Canvas`.
- Links: `Link`, `ExternalLink`, `EmailLink`, `PhoneLink`, `DownloadLink`.
- Forms: `Form`, `UploadForm`, `Input`, `EmailInput`, `PasswordInput`,
  `NumberInput`, `DateInput`, `SearchInput`, `HiddenInput`, `FileInput`,
  `RadioInput`, `Checkbox`, `TextArea`, `Select`, `Option`, `Datalist`,
  `Label`, `Fieldset`, `Legend`, `Output`, `Progress`, `Meter`.
- Actions: `Button`, `Action`, the compatible `Alert()` factory, and
  `Alert::success()`, `error()`, `warning()`, `info()`, `loading()`.
- Composition: `Group`, `RouterView`, `Slot`, `Content`, `ThemeProvider`, `ThemeSwitcher`.

`Group(...$children)` renders its children as siblings without producing an
extra HTML element. Use it when a component needs one `View` return value but
does not need a semantic or styled container.

## Modifiers

- Layout: `gap()`, `spacing()`, `padding()`, `center()`, `alignment()`.
- Typography: `fontSize()`, `fontWeight()`, `lineHeight()`, `letterSpacing()`,
  `textAlign()`, `textColor()`.
- Forms: `id()`, `placeholder()`, `autocomplete()`, `required()`,
  `minLength()`, `maxLength()`, `rows()`, `columns()`, `min()`, `max()`,
  `step()`, `readOnly()`, `multiple()`.
- Tables: `scope()`, `colSpan()`, `rowSpan()`.
- Links/media: `target()`, `rel()`, `language()`, `referrerPolicy()`,
  `download()`, `controls()`, `autoplay()`, `muted()`, `loop()`,
  `playsInline()`, `poster()`, `preload()`, `loading()`, `allowFullScreen()`.
- HTML/CSS: `class(...$names)`, `attribute()`, `style()`.
- Events: `onClick()`, `onSubmit()`, `onInput()`, `onChange()`, `onFocus()` and
  `onBlur()` accept a `ClientInstruction` or restricted frontend PHP closure.
- `submit()` marks a button as the explicit form submitter.
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
        ->language('en')->direction('ltr')
        ->favicon('/favicon.svg', 'image/svg+xml')
        ->meta('theme-color', '#ffffff')
        ->structuredData(['@context' => 'https://schema.org', '@type' => 'ProfilePage'])
        ->openGraph(image: 'https://example.com/assets/profile.png')
        ->twitter(image: 'https://example.com/assets/profile.png');
}
```

Use `->noIndex()` for private or temporary pages. `PageMetadata` also supports
typed preloads and renders `lang`/`dir` attributes for the document shell.
Structured data is JSON-encoded with HTML-significant characters escaped and
accepts the request CSP nonce through `render($nonce)`.

## Effects

- `#[Effect('state', runOnMount: true, debounce: 0, throttle: 0, concurrency: 'latest')]`
  marks a parameterless `void` method compiled from the safe frontend PHP subset.
- Multiple dependencies use `#[Effect(['first', 'last'])]` or the compatible
  named form `#[Effect(dependencies: ['first', 'last'])]`.
- Advanced effect methods may return `ClientInstruction` or `EffectPlan`.
- `OnChange($state, $closure, initial: false, debounce: 0, throttle: 0,
  concurrency: 'latest')` registers a short inline effect in `body()`.
- Inline dependencies must be `StateRef` instances; pass an array for multiple
  states. Raw PHP values are intentionally rejected because they no longer
  identify their originating state.
- `Effect($closure, $dependencies, runOnMount: true, debounce: 0, throttle: 0,
  concurrency: 'latest')` registers a hook-style effect before `body()` returns.
  Keep these calls unconditional. PHP permits the `Effect` class and function
  imports to coexist; they occupy separate symbol tables.
- Natural hook closures may emit `Console::log()`, `Console::warning()` and
  `Console::error()`. No other static PHP calls are compiled.
- `ActionState($closureOrInstruction)` returns an executable action exposing
  reactive `pending`, `success`, and `error` state references.
- `FormState()` returns `dirty`, `touched`, `valid`, `invalid`, `submitting`,
  `submitted`, and `error` references. Attach it with `Form(...)->state($form)`,
  wrap submission with `$form->action($instruction)`, and reset with
  `$form->reset()`.
- `Reducer($initial, $transitions)` creates isolated state; each transition
  receives its `StateRef`, and `dispatch($name)` returns the safe instruction.
- `Context($name, $value, $content)` and `UseContext($name, $fallback)` provide
  concise context creation and consumption.
- `Ref()` creates an element reference attached with `->ref($ref)`. Allowed
  actions are `focus()`, `blur()`, `select()`, `play()`, and `pause()`.
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
