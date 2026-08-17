<?php

declare(strict_types=1);

namespace AML\View;

use AML\Engine\ClientAction;
use AML\Engine\ClientInstruction;
use AML\Engine\ApiAction;
use AML\Engine\StateRef;
use AML\Engine\StateNamespace;

class Element implements View
{
    /** @var list<View> */
    private array $children;

    /** @var array<string, scalar|null> */
    private array $attributes = [];

    /** @var array<string, string> */
    private array $styles = [];

    /** @var array<string, ClientInstruction> */
    private array $clientEvents = [];

    /** @var list<array{state: string, class: string, equals: mixed}> */
    private array $reactiveClasses = [];

    /** @var array{state: string, equals: mixed}|null */
    private ?array $visibilityRule = null;

    /** @var array{state: string, equals: mixed}|null */
    private ?array $disabledRule = null;

    /** @var list<array{type: string, value?: int, message: string}> */
    private array $validationRules = [];

    /** @var array{request: array<string, mixed>, debounce: int, message: string}|null */
    private ?array $remoteValidation = null;

    public function __construct(private string $tag, View ...$children)
    {
        $this->children = $children;
    }

    public function attribute(string $name, string|int|float|bool|null $value): static
    {
        if (!preg_match('/^[a-zA-Z_:][a-zA-Z0-9_.:-]*$/', $name)) {
            throw new \InvalidArgumentException("Invalid HTML attribute: {$name}");
        }

        $this->attributes[$name] = $value;
        return $this;
    }

    public function class(string ...$names): static
    {
        $classes = preg_split('/\s+/', trim((string) ($this->attributes['class'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($names as $name) {
            if ($name === '' || preg_match('/\s/', $name) === 1) {
                throw new \InvalidArgumentException('A class name must be a non-empty HTML token.');
            }
            $classes[] = $name;
        }
        $this->attributes['class'] = implode(' ', array_values(array_unique($classes)));
        return $this;
    }

    public function style(string $name, string|int|float $value): static
    {
        if (!preg_match('/^[a-z-]+$/', $name)) {
            throw new \InvalidArgumentException("Invalid CSS property: {$name}");
        }

        $this->styles[$name] = (string) $value;
        return $this;
    }

    public function padding(int $pixels): static
    {
        return $this->style('padding', max(0, $pixels) . 'px');
    }

    public function spacing(int $pixels): static
    {
        return $this->style('gap', max(0, $pixels) . 'px');
    }

    public function gap(int $pixels): static
    {
        return $this->spacing($pixels);
    }

    public function center(): static
    {
        return $this
            ->style('align-items', 'center')
            ->style('justify-content', 'center');
    }

    public function disabled(bool $disabled = true): static
    {
        return $this->attribute('disabled', $disabled)->attribute('aria-disabled', $disabled ? 'true' : null);
    }

    public function status(ActionStatus $status, ?string $message = null): static
    {
        $this->attribute('data-aml-status', $status->value);
        if ($status === ActionStatus::Loading) {
            $this->disabled()->attribute('aria-busy', 'true');
        }
        if ($status === ActionStatus::Success || $status === ActionStatus::Error) {
            $this->attribute('aria-live', 'polite');
        }
        if ($message !== null) {
            $this->children = [textValue($message)];
        }
        return $this;
    }

    public function loadingLabel(string $label): static
    {
        return $this->attribute('data-aml-loading-label', $label);
    }

    public function nativeNavigation(bool $enabled = true): static
    {
        return $this->attribute('data-aml-native-navigation', $enabled ? 'true' : null);
    }

    public function preserve(string $key): static
    {
        if (preg_match('/^[a-zA-Z0-9_.-]{1,120}$/', $key) !== 1) {
            throw new \InvalidArgumentException('A preserved form key must contain only letters, numbers, dots, dashes, or underscores.');
        }
        return $this->attribute('data-aml-form-preserve', $key);
    }

    public function transition(string $name = 'fade', int $milliseconds = 180): static
    {
        if (!in_array($name, ['fade', 'slide', 'scale'], true)) {
            throw new \InvalidArgumentException("Unsupported AML transition: {$name}");
        }
        if ($milliseconds < 0 || $milliseconds > 10_000) {
            throw new \InvalidArgumentException('Transition duration must be between 0 and 10000 milliseconds.');
        }
        return $this->attribute('data-aml-transition', $name)
            ->attribute('data-aml-transition-duration', $milliseconds);
    }

    public function component(string $name): static
    {
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_.-]*$/', $name)) {
            throw new \InvalidArgumentException("Invalid AML component name: {$name}");
        }
        return $this->attribute('data-aml-component', $name);
    }

    public function showWhen(string|StateRef $state, mixed $equals = true): static
    {
        [$name, $initial] = self::reactiveState($state);
        $this->visibilityRule = ['state' => $name, 'equals' => $equals];
        if ($state instanceof StateRef) $this->attribute('hidden', $initial !== $equals);
        return $this;
    }

    public function classWhen(string|StateRef $state, string $class, mixed $equals = true): static
    {
        if ($class === '' || preg_match('/\s/', $class) === 1) {
            throw new \InvalidArgumentException('A reactive class must be a non-empty HTML token.');
        }
        [$name, $initial] = self::reactiveState($state);
        $this->reactiveClasses[] = ['state' => $name, 'class' => $class, 'equals' => $equals];
        if ($state instanceof StateRef && $initial === $equals) $this->class($class);
        return $this;
    }

    public function disabledWhen(string|StateRef $state, mixed $equals = true): static
    {
        [$name, $initial] = self::reactiveState($state);
        $this->disabledRule = ['state' => $name, 'equals' => $equals];
        if ($state instanceof StateRef) $this->disabled($initial === $equals);
        return $this;
    }

    public function click(ClientInstruction $handler): static
    {
        return $this->onClick($handler);
    }

    public function onClick(ClientInstruction $handler): static
    {
        $this->clientEvents['click'] = $handler;
        return $this;
    }

    public function updates(string $property, int|float $by = 1): static
    {
        return $this->onClick(ClientAction::increment($property, $by));
    }

    public function bindClient(string $property): static
    {
        $property = StateNamespace::qualify($property);
        return $this
            ->attribute('data-aml-model', $property)
            ->attribute('data-aml-bind', $property);
    }

    public function required(string $message = 'This field is required.'): static
    {
        $this->validationRules[] = ['type' => 'required', 'message' => $message];
        return $this->attribute('required', true);
    }

    public function minLength(int $length, ?string $message = null): static
    {
        if ($length < 0) throw new \InvalidArgumentException('Minimum length cannot be negative.');
        $this->validationRules[] = [
            'type' => 'min-length',
            'value' => $length,
            'message' => $message ?? "Use at least {$length} characters.",
        ];
        return $this->attribute('minlength', $length);
    }

    public function email(string $message = 'Enter a valid email address.'): static
    {
        $this->validationRules[] = ['type' => 'email', 'message' => $message];
        return $this->attribute('inputmode', 'email');
    }

    public function validateWith(
        ApiAction $request,
        int $debounce = 400,
        string $message = 'This value is not available.',
    ): static {
        if ($debounce < 0 || $debounce > 10000) {
            throw new \InvalidArgumentException('Validation debounce must be between 0 and 10000 milliseconds.');
        }
        $decoded = json_decode($request->json(), true, 512, JSON_THROW_ON_ERROR);
        $this->remoteValidation = ['request' => $decoded, 'debounce' => $debounce, 'message' => $message];
        return $this;
    }

    public function render(RenderContext $context): string
    {
        $attributes = $this->attributes;
        if ($this->styles !== []) {
            $attributes['style'] = implode(';', array_map(
                static fn (string $name, string $value): string => "{$name}:{$value}",
                array_keys($this->styles),
                $this->styles,
            ));
        }

        foreach ($this->clientEvents as $name => $action) {
            $attributes['data-aml-client-' . $name] = $action->json();
        }
        if ($this->visibilityRule !== null) {
            $attributes['data-aml-show-when'] = json_encode($this->visibilityRule, JSON_THROW_ON_ERROR);
        }
        if ($this->reactiveClasses !== []) {
            $attributes['data-aml-class-when'] = json_encode($this->reactiveClasses, JSON_THROW_ON_ERROR);
        }
        if ($this->disabledRule !== null) {
            $attributes['data-aml-disabled-when'] = json_encode($this->disabledRule, JSON_THROW_ON_ERROR);
        }
        if ($this->validationRules !== []) {
            $attributes['data-aml-validate'] = json_encode($this->validationRules, JSON_THROW_ON_ERROR);
        }
        if ($this->remoteValidation !== null) {
            $attributes['data-aml-validate-api'] = json_encode(
                $this->remoteValidation,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        }

        $htmlAttributes = '';
        foreach ($attributes as $name => $value) {
            if ($value === false || $value === null) {
                continue;
            }

            $htmlAttributes .= ' ' . $name;
            if ($value !== true) {
                $htmlAttributes .= '="' . htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
            }
        }

        $content = implode('', array_map(
            static fn (View $child): string => $child->render($context),
            $this->children,
        ));

        $html = "<{$this->tag}{$htmlAttributes}>{$content}</{$this->tag}>";
        if (in_array($this->tag, ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr'], true)) {
            $html = preg_replace('#></' . preg_quote($this->tag, '#') . '>$#', '>', $html) ?? $html;
        }
        return $html;
    }

    /** @return array{string, mixed} */
    private static function reactiveState(string|StateRef $state): array
    {
        $name = $state instanceof StateRef ? $state->name : StateNamespace::qualify($state);
        return [$name, $state instanceof StateRef ? $state->initial : null];
    }
}
