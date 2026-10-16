<?php

namespace AML\View\Testing;

/** @internal Calls only the closed builtin list, using PHP's weak argument rules. */
final class WeakBuiltinInvoker
{
    public static function invoke(string $name, array &$arguments): mixed
    {
        if (!in_array($name, \AML\Engine\BuiltinFunctions::NAMES, true)) throw new \InvalidArgumentException('Unsupported frontend builtin.');
        return $name(...$arguments);
    }
}
