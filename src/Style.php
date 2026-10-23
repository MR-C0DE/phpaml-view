<?php
declare(strict_types=1);

namespace AML\View;

/** An immutable, reusable description of an element's CSS. */
final class Style
{
    use StyleVariants;

    /** @var array<string, array<string, self>> */
    private array $keyframes = [];
    private array $properties = [];
    /** @var array<string, self> */
    private array $variants = [];
    /** @var array<string, array{css: string, classes: list<string>}> */
    private array $modules = [];
    /** Earlier properties become defaults when a CSS module is applied. */
    private array $fallbacks = [];

    private function __construct() {}

    /** @return ($definitions is null ? self : StyleCollection) */
    public static function create(array|string|null $definitions = null): self|StyleCollection
    {
        if ($definitions === null) return new self();
        if (is_string($definitions)) {
            $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['file'] ?? null;
            if ($caller === null) throw new \LogicException('A local style module requires a PHP source file.');
            return StyleModule::load($definitions, $caller);
        }
        $definitions = NaturalStateBindings::styleArgument('create', $definitions);
        $styles = [];
        foreach ($definitions as $name => $definition) {
            if (!is_string($name) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_-]*$/D', $name)) {
                throw new \InvalidArgumentException('Style::create() expects named styles.');
            }
            if (!$definition instanceof self && !is_array($definition)) {
                throw new \InvalidArgumentException("Style {$name} must be a Style or an array of properties.");
            }
            $styles[$name] = self::from($definition);
        }
        return new StyleCollection($styles);
    }

    public static function make(): self { return new self(); }

    /** Accept property maps and ordered lists of styles, as Element::style() does. */
    public static function from(self|array $definition): self
    {
        if ($definition instanceof self) return $definition;
        $definition = NaturalStateBindings::styleArgument('from', $definition);
        $style = new self();
        if (array_is_list($definition)) {
            foreach ($definition as $part) {
                if ($part === null || $part === false) continue;
                if (!$part instanceof self && !is_array($part)) throw new \InvalidArgumentException('A style list accepts only styles, property arrays, false or null.');
                $style = $style->merge(self::from($part));
            }
        } else {
            foreach ($definition as $name => $value) {
                if (!is_string($name)) {
                    throw new \InvalidArgumentException('Style properties require a name and a string or finite number.');
                }
                $style = $style->property($name, $value);
            }
        }
        return $style;
    }

    public function property(string $name, mixed $value): self
    {
        $name = str_starts_with($name, '--') ? $name : strtolower(preg_replace('/[A-Z]/', '-$0', $name));
        if (!preg_match('/^(?:-?[a-z][a-z-]*|--[a-zA-Z_][a-zA-Z0-9_-]*)$/D', $name)) {
            throw new \InvalidArgumentException("Invalid CSS property: {$name}");
        }
        $value = StyleValue::prepare($name, NaturalStateBindings::styleArgument('property', $value, 1));
        $copy = clone $this;
        // Keep declaration order significant for shorthands such as padding.
        unset($copy->properties[$name]);
        $copy->properties[$name] = $value;
        return $copy;
    }

    public function merge(self $other): self
    {
        $copy = clone $this;
        if ($other->modules !== [] && ($copy->properties !== [] || $copy->variants !== [])) {
            $fallback = new self();
            $fallback->properties = $copy->properties;
            $fallback->variants = $copy->variants;
            $copy->fallbacks[] = $fallback;
            $copy->properties = []; $copy->variants = [];
        }
        $copy->fallbacks = [...$copy->fallbacks, ...$other->fallbacks];
        $copy->keyframes = [...$copy->keyframes, ...$other->keyframes];
        foreach ($other->properties as $name => $value) {
            unset($copy->properties[$name]);
            $copy->properties[$name] = $value;
        }
        foreach ($other->variants as $selector => $style) {
            $copy->variants[$selector] = isset($copy->variants[$selector]) ? $copy->variants[$selector]->merge($style) : $style;
        }
        foreach ($other->modules as $id => $module) {
            $module['classes'] = array_values(array_unique([...($copy->modules[$id]['classes'] ?? []), ...$module['classes']]));
            $copy->modules[$id] = $module;
        }
        return $copy;
    }

    public function background(mixed $value): self { return $this->property('background', NaturalStateBindings::styleArgument('background', $value)); }
    public function backgroundColor(mixed $value): self { return $this->property('background-color', NaturalStateBindings::styleArgument('backgroundColor', $value)); }
    public function color(mixed $value): self { return $this->property('color', NaturalStateBindings::styleArgument('color', $value)); }
    public function textColor(mixed $value): self { return $this->color(NaturalStateBindings::styleArgument('textColor', $value)); }
    public function padding(mixed $value): self { return $this->property('padding', NaturalStateBindings::styleArgument('padding', $value)); }
    public function margin(mixed $value): self { return $this->property('margin', NaturalStateBindings::styleArgument('margin', $value)); }
    public function gap(mixed $value): self { return $this->property('gap', NaturalStateBindings::styleArgument('gap', $value)); }
    public function spacing(mixed $value): self { return $this->gap(NaturalStateBindings::styleArgument('spacing', $value)); }
    public function borderRadius(mixed $value): self { return $this->property('border-radius', NaturalStateBindings::styleArgument('borderRadius', $value)); }
    public function border(mixed $value): self { return $this->property('border', NaturalStateBindings::styleArgument('border', $value)); }
    public function width(mixed $value): self { return $this->property('width', NaturalStateBindings::styleArgument('width', $value)); }
    public function height(mixed $value): self { return $this->property('height', NaturalStateBindings::styleArgument('height', $value)); }
    public function fontSize(mixed $value): self { return $this->property('font-size', NaturalStateBindings::styleArgument('fontSize', $value)); }
    public function fontWeight(mixed $value): self { return $this->property('font-weight', NaturalStateBindings::styleArgument('fontWeight', $value)); }
    public function lineHeight(mixed $value): self { return $this->property('line-height', NaturalStateBindings::styleArgument('lineHeight', $value)); }
    public function letterSpacing(mixed $value): self { return $this->property('letter-spacing', NaturalStateBindings::styleArgument('letterSpacing', $value)); }
    public function textAlign(mixed $value): self { return $this->property('text-align', NaturalStateBindings::styleArgument('textAlign', $value)); }
    public function opacity(mixed $value): self { return $this->property('opacity', NaturalStateBindings::styleArgument('opacity', $value)); }
    public function display(mixed $value): self { return $this->property('display', NaturalStateBindings::styleArgument('display', $value)); }
    public function keyframes(string $name, array $frames): self
    {
        StyleAnimationNames::validate($name);
        if ($frames === []) throw new \InvalidArgumentException('Keyframes require at least one step.');
        $frames = NaturalStateBindings::styleArgument(__FUNCTION__, $frames, 1);
        $steps = [];
        foreach ($frames as $step => $definition) {
            if (!is_string($step)) throw new \InvalidArgumentException('Keyframe steps must use from, to or percentages.');
            foreach (explode(',', $step) as $offset) {
                $offset = trim($offset);
                if (!preg_match('/^(?:from|to|(?:\d+(?:\.\d+)?|\.\d+)%)$/D', $offset) || str_ends_with($offset, '%') && (float) $offset > 100) throw new \InvalidArgumentException('Invalid keyframe step: ' . $step);
            }
            if (!$definition instanceof self && !is_array($definition)) throw new \InvalidArgumentException('A keyframe step requires a Style or property map.');
            $frame = self::from($definition);
            if ($frame->modules !== [] || $frame->variants !== [] || $frame->fallbacks !== [] || $frame->keyframes !== []) throw new \InvalidArgumentException('Keyframe steps accept declarations only.');
            $frame = clone $frame;
            foreach ($frame->properties as $property => $value) if ($value instanceof StyleValue) $frame->properties[$property] = $value->forKeyframe();
            $steps[$step] = $frame;
        }
        $copy = clone $this;
        $copy->keyframes[$name] = $steps;
        return $copy;
    }

    private function variant(string $selector, self $style): self
    {
        if ($style->modules !== []) throw new \InvalidArgumentException('Write selectors and conditions inside the CSS module instead of nesting a module in a Style variant.');
        $copy = clone $this;
        $copy->variants[$selector] = isset($copy->variants[$selector]) ? $copy->variants[$selector]->merge($style) : $style;
        return $copy;
    }

    /** @internal */
    public static function module(string $id, string $css, string $class): self
    {
        $style = new self();
        $style->modules[$id] = ['css' => $css, 'classes' => [$class]];
        return $style;
    }

    /** @internal @return array{classes: list<string>, sheets: array<string, string>} */
    public function compiled(): array
    {
        $classes = []; $sheets = [];
        foreach ($this->modules as $id => $module) {
            $classes = [...$classes, ...$module['classes']];
            $sheets[$id] = $module['css'];
        }
        if ($this->properties !== [] || $this->variants !== [] || $this->fallbacks !== [] || $this->keyframes !== []) {
            $names = $this->animationNames();
            $id = 'aml-s-' . substr(hash('sha256', $this->css('&', $names)), 0, 20);
            $classes[] = $id;
            $sheets[$id] = $this->css('.' . $id, array_map(static fn ($name) => $id . '-kf-' . $name, $names));
        }
        return ['classes' => array_values(array_unique($classes)), 'sheets' => $sheets];
    }

    /** @internal @return array<string, StyleValue> */
    public function bindings(): array
    {
        $bindings = [];
        foreach ($this->properties as $value) if ($value instanceof StyleValue) $bindings[$value->variable] = $value;
        foreach ([...$this->fallbacks, ...array_values($this->variants)] as $style) $bindings = [...$bindings, ...$style->bindings()];
        foreach ($this->keyframes as $frames) foreach ($frames as $frame) $bindings = [...$bindings, ...$frame->bindings()];
        return $bindings;
    }

    private function animationNames(): array
    {
        $names = array_combine(array_keys($this->keyframes), array_keys($this->keyframes));
        foreach ([...$this->fallbacks, ...array_values($this->variants)] as $style) $names = [...$names, ...$style->animationNames()];
        return $names;
    }

    private function css(string $selector, array $animations = [], ?string $subject = null, bool $keyframe = false): string
    {
        $subject ??= $selector;
        $css = '';
        foreach ($this->fallbacks as $fallback) $css .= $fallback->css(':where(' . $selector . ')', $animations, $subject);
        if ($this->properties !== []) {
            $declarations = '';
            foreach ($this->properties as $name => $value) {
                $binding = $value instanceof StyleValue ? $value : null;
                $value = $binding ? 'var(' . $binding->variable . ')' : $value;
                if ($animations !== [] && preg_match('/^(?:-webkit-)?animation(?:-name)?$/iD', $name)) $value = StyleAnimationNames::rewrite($value, $animations);
                if ($binding && !$keyframe) {
                    if ($declarations !== '') $css .= $selector . '{' . $declarations . '}';
                    $declarations = '';
                    $keywords = in_array($binding->rule, ['css', 'raw', 'textColor'], true) ? StyleValue::KEYWORDS : [];
                    foreach (['', ...$keywords] as $keyword) {
                        $token = $binding->variable . ($keyword === '' ? '' : '-' . $keyword);
                        foreach (['active' => '', 'important' => ' !important'] as $flag => $priority) {
                            $guard = ':where([data-aml-style-' . $flag . '~="' . $token . '"])';
                            $guarded = str_replace($subject, $subject . $guard, $selector);
                            $css .= $guarded . '{' . $name . ':' . ($keyword === '' ? $value : $keyword) . $priority . ';}';
                        }
                    }
                } else $declarations .= $name . ':' . $value . ';';
            }
            if ($declarations !== '') $css .= $selector . '{' . $declarations . '}';
        }
        foreach ($this->keyframes as $name => $frames) {
            $css .= '@keyframes ' . $animations[$name] . '{';
            foreach ($frames as $step => $frame) $css .= $frame->css($step, $animations, $step, true);
            $css .= '}';
        }
        foreach ($this->variants as $variant => $style) {
            $css .= str_starts_with($variant, '@') ? $variant . '{' . $style->css($selector, $animations, $subject) . '}' : $style->css($selector . $variant, $animations, $subject);
        }
        return $css;
    }
}
