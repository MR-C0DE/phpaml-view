<?php
declare(strict_types=1);
namespace AML\View\Testing;

/** @internal Lets native PHP builtins request interpreted __toString conversions. */
final class TestStringableObject extends TestObject
{
    private static ?\WeakMap $converters = null;
    public static function bind(self $value, \Closure $converter): void { self::$converters ??= new \WeakMap(); self::$converters[$value] = $converter; }
    public function __toString(): string { return (self::$converters[$this])($this); }
}
