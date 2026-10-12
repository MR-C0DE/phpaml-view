<?php

declare(strict_types=1);

namespace AML\View;

use AML\Engine\StateRef;

/** @internal Keeps template interpolation scoped to the component being rendered. */
final class StateInterpolation
{
    /** @var array<string, list<array<string, StateRef>>> */
    private static array $contexts = [];

    /** @param array<string, StateRef> $states */
    public static function enter(array $states): void
    {
        self::$contexts[self::executionId()][] = $states;
    }

    public static function leave(): void
    {
        $id = self::executionId();
        array_pop(self::$contexts[$id]);
        if (self::$contexts[$id] === []) unset(self::$contexts[$id]);
    }

    /** @return array<string, StateRef>|null */
    public static function current(): ?array
    {
        $stack = self::$contexts[self::executionId()] ?? [];
        return $stack[array_key_last($stack)] ?? null;
    }

    private static function executionId(): string
    {
        $fiber = \Fiber::getCurrent();
        return $fiber === null ? 'main' : 'fiber:' . spl_object_id($fiber);
    }
}
