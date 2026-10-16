<?php
declare(strict_types=1);
namespace AML\View\Tests\Collections;
use AML\View\{Page, State, View};
use function AML\View\{Button, VStack};
class Bag implements \ArrayAccess, \Countable {
    public array $log = [];
    private array $items = ['n' => 2, 'float' => 2.0, 'list' => [3, 1], 'nil' => null, 'zero' => 0];
    public function offsetExists(mixed $offset): bool { $this->log[] = ['exists', $offset]; return array_key_exists($offset, $this->items); }
    public function offsetGet(mixed $offset): mixed { $this->log[] = ['get', $offset]; return $this->items[$offset] ?? null; }
    public function offsetSet(mixed $offset, mixed $value): void { $this->log[] = ['set', $offset, $value]; if ($offset === null) { $this->items[] = $value; } else { $this->items[$offset] = $value; } }
    public function offsetUnset(mixed $offset): void { $this->log[] = ['unset', $offset]; unset($this->items[$offset]); }
    public function count(): int { $this->log[] = ['count']; return count($this->items); }
    public function snapshot(): array { return $this->items; }
    public function inner(): array { $this['inner'] = 3; return [$this['inner'], isset($this['inner'])]; }
}
class ChildBag extends Bag {}
class Plain { public int $value = 1; }
class Counter implements \Countable { public function __construct(private int $size = -3) {} public function count(): int { return $this->size; } }
class LooseCounter implements \Countable { public function __construct(private mixed $size) {} #[\ReturnTypeWillChange] public function count() { return $this->size; } }
class BadCounter implements \Countable { public function count(): int { return 'oops'; } }
class RecursiveCounter implements \Countable { public function count(): int { return count($this); } }
class FailingBag implements \ArrayAccess {
    public function offsetExists(mixed $offset): bool { throw new \RuntimeException('exists failed'); }
    public function offsetGet(mixed $offset): mixed { throw new \RuntimeException('get failed'); }
    public function offsetSet(mixed $offset, mixed $value): void { throw new \RuntimeException('set failed'); }
    public function offsetUnset(mixed $offset): void { throw new \RuntimeException('unset failed'); }
}
class RawKeys implements \ArrayAccess {
    public array $log = [];
    public function offsetExists(mixed $offset): bool { $this->log[] = ['exists', gettype($offset)]; return true; }
    public function offsetGet(mixed $offset): mixed { $this->log[] = ['get', gettype($offset)]; return gettype($offset); }
    public function offsetSet(mixed $offset, mixed $value): void { $this->log[] = ['set', gettype($offset), gettype($value)]; }
    public function offsetUnset(mixed $offset): void { $this->log[] = ['unset', gettype($offset)]; }
}
class ObjectBag implements \ArrayAccess {
    public function __construct(public Bag $bag, public Plain $item) {}
    public function offsetExists(mixed $offset): bool { return true; }
    public function offsetGet(mixed $offset): mixed { return $offset === 'bag' ? $this->bag : $this->item; }
    public function offsetSet(mixed $offset, mixed $value): void { throw new \RuntimeException('outer setter must not run'); }
    public function offsetUnset(mixed $offset): void {}
}
function swapBag(Bag &$bag): int { $bag = new Bag; return 99; }
function logRhs(Bag $bag): int { $bag->log[] = ['rhs']; return 9; }
function makeBag(): Bag { return new Bag; }
function keepValue(mixed $value): mixed { return $value; }
function replaceValues(mixed &$a, mixed &$b): void { $a = 20; $b = 30; }
function replaceValue(mixed &$value): void { $value = 10; }
function contract(\Countable&\ArrayAccess $bag): array { return [count($bag), $bag['n']]; }
class CollectionObjectsPage extends Page {
    #[State] public array $result = [];
    #[State] public int $counter = 0;
    public string $handler = 'basic';
    public function body(): View { return VStack(Button('Run')->onClick($this->{$this->handler}(...))); }
    public function basic(): void { $b = new Bag; $this->result = [$b['n'], $b['float'], gettype($b['float']), count($b), sizeof($b), $b->log]; }
    public function write(): void { $b = new Bag; $r = $b['n'] = 5; $b[] = 'extra'; $b[null] = 'null'; $b[1.5] = 'float'; $this->result = [$r, $b->snapshot(), $b->log]; }
    public function quiet(): void { $b = new Bag; $this->result = [isset($b['nil']), isset($b['missing']), empty($b['nil']), empty($b['zero']), empty($b['n']), empty($b['missing']), $b['nil'] ?? 'fallback', $b['n'] ?? 'fallback', $b->log]; }
    public function coalesce(): void { $b = new Bag; $b['n'] ??= 9; $b['nil'] ??= 8; $b['missing'] ??= 7; $this->result = [$b->snapshot(), $b->log]; }
    public function compound(): void { $b = new Bag; $a = $b['n'] += 3; $b['n'] *= 2; $b['float'] /= 2; $b['text'] = 'A'; $b['text'] .= 'B'; $this->result = [$a, $b->snapshot(), gettype($b['float']), $b->log]; }
    public function increments(): void { $b = new Bag; $a = $b['n']++; $c = ++$b['n']; $d = $b['n']--; $e = --$b['n']; $this->result = [$a, $c, $d, $e, $b->snapshot(), $b->log]; }
    public function remove(): void { $b = new Bag; unset($b['n'], $b['missing']); $this->result = [$b->snapshot(), $b->log]; }
    public function nestedRead(): void { $b = new Bag; $this->result = [$b['list'][0], isset($b['list'][0]), empty($b['list'][1]), $b['missing'][0] ?? 'fallback', $b->log]; }
    public function nestedWrite(): void { $b = new Bag; $b['list'][] = 4; $b['nil']['x'] = 5; unset($b['list'][0]); $this->result = [$b->snapshot(), $b->log]; }
    public function nestedCoalesce(): void { $b = new Bag; $i = 0; $b['list'][$i++] ??= 8; $b['list'][4] ??= 8; $b['nil'][0] ??= 9; $b['missing'][0] ??= 7; $this->result = [$i, $b->snapshot(), $b->log]; }
    public function nestedCompound(): void { $b = new Bag; $b['list'][0] += 7; $a = $b['list'][0]++; $this->result = [$a, $b->snapshot(), $b->log]; }
    public function references(): void { $b = new Bag; replaceValue($b['n']); sort($b['list']); $this->result = [$b->snapshot(), $b->log]; }
    public function copyModify(): void { $b = new Bag; $list = $b['list']; $list[] = 9; $b['list'] = $list; $this->result = [$b->snapshot(), $b->log]; }
    public function objectOffsets(): void { $b = new Bag; $item = new Plain; $outer = new ObjectBag($b, $item); $outer['bag']['n'] = 10; $outer['item']->value = 8; replaceValue($outer['item']); $this->result = [$b->snapshot(), $item->value, $b->log]; }
    public function rawKeys(): void { $b = new RawKeys; $a = $b[1.0]; $c = $b[true]; $d = $b[null]; $e = $b[[]]; $f = $b[new Plain]; $b[[]] = 2.0; $b[] = 'x'; unset($b[[]]); $this->result = [$a, $c, $d, $e, $f, isset($b[[]]), $b->log]; }
    public function valueArguments(): void { $b = new Bag; $i = 0; $this->result = [keepValue($b['list'][$i++]), gettype($b['list'][0]), array_sum($b['list']), $i, $b->log]; }
    public function spreadArguments(): void { $b = new Bag; $a = max(...$b['list']); $b['wrapper'] = [[3, 1]]; $c = max(...$b['wrapper'][0]); replaceValues(...$b['list']); replaceValues(...$b['wrapper'][0]); $this->result = [$a, $c, $b->snapshot(), $b->log]; }
    public function temporary(): void { $a = makeBag()['n'] = 8; $b = makeBag()['n'] += 3; replaceValue(makeBag()['n']); $this->result = [$a, $b]; }
    public function countConversions(): void { $this->result = [count(new LooseCounter('12')), count(new LooseCounter(2.7)), count(new LooseCounter(null)), count(new LooseCounter([1])), count(new LooseCounter(new Plain))]; }
    public function evaluationOrder(): void { $b = new Bag; $old = $b; $b['n'] = swapBag($b); $first = [$old->log, $b->log]; $old = $b; $b['n'] += swapBag($b); $second = [$old->log, $b->log]; $old = $b; $b['nil'] ??= swapBag($b); $third = [$old->log, $b->log]; $b['list'][0] = logRhs($b); $b['list'][1] += logRhs($b); $this->result = [$first, $second, $third, $b->log]; }
    public function weakCounts(): void { $b = new Bag; $this->result = eval('return [count($b, "1"), sizeof($b, null), count($b, 1.5), $b->log];'); }
    public function counts(): void { $b = new Bag; $this->result = [count($b, COUNT_RECURSIVE), count([$b, ['nested' => [1]]], COUNT_RECURSIVE), count(new Counter), $b->log]; }
    public function predicates(): void { $b = new Bag; $this->result = [is_countable($b), is_countable(new Counter), is_countable(new RawKeys), is_countable(new Plain), is_countable([]), is_countable(null), is_countable('abc'), $b instanceof \Countable, $b instanceof \ArrayAccess, interface_exists(\ArrayAccess::class), get_class_methods(\Countable::class)]; }
    public function named(): void { $b = new Bag; $this->result = [count(mode: COUNT_RECURSIVE, value: $b), sizeof(value: $b), is_countable(value: $b), count(...['value' => $b]), $b->log]; }
    public function callbacks(): void { $b = new Bag; $f = count(...); $this->result = [$f($b), array_map('count', [$b, [1, 2]]), array_map('is_countable', [$b, new Plain, [], null]), $b->log]; }
    public function inherited(): void { $b = new ChildBag; $b['n'] = 4; $this->result = [contract($b), $b->log]; }
    public function selfOffsets(): void { $b = new Bag; $this->result = [$b->inner(), $b->log]; }
    public function destructure(): void { $b = new Bag; ['n' => $n, 'list' => [$first, $last]] = $b; $this->result = [$n, $first, $last, $b->log]; }
    public function plainRead(): void { $this->counter++; $b = new Plain; $this->result = [$b[0]]; }
    public function plainWrite(): void { $this->counter++; $b = new Plain; $b[0] = 1; }
    public function plainQuiet(): void { $this->counter++; $b = new Plain; $this->result = [isset($b[0])]; }
    public function plainUnset(): void { $this->counter++; $b = new Plain; unset($b[0]); }
    public function plainCount(): void { $this->counter++; $this->result = [count(new Plain)]; }
    public function countModeError(): void { $this->counter++; $this->result = [count(new Bag, 99)]; }
    public function countReturnError(): void { $this->counter++; $this->result = [count(new BadCounter)]; }
    public function countMissing(): void { $this->counter++; $this->result = [count()]; }
    public function countExtra(): void { $this->counter++; $this->result = [count([], 0, 1)]; }
    public function countNamedError(): void { $this->counter++; $this->result = [count(bad: [])]; }
    public function getError(): void { echo 'before'; $this->counter++; $b = new FailingBag; $this->result = [$b[0]]; }
    public function setError(): void { $this->counter++; $b = new FailingBag; $b[] = 2; }
    public function existsError(): void { $this->counter++; $b = new FailingBag; $this->result = [$b[0] ?? 'none']; }
    public function unsetError(): void { $this->counter++; $b = new FailingBag; unset($b[0]); }
    public function caught(): void { try { $b = new FailingBag; $b[] = 1; } catch (\RuntimeException $e) { $this->result = [$e->getMessage()]; } }
    public function arrayOnly(): void { $this->counter++; $this->result = [array_key_exists('n', new Bag)]; }
    public function arrayMapError(): void { $this->counter++; $this->result = array_map(fn ($x) => $x, new Bag); }
    public function invalidKey(): void { $this->counter++; $b = new Bag; $b[[]] = 1; }
    public function evalAccess(): void { $b = new Bag; $this->result = eval('$b["n"] += 3; $b[] = 6; unset($b["zero"]); return [count($b), is_countable($b), $b["n"], isset($b["nil"]), $b->log];'); }
    public function anonymous(): void { $b = new class implements \Countable { public function count(): int { return 7; } }; $this->result = [count($b), is_countable($b), $b instanceof \Countable]; }
    public function evalAnonymous(): void { $this->result = eval('$b = new class implements Countable, ArrayAccess { public array $items=[]; public function count(): int { return count($this->items); } public function offsetGet(mixed $key): mixed { return $this->items[$key]; } public function offsetSet(mixed $key, mixed $value): void { if ($key === null) { $this->items[] = $value; } else { $this->items[$key] = $value; } } public function offsetExists(mixed $key): bool { return isset($this->items[$key]); } public function offsetUnset(mixed $key): void { unset($this->items[$key]); } }; $b[] = 2.0; return [count($b), $b[0], gettype($b[0]), is_countable($b), interface_exists("Countable")];'); }
    public function invalidState(): void { $this->counter++; $this->result = [new Bag]; }
    public function recursionLimit(): void { $this->counter++; $this->result = [count(new RecursiveCounter)]; }
}
