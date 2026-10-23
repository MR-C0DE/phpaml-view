<?php
declare(strict_types=1);
namespace AML\View\Tests\Inputs;

use AML\View\{Component, Page, State, View};
use function AML\View\{Button, Text, TextField, VStack};

function Carte(string $a = '', string $id = 'card'): Carte { return new Carte($a, $id); }
function Apercu(string $a, string $id): Apercu { return new Apercu($a, $id); }
function replaceName(string &$value): void { $value = 'Référence'; }

final class Carte extends Component
{
    #[State] private int $clicks = 0;
    public function __construct(private string $a = '', private readonly string $id = 'card') {}
    private function rename(): void { $this->a = 'Méthode'; }
    public function body(): View
    {
        return VStack(
            Text($this->a)->attribute('data-test', $this->id),
            Text($this->clicks)->attribute('data-test', $this->id . '-clicks'),
            TextField($this->id . '-input', $this->a),
            Button($this->id . '-set')->onClick(function (): void { $this->a = 'Modifié'; $this->clicks++; }),
            Button($this->id . '-append')->onClick(function (): void { $this->a .= '!'; }),
            Button($this->id . '-method')->onClick(function (): void { $this->rename(); }),
            Button($this->id . '-reference')->onClick(function (): void { replaceName($this->a); }),
            Button($this->id . '-invalid')->onClick(function (): void { $this->clicks++; $this->a = 42; }),
            Apercu($this->a, $this->id . '-preview'),
        );
    }
}
final class Apercu extends Component
{
    public function __construct(private string $a, private readonly string $id) {}
    public function body(): View
    {
        return VStack(
            Text($this->a)->attribute('data-test', $this->id),
            Button($this->id . '-set')->onClick(function (): void { $this->a = 'Petit-enfant'; }),
        );
    }
}
final class DataEditor extends Component
{
    public function __construct(private array $data, private float $price, private ?string $optional) {}
    public function body(): View
    {
        return VStack(
            Text(gettype($this->price))->attribute('data-test', 'price-type'),
            Button('Data')->onClick(function (): void {
                $this->data['count']++;
                $this->data['items'][] = 'new';
                unset($this->data['keep']);
                $this->price += 1;
                $this->optional ??= 'fallback';
            }),
            Text(implode(',', $this->data['items']))->attribute('data-test', 'items'),
            Button('Array reference')->onClick(function (): void { array_push($this->data['items'], 'builtin'); }),
        );
    }
}
final class ReadonlyEditor extends Component
{
    public function __construct(private readonly string $a) {}
    public function body(): View { return Button('Readonly')->onClick(function (): void { $this->a = 'interdit'; }); }
}
final class CalculatedPage extends Page
{
    #[State] private string $a = 'André';
    public function body(): View { return Apercu(strtoupper($this->a), 'calculated'); }
}
final class Host extends Component
{
    #[State] private string $a = 'Isolé';
    public function body(): View { return Carte($this->a, 'host'); }
}
final class InputsPage extends Page
{
    #[State] private string $a = 'André';
    #[State] private string $other = 'André';
    #[State] private mixed $typed = 'Typé';
    #[State] private array $data = ['count' => 1, 'items' => [], 'keep' => true];
    #[State] private float $price = 1.0;
    #[State] private ?string $optional = null;
    public function body(): View
    {
        return VStack(
            Text($this->a)->attribute('data-test', 'parent'),
            Text($this->other)->attribute('data-test', 'other'),
            Carte($this->a),
            Carte(a: $this->other, id: 'sibling'),
            Carte($this->typed, 'typed'),
            new Host(),
            Carte('Bonjour', 'literal'),
            Carte(id: 'default'),
            new DataEditor($this->data, $this->price, $this->optional),
            new ReadonlyEditor($this->a),
            Button('Parent')->onClick(function (): void { $this->a = 'Parent'; }),
        );
    }
}
