<?php
declare(strict_types=1);
namespace AML\View\Tests\Objects;

interface Priced { public function total(): float; }
trait Labelled
{
    public function label(): string { return $this->name . ': ' . $this->total(); }
}
class Product implements Priced
{
    use Labelled;
    public const KIND = 'base';
    private int $secret = 2;
    protected float $rate = 1.0;
    public array $tags = [];
    public int $uninitialized;
    public ?Product $related = null;
    public function __construct(public string $name, public float $price = 10.0, public readonly int $id = 1) {}
    public function total(): float { return $this->price * $this->rate; }
    public function increase(float $amount): self { $this->price += $amount; return $this; }
    public function privateValue(): int { return $this->secret + $this->hidden(); }
    private function hidden(): int { return 3; }
    public function updater(): callable { return fn (float $n): float => $this->increase($n)->total(); }
    public function closure(): callable { return function (): string { return $this->name; }; }
    public function reader(): callable { return static fn (Product $p): int => $p->secret; }
    public function factorial(int $n): int { if ($n < 2) { return 1; } return $n * $this->factorial($n - 1); }
    public function forwarded(): float { return static::factor() * $this->price; }
    public static function factor(): float { return 1.0; }
    public static function make(string $name): self { return new self($name); }
    public static function late(string $name): static { return new static($name); }
    public function classInfo(): array { return [self::KIND, static::KIND, self::class, static::class]; }
    public function evalLocal(string $code): mixed { return eval($code); }
    public function defaults(int $n = 2, int ...$values): int { return $n + array_sum($values); }
    public function invalidReturn(): self { return 3; }
    public function wrongStatic(): static { return new Product('Wrong'); }
    public function copyPrice(Product $other): float { $other->price++; return $other->price; }
}
class DiscountProduct extends Product
{
    public const KIND = 'discount';
    private int $secret = 30;
    protected float $rate = 0.5;
    public function __construct(string $name, float $price = 20.0, int $id = 2) { parent::__construct($name, $price, $id); }
    public function total(): float { return parent::total() - 1.0; }
    public function childSecret(): int { return $this->secret; }
    public static function factor(): float { return 0.5; }
}
abstract class AbstractService { abstract public function value(): int; }
class ConcreteService extends AbstractService { public function value(): int { return 42; } }
class Plain { public int $value = 1; }
function readPrice(Priced $item): float { return $item->total(); }
function changePrice(float &$price): void { $price += 3.0; }
function choose(Product $item): Product { return $item; }

class Loud
{
    public function __construct(public int $value) { echo "created:" . $value . PHP_EOL; }
    public function announce(): void { echo "value:" . $this->value . PHP_EOL; }
}
class Guarded
{
    private function __construct(public int $value) {}
    public static function make(int $value): self { return new self($value); }
}

class StaticFields { public static int $counter = 0; }
class MagicService { public function __toString(): string { return 'magic'; } }
class LazyDefault { public int $value = NeverLoadedDependency::VALUE; }
class OneLine { public function a(): int { return 1; } public function b(): int { return 2; } }

class DefaultProbe { public static int $calls = 0; public function __construct() { self::$calls++; } }
class MethodDefault { public function a(): int { return 1; } public function b(mixed $value = new DefaultProbe()): mixed { return $value; } }
