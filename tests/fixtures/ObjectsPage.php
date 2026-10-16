<?php
declare(strict_types=1);
namespace AML\View\Tests\Objects;
use AML\View\{Page, State, View};
use function AML\View\{Button, VStack, Text};

final class ObjectsPage extends Page
{
    #[State] public array $result = [];
    #[State] public int $counter = 0;
    public string $handler = 'basic';
    public function body(): View { return VStack(Button('Run')->onClick($this->{$this->handler}(...)), Text('Counter: {counter}')); }
    public function basic(): void { $p = new Product('Livre'); $this->result = [$p->name, $p->price, $p->id, $p->total(), gettype($p), get_class($p), (bool) $p, is_object($p), is_object([])]; }
    public function named(): void { $p = new Product(id: 8, price: 12.5, name: 'Café'); $this->result = [$p->id, $p->price, $p->name]; }
    public function spread(): void { $args = ['name' => 'A', 'price' => 4.5]; $p = new Product(...$args); $this->result = [$p->label()]; }
    public function identity(): void { $a = new Product('A'); $b = $a; $c = new Product('A'); $b->price++; $this->result = [$a === $b, $a === $c, $a == $c, $a->price, $c->price]; }
    public function equalObjects(): void { $a = new Product('A'); $b = new Product('A'); $this->result = [$a == $b, $a === $b, $a != $b]; }
    public function properties(): void { $p = new Product('A'); $p->price += 2.5; ++$p->price; $p->name .= '!'; $p->tags[] = 'x'; $p->tags['key'] = 'y'; unset($p->tags[0]); $this->result = [$p->price, $p->name, $p->tags]; }
    public function inherited(): void { $p = new DiscountProduct('A'); $this->result = [$p->total(), $p->label(), $p->privateValue(), $p->childSecret(), $p->forwarded(), $p instanceof Product, $p instanceof Priced, readPrice($p)]; }
    public function chaining(): void { $p = new Product('A'); $this->result = [$p->increase(3.0)->increase(2.0)->total(), (new Product('B'))->total(), choose($p)->name]; }
    public function captures(): void { $p = new Product('A'); $f = $p->updater(); $g = $p->closure(); $h = $p->reader(); $this->result = [$f(2.0), $g(), $h($p), $p->price]; }
    public function callback(): void { $p = new Product('A'); $f = $p->increase(...); $f(4.0); $this->result = [array_map($p->factorial(...), [1, 3, 5]), $p->price]; }
    public function references(): void { $p = new Product('A'); changePrice($p->price); $this->result = [$p->price]; }
    public function valueParameter(): void { $p = new Product('A'); $q = new Product('B'); $this->result = [$q->copyPrice($p), $p->price]; }
    public function cloned(): void { $p = new Product('A'); $p->related = new Product('B'); $p->tags = [1]; $q = clone $p; $q->price++; $q->tags[] = 2; $q->related->price++; $this->result = [$p === $q, $p->price, $q->price, $p->tags, $q->tags, $p->related === $q->related, $p->related->price]; }
    public function nullable(): void { $p = null; $this->result = [$p?->name, $p?->increase($this->counter++), $this->counter]; }
    public function initialization(): void { $p = new Product('A'); $this->result = [isset($p->uninitialized), empty($p->uninitialized), $p->uninitialized ?? 12]; $p->uninitialized = 3; unset($p->uninitialized); $this->result[] = isset($p->uninitialized); }
    public function plain(): void { $p = new Plain; $this->result = [$p->value, (bool) $p]; }
    public function abstractConcrete(): void { $p = new ConcreteService; $this->result = [$p->value(), $p instanceof AbstractService]; }
    public function staticFactory(): void { $p = new DiscountProduct('A'); $q = DiscountProduct::late('B'); $this->result = [$q instanceof DiscountProduct, $q->total(), Product::make('C')->name, $p->forwarded()]; }
    public function badPropertyType(): void { $this->counter++; $p = new Product('A'); $p->price = 'wrong'; }
    public function badParameter(): void { $this->counter++; $p = new Product('A'); $p->increase('wrong'); }
    public function privateProperty(): void { $this->counter++; $p = new Product('A'); $this->result = [$p->secret]; }
    public function protectedProperty(): void { $this->counter++; $p = new Product('A'); $this->result = [$p->rate]; }
    public function privateMethod(): void { $this->counter++; $p = new Product('A'); $p->hidden(); }
    public function missingMethod(): void { $this->counter++; $p = new Product('A'); $p->absent(); }
    public function uninitialized(): void { $this->counter++; $p = new Product('A'); $this->result = [$p->uninitialized]; }
    public function readonlyWrite(): void { $this->counter++; $p = new Product('A'); $p->id = 7; }
    public function readonlyUnset(): void { $this->counter++; $p = new Product('A'); unset($p->id); }
    public function abstractNew(): void { $this->counter++; $p = new AbstractService; }
    public function caught(): void { try { $p = new Product('A'); $p->price = []; } catch (\TypeError $e) { $this->result = [get_class($e)]; } finally { $this->counter++; } }
    public function invalidState(): void { $this->counter++; $this->result = [new Product('A')]; }
    public function factoryFirst(): void { $p = DiscountProduct::late('A'); $this->result = [$p->total(), get_class($p)]; }
    public function floatKinds(): void { $p = new Product('A', 10.0); $this->result = [gettype($p->price), gettype($p->total()), gettype($p->increase(1)->total())]; }
    public function arrays(): void { $p = new Product('A'); $a = [$p]; $b = $a; $b[0]->price = 4; $total = 0.0; foreach ([$p, new DiscountProduct('B')] as $item) { $total += $item->total(); } $this->result = [$a[0]->price, $total, array_map(fn (Product $p): float => $p->total(), $a)]; }
    public function methodArguments(): void { $p = new Product('A'); $this->result = [$p->defaults(), $p->defaults(1, 2, 3), $p->defaults(n: 4, extra: 5)]; }
    public function methodError(): void { $this->counter++; $p = new Product('A'); $p->invalidReturn(); }
    public function staticReturnError(): void { $this->counter++; $p = new DiscountProduct('A'); $p->wrongStatic(); }
    public function guarded(): void { $p = Guarded::make(7); $this->result = [$p->value]; }
    public function privateConstructor(): void { $this->counter++; $p = new Guarded(7); }
    public function constructorOutput(): void { $p = new Loud(3); $p->announce(); $this->result = [$p->value]; }
    public function evalLiteral(): void { $p = new Product('A'); $this->result = [eval('$p->increase(2.0); return $p->total();')]; }
    public function evalDynamic(): void { $p = new Product('A'); $code = '$p->increase(3.0); return $p->total();'; $this->result = [eval($code)]; }
    public function evalBound(): void { $p = new Product('A'); $this->result = [$p->evalLocal('$this->price += 3; $f = fn () => $this->total(); return $f();')]; }
    public function evalNew(): void { $p = new Product('A'); $code = 'return new \\AML\\View\\Tests\\Objects\\Product("B", 12.0);'; $q = eval($code); $this->result = [$q->name, $q->price]; }
    public function evalStatic(): void { $p = new DiscountProduct('A'); $code = 'return \\AML\\View\\Tests\\Objects\\DiscountProduct::late("B");'; $q = eval($code); $this->result = [get_class($q), $q->total()]; }
    public function constants(): void { $p = new DiscountProduct('A'); $this->result = $p->classInfo(); }
    public function evalSelf(): void { $p = new DiscountProduct('A'); $this->result = [$p->evalLocal('return [new static("B"), self::factor(), static::factor()];')[0]->total()]; }
    public function oneLine(): void { $p = new OneLine; $this->result = [$p->a(), $p->b()]; }
    public function plainNamedError(): void { $this->counter++; $p = new Plain(value: 2); }
    public function plainSpreadError(): void { $this->counter++; $args = 12; $p = new Plain(...$args); }
    public function plainPositional(): void { $p = new Plain($this->counter++); $this->result = [$p->value, $this->counter]; }
    public function staticFields(): void { $p = new StaticFields; }
    public function magicService(): void { $p = new MagicService; }
    public function missingClass(): void { $p = new NeverLoadedDependency; }
    public function lazyDefault(): void { $p = new LazyDefault; }
    public function methodDefault(): void { $p = new MethodDefault; }
    public function internalClass(): void { $p = new \ReflectionClass('stdClass'); }
}
