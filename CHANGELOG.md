# Changelog

All notable changes follow Semantic Versioning.

## Unreleased

## 0.1.0-beta.14 — 2026-10-23

- Serialize completed compiler blocks once per compilation, preserving the
  instruction format and source locations while reducing deep-nesting cost.
  Keep the JSON depth protection and report its precise limit and PHP origin.
  Cover nested conditions/loops and failure recovery in PHP, Core, browser and
  installed-package tests; add actual Safari verification to CI.

- Add native Icon() with an optional Lucide catalogue: inline SVG, reactive names,
  labels and Style values, functional StateRef closures and keyed IList rows.
  Validate inert geometry and retain existing component APIs.
  Reject negative size/stroke values at initial rendering and in reactive
  bindings, retain the last valid dimensions on error and accept zero.
  Preserve State dependencies used only by nested if/elseif/else in read-only
  Icon closures, including local StateRef, size and labels. Keep static closures
  working and hide showWhen SVGs before Engine starts without replacing styles.
  The event instruction format and existing component APIs remain unchanged.

- Complete IList row field bindings across display, style, safe links, child
  props, nested lists and editable controls. Resolve writes by stable keys,
  support explicit editable projection sources, preserve child state on reorder
  and release it on removal. Bind presentation options to State and retain the
  last valid list on invalid direct or calculated data; keep legacy Each behavior.

- Support reactive calculated IList sources, including filters, maps, helpers,
  local programs and closure sources. Render initial projections with the shared
  bounded PHP interpreter; track source dependencies in Engine. Accept StateRef
  captures in frontend closures while preserving ownership, nested captures,
  type validation, ordinary value captures and read-only display contracts.

- Add fluent `IList()` for native unordered/ordered lists, numbering, markers,
  static views and reactive arrays, direct State binding, custom row content,
  empty placeholders and accessible reordering. Reuse Element styling and
  the existing collection runtime; keep existing list factories compatible.

- Discover function pages and components returning `View`, with optional `body()`
  grouping, local State, typed/default/named props and per-instance effects.
  Reuse class render/navigation lifecycles, preserve reactive props across both
  forms, and retain original PHP source locations and CSS module paths. Keep
  existing class pages, layouts and handwritten component factories compatible.

- Add component-local `State()` references with inferred or explicit types,
  nullable/union checks and isolated lifetimes. Accept references in button
  mutation helpers, forms and reactive styles; retain their owner when passed
  to child components. Keep property attributes and existing hook APIs intact.

- Harden HTML tag names, URL attributes and script source setters. Reject active
  document attributes while retaining ordinary links and passive media previews.
  Add regression coverage for server rendering and the browser security lab.

- Generate same-name component functions from their constructors during file
  application discovery, preserving types, defaults, named/variadic arguments,
  references, inheritance and reactive State forwarding. Keep handwritten
  factories and avoid executing object defaults during discovery or binding.

- Allow component events to assign directly to typed constructor props backed
  by parent State, preserving nested forwarding, instance isolation and errors.
  Give mutable promoted literal/default props instance-local values so the same
  named factory works with `Carte()`, `Carte('Hello')` and `Carte($this->name)`.

- Finalize existing Style behavior: preserve reactive `!important`, clear empty
  declarations without masking other CSS rules, reject unbalanced values and
  retain the last valid value on update failures. Apply CSS-wide keywords to
  their target declarations and document reactive keyframe limits. Synchronize TestView style
  values, priorities and recoverable diagnostics without simulating CSS layout.

- Add PHP Style `supports()`, `container()`, native form/structural selectors,
  pseudo-elements and isolated `keyframes()`, preserving reactive property
  values throughout nested variants and animation steps. Share animation name
  scoping with CSS modules. Preserve `<` in CSS range queries while escaping
  HTML style end tags.

- Bind Style property values automatically to State across direct modifiers,
  fluent styles, named maps, compositions and scoped CSS custom properties.
  Preserve initial HTML, immutable reuse and instance isolation; update hover,
  media and conditional content in place, with recoverable style diagnostics.

- Add immutable `Style::create()` / `Style::make()` fluent styles, named PHP
  style maps, ordered PHP style composition and local `.style.css` / `.module.css`
  modules. Preserve direct modifiers and `style(property, value)`. Scope module
  classes and keyframes; collect CSS once per render, including conditional
  templates, and keep it with the page through client navigation.

- Add Picker and Toggle with existing native form behavior, natural State bindings
  and child component writes. Add Calendar with civil-date selection, keyboard
  navigation, English/French labels, native validation and min/max/step rules.
- Add a styled `/controls` application page and document the form contracts;
  require Engine `^0.1.0-beta.10` for the calendar runtime.

- Make CSS imports control bundle inclusion: exclude unimported stylesheets,
  retaining global base CSS and themes. Collect state imports without rendering
  states, deduplicate shared files, and report missing imported stylesheets.
  Existing applications must explicitly import non-global CSS; removing the
  final import takes effect after reload or production rebuild.
- Restore status-owned disabled and accessibility attributes when leaving Loading;
  preserve explicit disabling. Apply chained Select values to server-rendered
  options without mutating shared option elements.
- Document native component method contracts, loading feedback, form values,
  event replacement and validation. Add PHP and browser regression fixtures for
  the existing APIs.

## 0.1.0-beta.13 — 2026-10-16

- Correct PHP 8.2/8.3 compatibility for negative array indices, Generator
  introspection and named magic-method closures; preserve live by-reference
  array traversal in ViewTest. Preserve the compiling server’s ICU default
  currency semantics for noncurrency NumberFormatter styles. Verify all three
  supported PHP versions in CI.

- coordinate distribution with CLI `1.7.0-beta.45`, View `0.1.0-beta.13` and
  Engine `0.1.0-beta.9`; the CLI release check creates a real application from
  ZIP packages and verifies its development and production runtimes.

- Run installed frontend application checks in CI with both Engine runtimes on Chromium, Firefox and WebKit, using candidate Composer distributions and a configurable matching Engine revision.

- Stabilize existing rendering: preserve ordinary child components when their parent has no readable source, cache lexical imports with content-based invalidation, and avoid recompiling static labels. Add a reproducible four-scenario render benchmark.
- Fix AML Tasks duplicate titles, submitted form validation, shared dashboard counts and locale races during navigation. Load the current local Engine as a cacheable external asset instead of embedding it in every page.
- Exercise complete task, reservation and catalogue workflows, including persistence, navigation, invalid input and recovery; add source-less parent and import-invalidation regressions without extending the frontend language.

- Bind child form controls back to forwarded parent State, including nested children, named spreads, nullable values and scalar array fields, without merging child State scopes. Reject calculated and computed form targets explicitly.
- Recognize direct component construction and simple factories; support fully unpacked Component() arguments. Project inherited constructors, named helper methods, dynamic declared-property access, early returns and PHP 8.4 hooks in isolated temporary objects, with type-error recovery and CSP browser tests.

- Preserve reactive helper arguments through unpacking, named keys, changing variadic tails and explicitly assigned function callables.
- Forward parent inputs through `Component()` constructors into promoted and locally derived properties, nested child displays, templates and event reads, while preserving child State isolation.
- Validate derived property types on updates, preserve the last valid display on failure, and recover after correction. Constructors run only once on PHP.
- Add PHP/ViewTest and CSP browser coverage for component composition.

- Preserve reactive dependencies through mutable local calculations and view helper arguments; support mixed variadic spreads and identifiable callable factories. Keep failed display values and expose located render diagnostics in ViewTest. Add ten ctype functions and five hash helpers, with PHP differential and CSP browser coverage.
- Extend seven frontend language areas: dynamic operands and symbols, seeded random generators, bounded advanced regex, dates/Intl/Unicode validation, reference destructuring/generators/properties, hooks/reflection/serialization/SPL, and named eval classes/enums/namespaces. Add native differential and CSP browser coverage and document exact adapter limits in View's extensions-langage guide.

- Add URL/Base64 helpers and filter_var_array, grapheme-aware reactive text, Normalizer and a bounded NumberFormatter adapter. Compile dynamic new/static targets and full nullsafe receiver chains, including eval and generators. Add native differential and CSP browser coverage, a combined profile example and an exact-scope guide.

- Align dynamic eval bitwise assignments with compiled events; correct invalid operand handling, integer result metadata and arrow-function parsing inside arrays and match arms.
- Interpret JsonSerializable hooks, add format-based date parsing and diagnostics, calendar intervals/diffs/periods, and closed-catalogue callable/validation utilities. Add differential tests, CSP browser coverage, exact scope guides and a reservation example covering leap dates, DST and invalid input.
- Compare DateTime and DateTimeImmutable by timestamp and microseconds across timezones, including date arrays, searches, regular sorting and min/max; reject ambiguous comparisons of other adapted date objects explicitly.
- Consolidate the frontend compatibility matrix and generate its native function list directly from Engine; check documentation drift in CI. Correct earlier guides that still excluded service objects, iterators and generators.
- Add JSON codecs and error state, opaque stdClass values, a bounded PCRE subset and DateTime/DateTimeImmutable/DateTimeZone adapters with explicit parsing/timezone limits. Add a combined JSON/catalogue example and native differential/browser coverage.
- Extend SPL with ArrayIterator/ArrayObject and iterator_* helpers, Traversable unpacking, reference aliases, referenced arrays, by-reference foreach and function returns. Preserve State rollback independently of live reference cells.
- Compile user enums, additional logical/bitwise operators, function static locals, argument introspection, computed method names and method array/string callables. Preserve component isolation and document remaining advanced-PHP boundaries.

- Interpret lazy generators (`yield`, `yield from`) in services, closures and eval, including suspended expressions, send/throw/getReturn, typed keys and bounded resumption across component clicks. Preserve console output, State rollback and PHP source diagnostics; add differential and CSP browser tests and a French catalogue example. Automatic garbage-collection finalization remains outside the frontend contract.

- Interpret Iterator/IteratorAggregate foreach in browser events and service functions, with Traversable/iterable contracts, is_iterable (290 native helpers), lazy traversal, typed keys, control flow and bounded delegation. Validate anonymous interface contracts and preserve rollback, source diagnostics and console behavior; add native differential tests, browser coverage and a French example/guide.

- Support Countable and ArrayAccess service collections, count/sizeof/is_countable (289 native helpers), offset reads/writes, quiet access, destructuring and reference semantics. Preserve PHP indirect-write notices and argument evaluation; add differential tests, CSP browser coverage and a French shopping-cart example/guide.

- Add twelve scope-aware object introspection builtins (288 native functions total) and __debugInfo console views. Preserve inherited visibility, method spelling/order, aliases, named/spread arguments, callbacks and eval; normalize debug hook failures into catchable frontend errors. Add differential tests, CSP browser coverage and a French example/guide.

- Support anonymous frontend classes and intersection/DNF type contracts, with constructors, typed/promoted properties, inheritance/interface validation, component-scoped statics and fresh anonymous identities per eval. Add differential PHP/ViewTest/Engine tests and browser coverage.

- Implement nine service magic methods for properties, instance/static method dispatch, invokable objects, Stringable conversions and cloning. Preserve visibility, strict types, named arguments, indirect-write notices, recursion guards and readonly clone behavior; add native differential tests, browser coverage and a profile example.

- Support typed service static properties with self/parent/late-static access, inheritance, visibility, references and eval. Keep storage isolated per component across handlers; reset on component recreation and retain static side effects after event errors. Add native PHP/ViewTest/Engine comparisons and Chrome lifecycle coverage.

- Compile and interpret local service objects: constructors, typed/readonly properties, visibility, inheritance, interfaces, traits, static dispatch, method closures, shallow cloning and existing-class eval. Preserve identity and float metadata, reject object State, and verify native PHP/ViewTest/Chrome behavior.

- Compile bounded static and dynamic eval(), preserve caller locals/references and State, isolate return, retain function declarations and catchable diagnostics; add a French guide and browser example.

- Add print_r/var_export (276 native functions total), console-only output or returned strings, pure reactive display, bounded nested formatting and native callbacks; preserve compiled negative zero literals.

- Add all 14 procedural BCMath functions (274 native functions total), exact bounded decimal arithmetic, eight bcround modes, typed local RoundingMode values and event-local bcscale; verify native PHP parity, pure reactive totals, source diagnostics and error rollback.

- Add 28 UTF-8 mbstring functions (260 native functions total), deterministic PHP-generated Unicode tables, bounded code-point operations, named arguments and native callbacks; verify PHP/ViewTest/Engine parity and reactive browser updates with accents and emoji.

- Expand the native catalogue to 232 math, string, array and scalar helper functions; share reflected signatures, named/spread/reference arguments and native callbacks across ViewTest and Engine. Separate runtime modules, retain pure reactive display rules, add differential PHP tests and a browser example.

- Compile `try/catch/finally`, throw expressions, standard and inherited custom exceptions, Throwable contracts/getters and PHP-configured assertions; preserve caught State changes and simulate propagation in ViewTest. Add level 10 service examples, native-PHP comparisons and browser trace/mode checks.

- Connect component-scoped `FileInput` controls to local browser text/JSON/CSV imports and downloads through Engine `BrowserFiles`; add filesystem diagnostics, a persisted-data example and a level 9 guide with explicit browser boundaries.

- Share reactive text handling across native text/label components: direct state, concatenation, PHP interpolation, repeated placeholders, multiline strings and read-only formatters; preserve SSR escaping, option markup and accessible overlay names. Add compiler diagnostics and PHP/Chrome coverage.

- Compile 25 string, validation and numeric builtins with typed/named/spread arguments, replacement counters and PHP source diagnostics; add browser input/menu and integer-cent examples, and explicit CLI-only diagnostics.
- Verify levels 7–8 against native PHP, ViewTest and Chrome, including terminal streams/exit status; resolve service names from first-class method callables and preserve lexical namespaces of PHP 8.4 closures.

- Compile user functions from loaded services, real function frames and returns, typed/default/named/variadic parameters, references, closures and bounded recursion; compare results and failures with native PHP.

- Compile dynamic bounded loops, switch/match, empty/isset, PHP array literals and mutations, destructuring, spread, collection callbacks and console-only echo; add native-PHP parity tests.

- Compile PHP 8 operator precedence, power, strict/loose comparisons, lazy logic/ternaries/coalescing, assignment expressions and pre/post increments; verify against native PHP.
- Enforce scalar state contracts, nullable and union types with PHP strict/weak assignment modes; report PHP source locations for compiled instructions.
- Resolve aliased/grouped constant imports and loaded class constants; compare body diagnostics and frontend failures against native PHP.
- compile invocation-local variables with value snapshots, branch updates and
  isolated scalar captures; keep bounded-loop control assignments in execution order;
- support scalar `gettype()`, console `var_dump()`, file/component constants,
  interpolated strings and PHP escape sequences;
- retain integral floats in manifests and align scalar conversions with PHP;
- require Engine beta.9 and verify compiler/runtime results against native PHP.

## 0.1.0-beta.12 — 2026-10-13

- add natural PHP handlers for keyboard, mouse and drag-and-drop events;
- compile safe `isset()`, `array_search()`, `array_key_exists()`, scalar casts,
  rounding and numeric bounds for reactive frontend closures;
- complete PHP-native collection loops, reusable handlers, asynchronous request
  state and client-only scripts without executing arbitrary PHP in the browser;
- retain the existing advanced forms, effects and isolated component APIs
  without introducing duplicate abstractions.

## 0.1.0-beta.11 — 2026-10-12

- Allow every `#[State]` property to bind directly through
  `Text($this->value)` and `Input()->value($this->value)`, while ordinary PHP
  properties remain static and existing explicit state APIs remain compatible.
- Add top-level `import('pages/home.css')` declarations that securely associate
  local stylesheets with their page, component, layout, or state source file.
- Keep the existing deterministic global stylesheet bundle for navigation and
  backwards compatibility while exposing deduplicated ownership metadata.

## 0.1.0-beta.10 — 2026-10-12

- Allow hook-style `Effect(function () {...})` closures to emit the explicitly
  allowlisted `Console::log()`, `Console::warning()` and `Console::error()`
  browser actions while continuing to reject arbitrary static PHP calls.

## 0.1.0-beta.9 — 2026-10-12

- Added safe reactive state interpolation in `Text()`, including nested paths
  and escaped literal braces.
- Added an initial no-`eval` frontend closure compiler for `onClick()`, covering
  direct state assignment, increment, decrement, `+=` and `-=` while rejecting
  arbitrary PHP execution.
- Added safe `if`/`elseif`/`else`, boolean-state checks and scalar comparisons to frontend
  event closures.
- Added local frontend variables and arithmetic expressions with PHP precedence,
  parentheses, multiplication, division and modulo.
- Added boolean negation, string concatenation and compound arithmetic assignments
  to natural frontend closures.
- Allowed `if`/`else` conditions to compare calculated local expressions and
  other reactive state values.
- Added safe reactive PHP ternary expressions to frontend closures.
- Added named component methods as reusable first-class frontend event handlers.
- Added concise PHP arrow functions for single-expression frontend handlers.
- Added reactive calculated and conditional text output through safe PHP values
  and `StateRef::choose()`.
- Added `prefixedBy()` and reactive suffix concatenation for readable text output.
- Added PHP-native collection append, `array_pop()` and `array_shift()` syntax to
  safe frontend handlers.
- Form controls now expose fluent `name()`, `type()` and `value()` attributes.
  Passing a `StateRef` to `value()` creates an explicit two-way binding, while
  scalar values remain ordinary non-reactive HTML values.
- Added `submit()`, `onSubmit()`, `onInput()`, `onChange()`, `onFocus()` and
  `onBlur()` using the same restricted PHP frontend closures as `onClick()`.
- `#[Effect('state')]` now accepts a concise single dependency and compiles
  `void` methods written with the safe natural frontend PHP subset.
- Added `OnChange($state, $closure)` for short inline effects while preserving
  named `#[Effect]` methods as the primary reusable API.
- Added hook-style `Effect($closure, $dependencies)` registration before the
  return statement of `body()`, analogous to React while retaining pure PHP.
- Added `ActionState()` with automatic reactive `pending`, `success`, and
  `error` state for frontend closures and client API instructions.
- Added `FormState()` to track dirty, touched, validity, submission and error
  state, including a reset action that restores bound controls.
- Added isolated named `Reducer()` transitions, concise `Context()` and
  `UseContext()` helpers, and allowlisted `Ref()` element operations.
- Standardized public examples on native grouped PHP imports to keep page files
  concise without exposing global functions.
- Updated `ViewTest` to evaluate safe state expressions emitted by frontend
  closures.

## 0.1.0-beta.8 — 2026-10-07

- support safe `plus()` and `minus()` state expressions in `sets()` actions;
- let buttons derive a new value from current state without specialized
  increment/decrement actions;
- retain all existing action shortcuts and advanced Engine APIs.

## 0.1.0-beta.7 — 2026-10-07

- add `$this->state('property')` as a safe, validated shorthand for reactive
  component state;
- add `increments()`, `decrements()`, `sets()` and `toggles()` element actions;
- add the `TextField()` factory and concise `model()` control modifier;
- allow `Button()` and `Action()` to receive an optional client instruction as
  their second argument;
- keep `StateRef` and `ClientAction` fully compatible for advanced use cases.

## 0.1.0-beta.6 — 2026-09-26

- add native semantic, typography, form, list, table, link and media factories;
- add the singular `Alert` component and typed `Console::log()` client action;
- add safe element modifiers and richer page metadata;
- support reactive native progress elements through PHPAML Engine beta 4;
- expand API, security, compatibility and regression-test documentation.

## 0.1.0-beta.5

- Document the complete Composer command for prerelease projects so the root
  package explicitly opts into both View and Engine beta versions.

## 0.1.0-beta.4

- Make a direct Packagist installation resolve PHPAML Engine beta 3 without
  requiring applications to add a separate root stability flag.

## 0.1.0-beta.3

- Add declarative `#[Effect]` methods with dependencies, mount control and debounce.
- Add run, timeout, interval, window and document effect plans.
- Scope effect dependencies and actions to each reusable component instance.
- Add deterministic dynamic-component cleanup, causal cycle detection and
  `latest`, `exhaust`, `queue`, and `parallel` async scheduling.
- Prevent stale API executions from committing result, error, or loading state.
- Add `throttle`, safe `EventRef` listener data, local cleanup plans and runtime
  inspection controls.
- Add accessible `Modal`, `Tabs`, `Accordion`, and declarative `AsyncBoundary`.
- Add sortable data tables, windowed virtual lists, dynamic forms, keyed drag
  and drop collections, and declarative entrance transitions.
- Add nested reactive contexts, persistent theme/locale preferences, query
  parameters, navigation actions, redirects and route-state boundaries.
- Preserve layout, focus, history and reduced-motion transitions during client
  navigation.
- Add accessible toasts, dropdown menus, tooltips, and popovers.
- Add multipart file inputs, conditional and multi-step forms, session draft restoration, keyboard navigation, focus management, and initial ARIA states.
- Add the self-contained `ViewTest` API for rendering, component/text lookup,
  state assertions, local interaction simulation, form filling, redirects, and
  exception assertions without a browser or DOM extension.
- Add the complete responsive AML Tasks reference application and an automated
  smoke suite for its routes, metadata, generated styles and route states.
- Add construction-free `Element()` and `Component()` factories, automatic
  component-file preloading, and same-name custom factories such as
  `Navigation()`.
- Replaced automatic server interactions with the frontend-only PHPAML Engine.
- Removed `BrowserRuntime`, `InteractionKernel`, signed tokens and server event
  closures from the public package.
- Added local state actions, explicit API actions, lifecycle events, client
  routing, reactive presentation and reactive collections.
- Added file-based pages, nested layouts, route states, automatic stylesheet
  bundling, themes and declarative SEO metadata.
- Added synchronous and asynchronous accessible frontend validation with
  same-origin API checks, debounce and stale-request cancellation.
- Added `PageResult` for the initial HTML contract.
- Added `#[Shared]` state across client-routed pages and `#[Persisted]` state
  backed by local or session browser storage.
- Added isolated state namespaces for reusable component instances, typed form
  coercion, cross-tab synchronization, versioned/expiring persistence and
  automatic cleanup of externally removed roots.
- Rejected prototype-polluting state paths and incompatible shared-state types.
- Added keyed collection updates, filtering, sorting, reordering, custom item
  components, nested state paths and atomic state transactions.
- Added declarative persistence migrations, IndexedDB storage, state snapshots
  and local development time travel.
- Added dependency-based frontend computed values, automatic local batching,
  keyed dynamic component state, `When()` and transaction rollback.
- Removed the obsolete `state()`/`StateCycle` API and server-validation
  attributes in favor of `#[State]`, `bindClient()` and frontend rules.

## 0.1.0-beta.2

- Added declarative per-page SEO metadata with canonical, robots, Open Graph,
  and Twitter support.
- Reconciled browser interaction results node by node instead of replacing the
  complete AML root.
- Added automatic file-based routing from `src/views`.
- Added root and nested layouts without a manual registry.
- Added dynamic `[id]` and catch-all `[...slug]` routes.
- Added declarative `states/Loading.php`, `states/Error.php`, and `states/NotFound.php` states.
- Preserved signed route parameters across browser interactions.
- Added browser loading and error fallbacks.
- Documented the `src/views` and `src/server` conventions.

## 0.1.0-beta.1

- Initial declarative component, page and layout API.
- Typed `#[State]` and cached `#[Computed]` properties.
- Signed browser interactions with expiration, replay protection and audience binding.
- Bound forms and declarative validation.
- Grid, stack, image, link, spacer and action-status components.
- Router parameters, `RouterView()` and layout `Slot()`.
- Complete demonstration application and automated test suite.
