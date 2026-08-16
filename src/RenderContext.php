<?php

declare(strict_types=1);

namespace AML\View;

use Closure;

final class RenderContext
{
    private EventRegistry $registry;

    public function __construct(private ?View $content = null, ?EventRegistry $registry = null)
    {
        $this->registry = $registry ?? new EventRegistry();
    }

    public function content(): ?View
    {
        return $this->content;
    }

    public function registerEvent(Closure $handler): string
    {
        return $this->registry->register($handler);
    }

    /** @return array<string, Closure> */
    public function events(): array
    {
        return $this->registry->all();
    }

    public function withContent(View $content): self
    {
        return new self($content, $this->registry);
    }
}
