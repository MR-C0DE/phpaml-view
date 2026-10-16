<?php
declare(strict_types=1);
namespace AML\View\Tests\Forms;
use AML\View\{Component as BaseComponent, Page, State, View};
use function AML\View\{Component, VStack, Text, TextField, TextArea, NumberInput, Checkbox, Select, Button, Input};

function forwardedField(string $value, string $name): View { return TextField($name, $value); }
final class Editor extends BaseComponent
{
    #[State] public string $local = 'local';
    public function __construct(private readonly string $name, private ?string $note, private int $quantity, private bool $enabled, private string $choice, private array $profile, private string $id) {}
    public function body(): View
    {
        return VStack(
            forwardedField($this->name, $this->id . '-name'),
            TextArea($this->id . '-note', $this->note),
            NumberInput($this->id . '-quantity', $this->quantity),
            Checkbox($this->id . '-enabled', $this->enabled),
            Select($this->id . '-choice', ['a'=>'A', 'b'=>'B'], $this->choice),
            Input($this->id . '-city')->value($this->profile['city']),
            NumberInput($this->id . '-count', $this->profile['count']),
            Checkbox($this->id . '-flag', $this->profile['flag']),
            Text(gettype($this->profile['count']) . '/' . gettype($this->profile['flag']))->attribute('data-test', $this->id . '-types'),
            TextField($this->id . '-local', $this->local),
            Text($this->name)->attribute('data-test', $this->id . '-label'),
        );
    }
}
class Prefix extends BaseComponent
{
    protected string $label;
    public function __construct(string $message) { $this->label = $this->format($message); }
    protected function format(string $message): string { return 'Parent ' . $message; }
    public function body(): View { return Text($this->label)->attribute('data-test', 'inherited'); }
}
final class Complex extends Prefix
{
    public function __construct(string $message) {
        parent::__construct($message);
        if ($message === '') { $this->label = 'vide'; return; }
        $property = 'label';
        $this->{$property} .= '!';
    }
    protected function format(string $message): string { return 'Bonjour ' . strtoupper($message); }
    public function body(): View { return Text($this->label)->attribute('data-test', 'complex'); }
}
if (PHP_VERSION_ID >= 80400) require __DIR__ . '/ComponentFormHooks.php';
else {
    final class Hooked extends BaseComponent
    {
        public function __construct(private string $message) {}
        public function body(): View { return Text('[' . trim($this->message) . ']')->attribute('data-test', 'hooked'); }
    }
}
final class DirectLabel extends BaseComponent
{
    public function __construct(private string $message, private string $id = 'raw') {}
    public function body(): View { return Text($this->message)->attribute('data-test', $this->id); }
}
class PrivateParent extends BaseComponent
{
    public function __construct(private string $message) {}
    public function body(): View { return Text($this->message)->attribute('data-test', 'inherited-private'); }
}
final class PrivateChild extends PrivateParent {}
final class TypedConstruction extends BaseComponent
{
    private int $number;
    public function __construct(string $message) { $this->assign($message); }
    private function assign(string $message): void { $this->number = $message === 'bad' ? 'invalid' : strlen($message); }
    public function body(): View { return Text($this->number)->attribute('data-test', 'constructed-number'); }
}
function directFactory(string $message): View { return new DirectLabel($message, 'raw-helper'); }
final class DirectReturn extends BaseComponent
{
    public function __construct(private string $message) {}
    public function body(): View { return new DirectLabel($this->message, 'direct-return'); }
}
final class NamedField extends BaseComponent
{
    public function __construct(private string $value, private string $id) {}
    public function body(): View { return TextField($this->id, $this->value); }
}
final class NestedField extends BaseComponent
{
    public function __construct(private string $value) {}
    public function body(): View { return new NamedField($this->value, 'nested-field'); }
}
final class NestedFunction extends BaseComponent
{
    private int $length;
    public function __construct(string $message) {
        function projectedLength(string $value): int { return strlen($value); }
        $this->assignLength($message);
    }
    private function assignLength(string $message): void { $this->length = projectedLength($message); }
    public function body(): View { return Text($this->length)->attribute('data-test', 'nested-function'); }
}
final class FormsPage extends Page
{
    #[State] public string $name = 'André';
    #[State] public ?string $note = null;
    #[State] public int $quantity = 2;
    #[State] public bool $enabled = false;
    #[State] public string $choice = 'a';
    #[State] public array $profile = ['city'=>'Montréal', 'keep'=>'intact', 'count'=>3, 'flag'=>false];
    #[State] public string $other = 'Voisin';
    #[State] public mixed $amount = 1.5;
    #[State] public array $fields = ['value'=>'Tableau', 'id'=>'named-field'];
    public function body(): View
    {
        $direct = new DirectLabel($this->name, 'raw-alias');
        return VStack(
            NumberInput('mixed-amount', $this->amount),
            Text(gettype($this->amount))->attribute('data-test', 'mixed-type'),
            Component(NamedField::class, ...$this->fields),
            Component(...['class'=>NamedField::class, 'value'=>$this->name, 'id'=>'spread-field']),
            Component(NestedField::class, $this->name),
            new DirectLabel($this->name),
            $direct,
            new DirectReturn($this->name),
            new PrivateChild($this->name),
            directFactory($this->name),
            Component(TypedConstruction::class, $this->name),
            Component(NestedFunction::class, $this->name),
            Component(Editor::class, $this->name, $this->note, $this->quantity, $this->enabled, $this->choice, $this->profile, 'a'),
            Component(Editor::class, $this->other, $this->note, $this->quantity, $this->enabled, $this->choice, $this->profile, 'b'),
            Component(Complex::class, $this->name),
            Component(Hooked::class, $this->name),
            Text($this->name)->attribute('data-test', 'parent-name'),
            Button('Parent')->onClick(function () { $this->name = 'Marie'; $this->note = 'Note'; $this->quantity = 7; $this->enabled = true; $this->choice = 'b'; $this->profile['city'] = 'Québec'; }),
            Button('Vide')->onClick(function () { $this->name = ''; }),
        );
    }
}
