# AML View 0.1 specification

## Identity

AML View is PHPAML's optional declarative web interface library. It is not a
mobile toolkit, a React clone or a replacement for classic PHP templates.

Its design principles are PHP-first authoring, server rendering, semantic HTML,
progressive interaction, MVC integration and explicit security boundaries.

## Stable beta vocabulary

| Purpose | AML View API |
|---|---|
| Mutable local state | `#[State]` |
| Derived value | `#[Computed]` |
| Vertical / horizontal layout | `VStack()` / `HStack()` |
| Overlay / grid | `ZStack()` / `Grid()` |
| Page and reusable layout | `Page` / `Layout` |
| Active layout content | `Slot()` |
| Routed content | `RouterView()` |
| Two-way control binding | `bind($this, 'property')` |
| Validation | `#[Required]`, `#[Email]`, `#[MinLength]` |

## Rendering contract

Text and attributes are escaped by default. Components return a `View` from
`body()`. `#[Computed]` values are cached during one render. `#[State]` is the
only component property persisted in the signed browser snapshot.

## Interaction contract

Only aliases registered by the application can be reconstructed. Interaction
tokens are HMAC signed, expire, are bound to an application-defined audience
and are accepted once. Payloads are size-limited. Every accepted interaction
returns fresh HTML and a new token.

## Compatibility

Version `0.x` follows semantic versioning but may contain documented breaking
changes between minor releases. `1.0.0` will establish the long-term public API.
