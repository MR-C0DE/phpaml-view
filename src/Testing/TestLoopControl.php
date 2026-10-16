<?php

declare(strict_types=1);

namespace AML\View\Testing;

/** Internal control signal, never an execution failure or a rollback trigger. */
final class TestLoopControl extends \Exception
{
    public function __construct(public string $operation, public int $levels) {}
}
