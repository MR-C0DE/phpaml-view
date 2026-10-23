<?php
declare(strict_types=1);
namespace AML\View\Tests\Factories\Components;

use AML\View\{Component, Page, Layout, View};
use ArrayObject as Options;
use function AML\View\Text;

enum Mode { case Compact; }
interface Left {}
interface Right {}
final class Both implements Left, Right {}

final class Values extends Component
{
    private const LABEL = 'André';
    public function __construct(
        public string $label = self::LABEL,
        public int $count = 3,
        public float $amount = 12.5,
        public bool $active = true,
        public ?string $optional = null,
        public string|int|null $union = null,
        public readonly array $options = ['mode' => Mode::Compact, 'nested' => [false, "a\nb"]],
        public Mode $mode = Mode::Compact,
    ) {}
    public function body(): View { return Text($this->label); }
}

class Inherited extends Component
{
    protected const LABEL = 'hérité';
    public function __construct(public ?self $previous = null, public string $label = self::LABEL) {}
    public function body(): View { return Text($this->label); }
}
final class Child extends Inherited {}
final class ParentType extends Inherited
{
    public function __construct(public ?parent $owner = null) {}
}
final class Intersection extends Component
{
    public function __construct(public Left&Right $value) {}
    public function body(): View { return Text('intersection'); }
}
final class Dnf extends Component
{
    public function __construct(public (Left&Right)|null $value = null) {}
    public function body(): View { return Text('dnf'); }
}
final class Tail extends Component
{
    public array $values;
    public function __construct(public string $label = 'tail', int ...$values) { $this->values = $values; }
    public function body(): View { return Text($this->label); }
}
final class Reference extends Component
{
    public function __construct(string &$value, string &...$rest) { $value .= '!'; foreach ($rest as &$item) $item .= '!'; }
    public function body(): View { return Text('reference'); }
}
final class EmptyCard extends Component
{
    public function body(): View { return Text('empty'); }
}
final class Manual extends Component
{
    public function __construct(public string $label = 'constructor') {}
    public function body(): View { return Text($this->label); }
}
abstract class AbstractCard extends Component {}
final class PrivateCard extends Component
{
    private function __construct() {}
    public function body(): View { return Text('private'); }
}
final class APage extends Page { public function body(): View { return Text('page'); } }
final class ALayout extends Layout { public function body(): View { return Text('layout'); } }
final class Utility {}

final class Counted
{
    public static int $calls = 0;
    public function __construct(public string $label = '') { self::$calls++; }
}
final class ObjectDefaults extends Component
{
    private const LABEL = 'object';
    public function __construct(
        public Counted $value = new Counted(label: self::LABEL),
        public Options $options = new Options(['mode' => Mode::Compact]),
        public array $nested = [new Counted(__DIR__)],
    ) {}
    public function body(): View { return Text($this->value->label); }
}

final class ReactiveDefault extends Component
{
    public function __construct(private string $label, private Counted $options = new Counted()) {}
    public function body(): View { return Text($this->label)->attribute('data-test', 'auto-label'); }
}
final class Totals extends Component
{
    private array $values;
    public function __construct(int ...$values) { $this->values = $values; }
    public function body(): View { return Text(array_sum($this->values))->attribute('data-test', 'auto-total'); }
}
function forward(string $label): ReactiveDefault { return ReactiveDefault($label); }

class_alias(EmptyCard::class, __NAMESPACE__ . '\\EmptyAlias');
