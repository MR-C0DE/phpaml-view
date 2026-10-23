<?php
declare(strict_types=1);
namespace AML\View\Tests\Factories\Pages\Home;

use AML\View\{Page, State, View};
use function AML\View\{VStack, Button};
use function AML\View\Tests\Factories\Components\{Values, EmptyCard, Manual, forward, Totals};

final class HomePage extends Page
{
    #[State] private string $label = 'André';
    #[State] private array $numbers = [2, 3];
    public function body(): View
    {
        return VStack(
            Values(), EmptyCard(), Manual(),
            forward($this->label),
            Totals(...$this->numbers),
            Button('Change')->onClick(function (): void { $this->label = 'Alice'; $this->numbers = [10, 20, 30]; }),
        );
    }
}
