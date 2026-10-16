<?php
declare(strict_types=1);
namespace AML\View\Tests\SplReferences;
use AML\View\{Page, State, View};
use function AML\View\{Button, VStack};
final class SplReferencesPage extends Page
{
    #[State] public array $result = [];
    #[State] public int $counter = 0;
    public string $handler = 'basic';
    public function body(): View {
        if ($this->handler === 'topology') return VStack(
            Button('Init')->click($this->beginTopology(...)),
            Button('Fail')->click($this->failTopology(...)),
            Button('Continue')->click($this->continueTopology(...)),
        );
        $method = $this->handler;
        return VStack(Button('Run')->click($this->$method(...)));
    }
    public function basic(): void { $it = new \ArrayIterator(['a' => 10, 'b' => 20]); foreach ($it as $key => $value) { $this->result[] = [$key, $value]; } $this->counter = count($it); }
    public function offsets(): void { $it = new \ArrayIterator(['nested' => [1], 'nil' => null]); $it['a'] = 2; $it[] = 3; $it['nested'][] = 5; unset($it['a']); $this->result = [$it->getArrayCopy(), isset($it['nil']), $it->offsetExists('nil'), empty($it['nil'])]; }
    public function manual(): void { $it = new \ArrayIterator([4, 8, 12]); $it->seek(1); $this->result = [$it->key(), $it->current(), $it->valid()]; $it->next(); $it->next(); $this->result[] = [$it->key(), $it->current(), $it->valid()]; }
    public function object(): void { $a = new \ArrayObject(['a' => 1]); $i = $a->getIterator(); $a['b'] = 2; $i['a'] = 3; $this->result = [iterator_to_array($a), iterator_to_array($i), $a->getIteratorClass()]; }
    public function copy(): void { $a = new \ArrayObject([1, 2]); $values = $a->getArrayCopy(); $values[] = 3; $old = $a->exchangeArray(['x' => 9]); $this->result = [$values, $old, $a->getArrayCopy()]; }
    public function cloneIt(): void { $a = new \ArrayIterator([1, 2]); $a->next(); $b = clone $a; $b[0] = 8; $this->result = [$a->getArrayCopy(), $b->getArrayCopy(), $a->key(), $b->key()]; }
    public function sorts(): void { $a = new \ArrayObject(['b' => 2, 'a' => 1]); $a->ksort(); $this->result[] = $a->getArrayCopy(); $a->uasort(fn ($a, $b) => $b <=> $a); $this->result[] = $a->getArrayCopy(); }
    public function iteratorBuiltins(): void { $f = function () { yield 'a' => 1; yield 'a' => 2; yield 7 => 3; }; $this->result = [iterator_to_array($f()), iterator_to_array($f(), false), iterator_count($f()), iterator_count([1, 2])]; }
    public function apply(): void { $it = new \ArrayIterator([2, 4, 6]); $sum = 0; $count = iterator_apply($it, function (int $factor) use ($it, &$sum) { $sum += $it->current() * $factor; return $sum < 10; }, [2]); $this->result = [$count, $sum, $it->key()]; }
    public function evalSpl(): void { $code = '$a = new \\ArrayObject([1, 2]); $a[] = 3; return iterator_to_array($a);'; $this->result = eval($code); }
    public function spread(): void { $a = new \ArrayIterator(['a' => 1, 4 => 2]); $this->result = [0, ...$a, 'a' => 3]; }
    public function spreadCall(): void { $a = new \ArrayIterator(['b' => 2, 'a' => 1]); $fn = fn ($a, $b) => [$a, $b]; $this->result = [$fn(...$a), sprintf(...new \ArrayIterator(['%s/%d', 'x', 3]))]; }
    public function spreadGenerator(): void { $a = function () { yield 'x' => 1; yield 5 => 2; }; $this->result = [...$a(), ...$a()]; }
    public function spreadKeyError(): void { $a = function () { yield 1.2 => 1; }; $this->result = [...$a()]; }
    public function aliases(): void { $a = 1; $b =& $a; $b = 2; $c = 3; $a =& $c; $a = 4; $this->result = [$a, $b, $c]; unset($a); $a = 5; $this->result[] = [$a, $b, $c]; }
    public function arrayAlias(): void { $a = [1, 2]; $ref =& $a[0]; $copy = $a; $ref = 9; $this->result = [$a, $copy]; }
    public function arrayDetach(): void { $a = [1, 2]; $ref =& $a[0]; unset($ref); $copy = $a; $a[0] = 9; $this->result = [$a, $copy]; }
    public function arrayRebind(): void { $a = [1, 2]; $ref =& $a[0]; $a = [3]; $ref = 9; $this->result = [$a, $ref]; }
    public function arrayBind(): void { $a = 1; $values = []; $values['a'] =& $a; $a = 2; $this->result = [$values, $a]; }
    public function evalAlias(): void { $code = '$a = [1]; $b =& $a[0]; $b = 3; return $a;'; $this->result = eval($code); }
    public function foreachRef(): void { $a = [1, 2, 3]; foreach ($a as &$v) { $v *= 2; } $copy = $a; $v = 9; $this->result = [$a, $copy]; }
    public function foreachUnset(): void { $a = [1, 2, 3]; foreach ($a as &$v) { $v *= 2; } unset($v); $copy = $a; $a[2] = 9; $this->result = [$a, $copy]; }
    public function foreachAppend(): void { $a = [1, 2]; foreach ($a as $k => &$v) { $v *= 2; if ($k === 0) { $a[] = 3; } } unset($v); $this->result = $a; }
    public function foreachReplace(): void { $a = [1, 2]; foreach ($a as &$v) { $this->result[] = $v; if ($v === 1) { $a = [3]; } } $this->result[] = $a; }
    public function foreachDelete(): void { $a = [1, 2, 3]; foreach ($a as $k => &$v) { $this->result[] = $v; if ($k === 0) { unset($a[0], $a[1]); } } }
    public function foreachFunction(): void { $f = function ($a) { foreach ($a as &$v) { $v++; } unset($v); return $a; }; $this->result = $f([1, 2]); }
    public function returnRef(): void { $a = [1, 2]; $fn = function & (array &$a) { return $a[1]; }; $b =& $fn($a); $b = 9; $this->result = [$a, $b]; }
    public function returnLocal(): void { $fn = function & () { $value = 3; return $value; }; $a =& $fn(); $a++; $this->result = [$a]; }
    public function returnNested(): void { $a = 1; $inner = function & () use (&$a) { return $a; }; $outer = function & () use ($inner) { return $inner(); }; $b =& $outer(); $b = 4; $this->result = [$a, $b]; }
    public function returnByValue(): void { $a = [1]; $fn = function & () use (&$a) { return $a[0]; }; $b = $fn(); $b = 8; $this->result = [$a, $b]; }
    public function evalReference(): void { $code = '$a = [1, 2]; $f = function & (array &$a) { foreach ($a as &$v) { $v++; } return $a[1]; }; $b =& $f($a); $b = 8; return $a;'; $this->result = eval($code); }
    public function arrayLiteralRef(): void { $a = 1; $values = [&$a, 'a' => &$a]; $copy = $values; $values[0] = 3; $this->result = [$a, $values, $copy]; }
    public function stateRef(): void { $ref =& $this->counter; $ref += 5; $this->result = [$ref]; }
    public function stateOffsetRef(): void { $this->result = [1, 2]; $ref =& $this->result[0]; $ref = 9; }
    public function referenceRollback(): void { $this->result = [1, 2]; $ref =& $this->result[0]; $ref = 9; throw new \RuntimeException('rollback'); }
    public function evalLiteralReference(): void { $code = '$a = 1; $b = [&$a]; $a = 2; return $b;'; $this->result = eval($code); }
    public function methodReference(): void { $obj = new class { public int $n = 1; public function &value(): int { return $this->n; } }; $ref =& $obj->value(); $ref = 8; $this->result = [$obj->n, $ref]; }
    public function functionDeclarationRef(): void { function &nestedReference(array &$a) { return $a[0]; } $a = [1]; $b =& nestedReference($a); $b = 3; $this->result = $a; }
    public function generatorForeachRef(): void { $f = function () { $a = [1, 2]; foreach ($a as &$v) { $v += yield $v; } unset($v); return $a; }; $g = $f(); $this->result = [$g->current(), $g->send(3), $g->send(4), $g->getReturn()]; }
    public function copySharedRefs(): void { $a = [1]; $r =& $a[0]; $b = $a; unset($r); $c = $a; $a[0] = 9; $this->result = [$a, $b, $c]; }
    public function aliasClosureRebind(): void { $a = 1; $f = function () use (&$a) { return $a; }; $b = 2; $a =& $b; $this->result = [$f(), $a]; }
    public function nestedAlias(): void { $a = [[1]]; $b =& $a[0][0]; $b = 2; $copy = $a; $b = 3; $this->result = [$a, $copy]; }

    public function arrayHelperRefs(): void {
        $x = 2; $a = ['x' => &$x, 'y' => 1];
        $values = array_values($a); $filter = array_filter($a); $slice = array_slice($a, 0); $chunks = array_chunk($a, 1);
        $merge = array_merge($a, ['z' => 3]); $replace = array_replace([], $a); $combine = array_combine(['a','b'], $a);
        $diff = array_diff($a, [1]); $intersect = array_intersect($a, [2]); $unique = array_unique($a); $reverse = array_reverse($a);
        $pad = array_pad($a, 3, 0); $case = array_change_key_case($a, CASE_UPPER); $spread = [...$a];
        $x = 8; $this->result = [$values,$filter,$slice,$chunks,$merge,$replace,$combine,$diff,$intersect,$unique,$reverse,$pad,$case,$spread];
    }
    public function arraySortRefs(): void {
        $x = 2; $a = ['x' => &$x, 'y' => 1]; $s = $a; sort($s); $rs = $a; rsort($rs); $as = $a; asort($as); $ks = $a; ksort($ks);
        $us = $a; usort($us, fn ($a,$b) => $a <=> $b); $uas = $a; uasort($uas, fn ($a,$b) => $a <=> $b); $uks = $a; uksort($uks, fn ($a,$b) => $b <=> $a);
        $multi = $a; array_multisort($multi); $shift = $a; array_unshift($shift, 3); $pop = $a; array_pop($pop); $splice = $a; $removed = array_splice($splice, 0, 1);
        $cursor = $a; next($cursor); $x = 8; $this->result = [$s,$rs,$as,$ks,$us,$uas,$uks,$multi,$shift,$pop,$splice,$removed,$cursor];
    }
    public function splForeachRef(): void { $a = new \ArrayObject([1,2]); foreach ($a as &$v) { $v *= 3; } unset($v); $i = $a->getIterator(); foreach ($i as &$v) { $v++; } unset($v); $this->result = [$a->getArrayCopy(), $i->getArrayCopy()]; }
    public function splOffsetRef(): void { $a = new \ArrayIterator([1,2]); $r =& $a[0]; $r = 4; $b = $a->getArrayCopy(); $r = 8; $this->result = [$a->getArrayCopy(), $b]; }
    public function magicReference(): void { $a = new class { private array $data = [1]; public function &__get(string $name): mixed { return $this->data; } }; $a->data[] = 2; $r =& $a->data; $r[] = 3; $this->result = [$a->data]; }
    public function offsetReference(): void { $a = new class implements \ArrayAccess { private array $data = [[1]]; public function offsetExists(mixed $offset): bool { return isset($this->data[$offset]); } public function &offsetGet(mixed $offset): mixed { return $this->data[$offset]; } public function offsetSet(mixed $offset, mixed $value): void { $this->data[$offset] = $value; } public function offsetUnset(mixed $offset): void { unset($this->data[$offset]); } }; $a[0][] = 2; $r =& $a[0]; $r[] = 3; $this->result = [$a[0]]; }

    public function stateForeachRef(): void { $this->result = [1,2]; foreach ($this->result as &$value) { $value++; } unset($value); }
    public function readonlyReference(): void { $a = new class { public readonly int $n; public function __construct() { $this->n = 1; } }; $r =& $a->n; $this->result = [$r]; }
    public function cycleReference(): void { $a = []; $a[] =& $a; $this->result = [1]; }
    public function splFlagBoundary(): void { $a = new \ArrayObject([], \ArrayObject::ARRAY_AS_PROPS); }
    public function splSubclassBoundary(): void { $a = new class extends \ArrayObject {}; }
    public function referencePatternBoundary(): void { $a = [1]; [&$v] = $a; }

    public function iteratorReferenceCopy(): void { $x = 1; $a = [&$x]; $b = iterator_to_array($a); $c = iterator_to_array(new \ArrayIterator($a)); $d = [...new \ArrayIterator($a)]; $x = 2; $this->result = [$a,$b,$c,$d]; }
    public function splSortReenter(): void { $a = new \ArrayObject([3,2,1]); $a->uasort(function ($x,$y) use ($a) { $a[] = 9; return $x <=> $y; }); }
    public function beginTopology(): void { $x = 1; $this->result = [&$x, &$x]; }
    public function failTopology(): void { $this->result[0] = 2; throw new \RuntimeException('rollback topology'); }
    public function continueTopology(): void { $this->result[0] = 3; }
    public function propertyArrayReferenceBoundary(): void { $a = [&$this->counter]; }
    public function propertyBindingBoundary(): void { $a = 1; $this->counter =& $a; }
    public function seekError(): void { $a = new \ArrayIterator([1]); $a->seek(-1); }
    public function wrongType(): void { $this->counter = iterator_count(3); }
}
