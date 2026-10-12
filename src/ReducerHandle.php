<?php

declare(strict_types=1);

namespace AML\View;

use AML\Engine\ClientAction;
use AML\Engine\ClientInstruction;
use AML\Engine\StateRef;

final readonly class ReducerHandle
{
    /** @param array<string, ClientInstruction> $actions */
    public function __construct(
        public StateRef $state,
        private array $actions,
    ) {}

    public function dispatch(string $action): ClientInstruction
    {
        return $this->actions[$action]
            ?? throw new \OutOfBoundsException("Unknown reducer action: {$action}");
    }
}
