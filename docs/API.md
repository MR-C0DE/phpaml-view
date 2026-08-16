# API reference

## Base types

- `View`: renderable contract.
- `Component`: base class for reusable declarative components.
- `Page`: routable component.
- `Layout`: component that renders the active page through `Slot()`.
- `Renderer`: static or interactive rendering entry point.

## State and validation

- `#[State]`: persists a typed property between signed interactions.
- `#[Computed]`: exposes a parameterless method as a cached read-only property.
- `#[Required]`, `#[Email]`, `#[MinLength]`: validation attributes.
- `bind($this, 'property')`: binds a control to a `#[State]` property.

## Components

- Layout: `VStack`, `HStack`, `Column`, `Row`, `Grid`, `ZStack`, `Spacer`.
- Content: `Heading`, `Text`, `Image`, `Link`, `Section`, `MainContent`.
- Actions: `Button`, `Action`, `Alert`.
- Forms: `Form`, `Input`, `TextArea`, `Select`, `Checkbox`.
- Composition: `RouterView`, `Slot`, `Content`.

## Modifiers

- Layout: `gap()`, `spacing()`, `padding()`, `center()`.
- HTML/CSS: `attribute()`, `style()`.
- Events: `onClick()`, `onSubmit()`, `onInput()`, `onChange()`.
- Actions: `disabled()`, `status()`, `loadingLabel()`.

## Routing

`Router::get($pattern, $page, $layout)` registers a GET route. Parameters use
`{name}` segments and are passed to the page factory as an associative array.

## Browser runtime

`InteractionKernel` mounts registered components and handles signed events.
`BrowserRuntime::script($endpoint)` provides the progressive browser transport.
