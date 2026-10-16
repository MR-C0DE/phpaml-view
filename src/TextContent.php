<?php

declare(strict_types=1);
namespace AML\View;

use AML\Engine\{StateRef, StateExpression, StateConditional};

/** @internal One text pipeline for scalar values, templates, bindings and composed views. */
final class TextContent
{
    public static function plainElement(string $tag, mixed $value): Element
    {
        if ($value instanceof \Closure && StateInterpolation::current() !== null) $value = FrontendClosureCompiler::displayClosure($value);
        if ($value instanceof \Closure) $value = $value();
        if (is_string($value)) $value = self::template($value);
        if (!is_scalar($value) && $value !== null && !$value instanceof DisplayValue && !$value instanceof StateRef && !$value instanceof StateExpression && !$value instanceof StateConditional) throw new \TypeError('Option text must be a scalar or a reactive value.');
        $initial = $value instanceof DisplayValue ? $value->initial : ($value instanceof StateRef ? $value->initial : InitialTextValue::evaluate($value));
        $element = new Element($tag, new TextView((string) ($initial ?? '')));
        if ($value instanceof StateRef) return $element->attribute('data-aml-bind', $value->name);
        if ($value instanceof DisplayValue) $value = $value->expression;
        return $value instanceof StateExpression || $value instanceof StateConditional
            ? $element->attribute('data-aml-bind-value', json_encode(normalizeReactiveValue($value), JSON_THROW_ON_ERROR)) : $element;
    }
    public static function view(mixed $value, bool $span = false): View
    {
        if ($value instanceof View) return $value;
        if ($value instanceof \Closure && StateInterpolation::current() !== null) {
            try { $value = FrontendClosureCompiler::displayClosure($value); }
            catch (\Throwable $error) {
                $reflection = new \ReflectionFunction($value);
                $file = $reflection->getFileName();
                $lines = $file && is_file($file) ? file($file) : [];
                $body = implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
                if (str_contains($body, '$this') || $reflection->getClosureThis() instanceof Component || array_filter($reflection->getStaticVariables(), static fn ($value) => $value instanceof StateRef || $value instanceof \Closure)) throw $error;
            }
        }
        if ($value instanceof DisplayValue) return self::binding($value->expression, $value->initial);
        if (is_string($value)) $value = self::template($value);
        if ($value instanceof StateRef) return (new Element('span', new TextView((string) ($value->initial ?? ''))))->attribute('data-aml-bind', $value->name);
        if ($value instanceof StateExpression || $value instanceof StateConditional) return self::binding($value, InitialTextValue::evaluate($value));
        if (!is_scalar($value) && $value !== null && !$value instanceof \Closure) throw new \TypeError('Text content must be scalar, a reactive value, a closure or a View.');
        $text = new TextView($value instanceof \Closure ? $value : (string) ($value ?? ''));
        return $span ? new Element('span', $text) : $text;
    }

    private static function binding(StateExpression|StateConditional $expression, mixed $initial): Element
    {
        return (new Element('span', new TextView((string) ($initial ?? ''))))
            ->attribute('data-aml-bind-value', json_encode(normalizeReactiveValue($expression), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    public static function template(string $text): mixed
    {
        $states = StateInterpolation::current();
        if ($states !== null) $states = [...NaturalStateBindings::currentProperties(), ...$states];
        if ($states === null || !str_contains($text, '{')) return $text;
        preg_match_all('/(?<!\{)\{([a-zA-Z_][a-zA-Z0-9_.-]*)\}(?!\})/', $text, $matches, PREG_OFFSET_CAPTURE);
        $parts = []; $offset = 0;
        foreach ($matches[0] as $index => [$token, $position]) {
            if ($position > $offset) $parts[] = str_replace(['{{', '}}'], ['{', '}'], substr($text, $offset, $position - $offset));
            $path = $matches[1][$index][0]; [$root] = explode('.', $path, 2);
            if (!isset($states[$root])) throw new \OutOfBoundsException("Unknown AML View state placeholder: {{$path}}");
            $binding = $states[$root];
            $expression = $binding instanceof DisplayValue ? $binding->expression : $binding;
            $segments = array_slice(explode('.', $path), 1);
            if ($segments !== [] && isset((StateInterpolation::current() ?? [])[$root])) {
                $initial = $binding->initial;
                foreach ($segments as $segment) $initial = is_array($initial) ? ($initial[$segment] ?? null) : null;
                $expression = StateRef::to($binding->name . '.' . implode('.', $segments), $initial);
            } else foreach ($segments as $segment) $expression = new StateExpression('offset', $expression, $segment);
            $parts[] = $expression; $offset = $position + strlen($token);
        }
        if ($parts === []) return str_replace(['{{', '}}'], ['{', '}'], $text);
        if ($offset < strlen($text)) $parts[] = str_replace(['{{', '}}'], ['{', '}'], substr($text, $offset));
        $expression = array_shift($parts);
        foreach ($parts as $part) $expression = new StateExpression('concat', $expression, $part);
        return $expression;
    }
}
