<?php
declare(strict_types=1);
namespace AML\View\Tests\Magic;
class Bag
{
    private array $data = ['amount' => 4, 'zero' => 0, 'nothing' => null, 'items' => [3, 1, 2]];
    private string $secret = 'private';
    public array $log = [];
    public int $plain = 7;
    public int $pending;
    public function __get(string $name): mixed { $this->log[] = 'get:' . $name; return $this->data[$name] ?? null; }
    public function __set(string $name, mixed $value): void { $this->log[] = 'set:' . $name; $this->data[$name] = $value; }
    public function __isset(string $name): bool { $this->log[] = 'isset:' . $name; return array_key_exists($name, $this->data); }
    public function __unset(string $name): void { $this->log[] = 'unset:' . $name; unset($this->data[$name]); }
    public function own(): string { return $this->secret; }
}
class MethodProxy
{
    public static string $tag = 'base';
    public function __call(string $name, array $arguments): array { return [$name, $arguments]; }
    public static function __callStatic(string $name, array $arguments): array { return [static::$tag, $name, $arguments]; }
    private function hidden(): string { return 'private'; }
    private static function secret(): string { return 'private'; }
    public function direct(int $n): int { return $n * 2; }
    public function forwarded(): array { return self::missing(3); }
}
class ChildProxy extends MethodProxy { public static string $tag = 'child'; }
class Invoker
{
    public function __invoke(int $n = 1, int ...$more): int { return $n + array_sum($more); }
}
class RefInvoker { public function __invoke(int &$n): int { return ++$n; } }
class Label
{
    public function __construct(public string $text = 'Bonjour') {}
    public function __toString(): string { return $this->text; }
}
class BadLabel { public function __toString(): string { return 42; } }
class ThrowingLabel { public function __toString(): string { throw new \RuntimeException('label error'); } }
class Cloneable
{
    public function __construct(public int $value = 2, public ?Label $label = null) {}
    public function __clone(): void { $this->value++; if ($this->label !== null) { $this->label = clone $this->label; } }
}
class PrivateClone { private function __clone(): void {} public function copy(): self { return clone $this; } }
class ReadonlyClone
{
    public function __construct(public readonly int $id = 1) {}
    public function __clone(): void { $this->id = 9; }
}
class DoubleReadonlyClone
{
    public function __construct(public readonly int $id = 1) {}
    public function __clone(): void { $this->id = 9; $this->id = 10; }
}
class RecursiveGet { public function __get(string $name): mixed { return $this->again; } }
class TypedGet
{
    public int $value = 3;
    public function __get(string $name): mixed { return 'bad'; }
}
class LazyGet
{
    public int $value = 3;
    public function __get(string $name): mixed { return 5; }
}
class ObjectGet
{
    private Label $label;
    public function __construct() { $this->label = new Label; }
    public function __get(string $name): Label { return $this->label; }
}
class ErrorGet { public function __get(string $name): mixed { throw new \RuntimeException('get error'); } }
class UnsupportedDestructor { public function __destruct() {} }
class UnsupportedDebugInfo { public function __debugInfo(): array { return []; } }
class ReferenceGet { public array $data = []; public function &__get(string $name): mixed { return $this->data; } }
function apply(callable $f, int $n): int { return $f($n); }
function text(\Stringable $s): string { return (string) $s; }
function strictString(string $s): string { return $s; }
function replace(&$n): void { $n = 99; }

class UnsetReadonlyClone
{
    public function __construct(public readonly int $id = 1) {}
    public function __clone(): void { unset($this->id); $this->id = 2; }
}
class RecursiveCall { public function __call(string $name, array $arguments): mixed { return $this->again(); } }
