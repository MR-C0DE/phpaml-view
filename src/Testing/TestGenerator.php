<?php
declare(strict_types=1);
namespace AML\View\Testing;

/** @internal A suspended interpreter frame, never an application PHP generator. */
final class TestGenerator extends TestObject
{
    public ?\Fiber $fiber;
    public array $frame;
    public bool $started = false;
    public bool $done = false;
    public bool $advanced = false;
    public bool $running = false;
    public ?array $packet = null;
    public ?array $result = null;
    public int $index = -1;
    public object $lifetime;
    private static ?\WeakMap $fibers = null;

    public function __construct(\Closure $body, array $frame, public readonly string $functionName, private \Closure $dispose)
    {
        parent::__construct(self::descriptor());
        $this->frame = $frame;
        $this->lifetime = (object) ['discarding' => false];
        $this->fiber = new \Fiber($body);
        self::$fibers ??= new \WeakMap(); self::$fibers[$this->fiber] = $this->lifetime;
    }
    public static function descriptor(): array
    {
        $methods = [];
        foreach (['rewind', 'valid', 'current', 'key', 'next', 'send', 'throw', 'getReturn', PHP_VERSION_ID >= 80400 ? '__debugInfo' : '__wakeup'] as $name) $methods[strtolower($name)] = ['name' => $name, 'id' => '@generator:' . strtolower($name), 'visibility' => 'public', 'owner' => 'Generator', 'static' => false, 'abstract' => false];
        return ['name' => 'Generator', 'ancestors' => ['Iterator', 'Traversable'], 'parent' => null, 'properties' => [], 'staticProperties' => [], 'methods' => $methods, 'constants' => [], 'final' => true, 'abstract' => false];
    }
    public static function discarding(): bool
    {
        $fiber = \Fiber::getCurrent();
        return $fiber !== null && (self::$fibers[$fiber]->discarding ?? false);
    }
    public function __destruct()
    {
        // Browser garbage collection has no synchronous PHP destructor semantics.
        // Discarding a suspended frame must not execute application instructions.
        $this->lifetime->discarding = true;
        ($this->dispose)($this);
    }
}
