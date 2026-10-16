<?php
declare(strict_types=1);
namespace AML\View\Tests\AdvancedFunctions;
use AML\View\{Page, State, View};
use function AML\View\{Button, VStack};
function runCode(string $code, object $c): array { return eval($code); }
class CastParent { private int $secret = 1; protected int $shared = 2; public int $public = 3; }
class CastChild extends CastParent { private int $secret = 4; public ?int $unset; }
class CastHolder { public ?object $object = null; public static ?object $stored = null; }
function counter(): int { static $count = 0; return ++$count; }
function closureFactory(): \Closure { return function (): int { static $count = 0; return ++$count; }; }
function arguments(int $a = 1, int $b = 2, ...$rest): array { $a += 10; $rest[0] = 100; return [func_num_args(), func_get_args(), func_get_arg(0)]; }
function argsGenerator(int $n): \Generator { static $i = 0; yield [++$i, func_get_args()]; $n++; yield [++$i, func_get_args()]; }
function callIt(callable $f, int $n): int { return $f($n); }
function recurse(int $n): int { static $sum = 0; $sum += $n; return $n === 0 ? $sum : recurse($n - 1); }
class Calculator {
 public function __construct(public int $offset = 3) {}
 public function add(int $n): int { return $this->offset + $n; }
 public static function double(int $n): int { return $n * 2; }
 public function callback(): array { return [$this, 'hidden']; }
 private function hidden(int $n): int { return $n; }
 public function count(): int { static $i = 0; return ++$i; }
}
class ChildCalculator extends Calculator {}
class AdvancedFunctionsPage extends Page {
 #[State] public array $result = [];
 #[State] public int $counter = 0;
 public string $handler = 'operators';
 public function body(): View { return VStack(Button('Run')->onClick($this->{$this->handler}(...))); }
 public function operators(): void { $n = 4; $this->result = [$n << 3, $n >> 1, ~0, ~-9, -8 >> 2, 1 << 64, -8 >> 100, true xor false, true xor true, false or true and false, 'v' . 1 << 2, 2 + 1 << 2]; }
 public function wordPrecedence(): void { $a = true and false; $b = false OR true; $c = true XOR true; $this->counter = 3 and false; $this->result = [$a,$b,$c]; }
 public function compoundBits(): void { $n = 3; $n <<= 2; $n >>= 1; $n |= 1; $n &= 6; $n ^= 3; $this->counter = 3; $this->counter <<= 2; $this->result = [$n]; }
 public function operatorOrder(): void { $n = 1; $this->result = [$n++ | $n++, $n++ << $n++, $n]; }
 public function operatorError(): void { $this->counter++; $n = 1 << -1; }
 public function bitTypeError(): void { $this->counter++; $n = ~true; }
 public function arguments(): void { $this->result = [arguments(), arguments(2,3,4,5), arguments(b: 9), arguments(2,3,foo: 9)]; }
 public function argumentIndexError(): void { $this->counter++; $fn = fn() => func_get_arg(0); $fn(); }
 public function statics(): void { $this->result = [counter(), counter(), recurse(3), (new Calculator)->count(), (new ChildCalculator)->count()]; }
 public function persisted(): void { $this->result = [counter(), ++$this->counter]; }
 public function closures(): void { $a = closureFactory(); $b = closureFactory(); $same = $a; $this->result = [$a(), $a(), $b(), $same(), $b()]; }
 public function arrayArrows(): void { $result = []; foreach ([fn() => 1, 'named' => static fn(int $n = 2): int => $n + 1] as $key => $fn) { $result[] = [$key, $fn()]; } $nested = [fn() => fn() => 4]; $generators = [fn() => yield 'key' => 5]; $this->result = [$result, ($nested[0]())(), iterator_to_array($generators[0]())]; }
 public function matchArrows(): void { $fn = match (1) { 1 => fn() => 6, default => fn() => 7 }; $this->result = [$fn()]; }
 public function arrayArrowsEval(): void { $this->result = runCode('$result = []; foreach ([fn() => 1, "named" => static fn(int $n = 2): int => $n + 1] as $key => $fn) { $result[] = [$key, $fn()]; } $nested = [fn() => fn() => 4]; $generators = [fn() => yield "key" => 5]; return [$result, ($nested[0]())(), iterator_to_array($generators[0]())];', new Calculator); }
 public function matchArrowsEval(): void { $this->result = runCode('$fn = match (1) { 1 => fn() => 6, default => fn() => 7 }; return [$fn()];', new Calculator); }
 public function staticGenerator(): void { $g = argsGenerator(5); $h = argsGenerator(8); $this->result = [$g->current(), $h->current(), $g->next(), $g->current()]; }
 public function callbackArray(): void { $c = new Calculator; $f = [$c, 'add']; $this->result = [$f(2), array_map([$c, 'add'], [1,2]), callIt([$c, 'add'], 4), array_map([Calculator::class, 'double'], [3,4])]; }
 public function callbackString(): void { $f = 'AML\\View\\Tests\\AdvancedFunctions\\Calculator::double'; $this->result = [$f(2), array_map('AML\\View\\Tests\\AdvancedFunctions\\Calculator::double', [3,4]), callIt($f, 5)]; }
 public function dynamic(): void { $c = new Calculator; $name = 'add'; $static = 'double'; $this->result = [$c->$name(2), $c->{'a' . 'dd'}(3), Calculator::$static(4), ($c->$name(...))(5)]; }
 public function dynamicNullsafe(): void { $c = null; $n = 0; $this->result = [$c?->{(string) $n++}($n++), $n]; }
 public function invalidMethod(): void { $c = new Calculator; $this->counter++; $name = 3; $c->$name(); }
 public function privateCallback(): void { $c = new Calculator; $f = $c->callback(); $this->counter++; $f(2); }
 public function castScalars(): void { $a = (object) 5; $b = (object) null; $this->result = [(array) null, (array) false, (array) 1.5, (array) 'x', $a->scalar, get_class($b), (array) $b]; }
 public function castArrays(): void { $a = ['name'=>'Anne', 2=>3, 'nested'=>[1]]; $b = (object) $a; $b->name = 'Bob'; $b->nested[] = 2; $key = '2'; $this->result = [$a, (array) $b, $b->$key, $b === (object) $b]; }
 public function dynamicProperty(): void { $v = (object) [2=>'yes']; $n = 0; $v->{$n++} = $n; $this->result = [$v->{2}, (array) $v, $n]; }
 public function castSpl(): void { $it = new \ArrayIterator(['a'=>1, 3=>2]); $object = new \ArrayObject(['b'=>2.0]); $copy = (array) $it; $copy['a'] = 99; $this->result = [(array) $it, (array) $object, $copy, (object) $it === $it]; }
 public function castReferences(): void { $x = 1; $array = ['x'=>&$x]; $object = (object) $array; $clone = clone $object; $copy = (array) $object; $x = 2.0; $copy['x'] = 3.0; $clone->x++; $this->result = [$x, $array, (array) $object, (array) $clone, $copy, gettype($copy['x'])]; }
 public function castReferenceUnset(): void { $x = 1; $object = (object) ['x'=>&$x]; unset($x); $clone = clone $object; $copy = (array) $object; $object->x = 2; $this->result = [(array) $object, (array) $clone, $copy]; }
 public function castCloneReferenceLifetime(): void { $x = 1; $object = (object) ['x'=>&$x]; $clone = clone $object; unset($x); $object->x = 2; $before = $clone->x; unset($object); $copy = clone $clone; $clone->x = 3; $this->result = [$before, (array) $clone, (array) $copy]; }
 public function castNumericKeys(): void { $object = (object) [0=>1.0, 1=>2, '01'=>3, -1=>4]; $array = (array) $object; $this->result = [array_keys($array), $array, gettype($array[0]), (array) (object) [1,2]]; }
 public function castReferenceProperty(): void { $object = (object) ['x'=>1]; $alias = &$object->x; $array = (array) $object; $clone = clone $object; unset($object->x); $alias = 2; $object->x = 3; $this->result = [$alias, $array, (array) $clone, (array) $object]; }
 public function castReferenceLifetime(): void { $x = 1; $object = (object) ['x'=>&$x]; $array = (array) $object; unset($x, $object); $copy = $array; $array['x'] = 2; $factory = function () { $n = 1; return (object) ['n'=>&$n]; }; $object = $factory(); $fields = (array) $object; $object->n = 3; $this->result = [$array, $copy, (array) $object, $fields]; }
 public function castReferenceOwners(): void { $x = 1; $object = (object) ['x'=>&$x]; $holder = new CastHolder; $holder->object = $object; $array = (array) $object; unset($x, $object); $copy = $array; $array['x'] = 2; $before = $copy['x']; unset($copy, $holder); $copy = $array; $array['x'] = 3; $after = $copy['x']; $x = 1; $object = (object) ['x'=>&$x]; CastHolder::$stored = $object; $array = (array) $object; unset($x, $object); $copy = $array; $array['x'] = 2; $staticBefore = $copy['x']; unset($copy); CastHolder::$stored = null; $copy = $array; $array['x'] = 3; $this->result = [$before, $after, $staticBefore, $copy['x']]; }
 public function runtimeEvalCasts(): void { $this->result = runCode('$x=1;$a=[&$x];$o=(object)$a;unset($x,$a);$b=(array)$o;$o->{0}=2;return [(array)$o,$b];', new Calculator); }
 public function castDateGuard(): void { $date = new \DateTimeImmutable('2024-01-01', new \DateTimeZone('UTC')); $this->counter++; $this->result = (array) $date; }
 public function castFields(): void { $this->result = [(array) new CastChild]; }
 public function runtimeEval(): void { $this->result = runCode('$name="add";$f=[$c,$name];$g=function($n){static $i=0;return [++$i,func_get_args(),$n<<1];};return [$f(2),$c->$name(3),$g(2),$g(3),true xor false,(array)(object)["x"=>2]];', new Calculator); }
 public function evalAdvanced(): void { $c = new Calculator; $this->result = eval('$name="add"; $f=[$c,$name]; $g=function($n){static $i=0;return [++$i,func_get_args(),$n<<1];}; return [$f(2),$c->$name(3),$g(2),$g(3),true xor false];'); }
}
