<?php
declare(strict_types=1);
namespace AML\View\Tests\Enums;
use AML\View\{Page, State, View};
use function AML\View\{Button, VStack};
interface Labelled { public function label(): string; }
trait Label { public function label(): string { return $this->name . ':' . $this->value; } }
enum Suit { case Hearts; case Clubs; public const Favorite = self::Hearts; public function other(): self { return $this === self::Hearts ? self::Clubs : self::Hearts; } }
enum Status: string implements Labelled { use Label; case Draft = 'draft'; case Paid = 'paid'; public const Default = self::Draft; public static function choose(bool $paid): self { return $paid ? self::Paid : self::Draft; } }
enum Number: int { case Zero = 0; case Two = 2; }
enum EmptyEnum {}
class Holder { public static ?Status $last = null; public function __construct(public Status $status = Status::Draft) {} }
function statusFromService(): Status { return Status::Paid; }
function enumName(\UnitEnum $value): string { return $value->name; }
function backedValue(\BackedEnum $value): int|string { return $value->value; }
class EnumsPage extends Page {
 #[State] public array $result = [];
 #[State] public int $counter = 0;
 public string $handler = 'basic';
 public function body(): View { return VStack(Button('Run')->onClick($this->{$this->handler}(...))); }
 public function basic(): void { $v = Suit::Hearts; $this->result = [$v->name, $v === Suit::Hearts, $v === Suit::Clubs, $v == Suit::Favorite, $v->other()->name]; }
 public function cases(): void { $this->result = [array_map(fn($v) => $v->name, Suit::cases()), array_map(fn($v) => [$v->name, $v->value], Status::cases()), EmptyEnum::cases()]; }
 public function from(): void { $v = Status::from('paid'); $this->result = [$v === Status::Paid, $v->label(), Status::tryFrom('missing'), Number::from(0)->name, Number::tryFrom(2)->value]; }
 public function named(): void { $this->result = [Status::from(value: 'paid')->name, Number::from(...['value' => 2])->value]; }
 public function contracts(): void { $v = Status::choose(true); $this->result = [enumName($v), backedValue($v), $v instanceof \UnitEnum, $v instanceof \BackedEnum, $v instanceof Labelled, Suit::Hearts instanceof \BackedEnum]; }
 public function defaults(): void { $h = new Holder; $this->result = [$h->status === Status::Default, (fn(Status $v = Status::Paid) => $v->name)()]; }
 public function matching(): void { $v = Status::Paid; $this->result = [match($v) { Status::Draft => 1, Status::Paid => 2 }, in_array($v, Status::cases(), true), array_search($v, Status::cases(), true)]; }
 public function callable(): void { $fn = Status::from(...); $case = Status::Paid; $method = $case->label(...); $this->result = [$fn('paid')->name, $method()]; }
 public function introspection(): void { $v = Status::Paid; $this->result = [get_class($v), get_object_vars($v), class_exists(Status::class), interface_exists(\UnitEnum::class), is_a($v, \BackedEnum::class), property_exists($v, 'name'), method_exists($v, 'cases')]; }
 public function persisted(): void { $this->result = [Holder::$last === Status::Paid, $this->counter++]; Holder::$last = Status::Paid; }
 public function output(): void { var_dump(Status::Paid, Suit::Hearts); }
 public function evalCases(): void { $v = Status::Paid; $this->result = eval('return [$v->name, $v->value, $v instanceof BackedEnum];'); }
 public function invalidValue(): void { $this->counter++; Status::from('missing'); }
 public function invalidType(): void { $this->counter++; Number::from('2'); }
 public function readonly(): void { $this->counter++; $v = Status::Paid; $v->name = 'Other'; }
 public function unsetName(): void { $this->counter++; $v = Status::Paid; unset($v->name); }
 public function cloneCase(): void { $this->counter++; $v = clone Status::Paid; }
 public function instantiate(): void { $this->counter++; $v = new Status; }
 public function ordering(): void { $a = Status::Draft; $b = Status::Paid; $this->result = [$a < $b, $a > $b, $a <= $b, $a >= $b, $a <=> $b, $a <= $a]; }
 public function extraArguments(): void { $this->counter++; Status::cases(1); }
 public function extraFrom(): void { $this->counter++; Status::from('paid', 2); }
 public function enumExists(): void { $this->result = [enum_exists(Status::class), enum_exists('MissingEnum'), enum_exists(Holder::class), (array) Status::Paid]; }
 public function serviceFunction(): void { $this->result = [statusFromService()->name, statusFromService() === Status::Paid]; }
 public function invalidState(): void { $this->result = [Status::Paid]; }
}
