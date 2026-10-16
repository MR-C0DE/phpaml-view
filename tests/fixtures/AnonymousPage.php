<?php
declare(strict_types=1);
namespace AML\View\Tests\Anonymous;
use AML\View\{Page, State, View};
use function AML\View\{Button, VStack};
interface Named { public function name(): string; }
interface Scored { public function score(): int; }
class NamedOnly implements Named { public function name(): string { return 'Only'; } }
class Both implements Named, Scored { public function name(): string { return 'Both'; } public function score(): int { return 3; } }
class Base { protected int $base = 4; public function score(): int { return $this->base; } public function name(): string { return 'Base'; } }
abstract class AbstractBase { abstract public function name(): string; }
class Holder { public Named&Scored $value; public (Named&Scored)|null $nullable = null; public static Named&Scored $shared; }
function describe(Named&Scored $value): string { return $value->name() . ':' . $value->score(); }
function identity(Named&Scored $value): Named&Scored { return $value; }
function wrongReturn(): Named&Scored { return new NamedOnly; }
function nullable((Named&Scored)|null $value): string { return $value === null ? 'null' : describe($value); }
function replace(Named&Scored &$value): void { $value = new Both; }
function make(int $n): object { return new class($n) { public static int $count = 0; public function __construct(public int $n) {} public function next(): int { return ++self::$count; } public function copy(): self { return new self($this->n); } }; }
class AnonymousPage extends Page {
    #[State] public array $result = [];
    #[State] public int $counter = 0;
    public string $handler = 'basic';
    public function body(): View { return VStack(Button('Run')->onClick($this->{$this->handler}(...))); }
    public function basic(): void { $o = new class(4) { public function __construct(public int $n) {} public function twice(): int { return $this->n * 2; } }; $this->result = [$o->n, $o->twice()]; }
    public function properties(): void { $o = new class { public int $x = 2, $y = 3; private string $secret = 'yes'; public function read(): string { return $this->secret; } }; $o->x += 5; $this->result = [$o->x, $o->y, $o->read()]; }
    public function inheritance(): void { $o = new class extends Base implements Named, Scored { public function name(): string { return parent::name() . ' child'; } public function score(): int { return $this->base + parent::score(); } }; $this->result = [describe($o), $o instanceof Named, $o instanceof Scored, $o instanceof Base]; }
    public function abstractParent(): void { $o = new class extends AbstractBase { public function name(): string { return 'Concrete'; } }; $this->result = [$o->name()]; }
    public function namedArguments(): void { $o = new class(n: 7) { public function __construct(public int $n = 2) {} public function sum(int $a = 1, int ...$rest): int { return $this->n + $a + array_sum($rest); } }; $this->result = [$o->sum(a: 3, extra: 4)]; }
    public function constructorDefault(): void { $o = new class { public function __construct(public int $n = 2) {} }; $this->result = [$o->n]; }
    public function statics(): void { $a = make(5); $b = make(7); $c = $a->copy(); $this->result = [$a->next(), $b->next(), $c->n, get_class($a) === get_class($b), get_class($a) === get_class($c), $a !== $b]; }
    public function distinct(): void { $a = new class {}; $b = new class {}; $this->result = [get_class($a) !== get_class($b)]; }
    public function nested(): void { $o = new class(5) { public function __construct(private int $n) {} public function inner(): object { return new class($this->n) { public function __construct(public int $n) {} }; } }; $this->result = [$o->inner()->n]; }
    public function closures(): void { $o = new class(4) { public function __construct(private int $n) {} public function callback(): \Closure { return fn(int $x): int => $this->n + $x; } }; $f = $o->callback(); $this->result = [$f(3)]; }
    public function constants(): void { $o = new class { private const LABEL = 'Label'; public int $n = 2 + 3; public function text(): string { return self::LABEL; } }; $this->result = [$o->text(), $o->n]; }
    public function magic(): void { $o = new class(3) { public function __construct(public int $n) {} public function __invoke(int $n): int { return $this->n + $n; } public function __toString(): string { return 'N=' . $this->n; } public function __clone(): void { $this->n++; } }; $copy = clone $o; $this->result = [$o(4), (string) $o, (string) $copy, $o instanceof \Stringable]; }
    public function intersection(): void { $this->result = [describe(identity(new Both)), nullable(null), nullable(new Both)]; }
    public function closureIntersection(): void { $f = fn(Named&Scored $o): Named&Scored => $o; $this->result = [describe($f(new Both))]; }
    public function referenceIntersection(): void { $o = new Both; replace($o); $f = function(Named&Scored &$v): void { $v = new Both; }; $f($o); $this->result = [describe($o)]; }
    public function propertyIntersection(): void { $h = new Holder; $h->value = new Both; $h->nullable = $h->value; Holder::$shared = $h->value; $this->result = [describe($h->value), describe($h->nullable), describe(Holder::$shared)]; }
    public function anonymousIntersection(): void { $o = new class(new Both) { public function __construct(public Named&Scored $value) {} public function get(): Named&Scored { return $this->value; } }; $this->result = [describe($o->get())]; }
    public function argumentError(): void { $this->counter++; describe(new NamedOnly); }
    public function returnError(): void { $this->counter++; wrongReturn(); }
    public function propertyError(): void { $this->counter++; $h = new Holder; $h->value = new NamedOnly; }
    public function staticError(): void { $this->counter++; Holder::$shared = new NamedOnly; }
    public function constructorError(): void { $this->counter++; $o = new class('bad') { public function __construct(public int $n) {} }; }
    public function visibilityError(): void { $this->counter++; $o = new class { private int $n = 1; }; $this->result = [$o->n]; }
    public function uninitializedError(): void { $this->counter++; $o = new class { public int $n; }; $this->result = [$o->n]; }
    public function caught(): void { try { describe(new NamedOnly); } catch (\TypeError $e) { $this->result = ['caught']; } }
    public function evalLiteral(): void { $this->result = eval('$o = new class(3) { public function __construct(public int $n) {} public function twice(): int { return $this->n * 2; } }; return [$o->twice()];'); }
    public function evalDynamic(): void { $code = '$o = new class(5) { public function __construct(public int $n) {} public function twice(): int { return $this->n * 2; } }; return [$o->twice()];'; $this->result = eval($code); }
    public function defaultConstant(): void { $o = new class { private const N = 7; public int $n = self::N; public static int $count = self::N; public function value(int $n = self::N): int { return $n + self::$count; } }; $this->result = [$o->n, $o->value()]; }
    public function readonly(): void { $o = new class(7) { public function __construct(public readonly int $n) {} }; $this->result = [$o->n]; }
    public function readonlyError(): void { $this->counter++; $o = new class(7) { public function __construct(public readonly int $n) {} }; $o->n = 8; }
    public function staticMethod(): void { $o = new class { public function value(): int { return self::twice(4); } private static function twice(int $n): int { return $n * 2; } }; $this->result = [$o->value()]; }
    public function methodReference(): void { $o = new class { public function twice(int $n): int { return 2 * $n; } }; $fn = $o->twice(...); $this->result = [$fn(4)]; }
    public function anonymousDnf(): void { $o = new class { public (Named&Scored)|null $value = null; public function pass((Named&Scored)|null $value): (Named&Scored)|null { return $value; } }; $o->value = new Both; $this->result = [describe($o->pass($o->value)), $o->pass(null)]; }
    public function dnfError(): void { $this->counter++; nullable(new NamedOnly); }
    public function evalReturned(): void { $code = 'return new class(7) { public function __construct(public int $n) {} public function value(): int { return $this->n; } };'; $o = eval($code); $this->result = [$o->value()]; }
    public function evalDistinct(): void { $code = 'return new class { public static int $n = 0; public function next(): int { return ++self::$n; } };'; $a = eval($code); $b = eval($code); $this->result = [get_class($a) !== get_class($b), $a->next(), $b->next(), $a->next()]; }
    public function evalIntersection(): void { $o = new Both; $code = '$f = fn(\\AML\\View\\Tests\\Anonymous\\Named&\\AML\\View\\Tests\\Anonymous\\Scored $v): \\AML\\View\\Tests\\Anonymous\\Named&\\AML\\View\\Tests\\Anonymous\\Scored => $v; return $f($o);'; $this->result = [describe(eval($code))]; }
    public function evalInheritance(): void { $base = new Base; $known = new Both; $code = '$o = new class extends \\AML\\View\\Tests\\Anonymous\\Base implements \\AML\\View\\Tests\\Anonymous\\Named, \\AML\\View\\Tests\\Anonymous\\Scored { public function name(): string { return parent::name() . " eval"; } }; return $o;'; $this->result = [describe(eval($code))]; }

    public function floatDefault(): void { $o = new class { public float $n = 2; public static float $m = 3; public function types(): array { return [gettype($this->n), gettype(self::$m)]; } }; $this->result = $o->types(); }
    public function variadicIntersection(): void { $fn = function (Named&Scored ...$values): int { return count($values); }; $this->result = [$fn(new Both, new Both)]; }
    public function closureDnf(): void { $fn = fn((Named&Scored)|null $o): (Named&Scored)|null => $o; $this->result = [$fn(null), describe($fn(new Both))]; }

    public function evalStringable(): void { $code = 'return new class implements \\Stringable { public function __toString(): string { return "label"; } };'; $this->result = [(string) eval($code)]; }

}
