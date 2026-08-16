<?php

declare(strict_types=1);

namespace AML\View;

final class Runtime
{
    private static ?StateCycle $cycle = null;

    public static function enter(StateCycle $cycle): void
    {
        if (self::$cycle !== null) {
            throw new \LogicException('An AML View render cycle is already active.');
        }
        self::$cycle = $cycle;
    }

    public static function leave(): void
    {
        self::$cycle = null;
    }

    public static function state(mixed $initial): StateValue
    {
        return self::$cycle?->create($initial) ?? new StateValue($initial);
    }

    public static function prepare(Component $component): bool
    {
        if (self::$cycle === null) {
            return false;
        }

        self::$cycle->remember($component);
        return self::$cycle->hydrateComponent($component);
    }
}
