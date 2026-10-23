<?php

declare(strict_types=1);

namespace AML\View;

/** @internal Keeps hook-created state scoped to the component body being built. */
final class InlineStateRegistry
{
    /** @var array<string, list<array<string, mixed>>> */
    private static array $contexts = [];

    /** @var array<string, list<int>> */
    private static array $counters = [];

    /** @var array<string, list<array<string, array>>> */
    private static array $typeRules = [];

    public static function enter(): void
    {
        $execution = self::executionId();
        self::$contexts[$execution][] = [];
        self::$counters[$execution][] = 0;
        self::$typeRules[$execution][] = [];
    }

    public static function nextId(string $prefix): string
    {
        $execution = self::executionId();
        $index = array_key_last(self::$counters[$execution] ?? []);
        if ($index === null) throw new \LogicException("{$prefix}() can only be called while building an AML View component body.");
        self::$counters[$execution][$index]++;
        return strtolower($prefix) . self::$counters[$execution][$index];
    }

    public static function register(string $name, mixed $initial, ?array $typeRule = null): void
    {
        $execution = self::executionId();
        $index = array_key_last(self::$contexts[$execution] ?? []);
        if ($index === null) throw new \LogicException('Inline state can only be registered while building an AML View component body.');
        if (array_key_exists($name, self::$contexts[$execution][$index])) {
            throw new \LogicException("Duplicate inline state identifier: {$name}");
        }
        self::$contexts[$execution][$index][$name] = $initial;
        if ($typeRule !== null) self::$typeRules[$execution][$index][$name] = $typeRule;
    }

    /** @return array<string, mixed> */
    public static function current(): array
    {
        $stack = self::$contexts[self::executionId()] ?? [];
        return $stack[array_key_last($stack)] ?? [];
    }

    public static function currentTypeRules(): array
    {
        $stack = self::$typeRules[self::executionId()] ?? [];
        return $stack[array_key_last($stack)] ?? [];
    }

    public static function leave(): void
    {
        $execution = self::executionId();
        array_pop(self::$contexts[$execution]);
        array_pop(self::$counters[$execution]);
        array_pop(self::$typeRules[$execution]);
        if ((self::$contexts[$execution] ?? []) === []) {
            unset(self::$contexts[$execution], self::$counters[$execution], self::$typeRules[$execution]);
        }
    }

    private static function executionId(): string
    {
        $fiber = \Fiber::getCurrent();
        return $fiber === null ? 'main' : 'fiber:' . spl_object_id($fiber);
    }
}
