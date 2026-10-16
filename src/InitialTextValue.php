<?php

declare(strict_types=1);
namespace AML\View;

use AML\Engine\{BuiltinFunctions, StateRef, StateExpression, StateConditional};

/** @internal Evaluate template substitutions without executing generated PHP. */
final class InitialTextValue
{
    private static ?\WeakMap $initialValues = null;

    public static function remember(StateExpression $expression, mixed $initial): void
    {
        self::$initialValues ??= new \WeakMap();
        self::$initialValues[$expression] = ['value' => $initial];
    }

    public static function evaluate(mixed $value): mixed
    {
        if ($value instanceof StateRef) return $value->initial;
        if ($value instanceof StateConditional) {
            $a = self::evaluate($value->left); $b = self::evaluate($value->right);
            $matches = match ($value->operator) {
                'eq' => $a === $b, 'neq' => $a !== $b, 'looseEq' => $a == $b, 'looseNeq' => $a != $b,
                'gt' => $a > $b, 'gte' => $a >= $b, 'lt' => $a < $b, 'lte' => $a <= $b,
                'truthy' => (bool) $a, 'falsy' => !(bool) $a,
                default => throw new \InvalidArgumentException('Unsupported initial text condition: ' . $value->operator),
            };
            return self::evaluate($matches ? $value->then : $value->otherwise);
        }
        if (is_array($value)) {
            if (array_key_exists('$float', $value)) return (float) $value['$float'];
            return array_map(self::evaluate(...), $value);
        }
        if (!$value instanceof StateExpression) return $value;
        $op = $value->operation;
        if ($op === 'display' && isset(self::$initialValues[$value])) return self::$initialValues[$value]['value'];
        if ($op === 'array') {
            $array = [];
            foreach ($value->left as $entry) {
                if (isset($entry['spread'])) { $array = [...$array, ...self::evaluate($entry['spread'])]; continue; }
                $item = self::evaluate($entry['value']);
                if (array_key_exists('key', $entry)) $array[self::evaluate($entry['key'])] = $item;
                else $array[] = $item;
            }
            return $array;
        }
        if ($op === 'roundingMode') {
            if (!enum_exists('RoundingMode') || !defined('RoundingMode::' . $value->left)) throw new \InvalidArgumentException('Unknown RoundingMode case');
            return constant('RoundingMode::' . $value->left);
        }
        if ($op === 'builtin') {
            $name = $value->left;
            if (!in_array($name, BuiltinFunctions::NAMES, true) || $name === 'random_int') throw new \InvalidArgumentException('Unsupported initial text builtin: ' . $name);
            $arguments = [];
            foreach ($value->right['arguments'] as $argument) {
                if ($argument['reference'] ?? false) throw new \InvalidArgumentException('Text template formatting cannot pass references.');
                $part = self::evaluate($argument['value']);
                if ($argument['spread'] ?? false) {
                    foreach ($part as $key => $item) { if (is_int($key)) $arguments[] = $item; else $arguments[$key] = $item; }
                } elseif (isset($argument['name'])) $arguments[$argument['name']] = $part;
                else $arguments[] = $part;
            }
            return $name(...$arguments);
        }
        if (in_array($op, ['call', 'closure', 'functionRef'], true)) throw new \InvalidArgumentException('Use PHP interpolation when passing reactive text to a user function; {state} templates are expanded by the display component.');
        $a = self::evaluate($value->left);
        if ($op === 'and') return (bool) $a && (bool) self::evaluate($value->right);
        if ($op === 'or') return (bool) $a || (bool) self::evaluate($value->right);
        if ($op === 'coalesce') return $a ?? self::evaluate($value->right);
        if ($op === 'elvis') return $a ?: self::evaluate($value->right);
        if ($op === 'match') {
            $fallback = null;
            foreach ($value->right as $arm) {
                if ($arm['default']) { $fallback = $arm; continue; }
                foreach ($arm['conditions'] as $condition) if ($a === self::evaluate($condition)) return self::evaluate($arm['value']);
            }
            if ($fallback !== null) return self::evaluate($fallback['value']);
            throw new \UnhandledMatchError('Unhandled match case in text');
        }
        $b = self::evaluate($value->right);
        return match ($op) {
            'add' => $a + $b, 'subtract' => $a - $b, 'multiply' => $a * $b, 'divide' => $a / $b, 'modulo' => $a % $b, 'power' => $a ** $b,
            'equal' => $a == $b, 'notEqual' => $a != $b, 'identical' => $a === $b, 'notIdentical' => $a !== $b,
            'less' => $a < $b, 'lessEqual' => $a <= $b, 'greater' => $a > $b, 'greaterEqual' => $a >= $b, 'compare' => $a <=> $b,
            'concat' => (string) $a . (string) $b, 'not' => !$a, 'gettype' => gettype($a),
            'toInt' => (int) $a, 'toFloat' => (float) $a, 'toString' => (string) $a, 'toBool' => (bool) $a,
            'isset' => $a !== null, 'empty' => empty($a), 'offset' => $a[$b] ?? null,
            'count' => count($a), 'sum' => array_sum($a), 'keys' => array_keys($a), 'values' => array_values($a),
            'ceil' => ceil($a), 'floor' => floor($a), 'round' => round($a, $b ?? 0),
            default => throw new \InvalidArgumentException('Unsupported initial text expression: ' . $op),
        };
    }
}
