<?php

declare(strict_types=1);

namespace App\Views\Pages\Tasks\Id;

use App\Support\Copy;
use AML\View\Page;
use AML\View\PageMetadata;
use AML\View\View;
use function AML\View\{Group, Heading, Link, MainContent, Section, Text};

final class IdPage extends Page
{
    public function metadata(): PageMetadata
    {
        return new PageMetadata('Task details · AML Tasks', 'Dynamic AML View route example.');
    }

    public function body(): View
    {
        $id = $this->param('id', 'unknown');
        return MainContent(Section(
            Text(Copy::text('dynamic'))->class('eyebrow'),
            Heading(Copy::text('task.details') . ' #' . $id),
            Text(Copy::text('task.route')),
            Link(Copy::text('task.back'), '/tasks')->class('button', 'button-primary'),
        )->class('section', 'shell', 'detail-card'));
    }
}
