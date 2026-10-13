<?php

declare(strict_types=1);

namespace AML\View;

use AML\Engine\ClientAction;
use AML\Engine\ClientInstruction;
use AML\Engine\ApiAction;
use AML\Engine\StateRef;
use AML\Engine\StateNamespace;
use Closure;

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
        if (preg_match('/^on/i', $name) === 1 || strtolower($name) === 'style') {
            throw new \InvalidArgumentException("Unsafe HTML attribute: {$name}");
        }

        $this->attributes[$name] = $value;
        return $this;
    }

    public function class(string ...$names): static
    {
        $classes = preg_split('/\s+/', trim((string) ($this->attributes['class'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($names as $name) {
            $tokens = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if ($tokens === []) {
                throw new \InvalidArgumentException('A class name must be a non-empty HTML token.');
            }
            foreach ($tokens as $token) {
                $classes[] = $token;
            }
        }
        $this->attributes['class'] = implode(' ', array_values(array_unique($classes)));
        return $this;
    }

    public function style(string $name, string|int|float $value): static
    {
        if (!preg_match('/^[a-z-]+$/', $name)) {
            throw new \InvalidArgumentException("Invalid CSS property: {$name}");
        }

        $value = (string) $value;
        if (preg_match('/[;{}<>\x00-\x1F\x7F]/', $value) === 1) {
            throw new \InvalidArgumentException("Unsafe CSS value for {$name}.");
        }
        $this->styles[$name] = $value;
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

    public function alignment(string $alignment): static
    {
        $value = match (strtolower($alignment)) {
            'leading', 'start', 'top' => 'flex-start',
            'center' => 'center',
            'trailing', 'end', 'bottom' => 'flex-end',
            'stretch' => 'stretch',
            default => throw new \InvalidArgumentException("Unknown alignment: {$alignment}"),
        };

        return $this->style('align-items', $value);
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

    public function id(string $id): static
    {
        if ($id === '' || preg_match('/\s/', $id) === 1) {
            throw new \InvalidArgumentException('An HTML id must be a non-empty token.');
        }
        return $this->attribute('id', $id);
    }

    public function name(string $name): static
    {
        if ($name === '' || preg_match('/\s/', $name) === 1) {
            throw new \InvalidArgumentException('A form control name must be a non-empty token.');
        }
        return $this->attribute('name', $name);
    }

    public function type(string $type): static
    {
        if ($type === '' || preg_match('/\s/', $type) === 1) {
            throw new \InvalidArgumentException('A form control type must be a non-empty token.');
        }
        return $this->attribute('type', strtolower($type));
    }

    public function value(string|int|float|bool|StateRef|null $value): static
    {
        if (is_scalar($value) || $value === null) {
            $value = NaturalStateBindings::resolve('value', $value);
        }
        $initial = $value instanceof StateRef ? $value->initial : $value;
        if ($value instanceof StateRef) {
            if ($this->tag === 'input' && strtolower((string) ($this->attributes['type'] ?? 'text')) === 'file') {
                throw new \InvalidArgumentException('A file input value cannot be controlled programmatically.');
            }
            $this->model($value);
        }

        $type = strtolower((string) ($this->attributes['type'] ?? ''));
        if ($this->tag === 'textarea') {
            $this->children = [new TextView((string) ($initial ?? ''))];
            return $this;
        }
        if ($this->tag === 'select') {
            return $this->attribute('data-aml-value', is_bool($initial) ? ($initial ? '1' : '0') : $initial);
        }
        if ($this->tag === 'input' && in_array($type, ['checkbox', 'radio'], true) && is_bool($initial)) {
            return $this->attribute('checked', $initial);
        }
        return $this->attribute('value', is_bool($initial) ? ($initial ? '1' : '0') : $initial);
    }

    public function autofocus(bool $enabled = true): static
    {
        return $this->attribute('autofocus', $enabled);
    }

    public function ref(RefHandle $ref): static
    {
        return $this->attribute('data-aml-ref', $ref->id);
    }

    public function pattern(string $pattern): static
    {
        if ($pattern === '') throw new \InvalidArgumentException('Input pattern cannot be empty.');
        return $this->attribute('pattern', $pattern);
    }

    public function inputMode(string $mode): static
    {
        $mode = strtolower($mode);
        if (!in_array($mode, ['none', 'text', 'decimal', 'numeric', 'tel', 'search', 'email', 'url'], true)) {
            throw new \InvalidArgumentException("Unknown input mode: {$mode}");
        }
        return $this->attribute('inputmode', $mode);
    }

    public function placeholder(string $text): static
    {
        return $this->attribute('placeholder', $text);
    }

    public function autocomplete(string $value): static
    {
        if (trim($value) === '') throw new \InvalidArgumentException('Autocomplete cannot be empty.');
        return $this->attribute('autocomplete', $value);
    }

    public function maxLength(int $length): static
    {
        if ($length < 0) throw new \InvalidArgumentException('Maximum length cannot be negative.');
        return $this->attribute('maxlength', $length);
    }

    public function rows(int $rows): static
    {
        if ($rows < 1) throw new \InvalidArgumentException('Textarea rows must be positive.');
        return $this->attribute('rows', $rows);
    }

    public function columns(int $columns): static
    {
        if ($columns < 1) throw new \InvalidArgumentException('Textarea columns must be positive.');
        return $this->attribute('cols', $columns);
    }

    public function min(int|float|string $value): static
    {
        return $this->attribute('min', $value);
    }

    public function max(int|float|string $value): static
    {
        return $this->attribute('max', $value);
    }

    public function step(int|float|string $value): static
    {
        if ($value === '' || (is_numeric($value) && (float) $value <= 0)) {
            throw new \InvalidArgumentException('Input step must be positive or "any".');
        }
        if (!is_numeric($value) && strtolower((string) $value) !== 'any') {
            throw new \InvalidArgumentException('Input step must be positive or "any".');
        }
        return $this->attribute('step', $value);
    }

    public function readOnly(bool $enabled = true): static
    {
        return $this->attribute('readonly', $enabled);
    }

    public function multiple(bool $enabled = true): static
    {
        return $this->attribute('multiple', $enabled);
    }

    public function scope(string $scope): static
    {
        $scope = strtolower($scope);
        if (!in_array($scope, ['row', 'col', 'rowgroup', 'colgroup'], true)) {
            throw new \InvalidArgumentException("Unknown table header scope: {$scope}");
        }
        return $this->attribute('scope', $scope);
    }

    public function colSpan(int $columns): static
    {
        if ($columns < 1 || $columns > 1000) {
            throw new \InvalidArgumentException('Table column span must be between 1 and 1000.');
        }
        return $this->attribute('colspan', $columns);
    }

    public function rowSpan(int $rows): static
    {
        if ($rows < 1 || $rows > 65534) {
            throw new \InvalidArgumentException('Table row span must be between 1 and 65534.');
        }
        return $this->attribute('rowspan', $rows);
    }

    public function target(string $target): static
    {
        if ($target === '' || preg_match('/\s/', $target) === 1) {
            throw new \InvalidArgumentException('A link target must be a non-empty token.');
        }
        $this->attribute('target', $target);
        if (strtolower($target) === '_blank') {
            $this->rel('noopener', 'noreferrer');
        }
        return $this;
    }

    public function rel(string ...$relationships): static
    {
        $tokens = preg_split('/\s+/', trim((string) ($this->attributes['rel'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($relationships as $relationship) {
            foreach (preg_split('/\s+/', trim($relationship), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
                if (preg_match('/^[a-zA-Z][a-zA-Z0-9-]*$/', $token) !== 1) {
                    throw new \InvalidArgumentException("Invalid link relationship: {$token}");
                }
                $tokens[] = strtolower($token);
            }
        }
        if ($tokens === []) throw new \InvalidArgumentException('A link relationship cannot be empty.');
        return $this->attribute('rel', implode(' ', array_values(array_unique($tokens))));
    }

    public function language(string $language): static
    {
        if (preg_match('/^[a-zA-Z]{2,8}(?:-[a-zA-Z0-9]{1,8})*$/', $language) !== 1) {
            throw new \InvalidArgumentException("Invalid language tag: {$language}");
        }
        return $this->attribute('hreflang', $language);
    }

    public function referrerPolicy(string $policy): static
    {
        $policy = strtolower($policy);
        if (!in_array($policy, [
            'no-referrer', 'no-referrer-when-downgrade', 'origin', 'origin-when-cross-origin',
            'same-origin', 'strict-origin', 'strict-origin-when-cross-origin', 'unsafe-url',
        ], true)) {
            throw new \InvalidArgumentException("Unknown referrer policy: {$policy}");
        }
        return $this->attribute('referrerpolicy', $policy);
    }

    public function download(?string $filename = null): static
    {
        return $this->attribute('download', $filename ?? true);
    }

    public function loading(string $mode): static
    {
        $mode = strtolower($mode);
        if (!in_array($mode, ['eager', 'lazy'], true)) {
            throw new \InvalidArgumentException("Unknown loading mode: {$mode}");
        }
        return $this->attribute('loading', $mode);
    }

    public function allowFullScreen(bool $enabled = true): static
    {
        return $this->attribute('allowfullscreen', $enabled);
    }

    public function fontSize(int|float $pixels): static
    {
        if ($pixels <= 0 || $pixels > 1000) {
            throw new \InvalidArgumentException('Font size must be between 0 and 1000 pixels.');
        }
        return $this->style('font-size', $pixels . 'px');
    }

    public function fontWeight(string|int $weight): static
    {
        $value = strtolower((string) $weight);
        if (!in_array($value, ['normal', 'bold', 'bolder', 'lighter'], true)
            && !(ctype_digit($value) && (int) $value >= 100 && (int) $value <= 900 && (int) $value % 100 === 0)) {
            throw new \InvalidArgumentException("Unknown font weight: {$value}");
        }
        return $this->style('font-weight', $value);
    }

    public function lineHeight(int|float $height): static
    {
        if ($height <= 0 || $height > 20) {
            throw new \InvalidArgumentException('Line height must be between 0 and 20.');
        }
        return $this->style('line-height', $height);
    }

    public function letterSpacing(int|float $pixels): static
    {
        if ($pixels < -100 || $pixels > 100) {
            throw new \InvalidArgumentException('Letter spacing must be between -100 and 100 pixels.');
        }
        return $this->style('letter-spacing', $pixels . 'px');
    }

    public function textAlign(string $alignment): static
    {
        $alignment = strtolower($alignment);
        if (!in_array($alignment, ['start', 'end', 'left', 'right', 'center', 'justify'], true)) {
            throw new \InvalidArgumentException("Unknown text alignment: {$alignment}");
        }
        return $this->style('text-align', $alignment);
    }

    public function textColor(string $color): static
    {
        if ($color === '' || preg_match('/[;{}<>\x00-\x1F\x7F]/', $color) === 1) {
            throw new \InvalidArgumentException('Invalid text color.');
        }
        return $this->style('color', $color);
    }

    public function cite(string $source): static
    {
        return $this->attribute('cite', safeLinkHref($source));
    }

    public function open(bool $enabled = true): static
    {
        return $this->attribute('open', $enabled);
    }

    public function controls(bool $enabled = true): static
    {
        return $this->attribute('controls', $enabled);
    }

    public function autoplay(bool $enabled = true): static
    {
        return $this->attribute('autoplay', $enabled);
    }

    public function muted(bool $enabled = true): static
    {
        return $this->attribute('muted', $enabled);
    }

    public function loop(bool $enabled = true): static
    {
        return $this->attribute('loop', $enabled);
    }

    public function playsInline(bool $enabled = true): static
    {
        return $this->attribute('playsinline', $enabled);
    }

    public function poster(string $source): static
    {
        return $this->attribute('poster', $source);
    }

    public function preload(string $mode): static
    {
        $mode = strtolower($mode);
        if (!in_array($mode, ['none', 'metadata', 'auto'], true)) {
            throw new \InvalidArgumentException("Unknown media preload mode: {$mode}");
        }

        return $this->attribute('preload', $mode);
    }

    public function status(ActionStatus $status, ?string $message = null): static
    {
        $this->attribute('data-aml-status', $status->value);
        if ($status === ActionStatus::Loading) {
            $this->disabled()->attribute('aria-busy', 'true');
        }
        if (in_array($status, [ActionStatus::Success, ActionStatus::Info], true)) {
            $this->attribute('aria-live', 'polite');
        }
        if (in_array($status, [ActionStatus::Error, ActionStatus::Warning], true)) {
            $this->attribute('aria-live', 'assertive');
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

    public function state(FormStateHandle $state): static
    {
        if ($this->tag !== 'form') {
            throw new \LogicException('FormState can only be attached to a form element.');
        }
        return $this->attribute(
            'data-aml-form-state',
            json_encode($state->manifest(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );
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

    public function click(ClientInstruction|Closure $handler): static
    {
        return $this->onClick($handler);
    }

    public function onClick(ClientInstruction|Closure $handler): static
    {
        return $this->onClientEvent('click', $handler);
    }

    public function onSubmit(ClientInstruction|Closure $handler): static
    {
        return $this->onClientEvent('submit', $handler);
    }

    public function onInput(ClientInstruction|Closure $handler): static
    {
        return $this->onClientEvent('input', $handler);
    }

    public function onChange(ClientInstruction|Closure $handler): static
    {
        return $this->onClientEvent('change', $handler);
    }

    public function onFocus(ClientInstruction|Closure $handler): static
    {
        return $this->onClientEvent('focus', $handler);
    }

    public function onBlur(ClientInstruction|Closure $handler): static
    {
        return $this->onClientEvent('blur', $handler);
    }

    public function onKeyDown(ClientInstruction|Closure $handler): static { return $this->onClientEvent('keydown', $handler); }
    public function onKeyUp(ClientInstruction|Closure $handler): static { return $this->onClientEvent('keyup', $handler); }
    public function onMouseEnter(ClientInstruction|Closure $handler): static { return $this->onClientEvent('mouseenter', $handler); }
    public function onMouseLeave(ClientInstruction|Closure $handler): static { return $this->onClientEvent('mouseleave', $handler); }
    public function onDragStart(ClientInstruction|Closure $handler): static { return $this->onClientEvent('dragstart', $handler); }
    public function onDragEnd(ClientInstruction|Closure $handler): static { return $this->onClientEvent('dragend', $handler); }
    public function onDragOver(ClientInstruction|Closure $handler): static { return $this->onClientEvent('dragover', $handler); }
    public function onDrop(ClientInstruction|Closure $handler): static { return $this->onClientEvent('drop', $handler); }

    public function submit(bool $enabled = true): static
    {
        return $this->type($enabled ? 'submit' : 'button');
    }

    private function onClientEvent(string $event, ClientInstruction|Closure $handler): static
    {
        if ($handler instanceof Closure) $handler = FrontendClosureCompiler::compile($handler);
        $this->clientEvents[$event] = $handler;
        return $this;
    }

    public function updates(string $property, int|float $by = 1): static
    {
        return $this->onClick(ClientAction::increment($property, $by));
    }

    public function increments(string $state, int|float $by = 1): static
    {
        return $this->onClick(ClientAction::increment($state, $by));
    }

    public function decrements(string $state, int|float $by = 1): static
    {
        return $this->onClick(ClientAction::decrement($state, $by));
    }

    public function sets(string $state, mixed $value): static
    {
        return $this->onClick(ClientAction::set($state, $value));
    }

    public function toggles(string $state): static
    {
        return $this->onClick(ClientAction::toggle($state));
    }

    public function bindClient(string $property): static
    {
        $property = StateNamespace::qualify($property);
        return $this
            ->attribute('data-aml-model', $property)
            ->attribute('data-aml-bind', $property);
    }

    public function model(string|StateRef $state): static
    {
        [$name] = self::reactiveState($state);
        return $this
            ->attribute('data-aml-model', $name)
            ->attribute('data-aml-bind', $name);
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
