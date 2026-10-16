<?php
declare(strict_types=1);
namespace AML\View\Tests\Statics;

class Counter
{
    public static int $count = 0;
    public static float $price = 10.0;
    public static string $label = 'A';
    public static bool $active = true;
    public static $loose;
    public static int $pending;
    public static ?int $nullable;
    public static array $items = [3, 1, 2];
    public static mixed $cache = null;
    protected static int $protected = 7;
    private static int $secret = 11;
    public static function next(): int { return ++static::$count; }
    public static function own(): int { return ++self::$count; }
    public static function access(): array { return [self::$secret, static::$protected]; }
    public static function hidden(): int { return static::$secret; }
    public static function reader(): \Closure { return static fn (): int => static::$count; }
    public static function evaluate(string $code): mixed { return eval($code); }
}
class ChildCounter extends Counter
{
    public static function parentValue(): int { return parent::$count; }
    public static function protectedValue(): int { return ++parent::$protected; }
}
class SeparateCounter extends Counter
{
    public static int $count = 20;
    private static int $secret = 22;
}
trait Hits { public static int $hits = 0; public static function hit(): int { return ++self::$hits; } }
class TraitCounter { use Hits; }
class OtherTraitCounter { use Hits; }
class Box { public function __construct(public int $value = 5) {} public function next(): int { return ++$this->value; } }
function bump(int &$n): void { $n += 3; }
function replaceValue(&$n): void { $n = 42; }
function badReference(&$n): void { $n = 'bad'; }
