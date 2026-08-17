<?php

declare(strict_types=1);

namespace App\Views\States;

use App\Support\Copy;
use AML\View\Page;
use AML\View\View;
use function AML\View\{Element, Heading, Link};

final class NotFoundPage extends Page
{
    public function body(): View
    {
        return Element(
            'main',
            Heading(Copy::text('not-found')),
            Link(Copy::text('return-home'), '/'),
        )->class('route-state');
    }
}
