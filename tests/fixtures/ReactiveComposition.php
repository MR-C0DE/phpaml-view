<?php
declare(strict_types=1);
namespace AML\View\Tests\Composition;
use AML\View\{Component, Page, State, View};
use function AML\View\{Component, Text, VStack, Button};

function summary(string $label = 'défaut', int ...$values): View
{
    return Text($label . ': ' . implode(',', array_keys($values)) . ' / ' . count($values) . ' / ' . array_sum($values));
}
function unpacked(string $message, string $suffix = '!'): View
{
    return Text($message . $suffix);
}
function forwarding(array $args): View
{
    return unpacked(...$args);
}
final class Caption extends Component
{
    public function __construct(public readonly string $message, public string $id, public array $numbers = []) {}
    public function body(): View
    {
        return VStack(
            Text($this->message)->attribute('data-test', $this->id),
            Text(count($this->numbers) . '/' . array_sum($this->numbers))->attribute('data-test', $this->id . '-numbers'),
        );
    }
}
final class Decorated extends Component
{
    public static int $constructions = 0;
    private string $label;
    public function __construct(string $text, private string $id, bool $upper = false)
    {
        self::$constructions++;
        $prefix = 'Bonjour ';
        $this->label = $prefix . ($upper ? strtoupper($text) : $text);
        $this->label .= '!';
    }
    public function body(): View
    {
        return Component(Caption::class, $this->label, $this->id);
    }
}
function customCaption(string $message): View
{
    return Component(Caption::class, $message, 'custom');
}
final class InterpolatedCaption extends Component
{
    private string $message;
    public function __construct(string $value) { $this->message = "Salut {$value}"; }
    public function body(): View { return Text($this->message)->attribute('data-test', 'interpolated-child'); }
}
final class MixedCaption extends Component
{
    public function __construct(private string $message, private View $content, private \AML\Engine\StateRef $reference) {}
    public function body(): View
    {
        return VStack(
            Text($this->message)->attribute('data-test', 'mixed-input'),
            Text($this->reference)->attribute('data-test', 'explicit-input'),
            $this->content,
        );
    }
}
final class TemplateCaption extends Component
{
    public function __construct(private string $message, private array $numbers) {}
    public function body(): View
    {
        return VStack(
            Text('{message} / {numbers.0}')->attribute('data-test', 'template-child'),
            \AML\View\Option('value', '{message}')->attribute('data-test', 'template-option'),
        );
    }
}
final class VariadicCaption extends Component
{
    private array $values;
    public function __construct(int ...$values) { $this->values = $values; }
    public function body(): View
    {
        return Text(count($this->values) . '/' . array_sum($this->values))->attribute('data-test', 'variadic-child');
    }
}
final class StrictInput extends Component
{
    private int $amount;
    public function __construct(mixed $amount) { $this->amount = $amount; }
    public function body(): View { return Text($this->amount)->attribute('data-test', 'typed-child'); }
}
final class Counter extends Component
{
    #[State] public int $count = 1;
    #[State] public string $observed = '';
    public function __construct(private string $label, private string $id) {}
    private function readLabel(): string
    {
        $formatter = new class($this->label) {
            public function __construct(private string $label) {}
            public function text(): string { return 'Lu ' . $this->label; }
        };
        return $formatter->text();
    }
    public function body(): View
    {
        return VStack(
            Text($this->label . ': ' . $this->count)->attribute('data-test', $this->id),
            Button('Compter ' . $this->id)->onClick(function (): void { $this->count++; }),
            Text($this->observed)->attribute('data-test', $this->id . '-observed'),
            Button('Lire ' . $this->id)->onClick(function (): void { $this->observed = $this->readLabel(); }),
        );
    }
}
final class CompositionPage extends Page
{
    #[State] public string $message = 'André';
    #[State] public array $numbers = [2, 3];
    #[State] public array $named = ['message' => 'André'];
    #[State] public array $extras = ['first' => 4];
    #[State] public mixed $typed = 3;
    public function body(): View
    {
        $arguments = [$this->message, ' ?'];
        $helper = unpacked(...);
        return VStack(
            summary($this->message, ...$this->numbers)->attribute('data-test', 'variadic'),
            summary(label: $this->message, first: 8, last: 9)->attribute('data-test', 'named-variadic'),
            summary('fixe', ...$this->extras)->attribute('data-test', 'spread-keys'),
            unpacked(...$arguments)->attribute('data-test', 'unpack-local'),
            forwarding($this->named)->attribute('data-test', 'forward'),
            $helper(...$arguments)->attribute('data-test', 'callable-helper'),
            Component(TemplateCaption::class, $this->message, $this->numbers),
            Component(MixedCaption::class, $this->message, Text('Fixe')->attribute('data-test', 'slot'), $this->state('message')),
            customCaption($this->message),
            Component(InterpolatedCaption::class, $this->message),
            Component(VariadicCaption::class, ...$this->numbers),
            Component(StrictInput::class, $this->typed),
            Component(Caption::class, $this->message, 'child', $this->numbers),
            Component(Caption::class, message: 'André', id: 'static'),
            Component(Decorated::class, text: $this->message, id: 'decorated'),
            Component(Counter::class, $this->message, 'counter-a'),
            Component(Counter::class, $this->message, 'counter-b'),
            Button('Vider extras')->onClick(function (): void { $this->extras = []; }),
            Button('Type invalide')->onClick(function (): void { $this->typed = 'invalide'; }),
            Button('Réparer type')->onClick(function (): void { $this->typed = 6; }),
            Button('Changer')->onClick(function (): void {
                $this->message = '<b>Marie</b>';
                $this->numbers = [10, 20, 30];
                $this->named = ['suffix' => '!!', 'message' => 'Alice'];
                $this->extras = ['left' => 5, 'right' => 6];
            }),
        );
    }
}

final class InvalidInputWrite extends Component
{
    public function __construct(private string $message) {}
    public function body(): View
    {
        return Button('Écrire')->onClick(function (): void { $this->message = 'Interdit'; });
    }
}

final class EarlyReturnInput extends Component
{
    public function __construct(private string $message) { if ($message !== '') return; }
    public function body(): View { return Text($this->message); }
}
final class MethodInput extends Component
{
    private string $message;
    public function __construct(string $message) { $this->fill($message); }
    private function fill(string $message): void { $this->message = strtoupper($message); }
    public function body(): View { return Text($this->message); }
}
