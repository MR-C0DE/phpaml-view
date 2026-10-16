<?php

declare(strict_types=1);
namespace AML\View\Tests\Language;

use AML\View\{Page, State, View};
use function AML\View\{Button, VStack};
use function AML\View\Tests\Language\Service\{add as plus, conditional, factorial, even, touchValue, touchReference, increment, aliases, sum, variadicKeys, variadicReferences, callTwice, factory, firstPositive, withEcho, floatResult, unionValue, wrongReturn, missingReturn, explodeCall, infinite, neverReturns, initialize, makeCounter, mutateThenFail, nullableResult, callbackDefault, returnFromWhile};
use function AML\View\Tests\Language\WeakService\{weakCall, weakReturn, weakReference};

final class LanguageFunctions extends Page
{
    #[State] public int $counter = 0;
    #[State] public array $result = [];
    public string $handler = 'calls';

    public function body(): View
    {
        if ($this->handler === 'captured') {
            $base = 4;
            $helper = fn (int $n): int => plus($base, $n);
            return VStack(Button('Run')->onClick(function () use ($helper): void { $this->result = [$helper(5)]; }));
        }
        return VStack(Button('Run')->onClick($this->{$this->handler}(...)));
    }
    public function captured(): void { $this->result = [plus(4, 5)]; }
    public function captureInitialize(): void
    {
        $fn = function () use (&$unknown): int { return ++$unknown; };
        $this->result = [isset($unknown), $fn(), $fn(), $unknown];
    }
    public function emptyCallbackError(): void { $this->counter++; $this->result = array_map(42, []); }
    public function offsetWarning(): void { $a = []; $f = function ($value): array { return [$value]; }; $this->result = $f($a['missing']); }
    public function onlyReturn(): void { return; }
    public function objectDefaultCall(): void { \AML\View\Tests\Language\Service\objectDefault(); }
    public function serverOnlyCall(): void { \AML\View\Tests\Language\Service\serverOnly(); }

    public function calls(): void
    {
        $this->result = [plus(10, 5), plus(b: 2, a: 4), plus(3), conditional(5), conditional(-3), conditional(0), even(8), even(9), $this->local(4), self::staticAdd(2), firstPositive([-3, 0, 7, 9]), firstPositive([-1, 0])];
        echo plus(10, 5) . PHP_EOL;
    }
    private function local(int $n): int { if ($n <= 0) { return 0; } $this->counter++; return $n + $this->local($n - 1); }
    private static function staticAdd(int $n, int $b = 2): int { return plus($n, $b); }
    public function weakPropertyReference(): void { weakReference($this->counter); $this->result = [$this->counter, gettype($this->counter)]; }
    public function references(): void
    {
        $data = ['n' => 1]; $copy = touchValue($data); touchReference($data);
        $n = 2; $same = aliases($n, $n);
        $items = [10, 20]; $i = 0; increment($items[$i++], 3);
        increment($this->counter, 4);
        $this->result = [$data, $copy, $n, $same, $items, $i, $this->counter];
    }
    public function variadics(): void
    {
        $values = [1, 2]; $a = 3; $b = 4; variadicReferences($a, tail: $b);
        $this->result = [sum(), sum(1, 2, 3), sum(...$values, extra: 4), variadicKeys(1, second: 2, third: 3), plus(...['b' => 8, 'a' => 2]), $a, $b, increment(...[10]), increment(...['value' => 20, 'by' => 3])];
    }
    public function closures(): void
    {
        $n = 3;
        $arrow = fn (int $x = 2): int => $x + $n;
        $byValue = function (int $x) use ($n): int { $n++; return $x + $n; };
        $byReference = function () use (&$n): int { $n++; return $n; };
        $n = 10;
        $call = factory(5);
        $this->result = [$arrow(), $byValue(1), $byValue(1), $byReference(), $byReference(), $n, $call(2), factory(3)(4), callTwice($call, 1), callTwice(fn (int $v): int => $v * 2, 3), gettype($arrow)];
    }
    public function recursiveClosure(): void
    {
        $factorial = function (int $n) use (&$factorial): int { if ($n <= 1) { return 1; } return $n * $factorial($n - 1); };
        $this->result = [$factorial(6)];
    }
    public function callbacks(): void
    {
        $n = 0;
        $mapper = function (int $v) use (&$n): int { $n++; return plus($v, $n); };
        $this->result = [array_map($mapper, [1, 2]), $n, array_map('AML\\View\\Tests\\Language\\Service\\factorial', [2, 3]), array_filter([1, 2, 3], function (int $v): bool { return $v > 1; }), array_reduce([1, 2, 3], function (int $carry, int $v): int { return plus($carry, $v); }, 0)];
        $f = plus(...); $this->result[] = $f(2, 3);
    }
    public function types(): void { $this->result = [gettype(floatResult(2)), gettype(floatResult()), unionValue(null), unionValue('ok'), weakCall(), weakReturn(), withEcho(4)]; }
    public function earlyReturn(): void { $this->counter++; for ($i = 0; $i < 3; $i++) { if ($i === 1) { return; } $this->result[] = $i; } $this->counter = 99; }
    public function nestedDeclaration(): void
    {
        function nestedLevelSix(int $n): int { if ($n === 0) { return 0; } return 1 + nestedLevelSix($n - 1); }
        $this->result = [nestedLevelSix(4)];
    }
    public function additionalCalls(): void
    {
        $name = 'AML\\View\\Tests\\Language\\Service\\factorial';
        $counter = makeCounter(3);
        initialize($undefined);
        $this->result = [$name(4), $counter(), $counter(), $undefined, nullableResult(), returnFromWhile(0), callbackDefault(), (fn (int $x): int => $x + 1)(2)];
    }
    public function nestedClosures(): void
    {
        $a = 2;
        $factory = fn (int $b): callable => fn (int $c): int => $a + $b + $c;
        $f = $factory(3);
        $this->result = [$f(4), $factory(4)(5)];
    }
    public function refFailure(): void { mutateThenFail($this->counter); }
    public function duplicateDeclaration(): void { $this->nestedDeclaration(); $this->nestedDeclaration(); }
    public function badArgument(): void { $this->counter++; $this->result[] = plus('10', 5); }
    public function badReturn(): void { $this->counter++; $this->result[] = wrongReturn(); }
    public function absentReturn(): void { $this->counter++; $this->result[] = missingReturn(); }
    public function absentArgument(): void { $this->counter++; $this->result[] = plus(); }
    public function badName(): void { $this->counter++; $this->result[] = plus(1, unknown: 2); }
    public function duplicateName(): void { $this->counter++; $this->result[] = plus(1, ...['a' => 2]); }
    public function badReference(): void { $this->counter++; increment(2); }
    public function badCallable(): void { $this->counter++; callTwice(42, 1); }
    public function badReturnInside(): void { $this->counter++; $f = fn (): int => 'bad'; $f(); }
    public function nestedError(): void { $this->counter++; explodeCall(0); }
    public function invalidNever(): void { $this->counter++; neverReturns(); }
    public function recursionLimit(): void { $this->counter++; infinite(0); }
    public function invalidState(): void { $this->counter++; $this->result[] = fn () => 1; }
}
