# Changelog

All notable changes follow Semantic Versioning.

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
