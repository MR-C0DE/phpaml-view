<?php
declare(strict_types=1);
namespace AML\View\Tests\Generators;
use AML\View\{Page, State, View};
use function AML\View\{Button, VStack};

function numbers(int $count = 3): \Generator { for ($i = 0; $i < $count; $i++) { yield $i + 0.0; } return 42.0; }
function rows(iterable $source): array { $result = []; foreach ($source as $k => $v) { $result[] = [$k, $v, gettype($k), gettype($v)]; } return $result; }
function child(): \Generator { $a = yield 'first'; yield $a; return 7; }
function parentGenerator(): \Generator { $result = yield from child(); yield $result; return 9; }
function takes(int $a, int $b): int { return $a + $b; }
function reference(array &$values, mixed $v): int { $values[] = $v; return count($values); }
interface StreamProvider { public function stream(): iterable; }
class Items implements \IteratorAggregate { public function __construct(public array $items = ['a' => 2, 'b' => 3]) {} public function getIterator(): \Generator { foreach ($this->items as $k => $v) { yield $k => $v; } } }
class Saved { public static ?\Generator $generator = null; public static function next(): int { self::$generator ??= numbers(5); $g = self::$generator; $value = $g->current(); $g->next(); return (int) $value; } }

class GeneratorsPage extends Page {
    #[State] public array $result = [];
    #[State] public int $counter = 0;
    public string $handler = 'basic';
    public function body(): View { return VStack(Button('Run')->onClick($this->{$this->handler}(...))); }
    public function basic(): void { $g = numbers(); $this->result = [rows($g), $g->getReturn(), is_iterable($g), $g instanceof \Generator, $g instanceof \Iterator]; }
    public function lazy(): void { $fn = function () { echo 'start'; yield 1; echo 'end'; }; $g = $fn(); echo 'created'; $this->result = [$g->current(), $g->current()]; echo 'middle'; $g->next(); $this->result[] = $g->valid(); }
    public function keys(): void { $fn = function () { yield; yield 'x' => 1; yield 7 => 2; yield 2 => 3; yield 2.0 => 4; yield [] => 5; yield 6; }; $this->result = rows($fn()); }
    public function empty(): void { $fn = function () { if (false) { yield 1; } return 'done'; }; $g = $fn(); $this->result = [$g->valid(), $g->current(), $g->key(), $g->getReturn()]; }
    public function send(): void { $g = child(); $this->result = [$g->current(), $g->send(value: 'hello'), $g->key(), $g->send(null), $g->getReturn()]; }
    public function sendFresh(): void { $g = child(); $this->result = [$g->send(12), $g->getReturn() ?? null]; }
    public function nextFresh(): void { $g = numbers(); $g->next(); $this->result = [$g->key(), $g->current()]; }
    public function delegate(): void { $g = parentGenerator(); $this->result = [$g->current(), $g->send(5), $g->send(null), $g->send(null), $g->getReturn()]; }
    public function arrays(): void { $fn = function () { yield 5 => 1; $r = yield from ['x' => 2, 10 => 3]; yield $r; yield 4; }; $this->result = rows($fn()); }
    public function aggregate(): void { $fn = function () { yield from new Items; }; $this->result = rows($fn()); }
    public function breaking(): void { $g = numbers(); foreach ($g as $v) { break; } $this->result = [$g->current(), rows($g), $g->getReturn()]; }
    public function finallyResume(): void { $fn = function () { try { yield 1; yield 2; } finally { echo 'finally'; } }; $g = $fn(); foreach ($g as $v) { break; } echo 'paused'; $this->result[] = $g->current(); $g->next(); $g->next(); $this->result[] = $g->valid(); }
    public function thrown(): void { $fn = function () { try { yield 1; } catch (\RuntimeException $e) { yield $e->getMessage(); } finally { echo 'done'; } return 8; }; $g = $fn(); $this->result = [$g->throw(new \RuntimeException('injected')), $g->send(null), $g->getReturn()]; }
    public function delegatedThrow(): void { $inner = function () { try { yield 1; } catch (\RuntimeException $e) { yield 'caught'; } return 4; }; $outer = function () use ($inner) { return yield from $inner(); }; $g = $outer(); $this->result = [$g->current(), $g->throw(new \RuntimeException('injected')), $g->send(null), $g->getReturn()]; }
    public function finallyYield(): void { $fn = function () { try { yield 1; return 5; } finally { yield 2; } }; $g = $fn(); $this->result = [rows($g), $g->getReturn()]; }
    public function conditions(): void { $fn = function () { $i = 0; while ($i++ < 3) { if ($i === 2) { continue; } yield $i; } do { yield $i--; } while ($i > 2); }; $this->result = rows($fn()); }
    public function expression(): void { $fn = function () { $a = 2 + (yield 1.0); yield $a; return (yield 9) * 2; }; $g = $fn(); $this->result = [$g->current(), $g->send(3.0), $g->send(null), $g->send(4), $g->getReturn()]; }
    public function lazyExpression(): void { $fn = function () { $a = false && (yield 1); $b = true || (yield 2); $c = 3 ?? (yield 4); $d = false ? (yield 5) : 6; yield [$a, $b, $c, $d]; }; $this->result = rows($fn()); }
    public function callExpression(): void { $fn = function () { $i = 1; yield takes($i++, (yield $i++)); yield $i; }; $g = $fn(); $this->result = [$g->current(), $g->send(5), $g->send(null), $g->send(null)]; }
    public function builtinExpression(): void { $fn = function () { yield str_replace('a', (yield 'replace'), 'aaa'); }; $g = $fn(); $this->result = [$g->current(), $g->send('x'), $g->send(null)]; }
    public function referenceExpression(): void { $fn = function () { $a = []; yield reference($a, (yield 1)); yield $a; }; $g = $fn(); $this->result = [$g->current(), $g->send('x'), $g->send(null), $g->send(null)]; }
    public function arrayExpression(): void { $fn = function () { $i = 0; yield [$i++ => (yield $i++), 'a' => (yield $i++)]; }; $g = $fn(); $this->result = [$g->current(), $g->send(6.0), $g->send(7), $g->send(null)]; }
    public function assignment(): void { $fn = function () { $a = [2]; $i = 0; $a[$i++] += yield 1; yield [$a, $i]; }; $g = $fn(); $this->result = [$g->current(), $g->send(5), $g->send(null)]; }
    public function matchExpression(): void { $fn = function () { yield match (yield 1) { 2 => (yield 3), default => 9 }; }; $g = $fn(); $this->result = [$g->current(), $g->send(2), $g->send(4), $g->send(null)]; }
    public function closureCapture(): void { $x = 3; $fn = function () use (&$x) { yield $x++; yield $x; }; $g = $fn(); $this->result = [rows($g), $x]; }
    public function arrow(): void { $fn = fn ($v) => yield $v; $this->result = rows($fn(4)); }
    public function nested(): void { $fn = function () { foreach (numbers(2) as $v) { yield from [$v, $v + 1]; } }; $this->result = rows($fn()); }
    public function anonymous(): void { $a = new class implements \IteratorAggregate { public function getIterator(): \Traversable { yield 4; yield 5; } }; $this->result = rows($a); }
    public function callableMethod(): void { $g = numbers(1); $read = $g->current(...); $this->result = [$read(), $g->valid()]; $g->next(); }
    public function evalGenerator(): void { $code = '$f = function (): Generator { yield 1; return 3; }; return $f();'; $g = eval($code); $this->result = [rows($g), $g->getReturn()]; }
    public function staticGenerator(): void { $this->result = [Saved::next(), Saved::next()]; }
    public function argumentError(): void { $this->counter++; $g = numbers('bad'); }
    public function bodyError(): void { $fn = function () { yield 1; throw new \RuntimeException('body failed'); }; $g = $fn(); $this->counter++; foreach ($g as $v) { echo $v; } }
    public function earlyReturnError(): void { $g = numbers(); $this->result = [$g->getReturn()]; }
    public function rewindError(): void { $g = numbers(); $g->next(); $g->rewind(); }
    public function closedError(): void { $g = numbers(1); rows($g); rows($g); }
    public function invalidDelegate(): void { $fn = function () { yield from 12; }; rows($fn()); }
    public function cloneError(): void { $g = numbers(); $copy = clone $g; }
    public function throwTypeError(): void { $g = numbers(); $g->throw('bad'); }
    public function reentry(): void { $g = null; $fn = function () use (&$g) { $g->next(); yield 1; }; $g = $fn(); $g->current(); }

    public function emptyGetReturn(): void { $fn = function () { echo 'start'; if (false) { yield; } return 5; }; $g = $fn(); $this->result = [$g->getReturn()]; }
    public function sideEffectGetReturn(): void { $fn = function () { echo 'start'; yield 1; }; $g = $fn(); try { $g->getReturn(); } catch (\Exception $e) { $this->result = [$g->current()]; } $g->next(); }
    public function covariance(): void { $a = new class implements StreamProvider { public function stream(): \Generator { yield 1; } }; $code = 'return new class implements \\AML\\View\\Tests\\Generators\\StreamProvider { public function stream(): \\Generator { yield 2; } };'; $b = eval($code); $this->result = [rows($a->stream()), rows($b->stream())]; }
    public function introspection(): void { $g = numbers(); $this->result = [get_class($g), get_parent_class($g), get_class_methods($g), get_object_vars($g), is_a($g, 'Generator'), is_subclass_of($g, 'Iterator'), method_exists($g, 'send'), property_exists($g, 'fiber'), class_exists('Generator', false)]; }
    public function methodDump(): void { $g = (new Items)->getIterator(); var_dump($g); $this->result = [$g->__debugInfo()]; }
    public function dump(): void { $g = numbers(); var_dump($g); $this->result = [$g->__debugInfo()]; }
    public function switchYield(): void { $fn = function () { switch (yield 1) { case 2: yield 3; case 4: yield 5; break; default: yield 6; } }; $g = $fn(); $this->result = [$g->current(), $g->send(2), $g->send(null), $g->send(null)]; }
    public function loopYield(): void { $fn = function () { for ($i = 0; (yield $i) && $i < 3; $i++) { yield $i + 5; } }; $g = $fn(); $this->result = [$g->current(), $g->send(true), $g->send(null), $g->send(false), $g->valid()]; }
    public function yieldedForeach(): void { $fn = function () { foreach ((yield 'source') as $k => $v) { yield $k => $v; } }; $g = $fn(); $this->result = [$g->current(), $g->send(['x' => 4]), $g->key(), $g->send(null)]; }
    public function nullsafe(): void { $fn = function () { $a = null; yield $a?->getIterator(yield 1); }; $this->result = rows($fn()); }
    public function coalesce(): void { $fn = function () { $a = null; $a ??= yield 1; $a ??= yield 2; yield $a; }; $g = $fn(); $this->result = [$g->current(), $g->send(4), $g->send(null)]; }
    public function builtinReference(): void { $fn = function () { $a = [1]; yield array_push($a, yield 2); yield $a; }; $g = $fn(); $this->result = [$g->current(), $g->send(4), $g->send(null), $g->send(null)]; }
    public function methodExpression(): void { $fn = function () { $obj = new Items; yield $obj->getIterator(yield 1)->current(); }; $g = $fn(); $this->result = [$g->current(), $g->send(4), $g->send(null)]; }
    public function recursion(): void { $fn = function ($n) use (&$fn) { if ($n > 0) { yield from $fn($n - 1); } yield $n; }; $this->result = rows($fn(5)); }
    public function delegateClosed(): void { $g = numbers(1); rows($g); $fn = function () use ($g) { yield from $g; }; rows($fn()); }
    public function throwClosed(): void { $g = numbers(0); $g->valid(); $g->throw(new \RuntimeException('closed')); }
    public function stateSuspension(): void { $fn = function () { $this->counter++; yield $this->counter; $this->counter++; yield $this->counter; }; $this->result = rows($fn()); }
    public function freshIsolation(): void { $this->result = [Saved::next()]; }

    public function changedReference(): void { $a = []; $fn = function () use (&$a) { yield reference($a, yield 1); yield $a; }; $g = $fn(); $g->current(); $a = [5]; $this->result = [$g->send(7), $g->send(null), $g->send(null), $a]; }
    public function changedBuiltinReference(): void { $a = []; $fn = function () use (&$a) { yield array_push($a, yield 1); yield $a; }; $g = $fn(); $g->current(); $a = [5]; $this->result = [$g->send(7), $g->send(null), $g->send(null), $a]; }
    public function destructureYield(): void { $fn = function () { $a = []; [$a[(yield 1)]] = [8]; yield $a; }; $g = $fn(); $this->result = [$g->current(), $g->send(2), $g->send(null)]; }
    public function foreachTargetYield(): void { $fn = function () { $a = []; foreach ([8] as $a[(yield 1)]) { yield $a; } }; $g = $fn(); $this->result = [$g->current(), $g->send(2), $g->send(null)]; }
    public function staticBudget(): void {
        if ($this->counter === 0) {
            $fn = function () { yield 0; for ($i = 0; $i < 8; $i++) { for ($j = 0; $j < 800; $j++) {} } yield 1; };
            Saved::$generator = $fn();
            for ($i = 0; $i < 6; $i++) { for ($j = 0; $j < 800; $j++) {} }
        } else { Saved::$generator->next(); }
        $this->result = [Saved::$generator->current()]; $this->counter++;
    }
    public function loopLimit(): void { $fn = function () { while (true) { yield 1; } }; foreach ($fn() as $v) {} }
    public function invalidState(): void { $this->result = [numbers()]; }
}
