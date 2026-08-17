<?php

declare(strict_types=1);

namespace App\Views\Layouts;

use App\Support\Copy;
use AML\View\Layout;
use AML\View\View;
use function AML\View\{ContextProvider, Element, Group, Slot, Text, ThemeProvider};
use function App\Views\Components\Navigation;

final class AppLayout extends Layout
{
    public function body(): View
    {
        return ThemeProvider('system', ContextProvider('locale', Copy::current(), Group(
            Navigation(),
            Slot(),
            Element('footer', Text(Copy::text('footer')))->class('site-footer', 'shell'),
        )));
    }
}
