<?php
declare(strict_types=1);
namespace AML\View\Testing;

/** @internal Lazy bridge to PHP's JSON recursion/flag rules; callbacks only interpret IR. */
final class TestJsonSerializableValue implements \JsonSerializable
{
    private static ?\WeakMap $callbacks = null;
    public function __construct(\Closure $callback) { self::$callbacks ??= new \WeakMap; self::$callbacks[$this] = $callback; }
    public function jsonSerialize(): mixed { return (self::$callbacks[$this])($this); }
}
