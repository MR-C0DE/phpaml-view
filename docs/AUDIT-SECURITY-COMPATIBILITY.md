# AML View and PHPAML Engine security and compatibility audit

Audit date: 2026-08-17

## Scope

- AML View rendering and state manifests;
- PHPAML Engine local actions, collections and nested state;
- API and navigation boundaries;
- local, session and IndexedDB persistence;
- migrations, diagnostics and time travel;
- demonstration application and CLI generation smoke test.

## Validated protections

- Text, attributes, metadata and initial collection values are escaped.
- Custom collection fields are assigned with `textContent`, not interpreted as HTML.
- State and migration paths reject `__proto__`, `prototype` and `constructor`.
- Nested client action data rejects reserved prototype-related keys recursively.
- Explicit API actions require same-origin absolute paths and runtime requests
  verify the resolved origin again.
- Async requests are aborted when their root is removed.
- Corrupt, expired, newer or unmigratable persisted state falls back safely and
  emits an explicit diagnostic event.
- State history is disabled by default and must be enabled on a root with
  `data-aml-history` or `PageResult::rootHtml(diagnostics: true)`.
- CSP nonces are validated and supported by `EngineRuntime::script($nonce)`.
- Effects validate dependencies, timers, events and concurrency strategies.
- Dynamic component effects receive isolated runtimes and deterministic cleanup.
- Stale effect requests cannot overwrite current result, error, or loading state.
- Rapid and debounce-delayed self-triggering cycles are blocked without treating
  external user updates as cycles.
- Event references expose only sanitized snapshots; cleanup rejects API actions.
- Effect inspection returns copied status, and pause/resume/manual-run controls
  do not expose mutable runtime objects.

## Findings corrected during the audit

1. Nested updates now persist and synchronize their owning parent state.
2. Associative PHP arrays remain objects after frontend type coercion.
3. Collection keys and migration paths are validated before rendering.
4. Conditions inside reusable components now use the component state namespace.
5. API response selection paths are no longer incorrectly component-qualified.
6. History snapshots are opt-in instead of retained in every production root.
7. A JSON clone fallback supports browsers without `structuredClone`.

## Compatibility

- PHP requirement: PHP 8.2 or newer.
- AML View `0.1.0-beta.5` is validated with PHPAML Engine `0.1.0-beta.3`.
- The engine requires standard modern browser features including modules of the
  DOM, `fetch`, `AbortController`, `MutationObserver`, Web Storage and optional
  IndexedDB for states that explicitly select it.
- IndexedDB failure does not prevent the server-rendered fallback page from working.
- Classic PHPAML pages remain available when AML View is not selected.

## Validation result

- AML View unit suite: 46 passed, 0 failed.
- AML View integration suite: 4 passed, 0 failed.
- PHPAML Engine unit suite: 18 passed, 0 failed.
- CLI AML View smoke suite: passed.
- Browser scenarios: nested transactions, rich collections, isolated roots,
  history restoration, IndexedDB migrations, corrupt storage and cross-tab sync passed.
- Reactivity stress scenarios: 1,000 keyed items, computed propagation,
  conditional rendering, automatic batching, dynamic state isolation and
  transaction rollback passed.
- Effect scenarios: dependency debounce, timers, listeners, dynamic stateful
  and stateless components, cleanup after removal, latest-request cancellation,
  API errors, diagnostic restoration, and rapid or sustained cycle protection passed.
- Advanced effect scenarios: event detail references, cleanup on pause,
  pause/resume inspection and throttled bursts passed.

No release or package publication is authorized by this audit.
