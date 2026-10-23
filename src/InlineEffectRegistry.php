<?php

declare(strict_types=1);

namespace AML\View;

/** @internal Keeps hook-style effects scoped to the component body being built. */
final class InlineEffectRegistry
{
    /** @var array<string, list<array<string, array<string, mixed>>>> */
    private static array $contexts = [];

    public static function enter(): void
    {
        self::$contexts[self::executionId()][] = [];
    }

    /** @param array<string, mixed> $effect */
    public static function register(string $id, array $effect): void
    {
        $id = \AML\Engine\StateNamespace::qualify($id);
        $execution = self::executionId();
        $index = array_key_last(self::$contexts[$execution] ?? []);
        if ($index === null) throw new \LogicException('Effect() can only be called while building an AML View component body.');
        if (isset(self::$contexts[$execution][$index][$id])) {
            throw new \LogicException("Duplicate inline effect identifier: {$id}");
        }
        self::$contexts[$execution][$index][$id] = $effect;
    }

    /** @return array<string, array<string, mixed>> */
    public static function current(): array
    {
        $stack = self::$contexts[self::executionId()] ?? [];
        return $stack[array_key_last($stack)] ?? [];
    }

    public static function leave(): void
    {
        $execution = self::executionId();
        array_pop(self::$contexts[$execution]);
        if ((self::$contexts[$execution] ?? []) === []) unset(self::$contexts[$execution]);
    }

    private static function executionId(): string
    {
        $fiber = \Fiber::getCurrent();
        return $fiber === null ? 'main' : 'fiber:' . spl_object_id($fiber);
    }
}
