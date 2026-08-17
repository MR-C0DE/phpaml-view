# AML View 0.1 specification

## Identity

AML View is PHPAML's optional declarative web interface library. It is not a
mobile toolkit, a React clone or a replacement for classic PHP templates.

Its design principles are PHP-first authoring, initial HTML rendering, semantic
HTML, frontend execution, MVC integration and explicit API boundaries.

## Stable beta vocabulary

| Purpose | AML View API |
|---|---|
| Mutable local state | `#[State]` |
| Shared state | `#[State, Shared('key')]` |
| Browser persistence | `#[State, Persisted('local'|'session'|'indexeddb')]` |
| Derived value | `#[Computed]` |
| Browser effect | `#[Effect]` returning `Effects::*()` |
| Vertical / horizontal layout | `VStack()` / `HStack()` |
| Overlay / grid | `ZStack()` / `Grid()` |
| Page and reusable layout | `Page` / `Layout` |
| Active layout content | `Slot()` |
| Routed content | `RouterView()` |
| Two-way control binding | `bindClient('property')` |
| Validation | `required()`, `email()`, `minLength()`, `validateWith()` |

## Rendering contract

Text and attributes are escaped by default. Components return a `View` from
`body()`. `#[Computed]` values are cached during one render. `#[State]` is the
only component property exposed as initial frontend state.

## Interaction contract

Ordinary events and state changes execute in PHPAML Engine without contacting
PHP. Backend communication is never implicit: it must use an explicit
same-origin `Api::*()` instruction. API payloads are always validated again by
the backend. AML View exposes no automatic interaction endpoint.

## Compatibility

Version `0.x` follows semantic versioning but may contain documented breaking
changes between minor releases. `1.0.0` will establish the long-term public API.

## Persistence contract

Persisted values use versioned envelopes. A newer declaration may provide
declarative `rename`, `defaults` and `remove` migrations for every intermediate
version. Missing migrations never guess: the engine emits
`aml:storage-migration-required` and discards the incompatible value.
`indexeddb` is intended for larger state; `local` and `session` remain the
simple synchronous choices.

## Collection contract

`Each()` accepts stable keys, nested labels and an optional item renderer. The
renderer returns ordinary AML View components using `CollectionItem::text()`
for reactive item fields. Browser updates clone an inert template and assign
content with `textContent`; collection data is never interpreted as HTML.
Stable keys also define dynamic component identity and state isolation.

## Complete reactivity contract

PHP-only `#[Computed]` methods are cached for one server render. Frontend
computed values require explicit dependencies and one supported deterministic
operation; AML View never translates or evaluates arbitrary PHP in JavaScript.
Local sequences are batched, explicit transactions roll back on failure, and
computed cycles emit `aml:reactivity-error`. `When()` provides declarative
branch rendering. Keyed collection reconciliation preserves component and DOM
identity across sorting and movement.

## Effect contract

An `#[Effect]` method is parameterless and returns a declarative
`ClientInstruction` or `EffectPlan`. Dependencies are explicit and scoped to
the owning component instance. `runOnMount`, `debounce`, `throttle` and the `latest`,
`exhaust`, `queue`, or `parallel` concurrency strategy are part of the public
manifest. AML View never translates PHP closures into JavaScript.

PHPAML Engine starts effects only after initial persisted state has been
restored. Reruns and unmounts cancel owned timers, listeners and API requests.
Dynamic keyed components receive independent effect runtimes. Restoring a
diagnostic state reruns affected effects. Self-triggered rapid and sustained
cycles are disabled without penalizing external user updates.

Listener actions may read a sanitized browser event snapshot through
`EventRef`. An effect plan may declare one local cleanup instruction; API work
is rejected during cleanup so unmount remains deterministic. Runtime inspection
returns copied status data and exposes explicit pause, resume and manual-run
operations without exposing mutable engine state.
