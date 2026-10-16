<?php
declare(strict_types=1);
namespace AML\View\Tests\Magic;
use AML\View\{Page, State, View};
use function AML\View\{Button, VStack};
class MagicPage extends Page
{
    #[State] public array $result = [];
    #[State] public int $counter = 0;
    public string $handler = 'read';
    public function body(): View { return VStack(Button('Run')->onClick($this->{$this->handler}(...))); }
    public function read(): void { $b = new Bag; $this->result = [$b->amount, $b->plain, $b->own(), $b->secret, $b->log]; }
    public function write(): void { $b = new Bag; $a = $b->amount = 8; $b->amount += 2; $c = $b->amount++; $d = ++$b->amount; $b->secret = 'virtual'; $this->result = [$a, $c, $d, $b->amount, $b->secret, $b->own(), $b->log]; }
    public function quiet(): void { $b = new Bag; $this->result = [isset($b->amount), isset($b->nothing), isset($b->missing), empty($b->zero), empty($b->amount), empty($b->missing), $b->nothing ?? 'fallback', $b->amount ?? 0, $b->log]; }
    public function coalesceAssign(): void { $b = new Bag; $b->amount ??= 99; $b->nothing ??= 9; $this->result = [$b->amount, $b->nothing, $b->log]; }
    public function remove(): void { $b = new Bag; unset($b->amount); $this->result = [isset($b->amount), $b->log]; }
    public function arrayRead(): void { $b = new Bag; $this->result = [$b->items[1], isset($b->items[1]), empty($b->items[1]), $b->items[3] ?? 4, $b->log]; }
    public function arrayWrite(): void { $b = new Bag; $b->items[] = 8; $this->result = [$b->items, $b->log]; }
    public function arrayUnset(): void { $b = new Bag; unset($b->items[0]); $this->result = [$b->items, $b->log]; }
    public function reference(): void { $b = new Bag; replace($b->amount); $this->result = [$b->amount, $b->log]; }
    public function nativeReference(): void { $b = new Bag; sort($b->items); $this->result = [$b->items, $b->log]; }
    public function returnedObject(): void { $b = new ObjectGet; $b->anything->text = 'Changed'; $this->result = [(string) $b->anything]; }
    public function pending(): void { $this->counter++; $b = new Bag; $this->result = [$b->pending]; }
    public function unsetDeclared(): void { $b = new LazyGet; unset($b->value); $this->result = [$b->value]; $b->value = 7; $this->result[] = $b->value; }
    public function unsetTypedError(): void { $this->counter++; $b = new TypedGet; unset($b->value); $this->result = [$b->value]; }
    public function recursive(): void { $b = new RecursiveGet; $this->result = [$b->again]; }
    public function method(): void { $p = new MethodProxy; $this->result = [$p->Unknown(1, 2), $p->hidden(), $p->direct(4), $p->forwarded()]; }
    public function named(): void { $p = new MethodProxy; $this->result = [$p->missing(x: 1, y: 2)]; }
    public function spread(): void { $p = new MethodProxy; $args = [2, 'name' => 4]; $this->result = [$p->missing(...$args)]; }
    public function duplicate(): void { $this->counter++; $p = new MethodProxy; $args = ['x' => 1]; $p->missing(...$args, x: 2); }
    public function staticMethod(): void { $this->result = [MethodProxy::missing(1), ChildProxy::missing(n: 2), ChildProxy::secret()]; }
    public function firstClass(): void { $p = new MethodProxy; $f = $p->unknown(...); $g = ChildProxy::Unknown(...); $this->result = [$f(1), $g(x: 3)]; }
    public function invoke(): void { $f = new Invoker; $this->result = [$f(), $f(n: 3, extra: 4), apply($f, 5), array_map($f, [2, 4])]; }
    public function invokeReference(): void { $f = new RefInvoker; $n = 1; $this->result = [$f($n), $n]; }
    public function invokeError(): void { $this->counter++; $f = new Invoker; $f('bad'); }
    public function string(): void { $s = new Label; $this->result = [(string) $s, 'Message: ' . $s, text($s), $s instanceof \Stringable]; echo $s; }
    public function strictStringError(): void { $this->counter++; $s = new Label; strictString($s); }
    public function stringBuiltinError(): void { $this->counter++; $s = new Label; strlen($s); }
    public function stringBuiltins(): void { $s = new Label; $this->result = [strval($s), implode('|', [$s, $s]), sprintf('%s!', $s)]; }
    public function badString(): void { $this->counter++; $s = new BadLabel; $this->result = [(string) $s]; }
    public function throwingString(): void { $this->counter++; $s = new ThrowingLabel; echo $s; }
    public function cloned(): void { $a = new Cloneable(2, new Label); $b = clone $a; $b->label->text = 'Copy'; $this->result = [$a->value, $b->value, (string) $a->label, (string) $b->label, $a->label === $b->label]; }
    public function privateClone(): void { $this->counter++; $a = new PrivateClone; $b = clone $a; }
    public function internalClone(): void { $a = new PrivateClone; $b = $a->copy(); $this->result = [$a === $b]; }
    public function readonlyClone(): void { $a = new ReadonlyClone; $b = clone $a; $this->result = [$a->id, $b->id]; }
    public function readonlyTwice(): void { $this->counter++; $a = new DoubleReadonlyClone; $b = clone $a; }
    public function getterError(): void { $this->counter++; $a = new ErrorGet; $this->result = [$a->nope]; }
    public function caught(): void { $a = new ErrorGet; try { $a->nope; } catch (\RuntimeException $e) { $this->result = [$e->getMessage()]; } finally { $this->counter++; } }
    public function evalMagic(): void { $b = new Bag; $p = new MethodProxy; $s = new Label; $code = '$b->amount = 6; return [$b->amount, $p->hey(a: 1), (string) $s];'; $this->result = eval($code); }
    public function unsetWrite(): void { $b = new Bag; unset($b->plain); $b->plain = 10; $this->result = [$b->plain, $b->log]; }
    public function unsetTwice(): void { $b = new Bag; unset($b->plain); unset($b->plain); $this->result = [$b->log]; }
    public function arrayCoalesce(): void { $b = new Bag; $b->items[1] ??= 10; $b->items[5] ??= 11; $this->result = [$b->items, $b->log]; }
    public function arrayIncrement(): void { $b = new Bag; $n = $b->items[0]++; $this->result = [$n, $b->items, $b->log]; }
    public function arrayReference(): void { $b = new Bag; replace($b->items[0]); $this->result = [$b->items, $b->log]; }
    public function weakString(): void { $s = new Label; $this->result = weakConversions($s); }
    public function weakReturn(): void { $s = new WeakLabel; $this->result = [(string) $s]; }
    public function magicFloat(): void { $p = new MethodProxy; $this->result = [$p->missing(10.0), gettype($p->missing(10.0)[1][0])]; }
    public function explicitMagic(): void { $b = new Bag; $b->__set('amount', 8); $this->result = [$b->__get('amount'), $b->__isset('nothing')]; }
    public function instanceStaticError(): void { $this->counter++; MethodProxy::direct(4); }
    public function absentStaticProperty(): void { $this->counter++; $this->result = [MethodProxy::$nope]; }
    public function readonlyUnsetClone(): void { $a = new UnsetReadonlyClone; $b = clone $a; $this->result = [$a->id, $b->id]; }
    public function recursionLimit(): void { $this->counter++; $a = new RecursiveCall; $a->again(); }
    public function notInvokable(): void { $this->counter++; $b = new Bag; $b(); }
    public function destructor(): void { $a = new UnsupportedDestructor; }
    public function debugInfo(): void { $a = new UnsupportedDebugInfo; }
    public function referenceGet(): void { $a = new ReferenceGet; }
}
