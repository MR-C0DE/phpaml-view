<?php

namespace AML\View\Testing;

/** @internal Mirrors weak constructor calls without evaluating PHP source. */
final class ExceptionFactory
{
    public static function make(string $class, array $arguments): \Throwable { return new $class(...$arguments); }
}
