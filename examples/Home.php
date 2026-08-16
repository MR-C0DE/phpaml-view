<?php

declare(strict_types=1);

use AML\View\Page;
use AML\View\View;
use function AML\View\{Action, Column, Heading, state};

final class Home extends Page
{
    public function body(): View
    {
        $count = state(0);

        return Column(
            Heading('Hello PHP')->size(42)->bold(),
            Action(fn () => "Count: {$count->value()}")
                ->click(fn () => $count->increment()),
        )
            ->center()
            ->spacing(16)
            ->padding(40);
    }
}
