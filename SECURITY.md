# Security policy

## Supported versions

Only the latest published beta is supported while AML View remains below 1.0.

## Production checklist

1. Serve the application and API over HTTPS.
2. Protect explicit API routes with suitable authentication and CSRF controls.
3. Validate every API payload on the server; browser state is never trusted.
4. Keep PHPAML, AML View and PHPAML Engine updated.
5. Return generic public errors and log technical details server-side.

AML View has no automatic server-interaction endpoint. Local actions run in
the browser, while backend communication is visible through explicit
`Api::get()`, `Api::post()` and related instructions.

`#[Persisted]` uses browser storage and must never contain passwords, session
tokens, private keys or other secrets. Persisted payloads are versioned and may
expire, but remain readable by scripts executing on the same origin. State
paths reject JavaScript prototype segments to prevent prototype pollution.

`#[Effect]` accepts only declarative AML instructions. Event names, state paths,
timer ranges and concurrency strategies are validated before a manifest is
rendered. Effect API calls remain same-origin. Canceled or outdated executions
cannot commit response, error, or loading state. Applications must still apply
authorization, validation, rate limits, and CSRF protection to API routes.

Avoid extremely short intervals for network work. Prefer the default `latest`
strategy, `exhaust` for non-overlapping submissions, or `queue` when every
execution must complete. Use `parallel` only when out-of-order completion is
safe by design.

`EventRef` exposes only a fixed event snapshot and never the DOM event, target,
document, or prototype chain. Custom-event `detail` data is still untrusted
input. Cleanup instructions are restricted to local client actions so an
unmount cannot silently start network work.

Persistent contexts use readable local browser storage and must contain
preferences only, never credentials or secrets. Context names and serialized
values are validated before rendering. Declarative navigation rejects control
characters and URL schemes other than relative, HTTP, and HTTPS destinations.
Only same-origin HTTP(S) pages use frontend fetching; external destinations use
native browser navigation.

## Reporting a vulnerability

Do not open a public issue for an unpatched vulnerability. Use GitHub's private
security advisory feature on the project repository.
