<?php

declare(strict_types=1);
namespace AML\View\Tests\Language\Service;

function add(int $a, int $b = 5): int { return $a + $b; }
function conditional(int $n): int
{
    if ($n < 0) { return add(-$n); }
    if ($n === 0) { return 0; }
    return factorial($n);
}
function factorial(int $n): int
{
    if ($n <= 1) { return 1; }
    return $n * factorial($n - 1);
}
function even(int $n): bool { if ($n === 0) { return true; } return odd($n - 1); }
function odd(int $n): bool { if ($n === 0) { return false; } return even($n - 1); }
function touchValue(array $value): array { $value['n']++; return $value; }
function touchReference(array &$value): void { $value['n']++; }
function increment(int &$value, int $by = 1): int { $value += $by; return $value; }
function aliases(int &$a, int &$b): array { $a++; $b += 2; return [$a, $b]; }
function sum(int ...$values): int { $total = 0; foreach ($values as $value) { $total += $value; } return $total; }
function variadicKeys(...$values): array { return $values; }
function variadicReferences(int &...$values): void { $values[0]++; $values['tail'] += 2; }
function callTwice(callable $f, int $value): int { return $f($f($value)); }
function factory(int $step): callable { return fn (int $n): int => $n + $step; }
function firstPositive(array $values): ?int
{
    foreach ($values as $value) {
        switch ($value) { case 0: break; default: if ($value > 0) { return $value; } }
    }
    return null;
}
function withEcho(int $value): int { echo 'service:' . $value . PHP_EOL; return $value * 2; }
function floatResult(float $value = 1.0): float { return $value; }
function unionValue(int|string|null $value): int|string|null { return $value; }
function wrongReturn(): int { return 'no'; }
function missingReturn(): int { $x = 1; }
function explodeCall(int $value): int { return 10 / $value; }
function infinite(int $value): int { return infinite($value + 1); }
function neverReturns(): never { $value = 1; }
function initialize(&$value): void { $value = 9; }
function makeCounter(int $start): callable { return function () use (&$start): int { return ++$start; }; }
function mutateThenFail(int &$value): int { $value++; return 1 / 0; }
function nullableResult(): ?int { return null; }
function callbackDefault($fn = 'AML\\View\\Tests\\Language\\Service\\factorial'): int { return $fn(4); }
function returnFromWhile(int $n): int { while (true) { if ($n > 0) { return $n; } $n++; } }

function serverOnly(): void { file_put_contents('/private/tmp/phpaml-level6-must-not-execute', 'executed'); }
final class ConstructorProbe
{
    public static int $calls = 0;
    public function __construct() { self::$calls++; }
}
function objectDefault($value = new ConstructorProbe()): void {}
