<?php
declare(strict_types=1);
namespace AML\View\Tests\Functional\Pages\Home;

use AML\View\{Style, View};
use function AML\View\{body, Button, Heading, Link, State, Text, VStack};
use function AML\View\Tests\Functional\Components\{Counter, DefaultObject, Editor, Labels, Legacy};
use function AML\View\Tests\Functional\Other\Badge;

function Home(): View
{
    $message = State('André');
    $size = State(16);
    $style = Style::create('home.style');
    return body(
        Heading('Functional home'),
        Text($message)->id('parent-message')->style($style->title)->fontSize($size),
        Button('Resize')->sets($size, 24),
        Counter('left'),
        Counter(initial: 10, label: 'right'),
        Editor($message),
        Legacy(),
        Badge(),
        Labels('labels', 'one', last: 'two'),
        DefaultObject(),
        VStack(...array_map(fn (int $index): View => Counter('loop-' . $index), [0, 1])),
        Link('Other page', '/functional-other'),
    );
}
