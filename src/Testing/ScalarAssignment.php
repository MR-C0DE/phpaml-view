<?php

// Deliberately weak: these calls use PHP's own weak scalar conversion rules.
namespace AML\View\Testing;

/** @internal Mirrors the type contract carried by the state manifest. */
final class ScalarAssignment
{
    public static function apply(array $rule, mixed $value, bool $strict): mixed
    {
        $types = array_map(static fn ($type) => ltrim($type, '?'), $rule['types']);
        if ($value instanceof \UnitEnum && $value::class === 'RoundingMode' && in_array('roundingmode', $types, true)) return $value;
        if ($value instanceof \Throwable) foreach ($types as $type) if (!array_filter(explode('&', $type), static fn ($member) => !$value instanceof $member)) return $value;
        $actual = match (gettype($value)) { 'integer' => 'int', 'double' => 'float', 'boolean' => 'bool', 'NULL' => 'null', default => gettype($value) };
        if (in_array('mixed', $types, true) || ($value === null && $rule['nullable']) || in_array($actual, $types, true)
            || ($value === true && in_array('true', $types, true)) || ($value === false && in_array('false', $types, true))) return $value;
        if ($actual === 'int' && in_array('float', $types, true)) return (float) $value;
        if (!$strict && is_scalar($value)) {
            $preferred = ['int', 'float', 'string', 'bool'];
            if (is_string($value) && is_numeric($value) && strpbrk($value, '.eE') !== false && in_array('float', $types, true)) $preferred = ['float', 'int', 'string', 'bool'];
            foreach ($preferred as $type) {
                if (!in_array($type, $types, true)) continue;
                if (in_array($type, ['int', 'float'], true) && is_string($value) && !is_numeric($value)) continue;
                if ($type === 'int' && is_float($value) && !is_finite($value)) continue;
                return match ($type) { 'int' => self::integer($value), 'float' => self::decimal($value), 'string' => self::text($value), 'bool' => self::boolean($value) };
            }
        }
        throw new \TypeError("Cannot assign {$actual} to property {$rule['property']} of type " . implode('|', $types));
    }

    private static function integer(int $value): int { return $value; }
    private static function decimal(float $value): float { return $value; }
    private static function text(string $value): string { return $value; }
    private static function boolean(bool $value): bool { return $value; }
}
