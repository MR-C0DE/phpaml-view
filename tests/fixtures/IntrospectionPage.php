<?php
declare(strict_types=1);
namespace AML\View\Tests\Introspection;
use AML\View\{Page, State, View};
use function AML\View\{Button, VStack};
interface RootContract { public function NamedMethod(): string; }
interface Contract extends RootContract {}
trait NamedTrait { public function FromTrait(): string { return 'trait'; } }
class BaseRecord {
    public int $first = 1;
    private string $secret = 'parent';
    protected float $amount = 2.0;
    public int $pending;
    public ?int $nullable;
    public static int $shared = 4;
    private static int $hiddenStatic = 5;
    private function HiddenMethod(): void {}
    protected function ProtectedMethod(): void {}
    public function ParentScope(): array { return [get_object_vars($this), get_class_methods($this), get_class(), get_called_class(), get_parent_class(), method_exists($this, 'HiddenMethod'), method_exists(static::class, 'HiddenMethod')]; }
    public static function Names(): array { return [get_class(), get_called_class(), get_parent_class()]; }
}
class Record extends BaseRecord implements Contract {
    use NamedTrait;
    public int $first = 3;
    private string $secret = 'child';
    public string $label = 'André';
    public array $items = [1, 2];
    public function NamedMethod(): string { return 'ok'; }
    public function ChildScope(): array { return [get_object_vars($this), get_class_methods($this), array_map('get_object_vars', [$this])]; }
    public function CallableScope(): array { $f = get_object_vars(...); return $f($this); }
    public function __get(string $key): mixed { throw new \RuntimeException('Introspection called __get'); }
    public function __call(string $key, array $values): mixed { throw new \RuntimeException('Introspection called __call'); }
}
class ShadowBase { private int $field = 1; public function Inspect(): array { return get_object_vars($this); } }
class ShadowChild extends ShadowBase { public int $field = 2; }
class_alias(Record::class, __NAMESPACE__ . '\\RecordAlias');
class UnusedService { public function __construct() { throw new \RuntimeException('Must not instantiate'); } public function Ready(): bool { return true; } }
class DebugRecord {
    private string $password = 'secret';
    public int $calls = 0;
    public function __debugInfo(): array { $this->calls++; return ['label' => 'André', 'amount' => 2.0, 'nested' => ['ok' => true], 7 => null]; }
}
class ChildDebug extends DebugRecord {}
class EmptyDebug { public int $hidden = 1; public function __debugInfo() { return null; } }
class RecursiveDebug { public function __debugInfo(): array { return ['self' => $this]; } }
class InvalidDebug { public function __debugInfo() { return 7; } }
class ThrowingDebug { public function __debugInfo(): array { throw new \RuntimeException('Debug failed'); } }
class LoopDebug { public function __debugInfo(): array { var_dump($this); return []; } }
function noScope(): array { return [get_class()]; }
function noCalledScope(): array { return [get_called_class()]; }
function noParentScope(): array { return [get_parent_class()]; }
class IntrospectionPage extends Page {
    #[State] public array $result = [];
    #[State] public int $counter = 0;
    public string $handler = 'exists';
    public function body(): View { return VStack(Button('Run')->onClick($this->{$this->handler}(...))); }
    public function exists(): void { $this->result = [class_exists(Record::class), class_exists(UnusedService::class, false), class_exists('missing_aml_service', false), class_exists(Contract::class), interface_exists(Contract::class), interface_exists(Record::class), interface_exists(RootContract::class), class_exists('\\aml\\view\\tests\\introspection\\record')]; }
    public function kinds(): void { $r = new Record; $f = fn () => 1; $e = new \RuntimeException; $this->result = [is_object($r), is_object($f), is_object($e), is_object([]), is_object(null), get_class($r), get_class($f), get_class($e), is_a($e, \Exception::class), is_subclass_of($e, \Exception::class), is_a($f, \Closure::class)]; }
    public function parents(): void { $r = new Record; $this->result = [get_parent_class($r), get_parent_class(Record::class), get_parent_class(BaseRecord::class), get_parent_class(Contract::class)]; }
    public function relations(): void { $r = new Record; $this->result = [is_a($r, Record::class), is_a($r, BaseRecord::class), is_a($r, Contract::class), is_a($r, RootContract::class), is_a(Record::class, BaseRecord::class), is_a(Record::class, BaseRecord::class, true), is_subclass_of($r, Record::class), is_subclass_of($r, BaseRecord::class), is_subclass_of(Record::class, RootContract::class), is_subclass_of(Record::class, BaseRecord::class, false), is_a(4, Record::class), is_a(null, Record::class), is_a($r, 'NoClass')]; }
    public function methods(): void { $r = new Record; $this->result = [method_exists($r, 'namedMETHOD'), method_exists(Record::class, 'NamedMethod'), method_exists($r, 'HiddenMethod'), method_exists(Record::class, 'HiddenMethod'), method_exists($r, 'ProtectedMethod'), method_exists($r, 'Unknown'), get_class_methods($r), get_class_methods(Contract::class)]; }
    public function properties(): void { $r = new Record; $this->result = [property_exists($r, 'label'), property_exists($r, 'LABEL'), property_exists($r, 'pending'), property_exists($r, 'nullable'), property_exists($r, 'shared'), property_exists($r, 'hiddenStatic'), property_exists($r, 'amount'), property_exists($r, 'secret'), property_exists($r, 'virtual'), property_exists('missing_aml_service', 'x')]; }
    public function publicVars(): void { $r = new Record; $this->result = [get_object_vars($r)]; }
    public function parentScope(): void { $r = new Record; $this->result = $r->ParentScope(); }
    public function childScope(): void { $r = new Record; $this->result = $r->ChildScope(); }
    public function callableScope(): void { $r = new Record; $this->result = $r->CallableScope(); }
    public function lateStatic(): void { $this->result = Record::Names(); }
    public function changedVars(): void { $r = new Record; $r->pending = 8; $r->nullable = null; unset($r->label); $this->result = [get_object_vars($r), property_exists($r, 'label')]; }
    public function copies(): void { $r = new Record; $vars = get_object_vars($r); $vars['items'][] = 9; $this->result = [$vars, $r->items]; }
    public function named(): void { $r = new Record; $this->result = [get_class(object: $r), method_exists(method: 'NamedMethod', object_or_class: $r), property_exists(property: 'pending', object_or_class: $r), is_a(class: Contract::class, object_or_class: $r), get_object_vars(object: $r)]; }
    public function spread(): void { $r = new Record; $args = ['object_or_class' => $r, 'method' => 'NamedMethod']; $this->result = [method_exists(...$args)]; }
    public function callbacks(): void { $r = new Record; $f = method_exists(...); $g = get_object_vars(...); $this->result = [$f($r, 'NamedMethod'), $g($r), array_map('get_class', [$r]), array_map('get_object_vars', [$r]), array_map(is_object(...), [$r, 1])]; }
    public function unknownMethods(): void { $this->result = [method_exists('missing_aml_service', 'x'), interface_exists('missing_aml_interface', false)]; }
    public function unknownClass(): void { $this->counter++; $this->result = get_class_methods('missing_aml_service'); }
    public function badObject(): void { $this->counter++; $this->result = get_object_vars([]); }
    public function badClass(): void { $this->counter++; $this->result = [get_class(null)]; }
    public function badMember(): void { $this->counter++; $this->result = [method_exists([], 'x')]; }
    public function missingArg(): void { $this->counter++; $this->result = [property_exists(Record::class)]; }
    public function extraArg(): void { $this->counter++; $this->result = [class_exists(Record::class, false, true)]; }
    public function duplicateArg(): void { $r = new Record; $this->counter++; $this->result = [method_exists($r, object_or_class: $r, method: 'x')]; }
    public function unknownArg(): void { $this->counter++; $this->result = [class_exists(wrong: Record::class)]; }
    public function globalClass(): void { $this->counter++; $this->result = noScope(); }
    public function globalCalled(): void { $this->counter++; $this->result = noCalledScope(); }
    public function globalParent(): void { $this->result = noParentScope(); }
    public function debug(): void { $r = new DebugRecord; var_dump($r); $this->result = [get_object_vars($r), $r->calls]; }
    public function debugNested(): void { $r = new ChildDebug; var_dump(['record' => $r, 'again' => $r]); $this->result = [$r->calls]; }
    public function debugEmpty(): void { var_dump(new EmptyDebug); }
    public function debugRecursive(): void { var_dump(new RecursiveDebug); }
    public function debugInvalid(): void { $this->counter++; var_dump(new InvalidDebug); }
    public function debugThrows(): void { echo 'before'; $this->counter++; var_dump(new ThrowingDebug); }
    public function debugCaught(): void { try { var_dump(new InvalidDebug); } catch (\TypeError $e) { $this->result = ['caught']; } }
    public function debugLimit(): void { $this->counter++; var_dump(new LoopDebug); }
    public function anonymous(): void { $r = new class { public int $value = 1; private int $hidden = 2; public function Show(): int { return $this->hidden; } public function __debugInfo(): array { return ['value' => $this->value]; } }; $this->result = [get_object_vars($r), get_class_methods($r), class_exists(get_class($r)), method_exists($r, 'show')]; var_dump($r); }
    public function evalIntrospection(): void { $r = new Record; $this->result = eval('return [get_object_vars($r), get_class_methods($r), is_a($r, get_class($r)), class_exists(get_class($r))];'); }
    public function evalDebug(): void { $r = eval('return new class { public function Show(): int { return 1; } public function __debugInfo(): array { return ["eval" => 3.0]; } };'); $this->result = [get_class_methods($r), get_object_vars($r), class_exists(get_class($r))]; var_dump($r); }
    public function shadowed(): void { $r = new ShadowChild; $this->result = [$r->Inspect(), get_object_vars($r)]; }
    public function aliases(): void { $this->result = [class_exists(RecordAlias::class), get_parent_class(RecordAlias::class), get_class_methods(RecordAlias::class), is_a(RecordAlias::class, BaseRecord::class, true), is_a(new Record, RecordAlias::class)]; }
    public function internalMemberError(): void { $this->result = get_object_vars(new \RuntimeException); }

}
