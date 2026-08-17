<?php

declare(strict_types=1);

use AML\Engine\ClientAction;
use AML\Engine\StateRef;
use AML\View\Page;
use AML\View\State;
use AML\View\View;
use function AML\View\{Button, Column, Heading, Text};

final class Home extends Page
{
    #[State]
    public int $count = 0;

    public function body(): View
    {
        return Column(
            Heading('Hello PHP')->size(42)->bold(),
            Text(StateRef::to('count', $this->count)),
            Button('Add one')->onClick(ClientAction::increment('count')),
        )
            ->center()
            ->spacing(16)
            ->padding(40);
    }
}
