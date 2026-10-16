<?php
declare(strict_types=1);
namespace AML\View\Tests\Statics;
use AML\View\{Component, Page, State, View};
use function AML\View\{Button, VStack, Each, When, Text};
final class StaticProbe extends Component
{
    public function body(): View
    {
        return Button('Tick')->onClick(static function (): void { echo Counter::next(); });
    }
}
final class StaticLifecyclePage extends Page
{
    #[State] public array $items = [];
    #[State] public bool $visible = true;
    public function body(): View
    {
        return VStack(
            Button('Add')->onClick(function (): void { $this->items = [['id' => 'a'], ['id' => 'b']]; }),
            Button('Remove')->onClick(function (): void { $this->items = []; }),
            Button('Toggle')->onClick(function (): void { $this->visible = !$this->visible; }),
            Each($this->state('items'), render: static fn () => new StaticProbe()),
            When($this->state('visible'), new StaticProbe(), Text('Hidden')),
        );
    }
}
