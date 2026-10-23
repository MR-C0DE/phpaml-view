<?php
declare(strict_types=1);
namespace AML\View\Tests\Functional\Components;

use AML\Engine\{ClientAction, StateRef};
use AML\View\{Component, State as StateAttribute, View};
use function AML\View\{body, Button, Effect, State, Text, TextField, VStack};

function Counter(string $label = 'default', int $initial = 0): View
{
    $count = State($initial);
    $mounts = State(0);
    Effect(ClientAction::increment($mounts));
    Effect(function () use ($label): void { echo 'mount:' . $label; });
    return VStack(
        Text($count)->id($label . '-count'),
        Text($mounts)->id($label . '-mounts'),
        Button($label . '+')->increments($count),
        Button($label . '-')->decrements($count),
        Button($label . '-invalid')->sets($count, 'bad'),
    );
}

function Preview(StateRef $message): View
{
    return body(Text($message)->id('preview'), Button('Preview')->sets($message, 'Preview'));
}

function Editor(StateRef $message): View
{
    return body(TextField('message', $message), Preview($message));
}

function Labels(string $prefix = 'labels', string ...$values): View
{
    return Text($prefix . ':' . implode(',', $values))->id('labels');
}

final class Counted
{
    public static int $calls = 0;
    public function __construct(public string $directory = __DIR__) { self::$calls++; }
}

function DefaultObject(Counted $value = new Counted()): View
{
    return Text($value->directory)->id('object-default');
}

function Broken(): View
{
    State('temporary');
    throw new \RuntimeException('functional render failed');
}

final class Legacy extends Component
{
    #[StateAttribute] private int $count = 5;
    public function body(): View
    {
        return body(Text($this->count)->id('legacy-count'), Button('Legacy+')->onClick(function (): void { $this->count++; }));
    }
}

// A handwritten class factory keeps returning its actual class instance.
function Legacy(): View { return new Legacy(); }

function scalarHelper(): int { return 7; }

function Footer(string $title): View { return Text($title)->id('forwarded-title'); }

function Title(string $title): View
{
    return body(Text($title)->id('title'), TextField('title', $title), Footer($title));
}

final class ClassTitle extends Component
{
    public function __construct(private string $title) {}
    public function body(): View { return Text($this->title)->id('class-title'); }
}

function ClassForward(string $title): View { return new ClassTitle($title); }

function Recursive(): View { return Recursive(); }

function Reference(string &$first, string &...$rest): View
{
    $first .= '!';
    foreach ($rest as &$value) $value .= '!';
    return Text($first);
}

function InvalidReturn(): View { return 'not a view'; }
