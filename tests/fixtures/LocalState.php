<?php

declare(strict_types=1);

namespace AML\View\Tests\LocalState;

use AML\Engine\{ClientAction, StateRef};
use AML\View\{Component, Page, State, Style, View};
use function AML\View\{Button, NumberInput, State, Text, TextField, Toggle, VStack};

final class Counter extends Component
{
    public function __construct(private string $label) {}

    private function counter(): StateRef { return State(0); }

    public function body(): View
    {
        $count = $this->counter();
        return VStack(
            Text($count)->id($this->label),
            Button($this->label . '+')->increments($count),
            Button($this->label . '-')->decrements($count),
        );
    }
}

final class Editor extends Component
{
    public function __construct(private StateRef $message) {}

    public function body(): View
    {
        return VStack(
            TextField('child-message', $this->message),
            Text($this->message)->id('child-message'),
            Button('Child')->sets($this->message, 'Shared'),
        );
    }
}

final class LocalPage extends Page
{
    #[State] private int $property = 3;

    public function body(): View
    {
        $count = State(0);
        $message = State('André');
        $enabled = State(false);
        $size = State(12, type: 'float');
        $optional = State(null, type: '?int');
        $union = State(1, type: 'int|string');
        $items = State(['first']);
        return VStack(
            Text($count)->id('count'),
            Button('Add')->increments($count),
            Button('Subtract')->decrements($count),
            Button('Double')->sets($count, $count->times(2)),
            Button('Invalid count')->sets($count, 'oops'),
            TextField('message', $message),
            Text($message)->id('message')->style(Style::create()->fontSize($size)),
            Button('Append')->sets($message, $message->concat('!')),
            Toggle('enabled', $enabled),
            Text($enabled->choose('yes', 'no'))->id('enabled'),
            Button('Toggle')->toggles($enabled),
            Button('Resize')->sets($size, 24),
            Text($size)->id('size'),
            NumberInput('optional', $optional),
            Text($optional)->id('optional'),
            Button('Clear')->sets($optional, null),
            Text($union)->id('union'),
            Button('Union')->sets($union, 'text'),
            Button('Invalid union')->sets($union, []),
            Button('Push')->onClick(ClientAction::append($items, 'second')),
            Button('Invalid array')->sets($items, false),
            Text($this->property)->id('property'),
            Button('Property')->onClick(function (): void { $this->property++; }),
            new Counter('left'),
            new Counter('right'),
            new Editor($message),
        );
    }
}
