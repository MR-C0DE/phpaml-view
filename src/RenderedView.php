<?php

declare(strict_types=1);

namespace AML\View;

final class RenderedView
{
    private RenderContext $context;
    private string $html;

    public function __construct(private View $root)
    {
        $this->refresh();
    }

    public function html(): string
    {
        return $this->html;
    }

    /** @return list<string> */
    public function eventIds(): array
    {
        return array_keys($this->context->events());
    }

    /** @param array<string, mixed> $data */
    public function dispatch(string $eventId, array $data = []): self
    {
        $handler = $this->context->events()[$eventId] ?? null;
        if ($handler === null) {
            throw new \OutOfBoundsException("Unknown AML View event: {$eventId}");
        }

        $reflection = new \ReflectionFunction($handler);
        $reflection->getNumberOfParameters() === 0 ? $handler() : $handler($data);
        $this->refresh();
        return $this;
    }

    private function refresh(): void
    {
        $this->context = new RenderContext();
        $this->html = $this->root->render($this->context);
    }
}
