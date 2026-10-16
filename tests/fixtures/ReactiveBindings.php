<?php
declare(strict_types=1);
namespace AML\View\Tests\Bindings;
use AML\View\{Page, State, View};
use function AML\View\{Text, Paragraph, VStack, Button};

function caption(string $text, string $prefix = 'Titre'): View
{
    $prefix = strtoupper($prefix);
    $label = $prefix;
    $label .= ' : ' . $text;
    return Text($label);
}
function nestedCaption(string $value): View
{
    return caption($value, 'Imbriqué');
}
function arrayCaption(array $numbers): View
{
    $total = 0;
    foreach ($numbers as $number) $total += $number;
    return Text(count($numbers) . ' / ' . $total);
}
final class BindingsPage extends Page
{
    #[State] public string $message = 'André';
    #[State] public int $count = 2;
    #[State] public bool $enabled = true;
    #[State] public array $numbers = [3, 1, 2];
    #[State] public int $divisor = 2;
    public function body(): View
    {
        $prefix = 'Bonjour'; $prefix .= ' à vous';
        $label = $this->message;
        if ($this->enabled) { $label .= ' actif'; } else { $label .= ' inactif'; }
        $sum = 0;
        foreach ($this->numbers as $number) { $sum += $number; }
        $suffix = '';
        for ($i = 0; $i < $this->count; $i++) { $suffix .= '!'; }
        $snapshot = $label; $label .= $suffix;
        $parts = [$this->message, ' / ', $this->count];
        $sorted = $this->numbers; $sortedOk = sort($sorted);
        switch ($this->count) {
            case 2: $status = 'deux'; break;
            default: $status = 'autre';
        }
        $steps = 0;
        while ($steps < $this->count) $steps++;
        try { $ratio = 10 / $this->divisor; }
        catch (\DivisionByZeroError $error) { $ratio = 'impossible'; }
        $factory = Text(...);
        return VStack(
            Text($prefix . ' ' . $this->message)->attribute('data-test', 'compound'),
            Text($label)->attribute('data-test', 'branch'),
            Text($snapshot)->attribute('data-test', 'snapshot'),
            Text($sum)->attribute('data-test', 'loop'),
            Text(implode(',', $sorted))->attribute('data-test', 'sort'),
            Paragraph('Préfixe ', ...$parts, ...[' fin'])->attribute('data-test', 'spread'),
            $factory('Dynamique ' . $this->message)->attribute('data-test', 'factory'),
            caption($this->message)->attribute('data-test', 'helper'),
            nestedCaption($label)->attribute('data-test', 'nested-helper'),
            caption(prefix: 'Nom', text: $this->message)->attribute('data-test', 'named-helper'),
            arrayCaption($this->numbers)->attribute('data-test', 'array-helper'),
            Text(hash('sha256', $this->message))->attribute('data-test', 'hash'),
            Text(ctype_alnum($this->message) ? 'oui' : 'non')->attribute('data-test', 'validation'),
            Text($status . ' / ' . $steps)->attribute('data-test', 'switch-while'),
            Text($ratio)->attribute('data-test', 'caught'),
            Text(10 / $this->divisor)->attribute('data-test', 'division'),
            Button('Changer')->onClick(function (): void {
                $this->message = '<b>Marie</b>'; $this->count = 3;
                $this->enabled = false; $this->numbers = [8, 2];
            }),
            Button('Erreur')->onClick(function (): void { $this->divisor = 0; }),
            Button('Réparer')->onClick(function (): void { $this->divisor = 5; }),
        );
    }
}
