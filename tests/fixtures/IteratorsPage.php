<?php
declare(strict_types=1);
namespace AML\View\Tests\Iterators;
use AML\View\{Page, State, View};
use function AML\View\{Button, VStack};

class Cursor implements \Iterator {
    public array $log = [];
    public int $position = 0;
    public function __construct(public array $values = [1.0, 2, 3], public array $keys = ['a', 7, 'z'], public string $failure = '') {}
    public function record(string $name): void { $this->log[] = $name; if ($this->failure === $name) { throw new \RuntimeException($name . ' failed'); } }
    public function rewind(): void { $this->record('rewind'); $this->position = 0; }
    public function valid(): bool { $this->record('valid'); return $this->position < count($this->values); }
    public function current(): mixed { $this->record('current'); return $this->values[$this->position]; }
    public function key(): mixed { $this->record('key'); return $this->keys[$this->position]; }
    public function next(): void { $this->record('next'); $this->position++; }
    public function inside(): array { $rows = []; foreach ($this as $k => $v) { $rows[] = [$k, $v]; } return $rows; }
}
interface CursorContract extends \Iterator {}
class InheritedCursor extends Cursor implements CursorContract {}
class Aggregate implements \IteratorAggregate {
    public int $calls = 0;
    public ?Cursor $last = null;
    public function __construct(public array $items = [1.0, 2, 3]) {}
    public function getIterator(): \Traversable { $this->calls++; $this->last = new Cursor($this->items, array_keys($this->items)); return $this->last; }
}
class Delegate implements \IteratorAggregate {
    public function __construct(public \Traversable $next) {}
    public function getIterator(): \Traversable { return $this->next; }
}
class SelfAggregate implements \IteratorAggregate { public function getIterator(): \Traversable { return $this; } }
class BadAggregate implements \IteratorAggregate { public function getIterator(): \Traversable { return []; } }
class LooseAggregate implements \IteratorAggregate { #[\ReturnTypeWillChange] public function getIterator() { return []; } }
class ThrowAggregate implements \IteratorAggregate { public function getIterator(): \Traversable { throw new \RuntimeException('factory failed'); } }
class Plain { public array $items = [1, 2]; }
class CountCursor extends Cursor implements \Countable { public function count(): int { return count($this->values); } }
class BadValid extends Cursor { public function valid(): bool { return []; } }
class LooseCursor implements \Iterator {
    public int $position = 0;
    public function rewind(): void { $this->position = 0; }
    #[\ReturnTypeWillChange] public function valid() { return $this->position < 2 ? 'yes' : '0'; }
    public function current(): mixed { return $this->position; }
    public function key(): mixed { return $this->position; }
    public function next(): void { $this->position++; }
}
class Holder { public function __construct(public iterable $items) {} }
abstract class Provider { abstract public function items(): iterable; }
class InfiniteCursor extends Cursor { public function valid(): bool { return true; } public function current(): mixed { return $this->position; } }
class InfiniteAggregate implements \IteratorAggregate { public function getIterator(): \Traversable { return new self; } }
class RecursiveCursor extends Cursor { public function valid(): bool { foreach ($this as $v) {} return false; } }
function collect(iterable $source): array { $rows = []; foreach ($source as $key => $value) { $rows[] = [$key, $value, gettype($key), gettype($value)]; } return $rows; }
function first(iterable $source): mixed { foreach ($source as $value) { return $value; } return null; }
function identity(iterable $source): iterable { return $source; }
function intersection(\Iterator&\Countable $source): array { return [count($source), collect($source)]; }
function runFailure(string $stage): void { foreach (new Cursor(failure: $stage) as $key => $value) {} }

class IteratorsPage extends Page {
    #[State] public array $result = [];
    #[State] public int $counter = 0;
    public string $handler = 'basic';
    public function body(): View {
        if ($this->handler === 'inline') {
            return VStack(Button('Run')->onClick(function (): void { $it = new Cursor; $rows = []; foreach ($it as $k => $v) { $rows[] = [$k, $v]; } $this->result = [$rows, $it->log]; }));
        }
        if ($this->handler === 'inlineError') {
            return VStack(Button('Run')->onClick(function (): void { $this->counter++; foreach (new Cursor(failure: 'next') as $v) { echo $v; } }));
        }
        return VStack(Button('Run')->onClick($this->{$this->handler}(...)));
    }
    public function basic(): void { $it = new Cursor; $this->result = [collect($it), $it->log]; }
    public function inline(): void { $it = new Cursor; $rows = []; foreach ($it as $k => $v) { $rows[] = [$k, $v]; } $this->result = [$rows, $it->log]; }
    public function inlineError(): void { $this->counter++; foreach (new Cursor(failure: 'next') as $v) { echo $v; } }
    public function valuesOnly(): void { $it = new Cursor; $rows = []; foreach ($it as $v) { $rows[] = $v; } $this->result = [$rows, $it->log]; }
    public function keyNotCalled(): void { $it = new Cursor(failure: 'key'); $rows = []; foreach ($it as $v) { $rows[] = $v; } $this->result = [$rows, $it->log]; }
    public function empty(): void { $it = new Cursor([]); foreach ($it as $k => $v) { $this->counter++; } $this->result = $it->log; }
    public function breaking(): void { $it = new Cursor; foreach ($it as $v) { break; } $this->result = [$v, $it->log]; }
    public function continuing(): void { $it = new Cursor; $rows = []; foreach ($it as $v) { if ($v < 3) { continue; } $rows[] = $v; } $this->result = [$rows, $it->log]; }
    public function returning(): void { $it = new Cursor; $this->result = [first($it), $it->log]; }
    public function repeat(): void { $it = new Cursor; $a = collect($it); $b = collect($it); $this->result = [$a, $b, $it->log]; }
    public function manualPosition(): void { $it = new Cursor; $it->next(); $it->next(); $this->result = [collect($it), $it->log]; }
    public function aggregate(): void { $it = new Aggregate; $this->result = [collect($it), $it->calls, $it->last->log]; }
    public function aggregateRepeat(): void { $it = new Aggregate; $a = collect($it); $b = collect($it); $this->result = [$a, $b, $it->calls, $it->last->log]; }
    public function aggregateNested(): void { $it = new Aggregate([4, 5]); $rows = []; foreach ($it as $a) { foreach ($it as $b) { $rows[] = [$a, $b]; } } $this->result = [$rows, $it->calls]; }
    public function sharedNested(): void { $it = new Cursor([4, 5], [0, 1]); $rows = []; foreach ($it as $a) { foreach ($it as $b) { $rows[] = [$a, $b]; } } $this->result = [$rows, $it->log]; }
    public function nestedControl(): void { $outer = new Cursor([1, 2], [0, 1]); $inner = new Cursor([3, 4], [0, 1]); foreach ($outer as $a) { foreach ($inner as $b) { continue 2; } } $first = [$outer->log, $inner->log]; $outer->log = []; $inner->log = []; foreach ($outer as $a) { foreach ($inner as $b) { break 2; } } $this->result = [$first, $outer->log, $inner->log]; }
    public function switchControl(): void { $it = new Cursor; $rows = []; foreach ($it as $v) { switch ($v) { case 1: continue 2; case 2: $rows[] = $v; break; default: break 2; } } $this->result = [$rows, $it->log]; }
    public function delegation(): void { $it = new Cursor; $this->result = [collect(new Delegate(new Delegate($it))), $it->log]; }
    public function destructure(): void { $it = new Cursor([[1, 2], [3, 4]], ['a', 'b']); $rows = []; foreach ($it as $k => [$a, $b]) { $rows[] = [$k, $a, $b]; } $this->result = [$rows, $it->log]; }
    public function assignmentOrder(): void { $it = new Cursor; $rows = []; foreach ($it as $same => $same) { $rows[] = $same; } $this->result = [$rows, $it->log]; }
    public function keyTypes(): void { $it = new Cursor([1, 2, 3], [1.0, null, ['key']]); $this->result = collect($it); }
    public function objects(): void { $obj = new Plain; $it = new Cursor([$obj], [0]); foreach ($it as $v) { $v->items[] = 3; } $this->result = [$obj->items]; }
    public function arrayCopies(): void { $it = new Cursor([[1]], [0]); foreach ($it as $v) { $v[] = 2; } $this->result = [$it->values, $v]; }
    public function mutation(): void { $it = new Cursor; $rows = []; foreach ($it as $v) { $rows[] = $v; if ($v === 1.0) { $it->values[1] = 9; $it->values[] = 4; } } $this->result = [$rows, $it->log]; }
    public function reassignSource(): void { $it = new Cursor; $old = $it; $rows = []; foreach ($it as $v) { $rows[] = $v; $it = new Cursor([]); } $this->result = [$rows, $old->log]; }
    public function predicates(): void { $it = new Cursor; $ag = new Aggregate; $f = is_iterable(...); $this->result = [is_iterable($it), is_iterable($ag), is_iterable([]), is_iterable(new Plain), is_iterable(null), $it instanceof \Traversable, $ag instanceof \IteratorAggregate, $f($it), array_map('is_iterable', [$it, $ag, new Plain, [], 1]), $it->log, $ag->calls, interface_exists(\Traversable::class), get_class_methods(\Iterator::class)]; }
    public function predicateNamed(): void { $this->result = [is_iterable(value: new Cursor), is_iterable(...['value' => []])]; }
    public function iterableContracts(): void { $it = new CountCursor; $holder = new Holder(identity($it)); $this->result = [collect($holder->items), intersection($it), collect(identity([8, 9]))]; }
    public function iterableError(): void { $this->counter++; $this->result = collect(new Plain); }
    public function iterablePropertyError(): void { $this->counter++; $holder = new Holder([]); $holder->items = new Plain; }
    public function inside(): void { $it = new Cursor; $this->result = [$it->inside(), $it->log]; }
    public function validTruthiness(): void { $this->result = collect(new LooseCursor); }
    public function rewindError(): void { echo 'before'; $this->counter++; runFailure('rewind'); }
    public function validError(): void { $this->counter++; runFailure('valid'); }
    public function currentError(): void { $this->counter++; runFailure('current'); }
    public function keyError(): void { $this->counter++; runFailure('key'); }
    public function nextError(): void { $this->counter++; runFailure('next'); }
    public function validTypeError(): void { $this->counter++; $this->result = collect(new BadValid); }
    public function factoryError(): void { $this->counter++; $this->result = collect(new ThrowAggregate); }
    public function factoryTypeError(): void { $this->counter++; $this->result = collect(new BadAggregate); }
    public function factoryValueError(): void { $this->counter++; $this->result = collect(new LooseAggregate); }
    public function factorySelfError(): void { $this->counter++; $this->result = collect(new SelfAggregate); }
    public function caught(): void { try { foreach (new Cursor(failure: 'current') as $v) {} } catch (\RuntimeException $e) { $this->result[] = $e->getMessage(); } finally { $this->counter++; } }
    public function finallyBreak(): void { $it = new Cursor; foreach ($it as $v) { try { break; } finally { $this->result[] = $v; } } $this->result[] = $it->log; }
    public function evalIterator(): void { $it = new Cursor; $this->result = eval('$rows=[]; foreach ($it as $key=>$value) { $rows[]=[$key,$value]; } return [$rows,$it->log,is_iterable($it)];'); }
    public function anonymous(): void { $it = new class implements \IteratorAggregate { public function getIterator(): \Traversable { return new Cursor; } }; $this->result = [collect($it), $it instanceof \Traversable]; }
    public function covariance(): void { $it = new class extends Provider { public function items(): Cursor { return new Cursor; } }; $this->result = collect($it->items()); }
    public function interfaceInheritance(): void { $it = new InheritedCursor; $this->result = [collect($it), $it instanceof CursorContract, $it instanceof \Traversable]; }
    public function evalAggregate(): void { $cursor = new Cursor; $this->result = eval('$aggregate = new class($cursor) implements IteratorAggregate { public function __construct(private Iterator $it) {} public function getIterator(): Traversable { return $this->it; } }; $rows=[]; foreach($aggregate as $k=>$v){$rows[]=[$k,$v];} return [$rows,is_iterable($aggregate),$aggregate instanceof Traversable,interface_exists("Traversable")];'); }
    public function evalAnonymous(): void { $this->result = eval('$it = new class implements Iterator { public int $i=0; public function rewind(): void {$this->i=0;} public function valid(): bool {return $this->i<2;} public function current(): mixed {return 1.0+$this->i;} public function key(): mixed {return "k".$this->i;} public function next(): void {$this->i++;} }; $rows=[]; foreach($it as $k=>$v){$rows[]=[$k,$v,gettype($v)];} return [$rows,is_iterable($it),$it instanceof Traversable,interface_exists("Traversable")];'); }
    public function loopLimit(): void { $this->counter++; foreach (new InfiniteCursor as $v) {} }
    public function cumulativeLimit(): void { $this->counter++; foreach (new InfiniteCursor as $v) { for ($i = 0; $i < 200; $i++) {} } }
    public function delegationLimit(): void { $this->counter++; foreach (new InfiniteAggregate as $v) {} }
    public function recursionLimit(): void { $this->counter++; foreach (new RecursiveCursor as $v) {} }
    public function plainObject(): void { $this->counter++; foreach (new Plain as $v) {} }
    public function invalidState(): void { $this->counter++; $this->result = [new Cursor]; }
}
