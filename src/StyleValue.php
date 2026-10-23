<?php
declare(strict_types=1);
namespace AML\View;

use AML\Engine\{StateRef, StateExpression, StateConditional};

/** @internal A validated reactive CSS value; its variable belongs to the element. */
final readonly class StyleValue
{
    public const KEYWORDS = ['initial', 'inherit', 'unset', 'revert', 'revert-layer'];
    public string $variable;
    public string $initial;

    private function __construct(public string $property, public mixed $expression, mixed $initial, public string $rule, public bool $keyframe = false)
    {
        $this->initial = self::normalize($property, $initial, $rule);
        self::declaration($property, $this->initial, $keyframe);
        $this->variable = '--aml-v-' . substr(hash('sha256', json_encode(normalizeReactiveValue([$property, $expression, $rule, $keyframe]), JSON_THROW_ON_ERROR)), 0, 20);
    }

    public static function prepare(string $property, mixed $value, string $rule = 'css'): string|self
    {
        if ($value instanceof self) return $value;
        if (is_string($value)) $value = TextContent::template($value);
        if ($value instanceof \Closure) {
            $value = FrontendClosureCompiler::displayClosure($value);
            if ($value instanceof \Closure) $value = $value();
        }
        if ($value instanceof DisplayValue) return new self($property, $value->expression, $value->initial, $rule);
        if ($value instanceof StateRef || $value instanceof StateExpression || $value instanceof StateConditional) {
            return new self($property, $value, InitialTextValue::evaluate($value), $rule);
        }
        return self::normalize($property, $value, $rule);
    }

    public static function normalize(string $property, mixed $value, string $rule = 'css'): string
    {
        if ((!is_string($value) && !is_int($value) && !is_float($value)) || is_float($value) && !is_finite($value)) {
            throw new \InvalidArgumentException("CSS {$property} requires a string or finite number.");
        }
        if ($rule === 'iconDimension') {
            $plain = is_string($value) ? trim(preg_replace('#/\*.*?\*/#s', ' ', self::declaration($property, $value)['value'])) : $value;
            $negative = is_int($plain) || is_float($plain) ? $plain < 0
                : preg_match('/^(-?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?)(?:[a-zA-Z]+|%)?$/D', $plain, $number) && (float) $number[1] < 0;
            if ($negative) throw new \InvalidArgumentException("Icon {$property} must not be negative.");
        }
        if (in_array($rule, ['padding', 'fontSize', 'letterSpacing', 'lineHeight'], true)) {
            if (!is_int($value) && !is_float($value)) throw new \InvalidArgumentException("CSS {$property} requires a number.");
            if ($rule === 'padding') $value = max(0, $value);
            if ($rule === 'fontSize' && ($value <= 0 || $value > 1000)
                || $rule === 'lineHeight' && ($value <= 0 || $value > 20)
                || $rule === 'letterSpacing' && ($value < -100 || $value > 100)) throw new \InvalidArgumentException("CSS {$property} is out of range.");
        }
        if ($rule === 'fontWeight') {
            $value = strtolower((string) $value);
            if (!in_array($value, ['normal', 'bold', 'bolder', 'lighter'], true) && !(ctype_digit($value) && (int) $value >= 100 && (int) $value <= 900 && (int) $value % 100 === 0)) throw new \InvalidArgumentException('Unknown font weight.');
        }
        if ($rule === 'textAlign') {
            $value = strtolower((string) $value);
            if (!in_array($value, ['start', 'end', 'left', 'right', 'center', 'justify'], true)) throw new \InvalidArgumentException('Unknown text alignment.');
        }
        if ($rule === 'alignment') $value = match (strtolower((string) $value)) {
            'leading', 'start', 'top' => 'flex-start', 'center' => 'center', 'trailing', 'end', 'bottom' => 'flex-end', 'stretch' => 'stretch',
            default => throw new \InvalidArgumentException('Unknown alignment.'),
        };
        if ($rule === 'textColor' && ($value === '' || preg_match('/[;{}<>\x00-\x1F\x7F]/', (string) $value))) throw new \InvalidArgumentException('Invalid text color.');
        if ($rule !== 'raw' && (is_int($value) || is_float($value))) {
            $dimension = preg_match('/^(?:(?:min-|max-)?(?:width|height)|(?:margin|padding)(?:-(?:top|right|bottom|left|inline|block)(?:-start|-end)?)?|(?:row-|column-)?gap|top|right|bottom|left|inset|font-size|letter-spacing|word-spacing|text-indent|outline-(?:width|offset)|border(?:-(?:top|right|bottom|left))?-width|border(?:-(?:top|bottom)-(?:left|right))?-radius)$/D', $property);
            $value = (string) $value . ($dimension ? 'px' : '');
        }
        $value = (string) $value;
        if (preg_match('/[;{}<>\x00-\x1F\x7F]/', $value)) throw new \InvalidArgumentException("Unsafe CSS value for {$property}.");
        self::declaration($property, $value);
        return $value;
    }

    public function payload(): array
    {
        return ['property' => $this->property, 'value' => normalizeReactiveValue($this->expression), 'rule' => $this->rule, 'keyframe' => $this->keyframe];
    }

    /** @internal CSS ignores important declarations inside keyframes. */
    public function forKeyframe(): self { return new self($this->property, $this->expression, $this->initial, $this->rule, true); }

    /** @internal Separate declaration priority from the custom property's value. */
    public static function declaration(string $property, string $value, bool $keyframe = false): array
    {
        $stack = []; $quote = null; $priority = null;
        for ($i = 0, $length = strlen($value); $i < $length; $i++) {
            $char = $value[$i];
            if ($char === '\\') { if (++$i >= $length) throw new \InvalidArgumentException('Unclosed CSS escape.'); continue; }
            if ($quote !== null) { if ($char === $quote) $quote = null; continue; }
            if ($char === '"' || $char === "'") { $quote = $char; continue; }
            if (substr($value, $i, 2) === '/*') {
                $end = strpos($value, '*/', $i + 2);
                if ($end === false) throw new \InvalidArgumentException('Unclosed CSS comment.');
                $i = $end + 1; continue;
            }
            if ($char === '(' || $char === '[') $stack[] = $char;
            if (($char === ')' || $char === ']') && array_pop($stack) !== ($char === ')' ? '(' : '[')) throw new \InvalidArgumentException('Unbalanced CSS value.');
            if ($char === '!' && $stack === []) $priority = $i;
        }
        if ($quote !== null || $stack !== []) throw new \InvalidArgumentException('Unbalanced CSS value.');
        $important = $priority !== null && preg_match('/^!\s*important\s*$/iD', preg_replace('#/\*.*?\*/#s', ' ', substr($value, $priority))) === 1;
        if ($important) $value = rtrim(substr($value, 0, $priority));
        $plain = trim(preg_replace('#/\*.*?\*/#s', ' ', $value));
        $keyword = in_array(strtolower($plain), self::KEYWORDS, true) ? strtolower($plain) : null;
        if ($keyframe && $keyword !== null && !$important) throw new \InvalidArgumentException('Write CSS-wide keywords statically in keyframes; reactive keyframe values require a concrete value.');
        $active = ($plain !== '' || str_starts_with($property, '--')) && !($keyframe && $important);
        if ($active && $value === '') $value = ' ';
        return ['value' => $value, 'important' => $important && $active, 'active' => $active, 'keyword' => $keyword];
    }
}
