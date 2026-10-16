<?php
declare(strict_types=1);
namespace AML\View\Tests\Statics;
use AML\View\{Page, State, View};
use function AML\View\{Button, VStack};

class StaticsPage extends Page
{
    #[State] public array $result = [];
    #[State] public int $counter = 0;
    public string $handler = 'values';
    public function body(): View { return VStack(Button('Run')->onClick($this->{$this->handler}(...))); }
    public function values(): void { $this->result = [Counter::$count, Counter::$price, gettype(Counter::$price), Counter::$label, Counter::$active, Counter::$loose]; }
    public function arithmetic(): void { Counter::$count = 4; $a = Counter::$count++; $b = ++Counter::$count; Counter::$count *= 2; Counter::$count -= 1; Counter::$price /= 2; Counter::$label .= '!'; Counter::$active = false; $this->result = [$a, $b, Counter::$count, Counter::$price, gettype(Counter::$price), Counter::$label, Counter::$active]; }
    public function inherited(): void { ChildCounter::$count = 4; $this->result = [Counter::$count, ChildCounter::next(), Counter::next(), ChildCounter::parentValue(), ChildCounter::protectedValue(), Counter::access()]; }
    public function redeclared(): void { SeparateCounter::next(); SeparateCounter::own(); $this->result = [Counter::$count, ChildCounter::$count, SeparateCounter::$count, SeparateCounter::access()]; }
    public function privateLate(): void { $this->counter++; $this->result = [SeparateCounter::hidden()]; }
    public function traits(): void { TraitCounter::hit(); $this->result = [TraitCounter::$hits, OtherTraitCounter::$hits]; }
    public function arrays(): void { $copy = Counter::$items; Counter::$items[] = 8; Counter::$items[1] = 9; unset(Counter::$items[0]); Counter::$items['more'] = [1]; Counter::$items['more'][] = 2; $this->result = [$copy, Counter::$items]; }
    public function sorting(): void { sort(Counter::$items); array_push(Counter::$items, 4); $last = array_pop(Counter::$items); $this->result = [Counter::$items, $last]; }
    public function reference(): void { bump(Counter::$count); bump(n: Counter::$count); $this->result = [Counter::$count]; }
    public function referenceError(): void { $this->counter++; badReference(Counter::$count); }
    public function uninitialized(): void { $this->counter++; $this->result = [Counter::$pending]; }
    public function initialized(): void { Counter::$pending = 3; $this->result = [Counter::$pending]; }
    public function quiet(): void { $this->result = [isset(Counter::$pending), empty(Counter::$pending), Counter::$pending ?? 7, isset(Counter::$secret), empty(Counter::$missing), Counter::$secret ?? 9]; Counter::$pending ??= 4; $this->result[] = Counter::$pending; }
    public function nullableReference(): void { replaceValue(Counter::$nullable); $this->result = [Counter::$nullable]; }
    public function uninitializedReference(): void { $this->counter++; replaceValue(Counter::$pending); }
    public function unsetStatic(): void { $this->counter++; unset(Counter::$count); }
    public function badType(): void { $this->counter++; Counter::$count = 'bad'; }
    public function strictType(): void { $this->counter++; Counter::$count = '12'; }
    public function missing(): void { $this->counter++; Counter::$missing = 4; }
    public function instanceAsStatic(): void { $this->counter++; $this->result = [Box::$value]; }
    public function privateAccess(): void { $this->counter++; $this->result = [Counter::$secret]; }
    public function protectedAccess(): void { $this->counter++; Counter::$protected = 4; }
    public function caught(): void { Counter::$count++; try { Counter::$count = []; } catch (\TypeError $e) { $this->result = [get_class($e), Counter::$count]; } finally { $this->counter++; } }
    public function closures(): void { $read = SeparateCounter::reader(); SeparateCounter::$count++; $this->result = [$read()]; }
    public function objectValue(): void { Counter::$cache = new Box; Counter::$cache->value++; $this->result = [Counter::$cache->next()]; }
    public function callableValue(): void { Counter::$cache = fn (int $n): int => $n + Counter::$count; Counter::$count = 3; $this->result = [(Counter::$cache)(4)]; }
    public function evalLiteral(): void { $this->result = [eval('\\AML\\View\\Tests\\Statics\\Counter::$count += 2; return \\AML\\View\\Tests\\Statics\\Counter::$count;')]; }
    public function evalDynamic(): void { Counter::$count = 4; $code = '\\AML\\View\\Tests\\Statics\\Counter::$count += 3; return \\AML\\View\\Tests\\Statics\\Counter::$count;'; $this->result = [eval($code)]; }
    public function evalScope(): void { $this->result = [SeparateCounter::evaluate('static::$count++; return [self::$count, static::$count, self::$secret];')]; }
    public function evalReference(): void { $this->result = [ChildCounter::evaluate('sort(self::$items); return [parent::$count ?? 8, self::$items];')]; }
    public function evalClosure(): void { $this->result = [Counter::evaluate('$f = static fn () => ++self::$count; return $f();')]; }
    public function next(): void { $this->result = [Counter::next()]; }
    public function read(): void { $this->result = [Counter::$count]; }
    public function failAfterWrite(): void { Counter::$count++; $this->counter++; throw new \RuntimeException('after write'); }
    public function saveObject(): void { Counter::$cache = new Box(10); $this->result = [Counter::$cache->value]; }
    public function useObject(): void { $this->result = [Counter::$cache->next()]; }
    public function saveClosure(): void { $n = 10; Counter::$cache = fn (): int => ++$n; $this->result = [1]; }
    public function useClosure(): void { $this->result = [(Counter::$cache)()]; }
}
