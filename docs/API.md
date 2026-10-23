# API reference

## Base types

- `View`: renderable contract.
- `Component`: base class for reusable declarative components.
- `Page`: routable component.
- `Layout`: component that renders the active page through `Slot()`.
- `Renderer`: initial HTML rendering entry point.

## Icons (optional Lucide catalogue)

Install the official pinned catalogue in the application with `aml install lucide`.
`Icon(mixed $name): IconElement` renders an inline SVG with the regular Element
and Style API. `size(mixed)` sets CSS width/height, `strokeWidth(mixed)` sets the
stroke thickness, and `label(mixed)` switches from a decorative icon to an
accessible image (`null` restores decoration). Color inherits through currentColor.
Names, labels and styles accept natural State bindings, StateRef and read-only
expressions/closures in class and functional views, including reactive IList rows.

No CDN or provider JavaScript is used. The initial geometry is rendered by PHP;
later name changes fetch only the requested same-origin JSON asset, with a bounded
cache and stale-response protection. Invalid/unavailable data preserves the last
SVG and emits `aml:render-error`. Deploy `public/assets/phpaml/lucide/` with the app.
`aml uninstall lucide` checks PHP references and preserves modified/personal files;
`--force` permits removal despite references. See [the complete guide](fr/icones.md).

## Function pages and components

`FileApplication` discovers PascalCase functions with an explicit `View` return
type in `components/*.php` (including subdirectories) and `pages/**/page.php`.
In `App\Views\Pages\Home`, `function Home(): View` serves the home route;
`function HomePage(): View` is also accepted. An existing `HomePage extends Page`
takes precedence. Existing class layouts, class components and their generated
or handwritten factories remain supported.

```php
namespace App\Views\Pages\Home;

use AML\View\View;
use function AML\View\{body, Button, State, Text};

function Home(): View
{
    $name = State('André');
    return body(
        Text($name),
        Button('Change')->sets($name, 'Marie'),
    );
}
```

`body(View ...$children): GroupView` is optional. It groups siblings without
adding a DOM element or an HTML `<body>`. Returning any `View`, including
`VStack()`, `MainContent()` or another component, works directly.

Each function component call returns a deferred view. Its PHP body runs during
rendering in its own component scope: repeated calls, nested calls and calls
from class components have independent local State and effects. Engine retains
state between events and initializes it anew when navigation recreates the view.
`Effect()` uses the same lifecycle as class components; effect identifiers are
scoped per instance, including effects without dependencies.

PHP parameter names, types, defaults, named arguments and variadics are retained.
Defaults containing objects are evaluated once per call, never during discovery.
Scalar props derived from parent State retain their reactive display/form bindings
when forwarded through functions and classes. To explicitly share and update a
local `State()` reference, declare a `AML\Engine\StateRef` parameter and use the
existing action helpers (`sets()`, `increments()`, etc.). Assigning to an ordinary
PHP parameter does not itself declare or update browser state.

For dynamic file routes, matching parameter names receive route strings. The
optional parameters `array $params`, `array $query` and `?Throwable $error` expose
the existing page routing context. Function pages use default `PageMetadata`;
retain a page class when overriding `metadata()` is needed. Loading/error/404
state files also accept the corresponding `Loading`, `Error`, `NotFound` functions.

Discovery prepares function boundaries in memory without modifying source files
or creating a disk cache. Original filenames, line numbers, `__FILE__`, `__DIR__`,
lexical imports and relative CSS modules are preserved. The declarations must be
loaded through `FileApplication` before direct function calls; an ordinary PHP
`require` does not install these boundaries. Restart persistent workers after
changing declarations, as with classes. Functions must return `View` by value;
lowercase helpers, closures, methods and functions with other return types are
not registered as function components. No JavaScript or Engine module is added.

## State and validation

`State(mixed $initial, ?string $type = null): AML\Engine\StateRef` creates local
frontend state during `body()` construction, including helpers called by it.
Import the function with `use function AML\View\State;`; the `#[State]` attribute
continues to use `use AML\View\State;`.

```php
$count = State(0);                        // int
$name = State('André');                   // string
$optional = State(null, type: '?string');
$selection = State(0, type: 'int|string');

return VStack(
    Text($count),
    Button('+')->increments($count),
    Button('Reset')->sets($count, 0),
    TextField('name', $name),
);
```

The reference is accepted by reactive displays, forms and Style, and by
`increments()`, `decrements()`, `updates()`, `sets()` and `toggles()`.
`ClientAction` targets also accept references, retaining their original owner
when forwarded to a child component. Type inference covers strings, integers,
floats, booleans, arrays and null. Explicit scalar/array unions, nullable types,
`true`, `false` and `mixed` are supported. Values must be browser-safe scalars,
null or arrays of those values. Initial values are checked strictly (an integer
may initialize a float); writes use Engine's existing state type enforcement.
`State(null)` infers null alone: declare a nullable type to allow later values.

State is isolated per rendered component, retained between its client events
and initialized anew when the component is recreated. Each call creates a
distinct state; identity follows construction order. It is not persistent
storage and cannot be declared inside a browser event or outside a component
render. `$count` is a reference object, so `$count++`, `$count = 3` and a closure
capturing it as a scalar are not state updates. Use the action helpers above;
for example `sets($count, $count->plus(1))`. `$count->initial` is only the initial
PHP value. String placeholders such as `{count}` still refer to component
properties; use `Text($count)` or `$count->prefixedBy('Count: ')` for local state.
For natural PHP assignments in event closures, keep typed `#[State]` properties.

`Text()` recognizes state placeholders while a component is being rendered:

```php
Text('Count: {count}');
Text('User: {profile.name}');
```

It also recognizes a direct `#[State]` property and keeps its typed value
reactive without a placeholder or explicit reference:

```php
Text($this->count);
Input()->value($this->name);
```

The second form is a two-way form binding. An unmarked PHP property remains a
normal server-rendered value. The association is compiled from the PHP source;
it is never guessed by comparing runtime values.

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
- `value($this->property)`: naturally connects a direct `#[State]` property to
  reactive state; unmarked scalar properties remain ordinary HTML values.
- `value($this->state('property'))`: explicit equivalent retained for advanced
  expressions and backwards compatibility.
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
- HTML/CSS: `class(...$names)`, `attribute()`, `style(property, value)` or
  `style(Style|array)`. `Style::create()` / `Style::make()` build immutable fluent
  styles; `Style::create(namedDefinitions)` returns a collection;
  `Style::create('home.style')` loads a local CSS module relative to the calling
  PHP file. Style values bind automatically to State, including calculations,
  interpolation, read-only closures and forwarded component inputs. Local CSS
  modules receive State through explicit CSS custom-property values. Style
  variants include `media()`, `supports()`, `container()`, `focusWithin()`,
  `checked()`, `valid()`, `invalid()`, `firstChild()`, `lastChild()`, `nthChild()`,
  `before()`, `after()` and `placeholder()` alongside existing hover/focus states.
  `keyframes(name, frames)` scopes local animation names and accepts reactive
  declarations per step. See [Style API and scope](fr/styles.md) for composition,
  units, conditions, animation limits and rendering contracts.
- Events: `onClick()`, `onSubmit()`, `onInput()`, `onChange()`, `onFocus()` and
  `onBlur()` accept a `ClientInstruction` or restricted frontend PHP closure.
- `submit()` marks a button as the explicit form submitter.
- Actions: `disabled()`, `status()`, `loadingLabel()`.

See the [native component method guide](fr/composants-natifs.md) for element-specific
behavior and examples. A later handler replaces the earlier handler for the same
event. `Button()->value()` sets its submitted value, not its label. `loadingLabel()`
temporarily replaces a triggering button's label during API/Files work, including
a form submitter; it preserves reactive children and restores the content on
completion. `status()` configures the PHP-rendered status; it does not subscribe
to an asynchronous action. Visibility, reactive disabling and pending work each
retain their own disabled state.

Radio models keep each option's value and update its checked state. A multiple
select model reads/writes an array of strings. `Select()->value()` also updates
the initial HTML selection. Form validation respects native constraints and
disabled fieldsets, keeps existing `aria-describedby` help and uses distinct
error IDs for controls with repeated names.

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

Declare `import('pages/home.css')` at the top level of a view file to include
that stylesheet; paths are relative to `stylesheets/`. Only imported files,
`stylesheets/base.css`, and CSS under `themes/` enter `FileApplication::styles()`.
The bundle remains global across routes, includes state imports before those
states are rendered, and deduplicates shared files. Removing the last import
removes its CSS on the next reload/build. `stylesheetImports()` lists owners.

## Picker, Toggle and Calendar

`Picker(string $name, array $options, string|int|StateRef|null $selected = null)`
retains Select's options, State bindings and native validation.
`Toggle(string $name = '', bool|StateRef $checked = false, string $value = '1')`
uses a native checkbox with the switch role and a boolean State.
`Calendar(string $name = '', string|StateRef|null $value = null, string $locale = 'en', int $firstDayOfWeek = 1)`
adds an inline month grid to a native date field. It accepts civil dates in
`YYYY-MM-DD` format, min/max/step, native required validation, EN/FR locales,
and keyboard day/month/year navigation. Empty input writes `''` into State.
All three support natural State bindings and forwarded child form properties.

Calendar's inherited attributes target its native input; its wrapper is
`.aml-calendar`. Presentation remains application CSS. See the
[complete guide and styled example](fr/controles.md).

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

- `IList(...$items)` returns `IListView`, an `Element` with native `<ul>` semantics
  by default. Accept scalar/view arguments, one indexed array, or one array
  `StateRef`. A direct class State array also binds automatically.
  `unordered()` / `type('unordered')` and `ordered()` / `type('ordered')` select
  the native tag. Use `start(int)`, `reversed(bool = true)`, `marker(string)`,
  `markerPosition('inside'|'outside')`, and `plain()` for list presentation.
  These options also accept StateRef, natural class State expressions and pure closures.
  Ordered numbering options are retained but omitted while unordered.
  `items(array|StateRef|Closure|DisplayValue)` replaces the source; `label(path)` and `key(path)`
  select record fields (defaults `label`, `id`). `renderItem(Closure)` receives
  `CollectionItem` and returns a View **inside** the generated `li`; existing
  `ListItem()` children are accepted directly by the static factory. Scalar
  templates use `$item->text('value')`. `$item->state(path, fallback)` supplies a
  live field reference for text, styles, links, conditions, props, models and
  event closures. Natural `$item->value(path)` arguments preserve their source
  dependency; ordinary PHP control flow remains initial construction. Editable
  records require unique stable keys. `editable(StateRef)` connects calculated
  projections to their original records explicitly; projected field paths write
  to the same source field, without inverse transformations. `empty(text|View)` declares a placeholder
  that follows array emptiness, and `sortable(bool = true)` enables pointer or
  Alt+Arrow reordering of a State source. Normal Element styles, attributes,
  references and events apply to the list. Invalid options, non-indexed sources
  and duplicate initial keys are rejected. Prefer stable string/integer keys
  for interactive rows. See the [complete list API](fr/composants-natifs.md#listes-avec-ilist).
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
- `Form(...)->preserve('draft-key')` restores ordinary values after navigation or a server error. Password, hidden, file, disabled, payment and one-time-code fields are excluded, as are fields with `autocomplete="off"`. Old draft values for excluded controls are discarded during restoration. Mark other sensitive inputs with `->autocomplete('off')` or avoid preserving the form.

Call `AMLEngine.clearFormDraft('draft-key')` after confirmed server success.
Browser `accept` filters are only hints: applications uploading files must validate
size, extension, MIME type, and content again on the server.

### Local browser files

`AML\Engine\BrowserFiles` provides `readText(?string $input = null)`,
`readJson(?string $input = null)` and `readCsv(?string $input = null, string $separator = ',')`.
Use `->storeIn('state')->errorIn('error')->loadingIn('loading')` and attach the
action directly to a file input's `onChange` or a button's `onClick`. A null input
means the triggering file input; a named input is scoped to its component.

`downloadText($value, $filename = 'document.txt')`,
`downloadJson($value, $filename = 'data.json')` and
`downloadCsv($rows, $filename = 'data.csv', $separator = ',')` download current
`StateRef` / expression values or literal data. They do not overwrite the source.
Options: `maxBytes(1048576)` (maximum 16 MiB), `fileAt(0)` for reads,
`pretty(false)` for compact JSON, `spreadsheetSafe(false)` for raw CSV.
CSV defaults to formula-safe export and imports lists of string cells.

These actions use the browser File API without HTTP requests, validate UTF-8 and
data limits, cancel superseded reads and reads on unmount, and emit
`aml:file-success` / `aml:file-error`. Compose them with `Actions::sequence`;
explicit state transactions reject file actions. State types are enforced and
failed imports preserve previous data. See [the level 9 guide](fr/niveau-9-fichiers-donnees.md).

## Frontend exceptions

Compiled event handlers and synchronous service functions support
`try/catch/finally`, multiple/union catches, `throw` statements and expressions,
Throwable parameter/return types and assertions. Catch matching follows PHP
class inheritance; `Throwable` covers both `Exception` and `Error`.
Custom exceptions must be loaded and inherit standard constructors, methods
and properties unchanged. Throwable objects remain local; State accepts their
message/code or trace data, not the objects themselves.

Caught errors retain earlier State changes; an uncaught synchronous event error
rolls back its State changes. Console output is not rolled back. PHP service
locations, compiled call frames and previous causes appear in the development
overlay when `aml-debug=true`; production disables the overlay without removing
diagnostic metadata. `echo` and `Console` remain browser-console output.
Assertions use the PHP compiler process's `zend.assertions` configuration,
independently of the overlay. See [the level 10 guide](fr/niveau-10-erreurs-exceptions.md)
and [the example](../examples/niveau-10/page.php).

## Declarative factories

- `Element(string $tag, View ...$children)` creates a generic element without
  `new` in the view tree.
- `Component(class-string $class, mixed ...$arguments)` instantiates a custom
  component through the generic factory.
- `FileApplication` automatically defines a same-name function such as `Navigation()`
  for each instantiable component declared under `components/`, before loading pages
  and layouts. The constructor supplies parameter names, types, defaults, references
  and variadics; the return type is the concrete component class. Inherited
  constructors retain their original type/default scope. Object defaults remain lazy.
- Handwritten functions take precedence. Pages, layouts, anonymous/abstract classes
  and non-public constructors are excluded. Functions are generated in memory, with
  no writable cache requirement. Import them with `use function` across namespaces.
- `aml make:view-component <name>` generates only the component class; there is no
  second signature to maintain. Outside `FileApplication`, use a manual factory,
  the generic `Component()` helper or direct construction.

Direct construction remains supported for backward compatibility.

Constructor props that directly forward a State are writable in frontend events:
`$this->a = 'Updated'`, compound assignments and array updates write the originating
State, including through nested named factories. Prop types and the originating
State type are checked before storage; uncaught event errors retain the existing
rollback behavior. A mutable promoted constructor prop with a literal/default
value receives instance-local state. `#[State]` fields retain their independent
state identity. Calculated inputs and hooked properties remain read-only through
this shorthand; direct writes to `readonly` properties fail. Constructor PHP
objects are not live references: these mutations execute in the browser.

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

## Native PHP function catalogue

Events and pure reactive text share the supported Math/String/Array catalogue,
including scalar callback helpers, references and first-class native callables.
See [the generated native catalogue](fr/compatibilite.md#catalogue-natif)
and [per-family boundaries](fr/fonctions-natives.md).

This includes 28 UTF-8 `mb_*` functions; see [Unicode support](fr/unicode.md)
for encoding, PHP version and code-point boundaries.

The catalogue also includes all 14 procedural BCMath functions. See
[exact decimal calculations](fr/bcmath.md) for RoundingMode, scale isolation and limits.

`eval()` is supported in compiled events and synchronous services. Static text is
compiled by View; dynamic text requires Engine’s optional same-origin parser asset.
See [scope, language limits and deployment](fr/eval.md). Reactive display rejects eval.

## Local service objects

Compiled events can instantiate loaded user classes and call their methods, including inheritance, visibility and typed properties. Engine owns these local instances; they cannot enter State. See the [object guide](fr/objets.md) for the supported syntax, eval integration and remaining PHP object limitations.


### Static service properties

Service classes support `Class::$field`, `self::$field`, `parent::$field` and
`static::$field`, including typed/visible fields, inherited or redeclared storage,
reference parameters, array offsets and compiled-class eval. Each component
instance retains its own static storage across handlers and effects. Engine
unmount, dynamic item/conditional recreation and page reload reset it to declared
PHP defaults. Current server static values are never captured. Static side effects
survive event errors even when State changes roll back. Static properties are not
reactive State and require an event to copy their result into State. See the
[service object guide](fr/objets.md) for limits and examples.


### Magic service methods

Compiled services support `__get`, `__set`, `__isset`, `__unset`, `__call`,
`__callStatic`, `__invoke`, `__toString`, `__clone` and `__debugInfo`. Implicit calls use ordinary
function contracts, source diagnostics and call budgets. Virtual array properties
returned by value warn on indirect writes instead of silently persisting them.
`Stringable` is accepted as a contract; strict string parameters still reject
objects. Clone hooks run on the copied object and respect visibility and PHP
version-specific readonly reinitialization. Reference returns from `__get` and
`offsetGet` follow the [reference contract](fr/spl-references.md).
PHP `serialize`/`unserialize` hooks are supported for compiled service objects;
destructors remain unsupported. See [the exact adapter scope](fr/extensions-langage.md).
`JsonSerializable` uses the [JSON adapter](fr/json-regex-dates.md#json). See the
[magic methods guide](fr/methodes-magiques.md).

### Anonymous classes and intersection types

`new class(...)` uses the ordinary service catalogue without server instantiation. Constructors, promoted/readonly properties, methods, inheritance and interfaces use the same runtime checks. `A&B` requires every member; `(A&B)|C` preserves union alternatives. Each eval invocation creates fresh anonymous types. Static fields remain component-scoped. See the [guide and limits](fr/classes-anonymes-intersections.md).

### Object introspection

Object introspection includes `is_object`, `get_class`, `get_parent_class`, `get_called_class`, `class_exists`, `interface_exists`, `method_exists`, `property_exists`, `is_a`, `is_subclass_of`, `get_object_vars` and `get_class_methods`. Member introspection runs in events; the pure `is_object` predicate also supports reactive text. They inspect the compiled service catalogue without autoload, preserve visibility and support named/spread arguments and callbacks. `var_dump()` calls `__debugInfo()` and sends its result only to the console. Hook errors are catchable and use ordinary event rollback, unlike native PHP process termination. See [contracts and limits](fr/introspection.md).

### Validation, dates and JSON services

The [callable and validation guide](fr/utilitaires.md) describes `is_callable`,
`function_exists`, `call_user_func*` and the supported `filter_var` inputs.
The [date guide](fr/dates-avancees.md) covers format-based parsing, diagnostics,
calendar intervals, differences and periods. [JsonSerializable](fr/json-regex-dates.md#json)
hooks execute as compiled methods. The [reservation example](fr/reservation.md)
combines these adapters and displays scalar results through State.

### URL, Unicode display and dynamic classes

See the [exact scope and examples](fr/web-unicode-dynamique.md) for URL/Base64
helpers, `filter_var_array`, grapheme functions, `Normalizer`, the `NumberFormatter`
adapter, dynamic `new`/static targets and nullsafe receiver chains. The number
formatter runs in events; pure grapheme and normalization functions also support
reactive text. PHP rendering requires ext-intl for these Unicode adapters.

### Calculated IList sources and captured StateRef

`IList(fn () => array_filter($items, ...))` and `IList()->items(fn () => ...)`
accept read-only compiled array projections, with initial HTML and dependency
tracking. Natural calculations on class State properties remain supported.
Filter keys are normalized in iteration order; explicit record keys preserve
DOM identity. Sortable lists still require a direct writable State source.

Frontend event/effect closures can capture StateRef handles with `use ($state)`
or arrow syntax. Reads and writes retain the owning component and type rules,
including nested closures. Ordinary captures and existing action APIs retain
their value semantics. Display closures can read these handles but cannot mutate
them. See the French [list guide](fr/composants-natifs.md#listes-avec-ilist).
