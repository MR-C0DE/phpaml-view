<?php

declare(strict_types=1);

namespace AML\View;

use Closure;

class Element implements View
{
    /** @var list<View> */
    private array $children;

    /** @var array<string, scalar|null> */
    private array $attributes = [];

    /** @var array<string, string> */
    private array $styles = [];

    /** @var array<string, Closure> */
    private array $events = [];

    /** @var array{component: Component, property: string}|null */
    private ?array $binding = null;

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

    public function event(string $name, Closure $handler): static
    {
        $this->events[$name] = $handler;
        return $this;
    }

    public function click(Closure $handler): static
    {
        return $this->event('click', $handler);
    }

    public function onClick(Closure $handler): static
    {
        return $this->click($handler);
    }

    public function onSubmit(Closure $handler): static
    {
        return $this->event('submit', $handler);
    }

    public function onInput(Closure $handler): static
    {
        return $this->event('input', $handler);
    }

    public function onChange(Closure $handler): static
    {
        return $this->event('change', $handler);
    }

    public function bind(Component $component, string $property): static
    {
        $reflection = new \ReflectionProperty($component, $property);
        if ($reflection->getAttributes(State::class) === []) {
            throw new \LogicException("Bound property {$property} must use #[State].");
        }
        if ($reflection->isStatic()) {
            throw new \LogicException("Bound property {$property} cannot be static.");
        }

        $this->binding = ['component' => $component, 'property' => $property];
        return $this;
    }

    public function render(RenderContext $context): string
    {
        $attributes = $this->attributes;
        if ($this->binding !== null) {
            $component = $this->binding['component'];
            $property = new \ReflectionProperty($component, $this->binding['property']);
            $value = $property->getValue($component);
            if (($attributes['type'] ?? null) === 'checkbox') {
                $attributes['checked'] = (bool) $value;
            } else {
                $attributes['value'] = is_scalar($value) || $value === null ? $value : '';
                $attributes['data-aml-value'] = is_scalar($value) || $value === null ? $value : '';
            }
            $this->events['change'] = static function (array $data) use ($component, $property): void {
                $raw = self::convertValue($property, $data['value'] ?? null);
                if ($component->validateProperty($property->getName(), $raw)) {
                    $property->setValue($component, $raw);
                }
            };
            $error = $component->validationError($property->getName());
            if ($error !== null) {
                $attributes['aria-invalid'] = 'true';
                $attributes['aria-describedby'] = 'aml-error-' . $property->getName();
            }
        }

        if ($this->tag === 'form' && isset($this->events['submit'])) {
            $handler = $this->events['submit'];
            $bindings = $this->collectBindings();
            $this->events['submit'] = static function (array $data) use ($handler, $bindings): void {
                $valid = true;
                foreach ($bindings as $name => [$component, $property, $checkbox]) {
                    $raw = self::convertValue($property, $checkbox ? ($data[$name] ?? false) : ($data[$name] ?? null));
                    if (!$component->validateProperty($property->getName(), $raw)) {
                        $valid = false;
                        continue;
                    }
                    $property->setValue($component, $raw);
                }
                if ($valid) {
                    $reflection = new \ReflectionFunction($handler);
                    $reflection->getNumberOfParameters() === 0 ? $handler() : $handler($data);
                }
            };
        }
        if ($this->styles !== []) {
            $attributes['style'] = implode(';', array_map(
                static fn (string $name, string $value): string => "{$name}:{$value}",
                array_keys($this->styles),
                $this->styles,
            ));
        }

        foreach ($this->events as $name => $handler) {
            $attributes['data-aml-' . $name] = $context->registerEvent($handler);
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
        if ($this->binding !== null) {
            $property = $this->binding['property'];
            $error = $this->binding['component']->validationError($property);
            if ($error !== null) {
                $message = htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                return '<div data-aml-field>' . $html . '<small id="aml-error-' . htmlspecialchars($property, ENT_QUOTES, 'UTF-8') . '" role="alert">' . $message . '</small></div>';
            }
        }
        return $html;
    }

    /** @return array<string, array{Component, \ReflectionProperty, bool}> */
    private function collectBindings(): array
    {
        $bindings = [];
        if ($this->binding !== null) {
            $name = $this->attributes['name'] ?? $this->binding['property'];
            $bindings[(string) $name] = [
                $this->binding['component'],
                new \ReflectionProperty($this->binding['component'], $this->binding['property']),
                ($this->attributes['type'] ?? null) === 'checkbox',
            ];
        }
        foreach ($this->children as $child) {
            if ($child instanceof self) {
                $bindings += $child->collectBindings();
            }
        }
        return $bindings;
    }

    private static function convertValue(\ReflectionProperty $property, mixed $raw): mixed
    {
        $type = $property->getType();
        if (!$type instanceof \ReflectionNamedType) {
            return $raw;
        }
        return match ($type->getName()) {
            'bool' => filter_var($raw, FILTER_VALIDATE_BOOL),
            'int' => (int) $raw,
            'float' => (float) $raw,
            'string' => (string) $raw,
            default => $raw,
        };
    }
}
