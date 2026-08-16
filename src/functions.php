<?php

declare(strict_types=1);

namespace AML\View;

use Closure;

function state(mixed $initial): StateValue
{
    return Runtime::state($initial);
}

function textValue(string|Closure|StateValue $value): View
{
    if ($value instanceof StateValue) {
        return new TextView(static fn (): string => (string) $value);
    }

    return new TextView($value);
}

function Text(string|Closure|StateValue $content): Element
{
    return new Element('span', textValue($content));
}

function Heading(string|Closure|StateValue $content, int $level = 1): HeadingView
{
    return new HeadingView(textValue($content), $level);
}

function Column(View ...$children): Element
{
    return (new Element('div', ...$children))
        ->style('display', 'flex')
        ->style('flex-direction', 'column');
}

function VStack(View ...$children): Element
{
    return Column(...$children);
}

function Row(View ...$children): Element
{
    return (new Element('div', ...$children))
        ->style('display', 'flex')
        ->style('flex-direction', 'row');
}

function HStack(View ...$children): Element
{
    return Row(...$children);
}

function ZStack(View ...$children): Element
{
    foreach ($children as $child) {
        if ($child instanceof Element) {
            $child->style('grid-area', '1/1');
        }
    }
    return (new Element('div', ...$children))->style('display', 'grid');
}

function Grid(int $columns, View ...$children): Element
{
    return (new Element('div', ...$children))
        ->style('display', 'grid')
        ->style('grid-template-columns', 'repeat(' . max(1, $columns) . ',minmax(0,1fr))');
}

function Spacer(): Element
{
    return (new Element('span'))->attribute('aria-hidden', 'true')->style('flex', '1');
}

function Image(string $source, string $alt = ''): Element
{
    return (new Element('img'))->attribute('src', $source)->attribute('alt', $alt);
}

function Link(string|Closure|StateValue $label, string $href): Element
{
    return (new Element('a', textValue($label)))->attribute('href', $href);
}

function Alert(string|Closure|StateValue $message, ActionStatus $status = ActionStatus::Idle): Element
{
    return (new Element('div', textValue($message)))
        ->attribute('role', $status === ActionStatus::Error ? 'alert' : 'status')
        ->attribute('data-aml-status', $status->value);
}

function Section(View ...$children): Element
{
    return new Element('section', ...$children);
}

function MainContent(View ...$children): Element
{
    return new Element('main', ...$children);
}

function Action(string|Closure|StateValue $label): Element
{
    return (new Element('button', textValue($label)))->attribute('type', 'button');
}

function Button(string|Closure|StateValue $label): Element
{
    return Action($label);
}

function Form(View ...$children): Element
{
    return (new Element('form', ...$children))->attribute('method', 'post');
}

function Input(string $name, string $type = 'text', string|int|float|null $value = null): Element
{
    return (new Element('input'))
        ->attribute('name', $name)
        ->attribute('type', $type)
        ->attribute('value', $value);
}

function TextArea(string $name, string $value = ''): Element
{
    return (new Element('textarea', textValue($value)))->attribute('name', $name);
}

function Checkbox(string $name, bool $checked = false, string $value = '1'): Element
{
    return (new Element('input'))
        ->attribute('name', $name)
        ->attribute('type', 'checkbox')
        ->attribute('value', $value)
        ->attribute('checked', $checked);
}

/** @param array<string|int, string> $options */
function Select(string $name, array $options, string|int|null $selected = null): Element
{
    $choices = [];
    foreach ($options as $value => $label) {
        $choices[] = (new Element('option', textValue($label)))
            ->attribute('value', (string) $value)
            ->attribute('selected', (string) $value === (string) $selected);
    }

    return (new Element('select', ...$choices))
        ->attribute('name', $name)
        ->attribute('data-aml-value', $selected);
}

function Content(): ContentView
{
    return new ContentView();
}

function Slot(): ContentView
{
    return Content();
}

function RouterView(Router $router, ?string $path = null): RouterView
{
    return new RouterView($router, $path ?? ($_SERVER['REQUEST_URI'] ?? '/'));
}
