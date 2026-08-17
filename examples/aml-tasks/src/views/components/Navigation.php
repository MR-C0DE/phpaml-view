<?php

declare(strict_types=1);

namespace App\Views\Components;

use App\Support\Copy;
use AML\View\Component;
use AML\View\View;
use function AML\View\{Element, Group, Image, Link, ThemeSwitcher};

final class Navigation extends Component
{
    public function body(): View
    {
        return Group(
            Element('header',
                Link(Copy::text('brand'), '/')->class('brand'),
                Element('nav',
                    Link(Copy::text('dashboard'), '/'),
                    Link(Copy::text('tasks'), '/tasks'),
                    Link(Copy::text('settings'), '/settings'),
                )->class('main-nav')->attribute('aria-label', 'Main navigation'),
                ThemeSwitcher('light', 'dark', 'system')->class('theme-switcher'),
            )->class('site-header', 'shell'),
        );
    }
}

function Navigation(): Navigation
{
    return new Navigation();
}
