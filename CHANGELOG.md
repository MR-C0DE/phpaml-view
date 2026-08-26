# Changelog

All notable changes follow Semantic Versioning.

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
