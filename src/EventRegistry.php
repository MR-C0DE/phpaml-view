<?php

declare(strict_types=1);

namespace AML\View;

use Closure;

final class EventRegistry
{
    private int $sequence = 0;

    /** @var array<string, Closure> */
    private array $events = [];

    public function register(Closure $handler): string
    {
        $id = 'aml-' . (++$this->sequence);
        $this->events[$id] = $handler;
        return $id;
    }

    /** @return array<string, Closure> */
    public function all(): array
    {
        return $this->events;
    }
}
