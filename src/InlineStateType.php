<?php

declare(strict_types=1);

namespace AML\View;

/** @internal Type contract for State() values transported to the browser. */
final class InlineStateType
{
    public static function rule(mixed $initial, ?string $type, string $target): array
    {
        $type ??= match (gettype($initial)) {
            'integer' => 'int', 'double' => 'float', 'boolean' => 'bool', 'NULL' => 'null',
            default => gettype($initial),
        };
        $type = strtolower(trim($type));
        $nullable = str_starts_with($type, '?');
        $types = array_map(trim(...), explode('|', $nullable ? substr($type, 1) : $type));
        $allowed = ['int', 'float', 'string', 'bool', 'array', 'null', 'mixed', 'true', 'false'];
        if (array_diff($types, $allowed) !== [] || count(array_unique($types)) !== count($types)
            || ($nullable && (count($types) !== 1 || in_array($types[0], ['null', 'mixed'], true)))
            || (count($types) > 1 && in_array('mixed', $types, true))
            || (in_array('bool', $types, true) && array_intersect($types, ['true', 'false']) !== [])
            || (in_array('true', $types, true) && in_array('false', $types, true))) {
            throw new \InvalidArgumentException("Unsupported State() type: {$type}. Use scalar types, array, null, mixed or a union of these types.");
        }
        return [
            'types' => $types,
            'nullable' => $nullable || in_array('null', $types, true) || in_array('mixed', $types, true),
            'property' => 'State(' . $target . ')',
        ];
    }

    public static function initial(mixed $value, array $rule): mixed
    {
        self::validateValue($value);
        $actual = get_debug_type($value);
        $types = $rule['types'];
        if (in_array('mixed', $types, true) || ($value === null && $rule['nullable']) || in_array($actual, $types, true)
            || ($value === true && in_array('true', $types, true)) || ($value === false && in_array('false', $types, true))) return $value;
        if (is_int($value) && in_array('float', $types, true)) return (float) $value;
        throw new \TypeError("Cannot initialize {$rule['property']} with {$actual}; expected " . ($rule['nullable'] ? '?' : '') . implode('|', $types));
    }

    private static function validateValue(mixed $value, int $depth = 0): void
    {
        if ($depth >= 512) throw new \InvalidArgumentException('State() exceeds JSON nesting depth.');
        if ($value !== null && !is_scalar($value) && !is_array($value)) throw new \InvalidArgumentException('State() accepts scalars, null and arrays of these values.');
        if (is_float($value) && !is_finite($value)) throw new \RangeException('State() requires finite floats.');
        if (is_int($value) && abs($value) > 9007199254740991) throw new \RangeException('State() exceeds JavaScript safe integer precision.');
        if (is_array($value)) foreach ($value as $key => $item) {
            if (is_int($key)) self::validateValue($key, $depth + 1);
            self::validateValue($item, $depth + 1);
        }
    }
}
